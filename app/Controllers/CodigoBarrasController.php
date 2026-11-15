<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Auth;
use App\Libraries\CodigoBarras;
use App\Libraries\Db;
use App\Libraries\PrecioTienda;

/**
 * Códigos de barras y referencia del proveedor de cada producto, y etiquetas para imprimir.
 *  Web:  GET  /inventario/codigos            (productos.editar) — escanear / generar en lote
 *  PWA:  GET  /m/codigos                     (MovilController)
 *  JSON: POST /api/codigos/{id}              guardar códigos de un producto
 *        POST /api/codigos/generar           código interno a los que no tienen
 *        GET  /api/etiquetas?p=ID:n,…&u=…    datos de etiquetas (impresora ESC/POS desde el celular)
 *  HTML: GET  /m/etiquetas?p=ID:n,…          etiquetas de 58 mm para imprimir desde el navegador
 */
class CodigoBarrasController extends BaseController
{
    public function index(): string
    {
        $filtro = (string) ($this->request->getGet('ver') ?? 'sin');
        if (!in_array($filtro, ['sin', 'interno', 'todos'], true)) $filtro = 'sin';
        $q = trim((string) ($this->request->getGet('q') ?? ''));
        $categoriaId = (int) ($this->request->getGet('categoria') ?? 0) ?: null;
        $empresaId = (int) $this->empresaId();

        return $this->render('inventario.codigos', [
            'page_title'    => 'Códigos de barras',
            'page_subtitle' => 'Escanea el código de cada producto o genera uno interno, y su referencia del proveedor',
            'productos'     => CodigoBarras::lista($empresaId, $filtro, $q, $categoriaId),
            'filtro'        => $filtro,
            'q'             => $q,
            'categoriaId'   => $categoriaId,
            'categorias'    => Db::all("SELECT categoria_id, nombre FROM categorias_productos WHERE empresa_id = ? ORDER BY nombre", [$empresaId]),
            'resumen'       => Db::row(
                "SELECT COUNT(*) AS total,
                        SUM(codigo_barras IS NULL OR codigo_barras = '') AS sin_codigo,
                        SUM(codigo_barras REGEXP '^20[01][0-9]{10}$') AS internos
                 FROM productos WHERE empresa_id = ? AND estado = 'activo'",
                [$empresaId]
            ),
        ]);
    }

    /** POST /api/codigos/{id} {codigo_barras?, supplier_part_no?, unidades?: {unidad_id: código|'interno'}} */
    public function guardar(int $productoId)
    {
        $d = $this->jsonInput();
        $datos = array_intersect_key($d, array_flip(['codigo_barras', 'supplier_part_no', 'unidades']));
        try {
            $r = CodigoBarras::guardar((int) $this->empresaId(), $productoId, $datos);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
        return $this->json(['success' => true] + $r);
    }

    /**
     * POST /api/codigos/generar {ids: [producto_id…], presentaciones: bool}
     * Código interno a los productos (y presentaciones) de la lista que no tienen ninguno.
     */
    public function generar()
    {
        $d = $this->jsonInput();
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($d['ids'] ?? [])))));
        if (!$ids) return $this->json(['error' => 'No hay productos seleccionados.'], 422);
        $empresaId = (int) $this->empresaId();
        $productos = 0;
        $presentaciones = 0;
        foreach (array_chunk($ids, 200) as $lote) {
            $in = implode(',', $lote);
            foreach (Db::all("SELECT producto_id FROM productos WHERE empresa_id = ? AND producto_id IN ({$in}) AND (codigo_barras IS NULL OR codigo_barras = '')", [$empresaId]) as $p) {
                CodigoBarras::guardar($empresaId, (int) $p['producto_id'], ['codigo_barras' => 'interno']);
                $productos++;
            }
            if (!empty($d['presentaciones'])) {
                foreach (Db::all(
                    "SELECT u.unidad_id, u.producto_id FROM productos_unidades u JOIN productos p ON p.producto_id = u.producto_id
                     WHERE p.empresa_id = ? AND u.producto_id IN ({$in}) AND u.factor_conversion > 1 AND (u.codigo_barras IS NULL OR u.codigo_barras = '')",
                    [$empresaId]
                ) as $u) {
                    CodigoBarras::guardar($empresaId, (int) $u['producto_id'], ['unidades' => [(int) $u['unidad_id'] => 'interno']]);
                    $presentaciones++;
                }
            }
        }
        return $this->json(['success' => true, 'productos' => $productos, 'presentaciones' => $presentaciones]);
    }

    /** GET /api/etiquetas?p=12:3,15:1&u=40:2 — una fila por etiqueta (repetida según la cantidad). */
    public function etiquetasDatos()
    {
        return $this->json(['etiquetas' => $this->etiquetas()]);
    }

    /** GET /m/etiquetas?p=… — etiquetas de 58 mm para el navegador (laptop con la Xprinter por USB). */
    public function etiquetasPagina()
    {
        if (!Auth::can('productos.ver')) return redirect()->to('/m');
        return view('movil/etiquetas', ['etiquetas' => $this->etiquetas()]);
    }

    /** "12:3,15:1" → [12 => 3, 15 => 1] (máx. 100 por producto). */
    private static function parLista(string $s): array
    {
        $out = [];
        foreach (explode(',', $s) as $par) {
            [$id, $n] = array_pad(explode(':', $par), 2, '1');
            $id = (int) $id;
            if ($id > 0) $out[$id] = max(1, min(100, (int) $n));
        }
        return $out;
    }

    /** Etiquetas con el precio de la tienda activa. */
    private function etiquetas(): array
    {
        $empresaId = (int) $this->empresaId();
        $s = (int) $this->sucursalId();
        $prods = self::parLista((string) ($this->request->getGet('p') ?? ''));
        $unis  = self::parLista((string) ($this->request->getGet('u') ?? ''));
        $out = [];

        if ($prods) {
            $filas = Db::all(
                "SELECT producto_id, codigo, nombre, codigo_barras, supplier_part_no, unidad_medida FROM productos
                 WHERE empresa_id = ? AND producto_id IN (" . implode(',', array_keys($prods)) . ")",
                [$empresaId]
            );
            foreach ($filas as $p) {
                $et = [
                    'nombre' => $p['nombre'], 'codigo' => $p['codigo'], 'referencia' => (string) $p['supplier_part_no'],
                    'barras' => (string) $p['codigo_barras'], 'precio' => PrecioTienda::precio((int) $p['producto_id'], $s),
                    'presentacion' => \App\Libraries\ProductoService::unidadMedida($p['unidad_medida']) === 'und' ? '' : \App\Libraries\ProductoService::abreviatura($p['unidad_medida']),
                ];
                for ($i = 0; $i < $prods[(int) $p['producto_id']]; $i++) $out[] = $et;
            }
        }
        if ($unis) {
            $filas = Db::all(
                "SELECT u.unidad_id, u.producto_id, u.nombre AS pres, u.codigo_barras, p.codigo, p.nombre, p.supplier_part_no
                 FROM productos_unidades u JOIN productos p ON p.producto_id = u.producto_id
                 WHERE p.empresa_id = ? AND u.unidad_id IN (" . implode(',', array_keys($unis)) . ")",
                [$empresaId]
            );
            foreach ($filas as $u) {
                $precio = 0.0;
                foreach (PrecioTienda::unidades((int) $u['producto_id'], $s) as $x) {
                    if ((int) $x['unidad_id'] === (int) $u['unidad_id']) $precio = (float) $x['precio_a'];
                }
                $et = [
                    'nombre' => $u['nombre'], 'codigo' => $u['codigo'], 'referencia' => (string) $u['supplier_part_no'],
                    'barras' => (string) $u['codigo_barras'], 'precio' => $precio, 'presentacion' => $u['pres'],
                ];
                for ($i = 0; $i < $unis[(int) $u['unidad_id']]; $i++) $out[] = $et;
            }
        }
        return $out;
    }
}

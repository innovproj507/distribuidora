<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\AuditService;
use App\Libraries\Auth;
use App\Libraries\CompraService;
use App\Libraries\Db;
use App\Libraries\Fila;
use App\Libraries\InventarioService;
use App\Libraries\NumeracionService;

/**
 * Compras: órdenes y facturas de proveedor, recepción total o parcial (entra al inventario
 * con costo promedio ponderado y lote), y creación rápida de productos y proveedores.
 * Permisos por ruta: compras.ver / crear / recibir.
 */
class CompraController extends BaseController
{
    public function index(): string
    {
        $sucursalId = $this->sucursalId();
        $buscar     = trim((string) ($this->request->getGet('buscar') ?? ''));
        $porPagina  = in_array((int) $this->request->getGet('por_pagina'), [10, 25, 50, 100], true) ? (int) $this->request->getGet('por_pagina') : 25;
        $page       = max(1, (int) ($this->request->getGet('page') ?? 1));
        $offset     = ($page - 1) * $porPagina;

        $where  = 'c.sucursal_id = ?';
        $params = [$sucursalId];
        if ($buscar !== '') {
            $where .= ' AND (c.numero_factura LIKE ? OR p.nombre LIKE ?)';
            array_push($params, "%{$buscar}%", "%{$buscar}%");
        }

        // LEFT JOIN: las compras sin proveedor (permitidas desde el móvil) también se listan
        $total = (int) Db::value("SELECT COUNT(*) FROM compras c LEFT JOIN proveedores p ON c.proveedor_id = p.proveedor_id WHERE {$where}", $params);

        return $this->render('compras.index', [
            'page_title'    => 'Gestión de Compras',
            'page_subtitle' => 'Entradas de mercancía y facturas de proveedores',
            'compras'       => Db::all(
                "SELECT c.*, COALESCE(p.nombre, 'Sin proveedor') AS proveedor_nombre
                 FROM compras c
                 LEFT JOIN proveedores p ON c.proveedor_id = p.proveedor_id
                 WHERE {$where}
                 ORDER BY c.fecha_compra DESC, c.compra_id DESC
                 LIMIT {$porPagina} OFFSET {$offset}",
                $params
            ),
            'stats' => Db::row(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN estado IN ('pendiente','parcialmente_recibida') THEN 1 ELSE 0 END) AS pendientes,
                        SUM(CASE WHEN estado = 'recibida' THEN 1 ELSE 0 END) AS recibidas,
                        COALESCE(SUM(CASE WHEN estado = 'recibida' THEN monto_total ELSE 0 END), 0) AS monto_recibido
                 FROM compras WHERE sucursal_id = ?",
                [$sucursalId]
            ),
            'buscar'     => $buscar,
            'pagination' => paginacion($total, $page, $porPagina) + ['per_page' => $porPagina],
        ]);
    }

    public function crear(): string
    {
        $empresa = Db::row("SELECT margen_precio_a, margen_precio_b, margen_precio_c FROM companies WHERE empresa_id = ?", [$this->empresaId()]);

        return $this->render('compras.crear', [
            'page_title'      => 'Nueva Compra',
            'page_subtitle'   => 'Registrar entrada de mercancía',
            // La vista usa ->campo y ['campo']
            'proveedores'     => Fila::lista(Db::all("SELECT * FROM proveedores WHERE empresa_id = ? AND estado = 'activo' ORDER BY nombre", [$this->empresaId()])),
            'depositos'       => Fila::lista(Db::all("SELECT * FROM depositos WHERE sucursal_id = ? AND estado = 'activo' ORDER BY es_principal DESC, nombre", [$this->sucursalId()])),
            'categorias'      => Db::all("SELECT categoria_id, nombre FROM categorias_productos WHERE empresa_id = ? ORDER BY nombre", [$this->empresaId()]),
            'margen_precio_a' => (float) ($empresa['margen_precio_a'] ?? 35),
            'margen_precio_b' => (float) ($empresa['margen_precio_b'] ?? 20),
            'margen_precio_c' => (float) ($empresa['margen_precio_c'] ?? 10),
        ]);
    }

    /** Editar una orden pendiente (agregar más productos antes de recibir). Usa el mismo formulario de Nueva Compra. */
    public function editar(int $id)
    {
        $compra = $this->buscar($id);
        $recibido = $compra ? (float) Db::value("SELECT COALESCE(SUM(cantidad_recibida), 0) FROM compras_detalle WHERE compra_id = ?", [$id]) : 0;
        if (!$compra || $compra['estado'] !== 'pendiente' || $recibido > 0) {
            $this->error('Solo se puede editar una orden pendiente que todavía no tiene nada recibido.');
            return redirect()->to($compra ? "/compras/{$id}" : '/compras');
        }

        $s = (int) $compra['sucursal_id']; // precios de la tienda de la compra
        $lineas = Db::all(
            "SELECT cd.producto_id, cd.cantidad, cd.costo, cd.presentacion, cd.factor_presentacion, p.nombre, p.codigo, p.unidad_medida,
                    (SELECT precio FROM precios_productos WHERE producto_id = cd.producto_id AND sucursal_id = {$s} AND tipo_precio = 'a') AS precio_a,
                    (SELECT precio FROM precios_productos WHERE producto_id = cd.producto_id AND sucursal_id = {$s} AND tipo_precio = 'b') AS precio_b,
                    (SELECT precio FROM precios_productos WHERE producto_id = cd.producto_id AND sucursal_id = {$s} AND tipo_precio = 'c') AS precio_c
             FROM compras_detalle cd JOIN productos p ON p.producto_id = cd.producto_id
             WHERE cd.compra_id = ? ORDER BY cd.detalle_id",
            [$id]
        );
        $nombresBase = ['und' => 'Unidad', 'lb' => 'Libra', 'kg' => 'Kg', 'oz' => 'Onza', 'gal' => 'Galón', 'lt' => 'Litro', 'm' => 'Metro', 'pie' => 'Pie', 'yd' => 'Yarda'];
        $items = array_map(function (array $l) use ($compra, $nombresBase): array {
            $costo  = (float) $l['costo'];
            $precio = (float) $l['precio_a'];
            // La orden guardada está en unidades base; se ofrecen las presentaciones para seguir editando
            $unidades = [['nombre' => $nombresBase[$l['unidad_medida'] ?? 'und'] ?? 'Unidad', 'factor' => 1]];
            foreach (Db::all("SELECT nombre, factor_conversion FROM productos_unidades WHERE producto_id = ? AND factor_conversion > 1 ORDER BY factor_conversion", [$l['producto_id']]) as $u) {
                $unidades[] = ['nombre' => $u['nombre'], 'factor' => (float) $u['factor_conversion']];
            }
            // Si la línea se escribió en una presentación (Caja de 6), se vuelve a mostrar así: 6 a $5.83 → 1 caja a $35
            $f = (float) ($l['factor_presentacion'] ?? 1);
            if ($f > 1) {
                if (!in_array($f, array_map(fn ($u) => (float) $u['factor'], $unidades), true)) {
                    $unidades[] = ['nombre' => $l['presentacion'] ?: "Presentación", 'factor' => $f]; // ya no existe en el producto
                }
            } else {
                $f = 1.0;
            }
            return [
                'unidades' => $unidades, 'factor' => $f,
                'producto_id' => (int) $l['producto_id'], 'nombre' => $l['nombre'], 'codigo' => $l['codigo'],
                'cantidad' => round((float) $l['cantidad'] / $f, 4), 'costo' => round($costo * $f, 10),
                'margen_pct' => $costo > 0 && $precio > 0 ? round(($precio / $costo - 1) * 100, 2) : 0,
                'precio_a' => $precio, 'precio_b' => (float) $l['precio_b'], 'precio_c' => (float) $l['precio_c'],
                'deposito_id' => (int) $compra['deposito_id'], 'itbms' => 0,
            ];
        }, $lineas);

        $empresa = Db::row("SELECT margen_precio_a, margen_precio_b, margen_precio_c FROM companies WHERE empresa_id = ?", [$this->empresaId()]);
        return $this->render('compras.crear', [
            'page_title'      => 'Editar orden ' . ($compra['numero_factura_proveedor'] ?: '#' . $id),
            'page_subtitle'   => 'Agregar o cambiar productos antes de recibir',
            'proveedores'     => Fila::lista(Db::all("SELECT * FROM proveedores WHERE empresa_id = ? AND (estado = 'activo' OR proveedor_id = ?) ORDER BY nombre", [$this->empresaId(), (int) $compra['proveedor_id']])),
            'depositos'       => Fila::lista(Db::all("SELECT * FROM depositos WHERE sucursal_id = ? AND estado = 'activo' ORDER BY es_principal DESC, nombre", [(int) $compra['sucursal_id']])),
            'categorias'      => Db::all("SELECT categoria_id, nombre FROM categorias_productos WHERE empresa_id = ? ORDER BY nombre", [$this->empresaId()]),
            'margen_precio_a' => (float) ($empresa['margen_precio_a'] ?? 35),
            'margen_precio_b' => (float) ($empresa['margen_precio_b'] ?? 20),
            'margen_precio_c' => (float) ($empresa['margen_precio_c'] ?? 10),
            'editar'          => [
                'compra_id' => $id,
                'compra'    => [
                    'proveedor_id'   => $compra['proveedor_id'] !== null ? (string) $compra['proveedor_id'] : '',
                    'numero_factura' => (string) ($compra['numero_factura_proveedor'] ?? ''),
                    'fecha_compra'   => substr((string) $compra['fecha_compra'], 0, 10),
                    'fecha_esperada' => '',
                    'terminos'       => '',
                    'notas'          => (string) ($compra['notas'] ?? ''),
                    'con_itbms'      => (float) $compra['monto_itbms'] > 0,
                ],
                'items' => $items,
            ],
        ]);
    }

    /** POST JSON (mismo formato que guardar) → reemplaza las líneas de la orden pendiente. */
    public function actualizar(int $id)
    {
        $data = $this->jsonInput();
        try {
            Db::begin();
            CompraService::actualizarPendiente($id, $data, ['empresa_id' => $this->empresaId(), 'sucursal_id' => $this->sucursalId()]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            log_message('error', 'Editar compra: ' . $e->getMessage());
            return $this->json(['error' => $e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo guardar la orden.'], $e instanceof \RuntimeException ? 422 : 500);
        }
        AuditService::log('compras.editar', "Orden de compra #{$id} editada (" . count($data['items'] ?? []) . ' productos)', $id, 'compra', ['total' => $data['total'] ?? 0], $this->empresaId());
        return $this->json(['success' => true, 'compra_id' => $id]);
    }

    public function detalle(int $id)
    {
        $compra = $this->buscar($id);
        if (!$compra) {
            $this->error('Compra no encontrada.');
            return redirect()->to('/compras');
        }

        return $this->render('compras.detalle', [
            'page_title'    => 'Compra #' . ($compra['numero_factura'] ?? $id),
            'page_subtitle' => 'Detalle de compra',
            'compra'        => $compra,
            'detalles'      => Db::all(
                "SELECT cd.*, pr.nombre AS producto_nombre, pr.codigo AS producto_codigo,
                        (cd.cantidad - cd.cantidad_recibida) AS cantidad_pendiente
                 FROM compras_detalle cd JOIN productos pr ON cd.producto_id = pr.producto_id
                 WHERE cd.compra_id = ?",
                [$id]
            ),
        ]);
    }

    // ── Recepción parcial / completa ─────────────────────────────────────────

    public function recibir(int $id)
    {
        $compra = $this->buscar($id);
        if (!$compra) {
            $this->error('Compra no encontrada.');
            return redirect()->to('/compras');
        }
        if (in_array($compra['estado'], ['recibida', 'cancelada'], true)) {
            $compra['estado'] === 'recibida' ? flash('info', 'Esta orden ya fue recibida completamente.') : $this->error('La orden está cancelada.');
            return redirect()->to("/compras/{$id}");
        }

        return $this->render('compras.recibir', [
            'page_title'    => 'Registrar Recepción',
            'page_subtitle' => 'Compra #' . ($compra['numero_factura'] ?? $id),
            'compra'        => $compra,
            'items'         => Db::all(
                "SELECT cd.*, pr.nombre AS producto_nombre, pr.codigo AS producto_codigo,
                        (cd.cantidad - cd.cantidad_recibida) AS pendiente
                 FROM compras_detalle cd JOIN productos pr ON cd.producto_id = pr.producto_id
                 WHERE cd.compra_id = ? AND cd.cantidad > cd.cantidad_recibida
                 ORDER BY pr.nombre",
                [$id]
            ),
            'depositos' => \App\Libraries\SucursalService::depositosDe((int) $this->sucursalId()),
        ]);
    }

    public function procesarRecepcion(int $id)
    {
        $compra = $this->buscar($id);
        if (!$compra || in_array($compra['estado'], ['recibida', 'cancelada'], true)) {
            $this->error('Estado de la orden no permite recepción.');
            return redirect()->to($compra ? "/compras/{$id}" : '/compras');
        }

        // Quien no crea compras (encargado) solo confirma: entra exactamente lo enviado
        $recibidas = \App\Libraries\Auth::can('compras.crear')
            ? (array) ($this->request->getPost('cantidad_recibida') ?? [])
            : CompraService::todoPendiente($id);
        $depositoId = (int) ($this->request->getPost('deposito_id') ?? 0) ?: (int) $compra['deposito_id'];
        if (!\App\Libraries\SucursalService::puedeUsarDeposito($depositoId, (int) $compra['sucursal_id'])) {
            $this->error('Ese depósito no pertenece a la sucursal de la compra o no tienes acceso.');
            return redirect()->to("/compras/{$id}/recibir");
        }

        try {
            Db::begin();

            $nuevoEstado = CompraService::recibirPendientes($id, $compra, $recibidas, $depositoId);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error('Error al procesar recepción: ' . $e->getMessage());
            return redirect()->to("/compras/{$id}/recibir");
        }

        AuditService::log('compras.recibir', 'Recepción ' . ($nuevoEstado === 'recibida' ? 'completa' : 'parcial') . " — Compra #{$id}", $id, 'compra', ['estado' => $nuevoEstado], $this->empresaId());
        $this->success($nuevoEstado === 'recibida' ? 'Recepción completa registrada. Inventario actualizado.' : 'Recepción parcial registrada. Quedan artículos pendientes.');
        return redirect()->to("/compras/{$id}");
    }

    // ── Producto rápido desde el formulario de compra (JSON) ─────────────────

    public function siguienteCodigoProducto()
    {
        return $this->json(['codigo' => $this->generarCodigoProducto()]);
    }

    public function crearProductoRapido()
    {
        $data   = $this->jsonInput();
        $nombre = trim((string) ($data['nombre'] ?? ''));
        $costo  = (float) ($data['costo'] ?? 0);

        if ($nombre === '') {
            return $this->json(['error' => 'El nombre es requerido'], 422);
        }

        $empresaId = $this->empresaId();
        $codigo    = trim((string) ($data['codigo'] ?? '')) ?: $this->generarCodigoProducto();

        if (Db::value("SELECT 1 FROM productos WHERE codigo = ? AND empresa_id = ? LIMIT 1", [$codigo, $empresaId])) {
            return $this->json(['error' => "El código '{$codigo}' ya está en uso."], 409);
        }

        try {
            Db::begin();
            $productoId = Db::insert('productos', [
                'empresa_id'     => $empresaId,
                'nombre'         => $nombre,
                'codigo'         => $codigo,
                'costo'          => $costo,
                'categoria_id'   => !empty($data['categoria_id']) ? (int) $data['categoria_id'] : null,
                'itbms'          => 0, // ningún producto lleva ITBMS (ver ProductoService)
                'estado'         => 'activo',
                'fecha_creacion' => date('Y-m-d H:i:s'),
            ]);

            // Registro de inventario (existencia 0) en todos los depósitos para que aparezca en cada sucursal
            \App\Libraries\SucursalService::poblarProducto($productoId);
            \App\Libraries\ConteoService::incluirProductoNuevo($productoId);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            log_message('error', 'Producto rápido: ' . $e->getMessage());
            return $this->json(['error' => 'No se pudo crear el producto.'], 500);
        }

        AuditService::log('inventario.producto.crear_rapido', "Producto creado rápido desde compras: {$nombre} ({$codigo})", $productoId, 'producto', [], $empresaId);

        return $this->json(['success' => true, 'producto' => [
            'producto_id' => $productoId, 'nombre' => $nombre, 'codigo' => $codigo, 'costo' => $costo,
            'precio_a' => 0, 'precio_b' => 0, 'precio_c' => 0,
        ]]);
    }

    /**
     * POST JSON (formulario web y PWA). Ver CompraService::registrar() para el formato.
     */
    public function guardar()
    {
        $data = $this->jsonInput();

        try {
            Db::begin();
            $compraId = CompraService::registrar($data, [
                'empresa_id'  => $this->empresaId(),
                'sucursal_id' => $this->sucursalId(),
                'deposito_id' => $this->depositoId(),
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            log_message('error', 'Compra: ' . $e->getMessage());
            return $this->json(['error' => $e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo registrar la compra.'], $e instanceof \RuntimeException ? 422 : 500);
        }

        $pendiente = !empty($data['guardar_como_pendiente']);
        AuditService::log(
            'compras.crear', "Compra #{$compraId} creada" . ($pendiente ? ' (orden pendiente)' : ' (recibida)'),
            $compraId, 'compra', ['estado' => $pendiente ? 'pendiente' : 'recibida', 'total' => $data['total'] ?? 0], $this->empresaId()
        );

        return $this->json(['success' => true, 'compra_id' => $compraId]);
    }

    /** POST JSON {nombre, telefono?, ruc?} → {success, proveedor: {id, nombre}} */
    public function crearProveedorRapido()
    {
        $data   = $this->jsonInput();
        $nombre = trim((string) ($data['nombre'] ?? ''));

        if ($nombre === '') {
            return $this->json(['error' => 'El nombre es requerido'], 422);
        }

        $empresaId = $this->empresaId();

        try {
            // Mismo formato de código que el resto de proveedores (Configuración → Numeración)
            $codigo = NumeracionService::siguiente('proveedor', $empresaId);

            $proveedorId = Db::insert('proveedores', [
                'empresa_id'     => $empresaId,
                'codigo'         => $codigo,
                'nombre'         => $nombre,
                'telefono'       => trim((string) ($data['telefono'] ?? '')) ?: null,
                'ruc'            => trim((string) ($data['ruc'] ?? '')) ?: null,
                'estado'         => 'activo',
                'fecha_registro' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Proveedor rápido: ' . $e->getMessage());
            return $this->json(['error' => 'No se pudo crear el proveedor.'], 500);
        }

        AuditService::log('inventario.proveedor.crear_rapido', "Proveedor creado rápido desde compras: {$nombre}", $proveedorId, 'proveedor', [], $empresaId);

        return $this->json(['success' => true, 'proveedor' => ['id' => $proveedorId, 'nombre' => $nombre]]);
    }

    private function buscar(int $id): ?array
    {
        return Db::row(
            "SELECT c.*, p.nombre AS proveedor_nombre, u.username AS usuario_nombre, d.nombre AS deposito_nombre
             FROM compras c
             LEFT JOIN proveedores p ON c.proveedor_id = p.proveedor_id
             LEFT JOIN users u ON c.usuario_id = u.id
             LEFT JOIN depositos d ON c.deposito_id = d.deposito_id
             WHERE c.compra_id = ? AND c.sucursal_id = ?",
            [$id, $this->sucursalId()]
        );
    }

    /** Código consecutivo PROD-00001 para productos creados rápido. */
    private function generarCodigoProducto(): string
    {
        $ultimo = Db::value(
            "SELECT codigo FROM productos WHERE empresa_id = ? AND codigo REGEXP '^PROD-[0-9]+$'
             ORDER BY CAST(SUBSTRING(codigo, 6) AS UNSIGNED) DESC LIMIT 1",
            [$this->empresaId()]
        );
        return 'PROD-' . str_pad((string) ($ultimo ? (int) substr($ultimo, 5) + 1 : 1), 5, '0', STR_PAD_LEFT);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\AuditService;
use App\Libraries\Db;
use App\Libraries\Fila;

/** Depósitos (almacenes) de la sucursal activa. Permisos por ruta: depositos.ver / gestionar. */
class DepositoController extends BaseController
{
    private const POR_PAGINA_DETALLE = 25;

    public function index(): string
    {
        $sucursalId = $this->sucursalId();
        $buscar     = trim((string) ($this->request->getGet('buscar') ?? ''));
        $porPagina  = in_array((int) $this->request->getGet('por_pagina'), [10, 25, 50, 100], true) ? (int) $this->request->getGet('por_pagina') : 25;
        $page       = max(1, (int) ($this->request->getGet('page') ?? 1));
        $offset     = ($page - 1) * $porPagina;

        $where  = 'd.sucursal_id = ?';
        $params = [$sucursalId];
        if ($buscar !== '') {
            $where .= ' AND (d.nombre LIKE ? OR d.codigo LIKE ?)';
            array_push($params, "%{$buscar}%", "%{$buscar}%");
        }

        $total = (int) Db::value("SELECT COUNT(*) FROM depositos d WHERE {$where}", $params);

        return $this->render('depositos.lista', [
            'page_title'    => 'Depósitos',
            'page_subtitle' => 'Gestión de almacenes',
            'depositos'     => Db::all(
                "SELECT d.*,
                        (SELECT COUNT(*) FROM inventario WHERE deposito_id = d.deposito_id) AS total_productos,
                        (SELECT COALESCE(SUM(existencia), 0) FROM inventario WHERE deposito_id = d.deposito_id) AS stock_total
                 FROM depositos d
                 WHERE {$where}
                 ORDER BY d.es_principal DESC, d.nombre ASC
                 LIMIT {$porPagina} OFFSET {$offset}",
                $params
            ),
            'stats' => Db::row(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN estado = 'activo' THEN 1 ELSE 0 END) AS activos,
                        (SELECT COUNT(DISTINCT i.producto_id) FROM inventario i JOIN depositos d2 ON i.deposito_id = d2.deposito_id WHERE d2.sucursal_id = ?) AS total_skus,
                        (SELECT COALESCE(SUM(i2.existencia), 0) FROM inventario i2 JOIN depositos d3 ON i2.deposito_id = d3.deposito_id WHERE d3.sucursal_id = ?) AS stock_total
                 FROM depositos WHERE sucursal_id = ?",
                [$sucursalId, $sucursalId, $sucursalId]
            ),
            'buscar'     => $buscar,
            'pagination' => paginacion($total, $page, $porPagina) + ['per_page' => $porPagina],
        ]);
    }

    public function crear(): string
    {
        return $this->render('depositos.crear', ['page_title' => 'Nuevo Depósito', 'page_subtitle' => 'Crear almacén']);
    }

    public function guardar()
    {
        $datos = $this->datos();
        if ($datos['nombre'] === '') {
            $this->error('El nombre es requerido.');
            return redirect()->to('/inventario/depositos/nuevo');
        }

        Db::begin();
        $this->unicoPrincipal($datos);
        $id = Db::insert('depositos', $datos + ['sucursal_id' => $this->sucursalId(), 'estado' => 'activo']);
        \App\Libraries\SucursalService::poblarInventario($id);
        Db::commit();

        $this->refrescarSesion();
        AuditService::log('inventario.deposito.crear', "Depósito creado: {$datos['nombre']}", $id, 'deposito', [], $this->empresaId());
        $this->success('Depósito creado exitosamente.');
        return redirect()->to('/inventario/depositos');
    }

    public function detalle(int $depositoId)
    {
        $deposito = $this->buscar($depositoId);
        if (!$deposito) {
            $this->error('No autorizado.');
            return redirect()->to('/inventario/depositos');
        }

        $page   = max(1, (int) ($this->request->getGet('page') ?? 1));
        $offset = ($page - 1) * self::POR_PAGINA_DETALLE;
        $total  = (int) Db::value("SELECT COUNT(*) FROM inventario WHERE deposito_id = ?", [$depositoId]);

        return $this->render('depositos.detalle', [
            'page_title'    => $deposito['nombre'],
            'page_subtitle' => 'Detalle del depósito',
            'deposito'      => new Fila($deposito),
            'inventario'    => Db::all(
                "SELECT i.*, p.codigo, p.nombre AS producto_nombre, p.imagen_principal
                 FROM inventario i JOIN productos p ON i.producto_id = p.producto_id
                 WHERE i.deposito_id = ?
                 ORDER BY p.nombre
                 LIMIT " . self::POR_PAGINA_DETALLE . " OFFSET {$offset}",
                [$depositoId]
            ),
            'pagination' => paginacion($total, $page, self::POR_PAGINA_DETALLE) + ['per_page' => self::POR_PAGINA_DETALLE],
        ]);
    }

    public function editar(int $depositoId)
    {
        $deposito = $this->buscar($depositoId);
        if (!$deposito) {
            $this->error('No autorizado.');
            return redirect()->to('/inventario/depositos');
        }

        return $this->render('depositos.editar', [
            'page_title'    => 'Editar Depósito',
            'page_subtitle' => $deposito['nombre'],
            'deposito'      => new Fila($deposito),
            'action'        => "/inventario/depositos/{$depositoId}/editar",
        ]);
    }

    public function actualizar(int $depositoId)
    {
        if (!$this->buscar($depositoId)) {
            $this->error('No autorizado.');
            return redirect()->to('/inventario/depositos');
        }

        $datos = $this->datos();
        Db::begin();
        $this->unicoPrincipal($datos, $depositoId);
        Db::update('depositos', $datos, ['deposito_id' => $depositoId]);
        Db::commit();

        $this->refrescarSesion();
        $this->success('Depósito actualizado.');
        return redirect()->to('/inventario/depositos');
    }

    public function eliminar(int $depositoId)
    {
        $deposito = $this->buscar($depositoId);
        if (!$deposito) {
            $this->error('No autorizado.');
            return redirect()->to('/inventario/depositos');
        }

        if ((int) Db::value("SELECT COUNT(*) FROM inventario WHERE deposito_id = ? AND existencia > 0", [$depositoId]) > 0) {
            $this->error('No se puede eliminar la bodega porque tiene productos con existencias. Debe trasladar los productos a otra bodega primero.');
            return redirect()->to('/inventario/depositos');
        }

        try {
            Db::query("DELETE FROM depositos WHERE deposito_id = ?", [$depositoId]);
        } catch (\Throwable) {
            // Tiene historial (ventas, compras, movimientos): se inactiva en lugar de borrarse
            Db::update('depositos', ['estado' => 'inactivo'], ['deposito_id' => $depositoId]);
            $this->warning('El depósito tiene historial de movimientos: se marcó como inactivo en lugar de eliminarse.');
            $this->refrescarSesion();
            return redirect()->to('/inventario/depositos');
        }

        $this->refrescarSesion();
        AuditService::log('inventario.deposito.eliminar', "Depósito eliminado: {$deposito['nombre']}", $depositoId, 'deposito', [], $this->empresaId());
        $this->success('Depósito eliminado.');
        return redirect()->to('/inventario/depositos');
    }

    private function buscar(int $depositoId): ?array
    {
        return Db::row("SELECT * FROM depositos WHERE deposito_id = ? AND sucursal_id = ?", [$depositoId, $this->sucursalId()]);
    }

    private function datos(): array
    {
        return [
            'codigo'       => trim((string) ($this->request->getPost('codigo') ?? '')),
            'nombre'       => trim((string) ($this->request->getPost('nombre') ?? '')),
            'descripcion'  => (string) ($this->request->getPost('descripcion') ?? ''),
            'es_principal' => $this->request->getPost('es_principal') === '1' ? 1 : 0,
        ];
    }

    /** Solo puede haber un depósito principal por sucursal. */
    private function unicoPrincipal(array $datos, int $exceptoId = 0): void
    {
        if ($datos['es_principal']) {
            Db::query("UPDATE depositos SET es_principal = 0 WHERE sucursal_id = ? AND deposito_id <> ?", [$this->sucursalId(), $exceptoId]);
        }
    }

    /** Fuerza al SucursalFilter a recargar la lista de depósitos en el próximo request. */
    private function refrescarSesion(): void
    {
        $this->session->remove('depositos_disponibles');
    }
}

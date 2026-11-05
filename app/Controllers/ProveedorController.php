<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\AuditService;
use App\Libraries\Auth;
use App\Libraries\Db;
use App\Libraries\Fila;
use App\Libraries\NumeracionService;

/** Permisos por ruta: proveedores.ver / crear / editar. */
class ProveedorController extends BaseController
{
    public function index(): string
    {
        $empresaId = $this->empresaId();
        $buscar    = trim((string) ($this->request->getGet('buscar') ?? ''));
        $porPagina = in_array((int) $this->request->getGet('por_pagina'), [10, 25, 50, 100], true) ? (int) $this->request->getGet('por_pagina') : 25;
        $page      = max(1, (int) ($this->request->getGet('page') ?? 1));

        $where  = 'empresa_id = ?';
        $params = [$empresaId];
        if ($buscar !== '') {
            $where .= ' AND (nombre LIKE ? OR codigo LIKE ? OR ruc LIKE ?)';
            array_push($params, "%{$buscar}%", "%{$buscar}%", "%{$buscar}%");
        }

        $total  = (int) Db::value("SELECT COUNT(*) FROM proveedores WHERE {$where}", $params);
        $offset = ($page - 1) * $porPagina;

        return $this->render('proveedores.lista', [
            'page_title'    => 'Proveedores',
            'page_subtitle' => 'Gestión de proveedores',
            'proveedores'   => Fila::lista(Db::all("SELECT * FROM proveedores WHERE {$where} ORDER BY nombre ASC LIMIT {$porPagina} OFFSET {$offset}", $params)),
            'pagination'    => paginacion($total, $page, $porPagina) + ['per_page' => $porPagina],
            'buscar'        => $buscar,
            'stats'         => Db::row(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN estado = 'activo' THEN 1 ELSE 0 END) AS activos,
                        SUM(CASE WHEN estado != 'activo' THEN 1 ELSE 0 END) AS inactivos,
                        (SELECT COUNT(DISTINCT proveedor_id) FROM compras WHERE empresa_id = ?) AS con_compras
                 FROM proveedores WHERE empresa_id = ?",
                [$empresaId, $empresaId]
            ),
        ]);
    }

    public function crear(): string
    {
        return $this->render('proveedores.crear', [
            'page_title'    => 'Nuevo Proveedor',
            'page_subtitle' => 'Registrar proveedor',
            'nuevo_codigo'  => NumeracionService::siguiente('proveedor', $this->empresaId()),
        ]);
    }

    public function guardar()
    {
        $datos = $this->datos();
        if ($datos['nombre'] === '') {
            $this->error('El nombre es requerido.');
            return redirect()->to('/inventario/proveedores/nuevo');
        }
        if ($datos['codigo'] === '') {
            $datos['codigo'] = NumeracionService::siguiente('proveedor', $this->empresaId());
        }

        $id = Db::insert('proveedores', $datos + [
            'empresa_id'     => $this->empresaId(),
            'estado'         => 'activo',
            'fecha_registro' => date('Y-m-d H:i:s'),
        ]);
        AuditService::log('inventario.proveedor.crear', "Proveedor creado: {$datos['nombre']}", $id, 'proveedor', [], $this->empresaId());

        $this->success('Proveedor creado exitosamente.');
        return redirect()->to('/inventario/proveedores');
    }

    public function editar(int $proveedorId)
    {
        $proveedor = $this->buscar($proveedorId);
        if (!$proveedor) {
            $this->error('Proveedor no encontrado o no autorizado.');
            return redirect()->to('/inventario/proveedores');
        }

        return $this->render('proveedores.editar', [
            'page_title'    => 'Editar Proveedor',
            'page_subtitle' => $proveedor['nombre'],
            'proveedor'     => new Fila($proveedor),
            'action'        => "/inventario/proveedores/{$proveedorId}/editar",
        ]);
    }

    public function actualizar(int $proveedorId)
    {
        if (!$this->buscar($proveedorId)) {
            $this->error('Acción no permitida.');
            return redirect()->to('/inventario/proveedores');
        }

        Db::update('proveedores', $this->datos(), ['proveedor_id' => $proveedorId]);
        $this->success('Proveedor actualizado.');
        return redirect()->to('/inventario/proveedores');
    }

    public function eliminar(int $proveedorId)
    {
        $proveedor = $this->buscar($proveedorId);
        if (!$proveedor) {
            $this->error('Acción no permitida.');
            return redirect()->to('/inventario/proveedores');
        }

        // Con compras registradas no se borra (perdería el historial): se inactiva
        if (Db::value("SELECT 1 FROM compras WHERE proveedor_id = ? LIMIT 1", [$proveedorId])) {
            Db::update('proveedores', ['estado' => 'inactivo'], ['proveedor_id' => $proveedorId]);
            $this->warning("El proveedor tiene compras registradas: se marcó como inactivo en lugar de eliminarse.");
            return redirect()->to('/inventario/proveedores');
        }

        Db::query("DELETE FROM proveedores WHERE proveedor_id = ?", [$proveedorId]);
        AuditService::log('inventario.proveedor.eliminar', "Proveedor eliminado: {$proveedor['nombre']}", $proveedorId, 'proveedor', [], $this->empresaId());

        $this->success('Proveedor eliminado.');
        return redirect()->to('/inventario/proveedores');
    }

    // ── Evaluaciones ─────────────────────────────────────────────────────────

    public function evaluaciones(int $proveedorId)
    {
        $proveedor = $this->buscar($proveedorId);
        if (!$proveedor) {
            $this->error('Proveedor no encontrado.');
            return redirect()->to('/inventario/proveedores');
        }

        return $this->render('proveedores.evaluaciones', [
            'page_title'    => 'Evaluaciones — ' . $proveedor['nombre'],
            'page_subtitle' => 'Historial de rendimiento',
            'proveedor'     => new Fila($proveedor),
            'evaluaciones'  => Db::all(
                "SELECT e.*, u.username AS usuario_nombre, c.numero_factura, c.fecha_compra
                 FROM proveedor_evaluaciones e
                 LEFT JOIN users u ON e.usuario_id = u.id
                 LEFT JOIN compras c ON e.compra_id = c.compra_id
                 WHERE e.proveedor_id = ?
                 ORDER BY e.fecha_evaluacion DESC",
                [$proveedorId]
            ),
            'metricas' => Db::row(
                "SELECT COUNT(*) AS total_evaluaciones, ROUND(AVG(dias_entrega), 1) AS avg_dias_entrega,
                        ROUND(AVG(pct_cumplimiento), 1) AS avg_cumplimiento, ROUND(AVG(calidad), 1) AS avg_calidad
                 FROM proveedor_evaluaciones WHERE proveedor_id = ?",
                [$proveedorId]
            ),
            // Compras recibidas aún sin evaluar
            'comprasSinEvaluar' => Db::all(
                "SELECT c.compra_id, c.numero_factura, c.fecha_compra, c.monto_total
                 FROM compras c
                 WHERE c.proveedor_id = ? AND c.sucursal_id = ?
                   AND c.estado IN ('recibida', 'parcialmente_recibida')
                   AND c.compra_id NOT IN (SELECT compra_id FROM proveedor_evaluaciones WHERE proveedor_id = ? AND compra_id IS NOT NULL)
                 ORDER BY c.fecha_compra DESC LIMIT 10",
                [$proveedorId, $this->sucursalId(), $proveedorId]
            ),
        ]);
    }

    public function guardarEvaluacion()
    {
        $proveedorId = (int) $this->request->getPost('proveedor_id');
        $compraId    = $this->request->getPost('compra_id') ? (int) $this->request->getPost('compra_id') : null;

        if (!$this->buscar($proveedorId)) {
            $this->error('Proveedor no válido.');
            return redirect()->to('/inventario/proveedores');
        }

        // El cumplimiento se calcula de la compra si se eligió una
        $pctCumplimiento = null;
        if ($compraId) {
            $row = Db::row("SELECT SUM(cantidad_recibida) AS rec, SUM(cantidad) AS ped FROM compras_detalle WHERE compra_id = ?", [$compraId]);
            if ($row && (float) $row['ped'] > 0) {
                $pctCumplimiento = round(((float) $row['rec'] / (float) $row['ped']) * 100, 2);
            }
        } elseif (($pct = (string) $this->request->getPost('pct_cumplimiento')) !== '') {
            $pctCumplimiento = (float) $pct;
        }

        $diasEntrega = (string) $this->request->getPost('dias_entrega');
        $calidad     = (string) $this->request->getPost('calidad');

        Db::insert('proveedor_evaluaciones', [
            'proveedor_id'     => $proveedorId,
            'compra_id'        => $compraId,
            'empresa_id'       => $this->empresaId(),
            'dias_entrega'     => $diasEntrega !== '' ? (int) $diasEntrega : null,
            'pct_cumplimiento' => $pctCumplimiento,
            'calidad'          => $calidad !== '' ? (int) $calidad : null,
            'notas'            => (string) ($this->request->getPost('notas') ?? ''),
            'usuario_id'       => Auth::id(),
        ]);

        $this->success('Evaluación registrada.');
        return redirect()->to("/inventario/proveedores/{$proveedorId}/evaluaciones");
    }

    private function buscar(int $proveedorId): ?array
    {
        return Db::row("SELECT * FROM proveedores WHERE proveedor_id = ? AND empresa_id = ?", [$proveedorId, $this->empresaId()]);
    }

    private function datos(): array
    {
        $post = fn (string $k) => trim((string) ($this->request->getPost($k) ?? ''));
        return [
            'codigo'    => $post('codigo'),
            'nombre'    => $post('nombre'),
            'ruc'       => $post('ruc'),
            'dv'        => $post('dv'),
            'direccion' => $post('direccion'),
            'telefono'  => $post('telefono'),
            'email'     => $post('email'),
            'contacto'  => $post('contacto'),
        ];
    }
}

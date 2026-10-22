<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Models\Proveedor;

class ProveedorController extends Controller
{
    public function index(): void
    {
        $buscar    = trim($this->request->get('buscar', ''));
        $empresaId = $this->empresaId();
        $perPage   = \in_array((int) $this->request->get('por_pagina', '25'), [10, 25, 50, 100], true)
                        ? (int) $this->request->get('por_pagina', '25') : 25;
        $page      = max(1, (int) $this->request->get('page', '1'));

        $where  = 'empresa_id = ?';
        $params = [$empresaId];
        if ($buscar !== '') {
            $where   .= ' AND (nombre LIKE ? OR codigo LIKE ? OR ruc LIKE ?)';
            $params[] = "%{$buscar}%";
            $params[] = "%{$buscar}%";
            $params[] = "%{$buscar}%";
        }

        $proveedores = Proveedor::paginate($perPage, $page, $where, $params, 'nombre ASC');

        $stats = Database::query(
            "SELECT
                COUNT(*)                                                              AS total,
                SUM(CASE WHEN estado = 'activo'   THEN 1 ELSE 0 END)                AS activos,
                SUM(CASE WHEN estado != 'activo'  THEN 1 ELSE 0 END)                AS inactivos,
                (SELECT COUNT(DISTINCT proveedor_id) FROM compras WHERE empresa_id = ?) AS con_compras
             FROM proveedores WHERE empresa_id = ?",
            [$empresaId, $empresaId]
        )->fetch();

        $this->view('proveedores.lista', [
            'page_title'    => 'Proveedores',
            'page_subtitle' => 'Gestión de proveedores',
            'proveedores'   => $proveedores['items'],
            'pagination'    => $proveedores,
            'buscar'        => $buscar,
            'stats'         => $stats,
        ]);
    }

    public function crear(): void
    {
        $this->view('proveedores.crear', [
            'page_title' => 'Nuevo Proveedor',
            'page_subtitle' => 'Registrar proveedor',
            'nuevo_codigo' => \App\Services\NumeracionService::siguiente('proveedor', $this->empresaId()),
        ]);
    }

    public function guardar(): void
    {
        if (!$this->verifyCsrf()) return;

        $codigo = trim($this->request->post('codigo', ''));
        if ($codigo === '') {
            $codigo = \App\Services\NumeracionService::siguiente('proveedor', $this->empresaId());
        }

        $data = [
            'empresa_id' => $this->empresaId(),
            'codigo' => $codigo,
            'nombre' => $this->request->post('nombre', ''),
            'ruc' => $this->request->post('ruc', ''),
            'dv' => $this->request->post('dv', ''),
            'direccion' => $this->request->post('direccion', ''),
            'telefono' => $this->request->post('telefono', ''),
            'email' => $this->request->post('email', ''),
            'contacto' => $this->request->post('contacto', ''),
            'estado' => 'activo',
            'fecha_registro' => date('Y-m-d H:i:s'),
        ];

        Proveedor::create($data);
        $this->success('Proveedor creado exitosamente.');
        $this->redirect('/inventario/proveedores');
    }

    public function editar(int $proveedor_id): void
    {
        $proveedor = Proveedor::whereFirst([
            'proveedor_id' => $proveedor_id,
            'empresa_id' => $this->empresaId()
        ]);

        if (!$proveedor) {
            $this->error('Proveedor no encontrado o no autorizado.');
            $this->redirect('/inventario/proveedores');
            return;
        }

        $this->view('proveedores.editar', [
            'page_title' => 'Editar Proveedor',
            'page_subtitle' => $proveedor->nombre,
            'proveedor' => $proveedor,
            'action' => "/inventario/proveedores/{$proveedor->proveedor_id}/editar"
        ]);
    }

    public function actualizar(int $proveedor_id): void
    {
        if (!$this->verifyCsrf()) return;
        
        $proveedor = Proveedor::whereFirst([
            'proveedor_id' => $proveedor_id,
            'empresa_id' => $this->empresaId()
        ]);

        if (!$proveedor) {
            $this->error('Acción no permitida.');
            $this->redirect('/inventario/proveedores');
            return;
        }

        $data = [
            'codigo' => $this->request->post('codigo', ''),
            'nombre' => $this->request->post('nombre', ''),
            'ruc' => $this->request->post('ruc', ''),
            'dv' => $this->request->post('dv', ''),
            'direccion' => $this->request->post('direccion', ''),
            'telefono' => $this->request->post('telefono', ''),
            'email' => $this->request->post('email', ''),
            'contacto' => $this->request->post('contacto', ''),
        ];

        $proveedor->update($data);
        $this->success('Proveedor actualizado.');
        $this->redirect('/inventario/proveedores');
    }

    public function eliminar(int $proveedor_id): void
    {
        if (!$this->verifyCsrf()) return;

        $proveedor = Proveedor::whereFirst([
            'proveedor_id' => $proveedor_id,
            'empresa_id' => $this->empresaId()
        ]);

        if (!$proveedor) {
            $this->error('Acción no permitida.');
            $this->redirect('/inventario/proveedores');
            return;
        }

        $proveedor->delete();
        $this->success('Proveedor eliminado.');
        $this->redirect('/inventario/proveedores');
    }

    // ── Evaluaciones ─────────────────────────────────────────────────────────

    public function evaluaciones(int $proveedor_id): void
    {
        $proveedor = Proveedor::whereFirst([
            'proveedor_id' => $proveedor_id,
            'empresa_id'   => $this->empresaId()
        ]);

        if (!$proveedor) {
            $this->error('Proveedor no encontrado.');
            $this->redirect('/inventario/proveedores');
            return;
        }

        $evaluaciones = Database::query(
            "SELECT e.*, u.username AS usuario_nombre,
                    c.numero_factura, c.fecha_compra
             FROM proveedor_evaluaciones e
             LEFT JOIN users u ON e.usuario_id = u.id
             LEFT JOIN compras c ON e.compra_id = c.compra_id
             WHERE e.proveedor_id = ?
             ORDER BY e.fecha_evaluacion DESC",
            [$proveedor_id]
        )->fetchAll();

        $metricas = Database::query(
            "SELECT
                COUNT(*)                        AS total_evaluaciones,
                ROUND(AVG(dias_entrega), 1)     AS avg_dias_entrega,
                ROUND(AVG(pct_cumplimiento), 1) AS avg_cumplimiento,
                ROUND(AVG(calidad), 1)          AS avg_calidad
             FROM proveedor_evaluaciones
             WHERE proveedor_id = ?",
            [$proveedor_id]
        )->fetch();

        // Compras recibidas sin evaluar
        $comprasSinEvaluar = Database::query(
            "SELECT c.compra_id, c.numero_factura, c.fecha_compra, c.monto_total
             FROM compras c
             WHERE c.proveedor_id = ?
               AND c.sucursal_id = ?
               AND c.estado IN ('recibida','parcialmente_recibida')
               AND c.compra_id NOT IN (
                   SELECT compra_id FROM proveedor_evaluaciones WHERE proveedor_id = ? AND compra_id IS NOT NULL
               )
             ORDER BY c.fecha_compra DESC
             LIMIT 10",
            [$proveedor_id, $this->sucursalId(), $proveedor_id]
        )->fetchAll();

        $this->view('proveedores.evaluaciones', [
            'page_title'         => 'Evaluaciones — ' . $proveedor->nombre,
            'page_subtitle'      => 'Historial de rendimiento',
            'proveedor'          => $proveedor,
            'evaluaciones'       => $evaluaciones,
            'metricas'           => $metricas,
            'comprasSinEvaluar'  => $comprasSinEvaluar,
        ]);
    }

    public function guardarEvaluacion(): void
    {
        if (!$this->verifyCsrf()) return;

        $proveedorId = (int) $this->request->post('proveedor_id');
        $compraId    = $this->request->post('compra_id') ? (int) $this->request->post('compra_id') : null;

        $proveedor = Proveedor::whereFirst([
            'proveedor_id' => $proveedorId,
            'empresa_id'   => $this->empresaId()
        ]);

        if (!$proveedor) {
            $this->error('Proveedor no válido.');
            $this->redirect('/inventario/proveedores');
            return;
        }

        // Calcular cumplimiento automático desde la compra si se proporcionó
        $pctCumplimiento = null;
        if ($compraId) {
            $row = Database::query(
                "SELECT SUM(cantidad_recibida) AS rec, SUM(cantidad) AS ped
                 FROM compras_detalle WHERE compra_id = ?",
                [$compraId]
            )->fetch();
            if ($row && (float)$row['ped'] > 0) {
                $pctCumplimiento = round(((float)$row['rec'] / (float)$row['ped']) * 100, 2);
            }
        } else {
            $pct = $this->request->post('pct_cumplimiento');
            $pctCumplimiento = $pct !== '' ? (float) $pct : null;
        }

        Database::query(
            "INSERT INTO proveedor_evaluaciones
                (proveedor_id, compra_id, empresa_id, dias_entrega, pct_cumplimiento, calidad, notas, usuario_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $proveedorId,
                $compraId,
                $this->empresaId(),
                $this->request->post('dias_entrega') !== '' ? (int) $this->request->post('dias_entrega') : null,
                $pctCumplimiento,
                $this->request->post('calidad') !== '' ? (int) $this->request->post('calidad') : null,
                $this->request->post('notas', ''),
                Auth::id(),
            ]
        );

        $this->success('Evaluación registrada.');
        $this->redirect("/inventario/proveedores/{$proveedorId}/evaluaciones");
    }
}

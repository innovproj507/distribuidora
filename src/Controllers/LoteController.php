<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

class LoteController extends Controller
{
    /**
     * Listado de lotes con estado y estadísticas
     */
    public function index(): void
    {
        $empresaId = $this->empresaId();
        $sucursalId = $this->sucursalId();

        $lotes = Database::query(
            "SELECT l.*,
                    p.nombre AS producto_nombre,
                    p.codigo AS codigo,
                    d.nombre AS deposito_nombre,
                    DATEDIFF(l.fecha_vencimiento, CURDATE()) AS dias_para_vencer,
                    CASE
                        WHEN l.fecha_vencimiento IS NULL THEN 'sin_vencimiento'
                        WHEN l.fecha_vencimiento < CURDATE() THEN 'vencido'
                        WHEN DATEDIFF(l.fecha_vencimiento, CURDATE()) <= 30 THEN 'por_vencer'
                        ELSE 'vigente'
                    END AS estado_vencimiento
             FROM lotes l
             JOIN productos p ON l.producto_id = p.producto_id
             JOIN depositos d ON l.deposito_id = d.deposito_id
             WHERE p.empresa_id = ? AND d.sucursal_id = ?
             ORDER BY l.fecha_vencimiento IS NULL ASC, l.fecha_vencimiento ASC",
            [$empresaId, $sucursalId]
        )->fetchAll();

        // Estadísticas
        $total = count($lotes);
        $porVencer = 0;
        $vencidos = 0;
        $sinFecha = 0;

        foreach ($lotes as $lot) {
            if ($lot['estado_vencimiento'] === 'por_vencer') {
                $porVencer++;
            } elseif ($lot['estado_vencimiento'] === 'vencido') {
                $vencidos++;
            } elseif ($lot['estado_vencimiento'] === 'sin_vencimiento') {
                $sinFecha++;
            }
        }

        $stats = [
            'total' => $total,
            'por_vencer' => $porVencer,
            'vencidos' => $vencidos,
            'sin_fecha' => $sinFecha,
        ];

        $this->view('inventario.lotes', [
            'page_title' => 'Control de Lotes',
            'page_subtitle' => 'Trazabilidad y fechas de vencimiento',
            'lotes' => $lotes,
            'stats' => $stats,
        ]);
    }

    /**
     * Marcar como vencidos los lotes cuya fecha ya pasó
     */
    public function marcarVencidos(): void
    {
        if (!$this->verifyCsrf()) return;

        Database::query(
            "UPDATE lotes SET estado='vencido' WHERE fecha_vencimiento < CURDATE() AND estado='activo'"
        );

        $this->success('Lotes vencidos actualizados correctamente.');
        $this->redirect('/inventario/lotes');
    }
}

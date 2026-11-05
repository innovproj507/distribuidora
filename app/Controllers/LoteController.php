<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Db;

/** Control de lotes y vencimientos. Permisos por ruta: lotes.ver / gestionar. */
class LoteController extends BaseController
{
    public function index(): string
    {
        $lotes = Db::all(
            "SELECT l.*, p.nombre AS producto_nombre, p.codigo AS codigo, d.nombre AS deposito_nombre,
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
            [$this->empresaId(), $this->sucursalId()]
        );

        $estados = array_count_values(array_column($lotes, 'estado_vencimiento'));

        return $this->render('inventario.lotes', [
            'page_title'    => 'Control de Lotes',
            'page_subtitle' => 'Trazabilidad y fechas de vencimiento',
            'lotes'         => $lotes,
            'stats'         => [
                'total'      => count($lotes),
                'por_vencer' => $estados['por_vencer'] ?? 0,
                'vencidos'   => $estados['vencido'] ?? 0,
                'sin_fecha'  => $estados['sin_vencimiento'] ?? 0,
            ],
        ]);
    }

    /** Marca como vencidos los lotes activos cuya fecha ya pasó. */
    public function marcarVencidos()
    {
        Db::query(
            "UPDATE lotes l JOIN productos p ON l.producto_id = p.producto_id
             SET l.estado = 'vencido'
             WHERE l.fecha_vencimiento < CURDATE() AND l.estado = 'activo' AND p.empresa_id = ?",
            [$this->empresaId()]
        );
        $this->success('Lotes vencidos actualizados correctamente.');
        return redirect()->to('/inventario/lotes');
    }
}

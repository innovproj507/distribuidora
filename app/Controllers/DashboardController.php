<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Db;
use App\Libraries\SmartInsightService;

class DashboardController extends BaseController
{
    public function index(): string
    {
        $sucursalId = $this->sucursalId();
        $empresaId  = $this->empresaId();
        $depositoId = $this->depositoId();

        if (!$depositoId) {
            $depositoId = (int) (Db::value(
                "SELECT deposito_id FROM depositos WHERE sucursal_id = ? AND estado = 'activo' ORDER BY es_principal DESC LIMIT 1",
                [$sucursalId]
            ) ?? 0);
        }

        $totalProductos = (int) Db::value(
            "SELECT COUNT(*) FROM productos p
             WHERE p.estado = 'activo' AND p.empresa_id = ?
               AND EXISTS (SELECT 1 FROM inventario i WHERE i.producto_id = p.producto_id AND i.deposito_id = ?)",
            [$empresaId, $depositoId]
        );

        $totalClientes = (int) Db::value(
            "SELECT COUNT(*) FROM clientes WHERE estado = 'activo' AND empresa_id = ?",
            [$empresaId]
        );

        $ventasHoy = Db::row(
            "SELECT COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total
             FROM ventas WHERE DATE(fecha) = CURDATE() AND estado != 'anulada' AND sucursal_id = ?",
            [$sucursalId]
        );

        $stockBajo = (int) Db::value(
            "SELECT COUNT(*) FROM inventario i JOIN productos p ON i.producto_id = p.producto_id
             WHERE i.existencia <= i.minimo AND i.existencia > 0 AND p.estado = 'activo' AND i.deposito_id = ?",
            [$depositoId]
        );

        $agotados = (int) Db::value(
            "SELECT COUNT(DISTINCT p.producto_id) FROM productos p
             JOIN inventario i ON p.producto_id = i.producto_id
             WHERE p.estado = 'activo' AND (i.existencia IS NULL OR i.existencia <= 0) AND i.deposito_id = ?",
            [$depositoId]
        );

        $ultimasVentas = Db::all(
            "SELECT v.*, c.nombre AS cliente_nombre, u.username AS vendedor_nombre
             FROM ventas v
             LEFT JOIN clientes c ON v.cliente_id = c.cliente_id
             LEFT JOIN users u ON v.vendedor_id = u.id
             WHERE v.sucursal_id = ?
             ORDER BY v.fecha DESC LIMIT 10",
            [$sucursalId]
        );

        $listaStockBajo = Db::all(
            "SELECT p.nombre, p.codigo, i.existencia, i.minimo, d.nombre AS deposito_nombre
             FROM inventario i
             JOIN productos p ON i.producto_id = p.producto_id
             JOIN depositos d ON i.deposito_id = d.deposito_id
             WHERE i.existencia <= i.minimo AND p.estado = 'activo' AND i.deposito_id = ?
             ORDER BY i.existencia ASC LIMIT 5",
            [$depositoId]
        );

        $aiEnabled = (bool) Db::value("SELECT ai_enabled FROM companies WHERE empresa_id = ?", [$empresaId]);
        $insights  = $aiEnabled ? SmartInsightService::generateInsights((int) $empresaId) : [];

        $lotesVenciendo = (int) Db::value(
            "SELECT COUNT(*) FROM lotes l
             JOIN depositos d ON l.deposito_id = d.deposito_id
             JOIN productos p ON l.producto_id = p.producto_id
             WHERE l.estado = 'activo' AND l.fecha_vencimiento IS NOT NULL
               AND DATEDIFF(l.fecha_vencimiento, CURDATE()) BETWEEN 0 AND 30
               AND p.empresa_id = ? AND d.sucursal_id = ?",
            [$empresaId, $sucursalId]
        );

        $lotesVencidos = (int) Db::value(
            "SELECT COUNT(*) FROM lotes l
             JOIN depositos d ON l.deposito_id = d.deposito_id
             JOIN productos p ON l.producto_id = p.producto_id
             WHERE l.estado = 'activo' AND l.fecha_vencimiento < CURDATE()
               AND p.empresa_id = ? AND d.sucursal_id = ?",
            [$empresaId, $sucursalId]
        );

        // Ventas de los últimos 7 días (días sin ventas en 0) para el gráfico
        $porDia = array_column(Db::all(
            "SELECT DATE(fecha) AS dia, SUM(total) AS total
             FROM ventas
             WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND estado != 'anulada' AND sucursal_id = ?
             GROUP BY DATE(fecha)",
            [$sucursalId]
        ), 'total', 'dia');

        $dias = ['Sunday' => 'Dom', 'Monday' => 'Lun', 'Tuesday' => 'Mar', 'Wednesday' => 'Mié', 'Thursday' => 'Jue', 'Friday' => 'Vie', 'Saturday' => 'Sáb'];
        $ventasSemanales = [];
        for ($i = 6; $i >= 0; $i--) {
            $ts = strtotime("-{$i} days");
            $ventasSemanales[] = [
                'label' => $dias[date('l', $ts)] . ' ' . date('d', $ts),
                'total' => (float) ($porDia[date('Y-m-d', $ts)] ?? 0),
            ];
        }

        return $this->render('dashboard.index', [
            'page_title'      => 'Dashboard',
            'page_subtitle'   => 'Resumen general del sistema',
            'totalProductos'  => $totalProductos,
            'totalClientes'   => $totalClientes,
            'ventasHoy'       => $ventasHoy,
            'stockBajo'       => $stockBajo,
            'agotados'        => $agotados,
            'ultimasVentas'   => $ultimasVentas,
            'listaStockBajo'  => $listaStockBajo,
            'insights'        => $insights,
            'ai_enabled'      => $aiEnabled,
            'lotesVenciendo'  => $lotesVenciendo,
            'lotesVencidos'   => $lotesVencidos,
            'ventasSemanales' => $ventasSemanales,
        ]);
    }
}

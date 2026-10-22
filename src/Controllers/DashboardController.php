<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Auth;
use App\Models\Empresa;
use App\Services\SmartInsightService;

class DashboardController extends Controller
{
    /**
     * Dashboard principal
     */
    public function index(): void
    {
        // Sistema empresa única — no hay Panel Maestro

        $sucursalId = $this->sucursalId();
        $depositoActivo = $this->session->get('deposito_actual');
        $depositoId = $depositoActivo ? (int)$depositoActivo['deposito_id'] : null;

        if (!$depositoId) {
            $depositoDefecto = Database::query("SELECT deposito_id FROM depositos WHERE sucursal_id = ? AND estado = 'activo' ORDER BY es_principal DESC LIMIT 1", [$sucursalId])->fetch();
            $depositoId = $depositoDefecto ? (int)$depositoDefecto['deposito_id'] : 0;
        }

        // Estadísticas rápidas (Filtradas por el depósito seleccionado)
        $totalProductos = Database::query(
            "SELECT COUNT(*) as t FROM productos p 
             WHERE p.estado = 'activo' AND p.empresa_id = ? 
             AND EXISTS (SELECT 1 FROM inventario i WHERE i.producto_id = p.producto_id AND i.deposito_id = ?)", 
            [$this->empresaId(), $depositoId]
        )->fetch()['t'];
        
        $totalClientes = Database::query("SELECT COUNT(DISTINCT c.cliente_id) as t 
             FROM clientes c 
             WHERE c.estado = 'activo' AND c.empresa_id = ?",
            [$this->empresaId()]
        )->fetch()['t'];

        // Ventas del día en esta sucursal
        $ventasHoy = Database::query(
            "SELECT COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total 
             FROM ventas WHERE DATE(fecha) = CURDATE() AND estado != 'anulada' AND sucursal_id = ?",
            [$sucursalId]
        )->fetch();

        // Productos con stock bajo (en el depósito seleccionado)
        $stockBajo = Database::query(
            "SELECT COUNT(*) as t FROM inventario i 
             JOIN productos p ON i.producto_id = p.producto_id 
             WHERE i.existencia <= i.minimo AND i.existencia > 0 AND p.estado = 'activo' AND i.deposito_id = ?",
             [$depositoId]
        )->fetch()['t'];

        // Productos agotados (en el depósito seleccionado)
        $agotados = Database::query(
            "SELECT COUNT(DISTINCT p.producto_id) as t FROM productos p 
             JOIN inventario i ON p.producto_id = i.producto_id 
             WHERE p.estado = 'activo' AND (i.existencia IS NULL OR i.existencia <= 0) AND i.deposito_id = ?",
             [$depositoId]
        )->fetch()['t'];

        // Últimas 10 ventas (de esta sucursal)
        $ultimasVentas = Database::query(
            "SELECT v.*, c.nombre as cliente_nombre, u.username as vendedor_nombre
             FROM ventas v 
             LEFT JOIN clientes c ON v.cliente_id = c.cliente_id 
             LEFT JOIN users u ON v.vendedor_id = u.id
             WHERE v.sucursal_id = ?
             ORDER BY v.fecha DESC LIMIT 10",
             [$sucursalId]
        )->fetchAll();

        // Productos con stock bajo (lista detallada de este depósito)
        $listaStockBajo = Database::query(
            "SELECT p.nombre, p.codigo, i.existencia, i.minimo, d.nombre as deposito_nombre
             FROM inventario i 
             JOIN productos p ON i.producto_id = p.producto_id 
             JOIN depositos d ON i.deposito_id = d.deposito_id
             WHERE i.existencia <= i.minimo AND p.estado = 'activo' AND i.deposito_id = ?
             ORDER BY i.existencia ASC LIMIT 5",
             [$depositoId]
        )->fetchAll();

        $empresaId = $this->empresaId();
        $empresa = Empresa::find($empresaId);
        $insights = [];

        if ($empresa && $empresa->ai_enabled) {
            $insights = SmartInsightService::generateInsights($empresaId);
        }

        // Alertas de lotes
        $lotesVenciendo = (int) Database::query(
            "SELECT COUNT(*) as t FROM lotes l
             JOIN depositos d ON l.deposito_id = d.deposito_id
             JOIN productos p ON l.producto_id = p.producto_id
             WHERE l.estado = 'activo'
               AND l.fecha_vencimiento IS NOT NULL
               AND DATEDIFF(l.fecha_vencimiento, CURDATE()) BETWEEN 0 AND 30
               AND p.empresa_id = ? AND d.sucursal_id = ?",
            [$empresaId, $sucursalId]
        )->fetch()['t'];

        $lotesVencidos = (int) Database::query(
            "SELECT COUNT(*) as t FROM lotes l
             JOIN depositos d ON l.deposito_id = d.deposito_id
             JOIN productos p ON l.producto_id = p.producto_id
             WHERE l.estado = 'activo'
               AND l.fecha_vencimiento < CURDATE()
               AND p.empresa_id = ? AND d.sucursal_id = ?",
            [$empresaId, $sucursalId]
        )->fetch()['t'];

        // Ventas de los últimos 7 días para el gráfico
        $ventasSemanalesRaw = Database::query(
            "SELECT DATE(fecha) as dia, SUM(total) as total
             FROM ventas
             WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
               AND estado != 'anulada'
               AND sucursal_id = ?
             GROUP BY DATE(fecha)
             ORDER BY dia ASC",
            [$sucursalId]
        )->fetchAll();

        // Rellenar días faltantes con 0
        $ventasSemanales = [];
        $diasLabels = [
            'Sunday' => 'Dom', 'Monday' => 'Lun', 'Tuesday' => 'Mar', 
            'Wednesday' => 'Mié', 'Thursday' => 'Jue', 'Friday' => 'Vie', 'Saturday' => 'Sáb'
        ];

        for ($i = 6; $i >= 0; $i--) {
            $timestamp = strtotime("-$i days");
            $date = date('Y-m-d', $timestamp);
            $dayName = $diasLabels[date('l', $timestamp)];
            
            $total = 0;
            foreach ($ventasSemanalesRaw as $v) {
                if ($v['dia'] === $date) {
                    $total = (float)$v['total'];
                    break;
                }
            }
            $ventasSemanales[] = [
                'label' => $dayName . ' ' . date('d', $timestamp),
                'total' => $total
            ];
        }

        $this->view('dashboard.index', [
            'page_title' => 'Dashboard',
            'page_subtitle' => 'Resumen general del sistema',
            'totalProductos' => $totalProductos,
            'totalClientes' => $totalClientes,
            'ventasHoy' => $ventasHoy,
            'stockBajo' => $stockBajo,
            'agotados' => $agotados,
            'ultimasVentas' => $ultimasVentas,
            'listaStockBajo' => $listaStockBajo,
            'insights' => $insights,
            'ai_enabled' => $empresa->ai_enabled ?? false,
            'lotesVenciendo' => $lotesVenciendo,
            'lotesVencidos' => $lotesVencidos,
            'ventasSemanales' => $ventasSemanales,
        ]);
    }
}

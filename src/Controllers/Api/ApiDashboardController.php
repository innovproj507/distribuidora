<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;

/**
 * ApiDashboardController - Estadísticas avanzadas para el dashboard
 */
class ApiDashboardController extends ApiController
{
    /**
     * Obtener estadísticas completas del dashboard
     */
    public function index(): void
    {
        $sucursalId = (int) $this->sucursalId();
        $empresaId = (int) $this->empresaId();
        
        // Si no hay empresa ni sucursal (ej: Superadmin Global), retornar vacío
        if (!$empresaId || !$sucursalId) {
            $this->successResponse([
                'ventas_hoy' => ['total' => 0, 'cantidad' => 0],
                'totalProductos' => 0,
                'stockBajo' => 0,
                'agotados' => 0,
                'listaStockBajo' => [],
                'ultimasVentas' => [],
                'ai_enabled' => false,
                'insights' => []
            ]);
            return;
        }
        // Ventas de hoy
        $ventasHoy = Database::query(
            "SELECT COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total 
             FROM ventas 
             WHERE sucursal_id = ? AND DATE(fecha) = CURDATE() AND estado != 'anulada'",
            [$sucursalId]
        )->fetch();

        // Métricas de Inventario (Productos en esta sucursal)
        $totalProductos = Database::query(
            "SELECT COUNT(DISTINCT p.producto_id) as total 
             FROM productos p 
             JOIN inventario i ON p.producto_id = i.producto_id 
             JOIN depositos d ON i.deposito_id = d.deposito_id
             WHERE d.sucursal_id = ? AND p.estado = 'activo'",
            [$sucursalId]
        )->fetch()['total'];

        // Stock Crítico: Existencia <= stock_minimo (subquery para evitar HAVING con columna agrupada)
        $stockBajoCount = Database::query(
            "SELECT COUNT(*) as total FROM (
                SELECT p.producto_id
                FROM productos p
                JOIN inventario i ON p.producto_id = i.producto_id
                JOIN depositos d ON i.deposito_id = d.deposito_id
                WHERE d.sucursal_id = ? AND p.estado = 'activo'
                GROUP BY p.producto_id, p.stock_minimo
                HAVING SUM(i.existencia) <= p.stock_minimo AND SUM(i.existencia) > 0
             ) sub",
            [$sucursalId]
        )->fetch()['total'] ?? 0;

        $agotadosCount = Database::query(
            "SELECT COUNT(*) as total FROM (
                SELECT p.producto_id
                FROM productos p
                JOIN inventario i ON p.producto_id = i.producto_id
                JOIN depositos d ON i.deposito_id = d.deposito_id
                WHERE d.sucursal_id = ? AND p.estado = 'activo'
                GROUP BY p.producto_id
                HAVING SUM(i.existencia) <= 0
             ) sub",
            [$sucursalId]
        )->fetch()['total'] ?? 0;

        // Lista de Stock Crítico (Top 5)
        $listaStockBajo = Database::query(
            "SELECT p.nombre, SUM(i.existencia) as existencia, p.stock_minimo as minimo, 
                    GROUP_CONCAT(DISTINCT d.nombre SEPARATOR ', ') as depositos
             FROM productos p
             JOIN inventario i ON p.producto_id = i.producto_id
             JOIN depositos d ON i.deposito_id = d.deposito_id
             WHERE d.sucursal_id = ? AND p.estado = 'activo'
             GROUP BY p.producto_id
             HAVING existencia <= p.stock_minimo
             ORDER BY existencia ASC LIMIT 5",
            [$sucursalId]
        )->fetchAll();

        // Últimas 5 ventas
        $ultimasVentas = Database::query(
            "SELECT v.*, c.nombre as cliente_nombre 
             FROM ventas v
             LEFT JOIN clientes c ON v.cliente_id = c.cliente_id
             WHERE v.sucursal_id = ?
             ORDER BY v.fecha DESC LIMIT 5",
            [$sucursalId]
        )->fetchAll();

        $this->successResponse([
            'ventas_hoy' => [
                'total' => (float) ($ventasHoy['total'] ?? 0),
                'cantidad' => (int) $ventasHoy['cantidad']
            ],
            'totalProductos' => (int) $totalProductos,
            'stockBajo' => (int) $stockBajoCount,
            'agotados' => (int) $agotadosCount,
            'listaStockBajo' => $listaStockBajo,
            'ultimasVentas' => $ultimasVentas,
            'ai_enabled' => true,
            'insights' => [] // Los insights se podrían cargar en una llamada aparte si son lentos
        ]);
    }
}

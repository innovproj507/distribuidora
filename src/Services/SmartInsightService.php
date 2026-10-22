<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class SmartInsightService
{
    /**
     * Generar lista de insights para el dashboard
     */
    public static function generateInsights(int $empresaId): array
    {
        $insights = [];

        // 1. Predicción de Agotamiento de Stock
        $stockInsights = self::getStockPredictions($empresaId);
        if (!empty($stockInsights)) {
            foreach ($stockInsights as $si) {
                $insights[] = [
                    'type' => 'warning',
                    'icon' => 'fas fa-hourglass-half',
                    'title' => 'Alerta de Inventario',
                    'message' => "El producto '{$si['nombre']}' se agotará en aprox. " . ceil($si['existencia'] / ($si['ventas_diarias'] ?: 1)) . " días.",
                    'detail' => "Ritmo de venta: " . number_format($si['ventas_diarias'], 2) . " unidades/día.",
                    'action_label' => 'Reordenar',
                    'action_url' => '/inventario'
                ];
            }
        }

        // 2. Clientes TOP (Crecimiento)
        $vipInsights = self::getVIPGrowth($empresaId);
        if ($vipInsights) {
            $insights[] = [
                'type' => 'success',
                'icon' => 'fas fa-star',
                'title' => 'Análisis de Clientes',
                'message' => "{$vipInsights['nombre']} es tu cliente más activo este mes.",
                'detail' => "Ha realizado {$vipInsights['ventas_mes']} compras. Un programa de lealtad podría aumentar su ticket.",
                'action_label' => 'Ficha de Cliente',
                'action_url' => "/clientes/{$vipInsights['cliente_id']}"
            ];
        }

        // 3. Alerta de Ticket Promedio
        $ticketInsight = self::getAverageTicketInsight($empresaId);
        if ($ticketInsight) {
            $insights[] = [
                'type' => 'info',
                'icon' => 'fas fa-chart-line',
                'title' => 'Rendimiento de Ventas',
                'message' => "Ticket promedio actual: $" . number_format($ticketInsight['current'], 2),
                'detail' => "Crecimiento del " . round($ticketInsight['increase']) . "% respecto al periodo anterior.",
                'action_label' => 'Análisis Detallado',
                'action_url' => '/reportes'
            ];
        }

        // Fallback: Si no hay insights específicos, mostrar uno genérico de "Bienvenida de IA"
        if (empty($insights)) {
            $insights[] = [
                'type' => 'info',
                'icon' => 'fas fa-robot',
                'title' => 'Asistente IA Prepado',
                'message' => "Estoy analizando tus datos en tiempo real.",
                'detail' => "A medida que realices más ventas, podré darte predicciones más precisas sobre tu stock y clientes.",
                'action_label' => 'Configurar IA',
                'action_url' => '/configuracion/sistema'
            ];
        }

        return $insights;
    }

    private static function getStockPredictions(int $empresaId): array
    {
        // Cálculo simplificado: Ventas últimos 30 días / 30 = ventas diarias
        // Si existencia / ventas diarias < 7 días -> Alerta
        return Database::query(
            "SELECT p.nombre, i.existencia, 
                   (SELECT SUM(vd.cantidad) FROM ventas_detalle vd 
                    JOIN ventas v ON vd.venta_id = v.venta_id 
                    WHERE vd.producto_id = p.producto_id AND v.fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)) / 30 as ventas_diarias
             FROM productos p
             JOIN inventario i ON p.producto_id = i.producto_id
             WHERE p.empresa_id = ? AND p.estado = 'activo'
             HAVING ventas_diarias > 0 AND (existencia / ventas_diarias) < 7
             ORDER BY (existencia / ventas_diarias) ASC LIMIT 3",
            [$empresaId]
        )->fetchAll();
    }

    private static function getVIPGrowth(int $empresaId): ?array
    {
        // Buscar cliente con más ventas este mes vs promedio
        return Database::query(
            "SELECT c.cliente_id, c.nombre, COUNT(v.venta_id) as ventas_mes
             FROM clientes c
             JOIN ventas v ON c.cliente_id = v.cliente_id
             WHERE c.empresa_id = ? AND v.fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY c.cliente_id
             ORDER BY ventas_mes DESC LIMIT 1",
            [$empresaId]
        )->fetch() ?: null;
    }

    private static function getAverageTicketInsight(int $empresaId): ?array
    {
        $current = Database::query(
            "SELECT AVG(total) as avg FROM ventas WHERE fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        )->fetch()['avg'] ?? 0;

        $previous = Database::query(
            "SELECT AVG(total) as avg FROM ventas WHERE fecha BETWEEN DATE_SUB(NOW(), INTERVAL 60 DAY) AND DATE_SUB(NOW(), INTERVAL 30 DAY)"
        )->fetch()['avg'] ?? 0;

        if ($current > $previous && $previous > 0) {
            return [
                'current' => (float)$current,
                'increase' => (($current - $previous) / $previous) * 100
            ];
        }

        return null;
    }
}

<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;

/**
 * ApiReporteController - Datos para reportes y gráficas vía API
 */
class ApiReporteController extends ApiController
{
    /**
     * Reporte de ventas por periodo
     */
    public function ventas(): void
    {
        $fechaInicio = $this->request->get('inicio', date('Y-m-01'));
        $fechaFin = $this->request->get('fin', date('Y-m-d'));
        $empresaId = $this->empresaId();

        $ventas = Database::query(
            "SELECT DATE(fecha) as fecha, SUM(total) as total, COUNT(*) as cantidad
             FROM ventas v
             JOIN branches s ON v.sucursal_id = s.sucursal_id
             WHERE s.empresa_id = ? AND DATE(v.fecha) BETWEEN ? AND ?
             GROUP BY DATE(v.fecha)
             ORDER BY DATE(v.fecha) ASC",
            [$empresaId, $fechaInicio, $fechaFin]
        )->fetchAll();

        $this->successResponse(['ventas' => $ventas]);
    }

    /**
     * Productos más vendidos
     */
    public function productosTop(): void
    {
        $empresaId = $this->empresaId();
        $limit = (int) ($this->request->get('limit', '10'));

        $productos = Database::query(
            "SELECT p.nombre, SUM(vd.cantidad) as total_vendido, SUM(vd.total_linea) as ingresos
             FROM ventas_detalle vd
             JOIN productos p ON vd.producto_id = p.producto_id
             JOIN ventas v ON vd.venta_id = v.venta_id
             JOIN branches s ON v.sucursal_id = s.sucursal_id
             WHERE s.empresa_id = ?
             GROUP BY vd.producto_id
             ORDER BY total_vendido DESC
             LIMIT ?",
            [$empresaId, $limit]
        )->fetchAll();

        $this->successResponse(['productos' => $productos]);
    }

    /**
     * Estado actual del inventario (Stock Bajo)
     */
    public function inventarioCritico(): void
    {
        $empresaId = $this->empresaId();

        $productos = Database::query(
            "SELECT p.nombre, SUM(i.existencia) as stock_actual, p.stock_minimo, c.nombre as categoria_nombre
             FROM productos p
             LEFT JOIN categorias_productos c ON p.categoria_id = c.categoria_id
             LEFT JOIN inventario i ON p.producto_id = i.producto_id
             WHERE p.empresa_id = ? AND p.estado = 'activo'
             GROUP BY p.producto_id
             HAVING stock_actual <= p.stock_minimo
             ORDER BY stock_actual ASC",
            [$empresaId]
        )->fetchAll();

        $this->successResponse(['productos' => $productos]);
    }
}

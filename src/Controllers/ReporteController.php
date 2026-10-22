<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

class ReporteController extends Controller
{
    public function index(): void
    {
        $this->view('reportes.index', [
            'page_title' => 'Reportes',
            'page_subtitle' => 'Centro de reportes',
        ]);
    }

    public function ventasPorPeriodo(): void
    {
        $desde = $this->request->get('desde', date('Y-m-01'));
        $hasta = $this->request->get('hasta', date('Y-m-d'));

        $ventas = Database::query(
            "SELECT DATE(v.fecha) as fecha_emision, COUNT(*) as total_transacciones, 
                    SUM(v.total) as total, SUM(v.subtotal) as subtotal,
                    SUM(v.itbms) as itbms, SUM(v.costo) as costo
             FROM ventas v
             WHERE DATE(v.fecha) BETWEEN ? AND ? AND v.estado != 'anulada'
             GROUP BY DATE(v.fecha) ORDER BY fecha_emision",
            [$desde, $hasta]
        )->fetchAll();

        $totales = Database::query(
            "SELECT COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total,
                    COALESCE(SUM(subtotal), 0) as subtotal, COALESCE(SUM(itbms), 0) as itbms,
                    COALESCE(SUM(costo), 0) as costo
             FROM ventas WHERE DATE(fecha) BETWEEN ? AND ? AND estado != 'anulada'",
            [$desde, $hasta]
        )->fetch();

        $this->view('reportes.ventas_periodo', [
            'page_title' => 'Ventas por Período',
            'page_subtitle' => "{$desde} - {$hasta}",
            'ventas' => $ventas,
            'totales' => $totales,
            'desde' => $desde,
            'hasta' => $hasta,
        ]);
    }

    public function productosMasVendidos(): void
    {
        $desde = $this->request->get('desde', date('Y-m-01'));
        $hasta = $this->request->get('hasta', date('Y-m-d'));

        $productos = Database::query(
            "SELECT p.codigo, p.nombre, SUM(d.cantidad) as total_vendido,
                    SUM(d.total_linea) as total_ingreso,
                    SUM(d.costo * d.cantidad) as total_costo,
                    COUNT(DISTINCT d.venta_id) as num_ventas
             FROM ventas_detalle d
             JOIN productos p ON d.producto_id = p.producto_id
             JOIN ventas v ON d.venta_id = v.venta_id
             WHERE DATE(v.fecha) BETWEEN ? AND ? AND v.estado != 'anulada'
             GROUP BY p.producto_id ORDER BY total_vendido DESC LIMIT 50",
            [$desde, $hasta]
        )->fetchAll();

        $this->view('reportes.productos_vendidos', [
            'page_title' => 'Productos Más Vendidos',
            'page_subtitle' => "{$desde} - {$hasta}",
            'productos' => $productos,
            'desde' => $desde,
            'hasta' => $hasta,
        ]);
    }

    public function inventarioActual(): void
    {
        $productos = Database::query(
            "SELECT p.codigo, p.nombre, p.stock_minimo, c.nombre as categoria,
                    COALESCE(SUM(i.existencia), 0) as stock_total,
                    COALESCE(AVG(i.costo_promedio), 0) as costo_promedio,
                    COALESCE(SUM(i.existencia * i.costo_promedio), 0) as valor_total
             FROM productos p
             LEFT JOIN categorias_productos c ON p.categoria_id = c.categoria_id
             LEFT JOIN inventario i ON p.producto_id = i.producto_id
             WHERE p.estado = 'activo'
             GROUP BY p.producto_id ORDER BY p.nombre"
        )->fetchAll();

        $valorTotal = array_sum(array_column($productos, 'valor_total'));
        $totalProductos = count($productos);

        $this->view('reportes.inventario', [
            'page_title' => 'Inventario Actual',
            'page_subtitle' => 'Reporte de existencias',
            'productos' => $productos,
            'valorTotal' => $valorTotal,
            'totalProductos' => $totalProductos,
        ]);
    }

    public function clientesTop(): void
    {
        $clientes = Database::query(
            "SELECT c.codigo, c.nombre, c.tipo, COUNT(v.venta_id) as num_compras,
                    SUM(v.total) as total_compras,
                    MAX(v.fecha) as ultima_compra
             FROM clientes c
             JOIN ventas v ON c.cliente_id = v.cliente_id
             WHERE v.estado != 'anulada'
             GROUP BY c.cliente_id ORDER BY total_compras DESC LIMIT 50"
        )->fetchAll();

        $this->view('reportes.clientes_top', [
            'page_title' => 'Mejores Clientes',
            'page_subtitle' => 'Top clientes por volumen de compras',
            'clientes' => $clientes,
        ]);
    }
}

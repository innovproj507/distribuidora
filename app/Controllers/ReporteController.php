<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Db;
use App\Libraries\SucursalService;

/**
 * Reportes. Permiso por ruta: reportes.ver.
 * Filtro ?sucursal=: vacío = consolidado de las sucursales que el usuario puede ver;
 * un id = solo esa sucursal (si no está entre las disponibles se ignora).
 */
class ReporteController extends BaseController
{
    public function index(): string
    {
        return $this->render('reportes.index', ['page_title' => 'Reportes', 'page_subtitle' => 'Centro de reportes']);
    }

    public function ventasPorPeriodo(): string
    {
        [$desde, $hasta] = $this->rango();
        [$in, $ids, $filtro] = $this->sucursales();
        $params = array_merge([$desde, $hasta], $ids);

        return $this->render('reportes.ventas_periodo', $filtro + [
            'page_title'    => 'Ventas por Período',
            'page_subtitle' => "{$desde} - {$hasta}",
            'desde'         => $desde,
            'hasta'         => $hasta,
            'ventas'        => Db::all(
                "SELECT DATE(fecha) AS fecha_emision, COUNT(*) AS total_transacciones,
                        SUM(total) AS total, SUM(subtotal) AS subtotal, SUM(itbms) AS itbms, SUM(costo) AS costo
                 FROM ventas
                 WHERE DATE(fecha) BETWEEN ? AND ? AND estado != 'anulada' AND sucursal_id IN ({$in})
                 GROUP BY DATE(fecha) ORDER BY fecha_emision",
                $params
            ),
            'totales' => Db::row(
                "SELECT COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total, COALESCE(SUM(subtotal), 0) AS subtotal,
                        COALESCE(SUM(itbms), 0) AS itbms, COALESCE(SUM(costo), 0) AS costo
                 FROM ventas WHERE DATE(fecha) BETWEEN ? AND ? AND estado != 'anulada' AND sucursal_id IN ({$in})",
                $params
            ),
            'por_sucursal' => count($ids) > 1 ? Db::all(
                "SELECT b.nombre, COUNT(v.venta_id) AS cantidad, COALESCE(SUM(v.total), 0) AS total, COALESCE(SUM(v.costo), 0) AS costo
                 FROM branches b
                 LEFT JOIN ventas v ON v.sucursal_id = b.sucursal_id AND DATE(v.fecha) BETWEEN ? AND ? AND v.estado != 'anulada'
                 WHERE b.sucursal_id IN ({$in})
                 GROUP BY b.sucursal_id, b.nombre ORDER BY total DESC",
                $params
            ) : [],
        ]);
    }

    public function productosMasVendidos(): string
    {
        [$desde, $hasta] = $this->rango();
        [$in, $ids, $filtro] = $this->sucursales();

        return $this->render('reportes.productos_vendidos', $filtro + [
            'page_title'    => 'Productos Más Vendidos',
            'page_subtitle' => "{$desde} - {$hasta}",
            'desde'         => $desde,
            'hasta'         => $hasta,
            'productos'     => Db::all(
                "SELECT p.codigo, p.nombre, SUM(d.cantidad) AS total_vendido, SUM(d.total_linea) AS total_ingreso,
                        SUM(d.costo * d.cantidad) AS total_costo, COUNT(DISTINCT d.venta_id) AS num_ventas
                 FROM ventas_detalle d
                 JOIN productos p ON d.producto_id = p.producto_id
                 JOIN ventas v ON d.venta_id = v.venta_id
                 WHERE DATE(v.fecha) BETWEEN ? AND ? AND v.estado != 'anulada' AND v.sucursal_id IN ({$in})
                 GROUP BY p.producto_id, p.codigo, p.nombre ORDER BY total_vendido DESC LIMIT 50",
                array_merge([$desde, $hasta], $ids)
            ),
        ]);
    }

    public function inventarioActual(): string
    {
        [$in, $ids, $filtro] = $this->sucursales();

        $productos = Db::all(
            "SELECT p.codigo, p.nombre, p.stock_minimo, c.nombre AS categoria,
                    COALESCE(SUM(i.existencia), 0) AS stock_total,
                    COALESCE(AVG(i.costo_promedio), 0) AS costo_promedio,
                    COALESCE(SUM(i.existencia * i.costo_promedio), 0) AS valor_total
             FROM productos p
             LEFT JOIN categorias_productos c ON p.categoria_id = c.categoria_id
             LEFT JOIN inventario i ON p.producto_id = i.producto_id
                   AND i.deposito_id IN (SELECT deposito_id FROM depositos WHERE sucursal_id IN ({$in}))
             WHERE p.estado = 'activo' AND p.empresa_id = ?
             GROUP BY p.producto_id, p.codigo, p.nombre, p.stock_minimo, c.nombre
             ORDER BY p.nombre",
            array_merge($ids, [$this->empresaId()])
        );

        return $this->render('reportes.inventario', $filtro + [
            'page_title'     => 'Inventario Actual',
            'page_subtitle'  => 'Reporte de existencias',
            'productos'      => $productos,
            'valorTotal'     => array_sum(array_column($productos, 'valor_total')),
            'totalProductos' => count($productos),
        ]);
    }

    public function clientesTop(): string
    {
        [$in, $ids, $filtro] = $this->sucursales();

        return $this->render('reportes.clientes_top', $filtro + [
            'page_title'    => 'Mejores Clientes',
            'page_subtitle' => 'Top clientes por volumen de compras',
            'clientes'      => Db::all(
                "SELECT c.codigo, c.nombre, c.tipo, COUNT(v.venta_id) AS num_compras,
                        SUM(v.total) AS total_compras, MAX(v.fecha) AS ultima_compra
                 FROM clientes c
                 JOIN ventas v ON c.cliente_id = v.cliente_id
                 WHERE v.estado != 'anulada' AND c.empresa_id = ? AND v.sucursal_id IN ({$in})
                 GROUP BY c.cliente_id, c.codigo, c.nombre, c.tipo ORDER BY total_compras DESC LIMIT 50",
                array_merge([$this->empresaId()], $ids)
            ),
        ]);
    }

    /** @return array{0: string, 1: string} desde/hasta (por defecto el mes en curso) */
    private function rango(): array
    {
        return [
            (string) ($this->request->getGet('desde') ?: date('Y-m-01')),
            (string) ($this->request->getGet('hasta') ?: date('Y-m-d')),
        ];
    }

    /**
     * Sucursales del reporte.
     *
     * @return array{0: string, 1: int[], 2: array} placeholders "?,?", ids, y datos para la vista
     *         (sucursales_reporte, sucursal_filtro)
     */
    private function sucursales(): array
    {
        $disponibles = SucursalService::disponibles((int) $this->empresaId());
        $ids         = array_map('intval', array_column($disponibles, 'sucursal_id')) ?: [0];

        $pedida = (int) ($this->request->getGet('sucursal') ?? 0);
        if ($pedida && in_array($pedida, $ids, true)) {
            $ids = [$pedida];
        } else {
            $pedida = 0;
        }

        return [
            implode(',', array_fill(0, count($ids), '?')),
            $ids,
            ['sucursales_reporte' => $disponibles, 'sucursal_filtro' => $pedida],
        ];
    }
}

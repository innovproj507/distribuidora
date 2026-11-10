<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Db;
use App\Libraries\ProductoService;
use App\Libraries\SimpleXlsxWriter;
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

    /**
     * Inventario general: existencias por tienda/depósito, valor a precio de venta y (con costos.ver) a costo,
     * resumen por categoría. Filtros: sucursal, deposito, categoria, stock (con|agotado|bajo), q. ?formato=xlsx exporta.
     */
    public function inventarioActual()
    {
        [$in, $ids, $filtro] = $this->sucursales();
        $g = fn (string $k) => trim((string) ($this->request->getGet($k) ?? ''));

        $depositos = Db::all(
            "SELECT d.deposito_id, d.nombre, b.nombre AS sucursal FROM depositos d JOIN branches b ON b.sucursal_id = d.sucursal_id
             WHERE d.sucursal_id IN ({$in}) AND d.estado = 'activo' ORDER BY b.nombre, d.es_principal DESC, d.nombre",
            $ids
        );
        $depositoId = (int) $g('deposito');
        $enUso = $depositoId && in_array($depositoId, array_map('intval', array_column($depositos, 'deposito_id')), true)
            ? array_values(array_filter($depositos, fn ($d) => (int) $d['deposito_id'] === $depositoId))
            : $depositos;
        if (!$depositoId || count($enUso) !== 1) $depositoId = 0;
        $depIds = array_map('intval', array_column($enUso, 'deposito_id')) ?: [0];

        $where  = ['p.estado = \'activo\'', 'p.empresa_id = ?'];
        $params = [$this->empresaId()];
        $categoria = (int) $g('categoria');
        if ($categoria) { $where[] = 'p.categoria_id = ?'; $params[] = $categoria; }
        if (($q = $g('q')) !== '') {
            $where[] = '(p.nombre LIKE ? OR p.codigo LIKE ? OR p.codigo_barras = ?)';
            array_push($params, "%{$q}%", "%{$q}%", $q);
        }

        $productos = Db::all(
            "SELECT p.producto_id, p.codigo, p.nombre, p.unidad_medida, p.costo, p.stock_minimo, COALESCE(c.nombre, 'Sin categoría') AS categoria,
                    COALESCE((SELECT precio FROM precios_productos WHERE producto_id = p.producto_id AND tipo_precio = 'a'), 0) AS precio
             FROM productos p LEFT JOIN categorias_productos c ON c.categoria_id = p.categoria_id
             WHERE " . implode(' AND ', $where) . " ORDER BY categoria, p.nombre",
            $params
        );

        // Existencias por depósito (una consulta) y presentaciones para el desglose
        $stock = [];
        $phD   = implode(',', array_fill(0, count($depIds), '?'));
        foreach (Db::all(
            "SELECT producto_id, deposito_id, existencia, minimo, costo_promedio, ubicacion, fecha_vencimiento FROM inventario WHERE deposito_id IN ({$phD})",
            $depIds
        ) as $i) {
            $stock[(int) $i['producto_id']][(int) $i['deposito_id']] = $i;
        }
        $pres = [];
        foreach (Db::all("SELECT u.producto_id, u.nombre, u.factor_conversion FROM productos_unidades u JOIN productos p ON p.producto_id = u.producto_id WHERE p.empresa_id = ?", [$this->empresaId()]) as $u) {
            $pres[(int) $u['producto_id']][] = $u;
        }

        $filtroStock = $g('stock');
        $filas = [];
        $porCategoria = [];
        $tot = ['productos' => 0, 'con_stock' => 0, 'agotados' => 0, 'bajos' => 0, 'unidades' => 0.0, 'valor_venta' => 0.0, 'valor_costo' => 0.0];
        foreach ($productos as $p) {
            $pid = (int) $p['producto_id'];
            $porDep = [];
            $total = $valorCosto = $minimo = 0.0;
            foreach ($depIds as $d) {
                $i = $stock[$pid][$d] ?? null;
                $e = $i ? (float) $i['existencia'] : 0.0;
                $porDep[$d] = $e;
                $total += $e;
                $valorCosto += $e * (float) ($i['costo_promedio'] ?? 0 ?: $p['costo']);
                $minimo += $i ? (float) $i['minimo'] : 0.0;
            }
            if ($minimo <= 0) $minimo = (float) $p['stock_minimo'];
            $estado = $total <= 0 ? 'agotado' : ($minimo > 0 && $total <= $minimo ? 'bajo' : 'ok');
            if ($filtroStock === 'con' && $total <= 0) continue;
            if ($filtroStock === 'agotado' && $estado !== 'agotado') continue;
            if ($filtroStock === 'bajo' && $estado !== 'bajo') continue;

            $uno = $depositoId ? ($stock[$pid][$depositoId] ?? null) : null;
            $fila = $p + [
                'por_deposito' => $porDep, 'total' => $total, 'minimo' => $minimo, 'estado_stock' => $estado,
                'desglose' => ProductoService::desglose($total, $pres[$pid] ?? [], $p['unidad_medida']),
                'um' => ProductoService::abreviatura($p['unidad_medida'] ?? 'und'),
                'valor_venta' => max(0, $total) * (float) $p['precio'], 'valor_costo' => max(0, $valorCosto),
                'ubicacion' => $uno['ubicacion'] ?? '', 'vence' => $uno['fecha_vencimiento'] ?? null,
            ];
            $filas[] = $fila;

            $tot['productos']++;
            $tot[$estado === 'agotado' ? 'agotados' : ($estado === 'bajo' ? 'bajos' : 'con_stock')]++;
            if ($estado === 'bajo') $tot['con_stock']++;
            $tot['unidades']    += max(0, $total);
            $tot['valor_venta'] += $fila['valor_venta'];
            $tot['valor_costo'] += $fila['valor_costo'];
            $cat = &$porCategoria[$p['categoria']];
            $cat ??= ['categoria' => $p['categoria'], 'productos' => 0, 'unidades' => 0.0, 'valor_venta' => 0.0, 'valor_costo' => 0.0];
            $cat['productos']++;
            $cat['unidades']    += max(0, $total);
            $cat['valor_venta'] += $fila['valor_venta'];
            $cat['valor_costo'] += $fila['valor_costo'];
            unset($cat);
        }
        uasort($porCategoria, fn ($a, $b) => $b['valor_venta'] <=> $a['valor_venta']);

        if ($g('formato') === 'xlsx') {
            $costos = ver_costos();
            $enc = array_merge(['Código', 'Producto', 'Categoría'], array_map(fn ($d) => $d['nombre'], $enUso),
                ['Total', 'Unidad', 'En presentaciones', 'Mínimo', 'Precio venta', 'Valor a precio de venta'],
                $costos ? ['Costo', 'Valor a costo', 'Ganancia potencial'] : [], ['Estado']);
            $rows = array_map(fn ($f) => array_merge(
                [$f['codigo'], $f['nombre'], $f['categoria']], array_values($f['por_deposito']),
                [$f['total'], $f['um'], $f['desglose'], $f['minimo'], (float) $f['precio'], round($f['valor_venta'], 2)],
                $costos ? [(float) $f['costo'], round($f['valor_costo'], 2), round($f['valor_venta'] - $f['valor_costo'], 2)] : [],
                [['ok' => 'OK', 'bajo' => 'Bajo mínimo', 'agotado' => 'Agotado'][$f['estado_stock']]]
            ), $filas);
            return SimpleXlsxWriter::descarga('inventario_' . date('Y-m-d') . '.xlsx', $enc, $rows);
        }

        return $this->render('reportes.inventario', $filtro + [
            'page_title'    => 'Inventario general',
            'page_subtitle' => 'Existencias y valor al ' . date('d/m/Y H:i'),
            'filas'         => $filas,
            'depositos'     => $depositos,
            'enUso'         => $enUso,
            'depositoId'    => $depositoId,
            'categorias'    => Db::all("SELECT categoria_id, nombre FROM categorias_productos WHERE empresa_id = ? ORDER BY nombre", [$this->empresaId()]),
            'categoria'     => $categoria,
            'filtroStock'   => $filtroStock,
            'q'             => $q,
            'tot'           => $tot,
            'porCategoria'  => array_values($porCategoria),
        ]);
    }

    /**
     * Resultado de un conteo físico (por defecto el último cerrado de las tiendas del usuario):
     * sistema vs contado, faltantes/sobrantes y su valor. ?id=, ?ver=diferencias|faltantes|sobrantes|sin_contar, ?formato=xlsx.
     */
    public function conteo()
    {
        [$in, $ids, $filtro] = $this->sucursales();
        $conteos = Db::all(
            "SELECT c.conteo_id, c.descripcion, c.estado, c.fecha_inicio, c.fecha_cierre, d.nombre AS deposito, b.nombre AS sucursal
             FROM inventario_conteos c JOIN depositos d ON d.deposito_id = c.deposito_id JOIN branches b ON b.sucursal_id = c.sucursal_id
             WHERE c.empresa_id = ? AND c.sucursal_id IN ({$in}) AND c.estado IN ('completado', 'en_proceso')
             ORDER BY (c.estado = 'completado') DESC, COALESCE(c.fecha_cierre, c.fecha_inicio) DESC LIMIT 50",
            array_merge([$this->empresaId()], $ids)
        );
        $id = (int) ($this->request->getGet('id') ?? 0);
        if (!$id || !in_array($id, array_map('intval', array_column($conteos, 'conteo_id')), true)) {
            $id = (int) ($conteos[0]['conteo_id'] ?? 0);
        }

        $conteo = $id ? Db::row(
            "SELECT c.*, d.nombre AS deposito, b.nombre AS sucursal, u.username AS usuario
             FROM inventario_conteos c JOIN depositos d ON d.deposito_id = c.deposito_id JOIN branches b ON b.sucursal_id = c.sucursal_id
             LEFT JOIN users u ON u.id = c.usuario_id WHERE c.conteo_id = ?",
            [$id]
        ) : null;

        $lineas = $id ? Db::all(
            "SELECT cd.producto_id, cd.cantidad_sistema, cd.cantidad_contada, cd.diferencia, cd.costo_unitario, cd.estado, cd.notas,
                    p.codigo, p.nombre, p.unidad_medida, COALESCE(c.nombre, 'Sin categoría') AS categoria, i.ubicacion,
                    COALESCE((SELECT precio FROM precios_productos WHERE producto_id = p.producto_id AND tipo_precio = 'a'), 0) AS precio
             FROM conteo_detalle cd JOIN productos p ON p.producto_id = cd.producto_id
             LEFT JOIN categorias_productos c ON c.categoria_id = p.categoria_id
             LEFT JOIN inventario i ON i.producto_id = cd.producto_id AND i.deposito_id = ?
             WHERE cd.conteo_id = ? ORDER BY categoria, p.nombre",
            [(int) ($conteo['deposito_id'] ?? 0), $id]
        ) : [];

        $res = ['total' => 0, 'contados' => 0, 'sin_contar' => 0, 'exactos' => 0, 'faltantes' => 0, 'sobrantes' => 0,
                'u_faltan' => 0.0, 'u_sobran' => 0.0, 'v_faltan' => 0.0, 'v_sobran' => 0.0, 'pv_faltan' => 0.0, 'pv_sobran' => 0.0];
        foreach ($lineas as &$l) {
            $contado = $l['estado'] !== 'pendiente' && $l['cantidad_contada'] !== null;
            $dif = $contado ? round((float) $l['cantidad_contada'] - (float) $l['cantidad_sistema'], 2) : 0.0;
            $l['contado'] = $contado;
            $l['dif'] = $dif;
            $l['valor_costo'] = $dif * (float) $l['costo_unitario'];
            $l['valor_venta'] = $dif * (float) $l['precio'];
            $l['um'] = ProductoService::abreviatura($l['unidad_medida'] ?? 'und');
            $res['total']++;
            if (!$contado) { $res['sin_contar']++; continue; }
            $res['contados']++;
            if (abs($dif) < 0.005) { $res['exactos']++; continue; }
            $k = $dif < 0 ? 'faltan' : 'sobran';
            $res[$dif < 0 ? 'faltantes' : 'sobrantes']++;
            $res['u_' . $k]  += abs($dif);
            $res['v_' . $k]  += abs($l['valor_costo']);
            $res['pv_' . $k] += abs($l['valor_venta']);
        }
        unset($l);

        $ver = (string) ($this->request->getGet('ver') ?? '');
        $visibles = array_values(array_filter($lineas, fn ($l) => match ($ver) {
            'diferencias' => $l['contado'] && abs($l['dif']) >= 0.005,
            'faltantes'   => $l['contado'] && $l['dif'] <= -0.005,
            'sobrantes'   => $l['contado'] && $l['dif'] >= 0.005,
            'sin_contar'  => !$l['contado'],
            default       => true,
        }));

        if (($this->request->getGet('formato') ?? '') === 'xlsx' && $conteo) {
            $costos = ver_costos();
            $enc = array_merge(['Código', 'Producto', 'Categoría', 'Ubicación', 'Unidad', 'Sistema', 'Contado', 'Diferencia', 'Precio venta', 'Diferencia a precio de venta'],
                $costos ? ['Costo', 'Diferencia a costo'] : [], ['Resultado']);
            $rows = array_map(fn ($l) => array_merge(
                [$l['codigo'], $l['nombre'], $l['categoria'], (string) $l['ubicacion'], $l['um'], (float) $l['cantidad_sistema'],
                 $l['contado'] ? (float) $l['cantidad_contada'] : '', $l['contado'] ? $l['dif'] : '', (float) $l['precio'], $l['contado'] ? round($l['valor_venta'], 2) : ''],
                $costos ? [(float) $l['costo_unitario'], $l['contado'] ? round($l['valor_costo'], 2) : ''] : [],
                [!$l['contado'] ? 'Sin contar' : (abs($l['dif']) < 0.005 ? 'Exacto' : ($l['dif'] < 0 ? 'Faltante' : 'Sobrante'))]
            ), $visibles);
            return SimpleXlsxWriter::descarga('conteo_' . $id . '_' . date('Y-m-d') . '.xlsx', $enc, $rows);
        }

        return $this->render('reportes.conteo', $filtro + [
            'page_title'    => 'Resultado del inventario físico',
            'page_subtitle' => $conteo ? ($conteo['descripcion'] ?: 'Conteo #' . $id) : 'Sin conteos',
            'conteos'       => $conteos,
            'conteo'        => $conteo,
            'lineas'        => $visibles,
            'res'           => $res,
            'ver'           => $ver,
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

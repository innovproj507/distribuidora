<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Pedido consolidado a la ciudad. Las compras llegan al depósito de la sucursal principal (hub)
 * y desde ahí se reparten a las demás tiendas con traslados.
 *
 * Para cada producto y tienda: objetivo = max(demanda diaria × días de cobertura, mínimo);
 * necesita = objetivo − existencia. La demanda sale de las salidas (ventas y ajustes de salida)
 * de los últimos 30 días; los traslados no cuentan como demanda.
 */
final class PedidoCiudadService
{
    public const VENTANA_DEMANDA = 30;

    /** Tiendas activas con su depósito principal; la sucursal principal va primero (hub). */
    public static function tiendas(int $empresaId): array
    {
        return Db::all(
            "SELECT b.sucursal_id, b.nombre AS sucursal, d.deposito_id, b.es_principal
             FROM branches b
             JOIN depositos d ON d.sucursal_id = b.sucursal_id AND d.es_principal = 1 AND d.estado = 'activo'
             WHERE b.empresa_id = ? AND b.activa = 1
             ORDER BY b.es_principal DESC, b.nombre",
            [$empresaId]
        );
    }

    /**
     * @return array{tiendas: array, items: array, total_estimado: float}
     */
    public static function calcular(int $empresaId, int $dias): array
    {
        $dias    = max(1, min(60, $dias));
        $tiendas = self::tiendas($empresaId);
        if (!$tiendas) {
            return ['tiendas' => [], 'items' => [], 'total_estimado' => 0.0];
        }
        $depositos = array_map(fn ($t) => (int) $t['deposito_id'], $tiendas);
        $in        = implode(',', array_fill(0, count($depositos), '?'));

        $filas = Db::all(
            "SELECT i.producto_id, i.deposito_id, i.existencia, i.minimo, i.costo_promedio,
                    p.nombre, p.codigo, p.costo, p.proveedor_id, pr.nombre AS proveedor_nombre,
                    COALESCE((SELECT SUM(m.cantidad) FROM inventario_movimientos m
                              WHERE m.inventario_id = i.inventario_id AND m.tipo = 'salida'
                                AND m.fecha_registro >= DATE_SUB(NOW(), INTERVAL " . self::VENTANA_DEMANDA . " DAY)), 0) AS salidas
             FROM inventario i
             JOIN productos p ON p.producto_id = i.producto_id AND p.estado = 'activo' AND p.empresa_id = ?
             LEFT JOIN proveedores pr ON pr.proveedor_id = p.proveedor_id
             WHERE i.deposito_id IN ({$in})",
            array_merge([$empresaId], $depositos)
        );

        $items = [];
        foreach ($filas as $f) {
            $demanda  = (float) $f['salidas'] / self::VENTANA_DEMANDA;
            $objetivo = max($demanda * $dias, (float) $f['minimo']);
            $necesita = $objetivo > 0 ? max(0.0, ceil($objetivo - (float) $f['existencia'])) : 0.0;

            $pid = (int) $f['producto_id'];
            $items[$pid] ??= [
                'producto_id'      => $pid,
                'nombre'           => $f['nombre'],
                'codigo'           => $f['codigo'],
                'proveedor_id'     => $f['proveedor_id'] ? (int) $f['proveedor_id'] : null,
                'proveedor_nombre' => $f['proveedor_nombre'] ?: 'Sin proveedor',
                'costo'            => (float) ($f['costo_promedio'] ?: $f['costo']),
                'tiendas'          => [],
            ];
            $items[$pid]['tiendas'][(int) $f['deposito_id']] = [
                'existencia' => (float) $f['existencia'],
                'minimo'     => (float) $f['minimo'],
                'demanda'    => round($demanda, 2),
                'necesita'   => $necesita,
            ];
        }

        // Solo productos que alguna tienda necesita
        $items = array_values(array_filter($items, function (array $it): bool {
            foreach ($it['tiendas'] as $t) {
                if ($t['necesita'] > 0) return true;
            }
            return false;
        }));

        usort($items, fn ($a, $b) => [$a['proveedor_nombre'], $a['nombre']] <=> [$b['proveedor_nombre'], $b['nombre']]);

        $total = 0.0;
        foreach ($items as $it) {
            $total += array_sum(array_column($it['tiendas'], 'necesita')) * $it['costo'];
        }

        return ['tiendas' => $tiendas, 'items' => $items, 'total_estimado' => round($total, 2)];
    }

    /**
     * Lo que ya se puede enviar a otras tiendas de una compra: el reparto planeado de cada línea,
     * proporcional a lo ya recibido, menos lo ya enviado.
     *
     * @return array<int,array{deposito_id:int,sucursal:string,lineas:array}> por depósito destino
     */
    public static function porEnviar(int $compraId): array
    {
        $out = [];
        $lineas = Db::all(
            "SELECT d.producto_id, d.cantidad, d.cantidad_recibida, d.reparto, d.reparto_enviado, p.nombre, p.codigo
             FROM compras_detalle d JOIN productos p ON p.producto_id = d.producto_id
             WHERE d.compra_id = ? AND d.reparto IS NOT NULL AND d.cantidad_recibida > 0",
            [$compraId]
        );
        foreach ($lineas as $l) {
            $reparto = json_decode((string) $l['reparto'], true) ?: [];
            $enviado = json_decode((string) ($l['reparto_enviado'] ?? ''), true) ?: [];
            $fraccion = (float) $l['cantidad'] > 0 ? min(1.0, (float) $l['cantidad_recibida'] / (float) $l['cantidad']) : 0.0;
            foreach ($reparto as $depId => $cant) {
                $disponible = round((float) $cant * $fraccion - (float) ($enviado[$depId] ?? 0), 2);
                if ($disponible <= 0) continue;
                $out[(int) $depId] ??= [
                    'deposito_id' => (int) $depId,
                    'sucursal'    => (string) Db::value("SELECT b.nombre FROM depositos d JOIN branches b ON b.sucursal_id = d.sucursal_id WHERE d.deposito_id = ?", [(int) $depId]),
                    'lineas'      => [],
                ];
                $out[(int) $depId]['lineas'][] = ['producto_id' => (int) $l['producto_id'], 'nombre' => $l['nombre'], 'codigo' => $l['codigo'], 'cantidad' => $disponible];
            }
        }
        return array_values($out);
    }

    /**
     * Crea el traslado del depósito de la compra hacia otra tienda con (parte de) lo pendiente de enviar.
     *
     * @param array<int,float> $cantidades producto_id → cantidad a enviar (tope: lo disponible)
     * @return int traslado_id. El llamador abre y cierra la transacción.
     */
    public static function enviarReparto(int $compraId, int $destinoDepositoId, array $cantidades, array $ctx): int
    {
        $compra = Db::row("SELECT deposito_id, sucursal_id, numero_factura FROM compras WHERE compra_id = ? AND empresa_id = ?", [$compraId, $ctx['empresa_id']]);
        if (!$compra) {
            throw new \RuntimeException('Compra no encontrada.');
        }
        $bloque = null;
        foreach (self::porEnviar($compraId) as $b) {
            if ($b['deposito_id'] === $destinoDepositoId) $bloque = $b;
        }
        if (!$bloque) {
            throw new \RuntimeException('No hay nada pendiente de enviar a esa tienda.');
        }

        $items = [];
        foreach ($bloque['lineas'] as $l) {
            $cant = min((float) ($cantidades[$l['producto_id']] ?? 0), (float) $l['cantidad']);
            if ($cant > 0) $items[] = ['producto_id' => $l['producto_id'], 'cantidad' => $cant];
        }
        if (!$items) {
            throw new \RuntimeException('Indica al menos una cantidad a enviar.');
        }

        $trasladoId = TrasladoService::enviar(
            (int) $compra['deposito_id'], $destinoDepositoId, $items,
            'Reparto de la compra ' . $compra['numero_factura'],
            ['empresa_id' => $ctx['empresa_id'], 'sucursal_id' => (int) $compra['sucursal_id']]
        );

        foreach ($items as $it) {
            $fila    = Db::row("SELECT detalle_id, reparto_enviado FROM compras_detalle WHERE compra_id = ? AND producto_id = ? AND reparto IS NOT NULL LIMIT 1", [$compraId, $it['producto_id']]);
            $enviado = json_decode((string) ($fila['reparto_enviado'] ?? ''), true) ?: [];
            $enviado[$destinoDepositoId] = round((float) ($enviado[$destinoDepositoId] ?? 0) + $it['cantidad'], 2);
            Db::update('compras_detalle', ['reparto_enviado' => json_encode($enviado)], ['detalle_id' => $fila['detalle_id']]);
        }
        return $trasladoId;
    }

    /**
     * Crea una compra pendiente por proveedor en el depósito hub, anotando el reparto a las demás tiendas.
     *
     * @param array $lineas [{producto_id, cantidades: {deposito_id: cantidad}}]
     * @return array [{compra_id, proveedor, lineas, total}]
     */
    public static function crearOrdenes(array $lineas, array $ctx): array
    {
        $tiendas = self::tiendas((int) $ctx['empresa_id']);
        if (!$tiendas) {
            throw new \RuntimeException('No hay tiendas activas.');
        }
        $hub       = (int) $tiendas[0]['deposito_id'];
        $validos   = array_map(fn ($t) => (int) $t['deposito_id'], $tiendas);
        $porProv   = [];

        foreach ($lineas as $l) {
            $pid = (int) ($l['producto_id'] ?? 0);
            $prod = Db::row(
                "SELECT p.producto_id, p.proveedor_id, p.costo, COALESCE(NULLIF(i.costo_promedio, 0), p.costo) AS costo_unit
                 FROM productos p LEFT JOIN inventario i ON i.producto_id = p.producto_id AND i.deposito_id = ?
                 WHERE p.producto_id = ? AND p.empresa_id = ? AND p.estado = 'activo'",
                [$hub, $pid, $ctx['empresa_id']]
            );
            if (!$prod) continue;

            $total = 0.0;
            $reparto = [];
            foreach ((array) ($l['cantidades'] ?? []) as $depId => $cant) {
                $depId = (int) $depId;
                $cant  = (float) $cant;
                if ($cant <= 0 || !in_array($depId, $validos, true)) continue;
                $total += $cant;
                if ($depId !== $hub) $reparto[$depId] = $cant;
            }
            if ($total <= 0) continue;

            $porProv[(int) $prod['proveedor_id']][] = [
                'item'    => ['producto_id' => $pid, 'cantidad' => $total, 'costo' => (float) $prod['costo_unit'], 'deposito_id' => $hub],
                'reparto' => $reparto,
            ];
        }
        if (!$porProv) {
            throw new \RuntimeException('Elige al menos un producto con cantidad.');
        }

        $creadas = [];
        foreach ($porProv as $proveedorId => $entradas) {
            $items    = array_column($entradas, 'item');
            $compraId = CompraService::registrar([
                'proveedor_id'           => $proveedorId ?: null,
                'guardar_como_pendiente' => true,
                'notas'                  => 'Pedido consolidado a la ciudad (' . date('d/m/Y') . ')',
                'items'                  => $items,
            ], ['empresa_id' => $ctx['empresa_id'], 'sucursal_id' => (int) $tiendas[0]['sucursal_id'], 'deposito_id' => $hub]);

            foreach ($entradas as $e) {
                if ($e['reparto']) {
                    Db::update('compras_detalle', ['reparto' => json_encode($e['reparto'])], [
                        'compra_id' => $compraId, 'producto_id' => $e['item']['producto_id'],
                    ]);
                }
            }
            $creadas[] = [
                'compra_id' => $compraId,
                'proveedor' => $proveedorId ? (string) Db::value("SELECT nombre FROM proveedores WHERE proveedor_id = ?", [$proveedorId]) : 'Sin proveedor',
                'lineas'    => count($items),
                'total'     => array_sum(array_map(fn ($i) => $i['cantidad'] * $i['costo'], $items)),
            ];
        }
        return $creadas;
    }
}

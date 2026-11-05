<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Compras a proveedor: registro (recibida o como orden pendiente) y recepción de líneas
 * con costo promedio ponderado y lotes. Lo usan la web, la PWA y la API.
 * El llamador abre y cierra la transacción.
 */
final class CompraService
{
    /**
     * $data: proveedor_id?, numero_factura?, fecha_compra?, notas?, subtotal?, itbms?, total?,
     *        guardar_como_pendiente?, items[{producto_id, cantidad, costo, deposito_id?, unidad_id?,
     *        factor?, itbms?, precio_a/b/c?, numero_lote?, fecha_vencimiento?, fecha_fabricacion?}]
     * $ctx:  empresa_id, sucursal_id, deposito_id (por defecto)
     *
     * @return int compra_id
     */
    public static function registrar(array $data, array $ctx): int
    {
        $items = $data['items'] ?? [];
        if (empty($items)) {
            throw new \RuntimeException('Agrega al menos un producto.');
        }

        $esPendiente       = !empty($data['guardar_como_pendiente']);
        $depositoPrincipal = (int) ($items[0]['deposito_id'] ?? 0) ?: (int) ($ctx['deposito_id'] ?? 0);
        $factura           = trim((string) ($data['numero_factura'] ?? ''));

        // Si no vienen totales se calculan de las líneas
        $calculado = array_sum(array_map(fn ($i) => (float) $i['cantidad'] * (float) $i['costo'], $items));
        $total     = isset($data['total']) ? (float) $data['total'] : $calculado;

        $compraId = Db::insert('compras', [
            'proveedor_id'             => !empty($data['proveedor_id']) ? (int) $data['proveedor_id'] : null,
            'sucursal_id'              => $ctx['sucursal_id'],
            'empresa_id'               => $ctx['empresa_id'],
            'deposito_id'              => $depositoPrincipal,
            'numero_factura'           => $factura !== '' ? $factura : ('PEND-' . date('YmdHis') . '-' . random_int(100, 999)),
            'numero_factura_proveedor' => $factura !== '' ? $factura : null,
            'monto_subtotal'           => isset($data['subtotal']) ? (float) $data['subtotal'] : $calculado,
            'monto_itbms'              => (float) ($data['itbms'] ?? 0),
            'monto_total'              => $total,
            'estado'                   => $esPendiente ? 'pendiente' : 'recibida',
            'fecha_compra'             => $data['fecha_compra'] ?? date('Y-m-d'),
            'notas'                    => $data['notas'] ?? '',
            'usuario_id'               => Auth::id(),
        ]);

        foreach ($items as $item) {
            $productoId = (int) $item['producto_id'];
            $cantidad   = (float) $item['cantidad'];
            $costo      = (float) $item['costo'];
            $depositoId = (int) ($item['deposito_id'] ?? 0) ?: $depositoPrincipal;
            $unidadId   = !empty($item['unidad_id']) ? (int) $item['unidad_id'] : null;

            if ($cantidad <= 0) {
                throw new \RuntimeException('Las cantidades deben ser mayores que cero.');
            }

            $factor = max(1.0, (float) ($item['factor'] ?? 1));
            if ($unidadId) {
                $f = Db::value("SELECT factor_conversion FROM productos_unidades WHERE unidad_id = ? AND producto_id = ?", [$unidadId, $productoId]);
                if ($f !== null) $factor = (float) $f;
            }

            $cantidadBase = $cantidad * $factor;
            $costoBase    = $factor > 0 ? $costo / $factor : $costo;
            $numeroLote   = !empty($item['numero_lote']) ? trim($item['numero_lote']) : null;
            $vence        = !empty($item['fecha_vencimiento']) ? $item['fecha_vencimiento'] : null;

            Db::insert('compras_detalle', [
                'compra_id'         => $compraId,
                'producto_id'       => $productoId,
                'cantidad'          => $cantidadBase,
                'cantidad_recibida' => $esPendiente ? 0 : $cantidadBase,
                'costo'             => $costoBase,
                'itbms'             => (float) ($item['itbms'] ?? 0),
                'total_linea'       => $cantidad * $costo,
                'numero_lote'       => $numeroLote,
                'fecha_vencimiento' => $vence,
                'fecha_fabricacion' => !empty($item['fecha_fabricacion']) ? $item['fecha_fabricacion'] : null,
            ]);

            // Precios de venta A/B/C si vienen en el ítem
            foreach (['a' => 'precio_a', 'b' => 'precio_b', 'c' => 'precio_c'] as $tipo => $campo) {
                $precio = (float) ($item[$campo] ?? 0);
                if ($precio > 0) {
                    Db::query(
                        "INSERT INTO precios_productos (producto_id, tipo_precio, precio, fecha_inicio) VALUES (?, ?, ?, CURDATE())
                         ON DUPLICATE KEY UPDATE precio = VALUES(precio), fecha_inicio = VALUES(fecha_inicio)",
                        [$productoId, $tipo, $precio]
                    );
                }
            }

            if (!$esPendiente) {
                self::recibirLinea($productoId, $depositoId, $cantidadBase, $costoBase, $compraId, $factura, $numeroLote, $vence);
            }
        }

        return $compraId;
    }

    /**
     * Recibe líneas pendientes de una compra. $recibidas: detalle_id → cantidad (tope: lo pendiente).
     * Actualiza el estado de la compra. El llamador abre y cierra la transacción.
     *
     * @return string nuevo estado ('recibida' | 'parcialmente_recibida')
     */
    public static function recibirPendientes(int $compraId, array $compra, array $recibidas, int $depositoId): string
    {
        $recibidoAlgo = false;
        foreach (Db::all("SELECT * FROM compras_detalle WHERE compra_id = ? AND cantidad > cantidad_recibida", [$compraId]) as $linea) {
            $detalleId = (int) $linea['detalle_id'];
            $pendiente = (float) $linea['cantidad'] - (float) $linea['cantidad_recibida'];
            $cantidad  = isset($recibidas[$detalleId]) ? min((float) $recibidas[$detalleId], $pendiente) : 0.0;
            if ($cantidad <= 0) continue;

            self::recibirLinea((int) $linea['producto_id'], $depositoId, $cantidad, (float) $linea['costo'], $compraId, (string) ($compra['numero_factura'] ?? ''), $linea['numero_lote'] ?: null, $linea['fecha_vencimiento'] ?: null);
            Db::query("UPDATE compras_detalle SET cantidad_recibida = cantidad_recibida + ? WHERE detalle_id = ?", [$cantidad, $detalleId]);
            $recibidoAlgo = true;
        }
        if (!$recibidoAlgo) {
            throw new \RuntimeException('Indica al menos una cantidad recibida.');
        }

        $sinRecibir  = (int) Db::value("SELECT COUNT(*) FROM compras_detalle WHERE compra_id = ? AND cantidad > cantidad_recibida", [$compraId]);
        $nuevoEstado = $sinRecibir === 0 ? 'recibida' : 'parcialmente_recibida';
        Db::query(
            "UPDATE compras SET estado = ?" . ($nuevoEstado === 'recibida' ? ', fecha_recepcion = NOW()' : '') . " WHERE compra_id = ?",
            [$nuevoEstado, $compraId]
        );
        return $nuevoEstado;
    }

    /** Entrada al inventario con costo promedio ponderado (y lote, si viene). */
    public static function recibirLinea(int $productoId, int $depositoId, float $cantidadBase, float $costoUnit, int $compraId, string $factura, ?string $numeroLote, ?string $vence): void
    {
        $inv = Db::row("SELECT inventario_id, existencia, costo_promedio FROM inventario WHERE producto_id = ? AND deposito_id = ? LIMIT 1", [$productoId, $depositoId]);

        if (!$inv) {
            $inventarioId = Db::insert('inventario', [
                'producto_id' => $productoId, 'deposito_id' => $depositoId, 'existencia' => 0,
                'ultimo_costo' => $costoUnit, 'costo_promedio' => $costoUnit, 'fecha_actualizacion_costo' => date('Y-m-d H:i:s'),
            ]);
            $oldQty = 0.0;
            $oldAvg = $costoUnit;
        } else {
            $inventarioId = (int) $inv['inventario_id'];
            $oldQty       = (float) $inv['existencia'];
            $oldAvg       = (float) $inv['costo_promedio'];
        }

        $newQty = $oldQty + $cantidadBase;
        $newAvg = ($oldQty <= 0 || $newQty <= 0) ? $costoUnit : (($oldQty * $oldAvg) + ($cantidadBase * $costoUnit)) / $newQty;

        InventarioService::registrar($inventarioId, 'entrada', $cantidadBase, $compraId, 'compras', "Compra Factura #{$factura}");

        Db::query("UPDATE inventario SET ultimo_costo = ?, costo_promedio = ?, fecha_actualizacion_costo = NOW() WHERE inventario_id = ?", [$costoUnit, $newAvg, $inventarioId]);
        Db::query("UPDATE productos SET costo = ? WHERE producto_id = ?", [$costoUnit, $productoId]);

        if (!$numeroLote) {
            return;
        }

        $lote = Db::row("SELECT lote_id, cantidad_actual FROM lotes WHERE producto_id = ? AND deposito_id = ? AND numero_lote = ? LIMIT 1", [$productoId, $depositoId, $numeroLote]);
        if ($lote) {
            $saldo = (float) $lote['cantidad_actual'] + $cantidadBase;
            Db::query("UPDATE lotes SET cantidad_actual = ?, estado = 'activo' WHERE lote_id = ?", [$saldo, $lote['lote_id']]);
            Db::query(
                "INSERT INTO lote_movimientos (lote_id, tipo, cantidad, saldo_anterior, saldo_nuevo, referencia_id, referencia_tipo) VALUES (?, 'entrada', ?, ?, ?, ?, 'compras')",
                [$lote['lote_id'], $cantidadBase, $lote['cantidad_actual'], $saldo, $compraId]
            );
        } else {
            $loteId = Db::insert('lotes', [
                'producto_id' => $productoId, 'deposito_id' => $depositoId, 'numero_lote' => $numeroLote,
                'fecha_vencimiento' => $vence, 'cantidad_inicial' => $cantidadBase, 'cantidad_actual' => $cantidadBase, 'estado' => 'activo',
            ]);
            Db::query(
                "INSERT INTO lote_movimientos (lote_id, tipo, cantidad, saldo_anterior, saldo_nuevo, referencia_id, referencia_tipo) VALUES (?, 'entrada', ?, 0, ?, ?, 'compras')",
                [$loteId, $cantidadBase, $cantidadBase, $compraId]
            );
        }
    }
}

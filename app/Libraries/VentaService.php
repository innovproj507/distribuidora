<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Registro de ventas. Lo usan el POS web, el formulario de venta y la PWA móvil,
 * para que todas las ventas tengan la misma factura, ITBMS, pagos, crédito y kardex.
 */
final class VentaService
{
    /**
     * Procesa una venta. El llamador abre y cierra la transacción.
     *
     * $data: items[{producto_id, cantidad, precio, descuento?, unidad_id?, deposito_id?}],
     *        pagos[{forma_pago, monto}] (o forma_pago suelto), cliente_id?, descuento?,
     *        aplicar_itbms?, notas?, cotizacion_origen_id?
     * $ctx:  empresa_id, sucursal_id, deposito_id (por defecto para ítems sin depósito), ip
     *
     * @return array{success: bool, venta_id: int, numero_factura: string, total: float, vuelto: float}
     */
    public static function procesar(array $data, array $ctx): array
    {
        $items = $data['items'] ?? [];
        if (empty($items)) {
            throw new \RuntimeException('No hay productos en la venta.');
        }

        // Pagos: [{forma_pago, monto}] o forma_pago suelto (modo de un solo pago)
        $pagos = $data['pagos'] ?? null;
        if (empty($pagos) || !is_array($pagos)) {
            $pagos = [['forma_pago' => $data['forma_pago'] ?? 'EFECTIVO', 'monto' => 0]];
        }
        foreach ($pagos as &$pg) {
            $pg['forma_pago'] = strtoupper(trim($pg['forma_pago'] ?? 'EFECTIVO'));
            $pg['monto']      = (float) ($pg['monto'] ?? 0);
        }
        unset($pg);

        $clienteId  = !empty($data['cliente_id']) ? (int) $data['cliente_id'] : null;
        $hayCredito = count(array_filter($pagos, fn ($p) => $p['forma_pago'] === 'CREDITO')) > 0;

        $formasPago       = array_unique(array_column($pagos, 'forma_pago'));
        $formaPagoResumen = count($formasPago) === 1 ? $formasPago[0] : 'MIXTO';

        $itbmsRate     = (float) env('ITBMS_RATE', 0.07);
        $subtotal      = 0.0;
        $costoTotal    = 0.0;
        $numeroFactura = NumeracionService::siguiente('factura', $ctx['empresa_id'], (int) $ctx['sucursal_id']);

        $ventaId = Db::insert('ventas', [
            'sucursal_id'    => $ctx['sucursal_id'],
            'empresa_id'     => $ctx['empresa_id'],
            'numero_factura' => $numeroFactura,
            'cliente_id'     => $clienteId,
            'vendedor_id'    => Auth::id(),
            'forma_pago'     => $formaPagoResumen,
            'estado'         => $hayCredito ? 'pendiente' : 'pagada',
            'notas'          => $data['notas'] ?? '',
            'ip'             => $ctx['ip'] ?? null,
            'subtotal'       => 0,
            'descuento'      => (float) ($data['descuento'] ?? 0),
            'itbms'          => 0,
            'total'          => 0,
            'costo'          => 0,
            'fecha'          => date('Y-m-d H:i:s'),
        ]);

        foreach ($items as $item) {
            $productoId = (int) $item['producto_id'];
            $cantidad   = (float) $item['cantidad'];
            // Sin depósito explícito se usa el activo de la sesión, para que siempre
            // se descuente stock (antes el POS web no lo enviaba y no bajaba inventario).
            $depositoId = (int) ($item['deposito_id'] ?? 0) ?: (int) ($ctx['deposito_id'] ?? 0);
            if ($depositoId > 0) {
                // Un deposito_id enviado por el cliente no puede descontar stock de otra sucursal
                SucursalService::exigirDepositoDeSucursal($depositoId, (int) $ctx['sucursal_id']);
            }

            $factor = 1.0;
            if (!empty($item['unidad_id'])) {
                $f = Db::value("SELECT factor_conversion FROM productos_unidades WHERE unidad_id = ?", [$item['unidad_id']]);
                if ($f !== null) $factor = (float) $f;
            }
            $cantidadBase = $cantidad * $factor;

            $precio     = (float) $item['precio'];
            $descuento  = (float) ($item['descuento'] ?? 0);
            $totalLinea = ($cantidad * $precio) - $descuento;
            $itbmsLinea = $totalLinea * $itbmsRate;
            $subtotal  += $totalLinea;

            $costo = $depositoId
                ? (float) (Db::value("SELECT ultimo_costo FROM inventario WHERE producto_id = ? AND deposito_id = ? LIMIT 1", [$productoId, $depositoId]) ?? 0)
                : 0.0;
            $costoTotal += $costo * $cantidadBase;

            $detalleId = Db::insert('ventas_detalle', [
                'venta_id'    => $ventaId,
                'producto_id' => $productoId,
                'cantidad'    => $cantidad,
                'precio'      => $precio,
                'costo'       => $costo,
                'itbms'       => $itbmsLinea,
                'descuento'   => $descuento,
                'total_linea' => $totalLinea + $itbmsLinea,
                'deposito_id' => $depositoId ?: null,
            ]);

            if ($depositoId) {
                $inventarioId = InventarioService::inventarioId($productoId, $depositoId);
                if ($inventarioId) {
                    InventarioService::registrar($inventarioId, 'salida', $cantidadBase, $ventaId, 'ventas', "Venta #{$numeroFactura}");
                }

                $manejaLotes = (int) Db::value("SELECT maneja_lotes FROM productos WHERE producto_id = ?", [$productoId]);
                if ($manejaLotes) {
                    $consumido = InventarioService::descontarLotesFefo($productoId, $depositoId, $cantidadBase, $ventaId, 'ventas');
                    if ($consumido) {
                        Db::query("UPDATE ventas_detalle SET lote_id = ? WHERE detalle_id = ?", [array_key_first($consumido), $detalleId]);
                    }
                }
            }
        }

        $descuentoGlobal = (float) ($data['descuento'] ?? 0);
        $itbmsTotal      = ($data['aplicar_itbms'] ?? true) ? $subtotal * $itbmsRate : 0;
        $total           = round($subtotal + $itbmsTotal - $descuentoGlobal, 2);

        $totalPagado = array_sum(array_column($pagos, 'monto'));
        if ($totalPagado > 0 && $totalPagado < $total - 0.01) {
            throw new \RuntimeException('El monto pagado ($' . number_format($totalPagado, 2) . ') es menor al total ($' . number_format($total, 2) . ').');
        }
        if ($totalPagado == 0) {
            $pagos[0]['monto'] = $total; // un solo pago por el total exacto
        }

        if ($hayCredito) {
            if (!$clienteId) {
                throw new \RuntimeException('Debe seleccionar un cliente para ventas a crédito.');
            }
            $cliente = Db::row("SELECT limite_credito, saldo_pendiente FROM clientes WHERE cliente_id = ?", [$clienteId]);
            if (!$cliente) {
                throw new \RuntimeException('Cliente no encontrado.');
            }
            $montoCredito = array_sum(array_column(array_filter($pagos, fn ($p) => $p['forma_pago'] === 'CREDITO'), 'monto'));
            $disponible   = (float) $cliente['limite_credito'] - (float) $cliente['saldo_pendiente'];
            if ($montoCredito > $disponible + 0.01) {
                throw new \RuntimeException('Crédito insuficiente. Disponible: $' . number_format($disponible, 2));
            }
            Db::query("UPDATE clientes SET saldo_pendiente = saldo_pendiente + ? WHERE cliente_id = ?", [$montoCredito, $clienteId]);
        }

        Db::update('ventas', [
            'subtotal'             => round($subtotal, 2),
            'itbms'                => round($itbmsTotal, 2),
            'total'                => $total,
            'costo'                => round($costoTotal, 2),
            'cotizacion_origen_id' => !empty($data['cotizacion_origen_id']) ? (int) $data['cotizacion_origen_id'] : null,
        ], ['venta_id' => $ventaId]);

        foreach ($pagos as $pg) {
            if ($pg['monto'] > 0) {
                Db::insert('ventas_pagos', ['venta_id' => $ventaId, 'forma_pago' => $pg['forma_pago'], 'monto' => round($pg['monto'], 2)]);
            }
        }

        if (!empty($data['cotizacion_origen_id'])) {
            Db::query("UPDATE cotizaciones SET estado = 'convertida' WHERE cotizacion_id = ?", [(int) $data['cotizacion_origen_id']]);
        }

        return [
            'success'        => true,
            'venta_id'       => $ventaId,
            'numero_factura' => $numeroFactura,
            'total'          => $total,
            'vuelto'         => max(0, round($totalPagado - $total, 2)),
        ];
    }

    /**
     * Anula una venta: devuelve el stock (con kardex), libera el crédito si estaba pendiente
     * y genera la nota de crédito. El llamador abre y cierra la transacción.
     *
     * @return string número de la nota de crédito
     */
    public static function anular(int $ventaId, int $sucursalId, int $empresaId, string $motivo): string
    {
        $venta = Db::row("SELECT * FROM ventas WHERE venta_id = ? AND sucursal_id = ? FOR UPDATE", [$ventaId, $sucursalId]);
        if (!$venta) {
            throw new \RuntimeException('Venta no encontrada.');
        }
        if ($venta['estado'] === 'anulada') {
            throw new \RuntimeException('Esta venta ya fue anulada.');
        }

        foreach (Db::all("SELECT producto_id, cantidad, deposito_id FROM ventas_detalle WHERE venta_id = ?", [$ventaId]) as $d) {
            if (!$d['deposito_id']) continue;
            $inventarioId = InventarioService::inventarioId((int) $d['producto_id'], (int) $d['deposito_id']);
            if ($inventarioId) {
                InventarioService::registrar($inventarioId, 'entrada', (float) $d['cantidad'], $ventaId, 'ventas_anulacion', "Anulación venta #{$venta['numero_factura']}");
            }
        }

        // Una venta a crédito pendiente deja de sumar al saldo del cliente
        if ($venta['estado'] === 'pendiente' && $venta['cliente_id']) {
            $credito = (float) Db::value("SELECT COALESCE(SUM(monto), 0) FROM ventas_pagos WHERE venta_id = ? AND forma_pago = 'CREDITO'", [$ventaId]);
            if ($credito > 0) {
                Db::query("UPDATE clientes SET saldo_pendiente = GREATEST(0, saldo_pendiente - ?) WHERE cliente_id = ?", [$credito, $venta['cliente_id']]);
            }
        }

        Db::update('ventas', ['estado' => 'anulada'], ['venta_id' => $ventaId]);

        $count      = (int) Db::value("SELECT COUNT(*) FROM notas_credito WHERE DATE(fecha) = CURDATE()");
        $numeroNota = 'NC' . date('Ymd') . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);

        Db::insert('notas_credito', [
            'venta_id'    => $ventaId,
            'sucursal_id' => $sucursalId,
            'empresa_id'  => $empresaId,
            'numero'      => $numeroNota,
            'motivo'      => $motivo,
            'monto'       => $venta['total'],
            'usuario_id'  => Auth::id(),
        ]);

        return $numeroNota;
    }

    /**
     * Precio de lista del producto para el tipo pedido (a/b/c), con respaldo en el precio A.
     * Lo usa la API para no confiar en precios enviados por el cliente.
     */
    public static function precioLista(int $productoId, string $tipo = 'a'): float
    {
        $tipo   = in_array($tipo, ['a', 'b', 'c'], true) ? $tipo : 'a';
        $precio = (float) (Db::value("SELECT precio FROM precios_productos WHERE producto_id = ? AND tipo_precio = ?", [$productoId, $tipo]) ?? 0);
        if ($precio <= 0 && $tipo !== 'a') {
            $precio = (float) (Db::value("SELECT precio FROM precios_productos WHERE producto_id = ? AND tipo_precio = 'a'", [$productoId]) ?? 0);
        }
        return $precio;
    }
}

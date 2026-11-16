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

        // Caja abierta del vendedor (si la empresa la exige y no hay, se rechaza antes de escribir)
        $cajaId = CajaService::paraVenta($ctx);

        // Pagos: [{forma_pago, monto}] o forma_pago suelto (modo de un solo pago)
        $pagos = $data['pagos'] ?? null;
        if (empty($pagos) || !is_array($pagos)) {
            $pagos = [['forma_pago' => $data['forma_pago'] ?? 'EFECTIVO', 'monto' => 0, 'referencia' => $data['referencia'] ?? null]];
        }
        foreach ($pagos as &$pg) {
            $pg['forma_pago'] = strtoupper(trim($pg['forma_pago'] ?? 'EFECTIVO'));
            $pg['monto']      = (float) ($pg['monto'] ?? 0);
            // Nº de comprobante (Yappy, transferencia…), opcional
            $ref              = trim((string) ($pg['referencia'] ?? ''));
            $pg['referencia'] = $ref !== '' ? mb_substr($ref, 0, 100) : null;
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
            'caja_id'        => $cajaId,
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

            $factor       = 1.0;
            $unidadId     = null;
            $unidadNombre = null;
            if (!empty($item['unidad_id'])) {
                // La presentación debe ser del producto (si no, un factor ajeno alteraría el stock)
                $u = Db::row("SELECT unidad_id, nombre, factor_conversion FROM productos_unidades WHERE unidad_id = ? AND producto_id = ?", [(int) $item['unidad_id'], $productoId]);
                if (!$u) {
                    throw new \RuntimeException('La presentación indicada no pertenece al producto.');
                }
                $factor       = max(0.001, (float) $u['factor_conversion']);
                $unidadId     = (int) $u['unidad_id'];
                $unidadNombre = $u['nombre'];
            }
            if ($unidadId === null) {
                // Venta en la unidad base: si no es "unidad" (lb, kg, gal…) la línea lo dice en la factura
                $um = (string) (Db::value("SELECT unidad_medida FROM productos WHERE producto_id = ?", [$productoId]) ?? 'und');
                if ($um !== 'und') {
                    $unidadNombre = ProductoService::abreviatura($um);
                }
            }
            $cantidadBase = $cantidad * $factor;

            $precio     = (float) $item['precio'];
            $descuento  = (float) ($item['descuento'] ?? 0);

            if ($cantidad <= 0) {
                throw new \RuntimeException('Las cantidades deben ser mayores que cero.');
            }
            if ($precio < 0 || $descuento < 0 || $descuento > $cantidad * $precio + 0.005) {
                throw new \RuntimeException('Precio o descuento inválido en una de las líneas.');
            }
            if ($depositoId > 0) {
                // Sin stock suficiente no se vende (las líneas del mismo producto ya descontaron antes en este bucle)
                $inv = Db::row(
                    "SELECT i.existencia, p.nombre FROM inventario i JOIN productos p ON p.producto_id = i.producto_id
                     WHERE i.producto_id = ? AND i.deposito_id = ? FOR UPDATE",
                    [$productoId, $depositoId]
                );
                if (!$inv || (float) $inv['existencia'] < $cantidadBase - 0.0001) {
                    $nombre = $inv['nombre'] ?? Db::value("SELECT nombre FROM productos WHERE producto_id = ?", [$productoId]) ?? "#{$productoId}";
                    throw new \RuntimeException("Stock insuficiente de \"{$nombre}\" (hay " . (float) ($inv['existencia'] ?? 0) . ', pides ' . (float) $cantidadBase . ').');
                }
            }
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
                'unidad_id'     => $unidadId,
                'unidad_nombre' => $unidadNombre,
                'factor'        => $factor,
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
        } elseif ($totalPagado > $total + 0.005) {
            // El cliente entregó de más (vuelto): los pagos registrados deben sumar el total,
            // si no el vuelto se cuenta como ingreso en efectivo. El vuelto sale de las líneas en efectivo.
            $exceso = $totalPagado - $total;
            for ($i = count($pagos) - 1; $i >= 0 && $exceso > 0.005; $i--) {
                if ($pagos[$i]['forma_pago'] !== 'EFECTIVO') continue;
                $quitar            = min($exceso, (float) $pagos[$i]['monto']);
                $pagos[$i]['monto'] = round((float) $pagos[$i]['monto'] - $quitar, 2);
                $exceso            -= $quitar;
            }

            // Pagó de más por Yappy / transferencia y se le dio el vuelto en efectivo: el pago queda por lo
            // que de verdad entró (cuadra con la cuenta) y el vuelto sale de la caja como salida de efectivo.
            $exceso = round($exceso, 2);
            if ($exceso > 0.005) {
                $digital = array_sum(array_map(fn ($p) => in_array($p['forma_pago'], ['YAPPY', 'TRANSFERENCIA'], true) ? (float) $p['monto'] : 0.0, $pagos));
                if ($digital + 0.005 < $exceso) {
                    throw new \RuntimeException('Solo se puede dar vuelto del efectivo o de un pago por Yappy / transferencia.');
                }
                if (!$cajaId) {
                    throw new \RuntimeException('Para dar vuelto en efectivo abre tu caja primero.');
                }
                $formasVuelto = implode('/', array_unique(array_map(fn ($p) => $p['forma_pago'], array_filter($pagos, fn ($p) => in_array($p['forma_pago'], ['YAPPY', 'TRANSFERENCIA'], true)))));
                CajaService::movimiento($cajaId, 'salida', $exceso, 'Vuelto en efectivo de pago ' . ucfirst(strtolower($formasVuelto)) . " (venta {$numeroFactura})");
            }
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
                Db::insert('ventas_pagos', ['venta_id' => $ventaId, 'forma_pago' => $pg['forma_pago'], 'monto' => round($pg['monto'], 2), 'referencia' => $pg['referencia']]);
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

    /** Vuelto entregado en efectivo por un pago de más con Yappy / transferencia (salida de caja de la venta). */
    public static function vueltoEfectivo(string $numeroFactura, ?int $cajaId): float
    {
        if (!$cajaId || $numeroFactura === '') return 0.0;
        return round((float) Db::value(
            "SELECT COALESCE(SUM(monto), 0) FROM caja_movimientos WHERE caja_id = ? AND tipo = 'salida' AND motivo LIKE ?",
            [$cajaId, 'Vuelto en efectivo%(venta ' . $numeroFactura . ')']
        ), 2);
    }

    /**
     * Agrega un producto que se quedó fuera de una factura ya hecha (solo del día, no anulada, sin abonos).
     * Precio de lista de la tienda, valida stock, kardex con el número de la factura, recalcula totales.
     * El monto extra: si la factura es a crédito se suma al crédito (y al saldo del cliente); si no, entra
     * con $formaPago (efectivo exige que la caja de la venta siga abierta). El llamador abre la transacción.
     *
     * @param array $item {producto_id, cantidad, unidad_id?}
     * @return array{total: float, agregado: float, numero_factura: string}
     */
    public static function agregarLinea(int $ventaId, array $item, ?string $formaPago, array $ctx): array
    {
        $v = Db::row("SELECT * FROM ventas WHERE venta_id = ? AND sucursal_id = ? FOR UPDATE", [$ventaId, $ctx['sucursal_id']]);
        if (!$v) throw new \RuntimeException('Venta no encontrada en esta sucursal.');
        if ($v['estado'] === 'anulada') throw new \RuntimeException('La venta está anulada.');
        if (substr((string) $v['fecha'], 0, 10) !== date('Y-m-d')) {
            throw new \RuntimeException('Solo se pueden agregar productos a facturas de hoy. Para una factura anterior, anúlala y hazla de nuevo.');
        }
        if ((float) Db::value("SELECT COALESCE(SUM(monto), 0) FROM cobros_ventas WHERE venta_id = ?", [$ventaId]) > 0) {
            throw new \RuntimeException('La factura ya tiene abonos; no se puede cambiar.');
        }

        $productoId = (int) ($item['producto_id'] ?? 0);
        $cantidad   = round((float) ($item['cantidad'] ?? 0), 2);
        if ($productoId <= 0 || $cantidad <= 0) throw new \RuntimeException('Indica el producto y una cantidad mayor que cero.');
        $sucursalId = (int) $v['sucursal_id'];
        // Mismo depósito que el resto de la factura (o el activo)
        $depositoId = (int) (Db::value("SELECT deposito_id FROM ventas_detalle WHERE venta_id = ? AND deposito_id IS NOT NULL LIMIT 1", [$ventaId]) ?? 0)
            ?: (int) ($ctx['deposito_id'] ?? 0);
        SucursalService::exigirDepositoDeSucursal($depositoId, $sucursalId);

        $factor = 1.0; $unidadId = null; $unidadNombre = null;
        $precio = self::precioLista($productoId, 'a', $sucursalId);
        if (!empty($item['unidad_id'])) {
            $u = null;
            foreach (PrecioTienda::unidades($productoId, $sucursalId) as $x) {
                if ((int) $x['unidad_id'] === (int) $item['unidad_id']) $u = $x;
            }
            if (!$u) throw new \RuntimeException('La presentación indicada no pertenece al producto.');
            $factor = max(0.001, (float) $u['factor_conversion']);
            $unidadId = (int) $u['unidad_id'];
            $unidadNombre = $u['nombre'];
            $precio = (float) $u['precio_a'];
        } else {
            $um = (string) (Db::value("SELECT unidad_medida FROM productos WHERE producto_id = ?", [$productoId]) ?? 'und');
            if ($um !== 'und') $unidadNombre = ProductoService::abreviatura($um);
        }
        if ($precio <= 0) throw new \RuntimeException('Ese producto no tiene precio en esta tienda.');
        $cantidadBase = $cantidad * $factor;

        $inv = Db::row("SELECT i.inventario_id, i.existencia, i.ultimo_costo, p.nombre FROM inventario i JOIN productos p ON p.producto_id = i.producto_id
                        WHERE i.producto_id = ? AND i.deposito_id = ? FOR UPDATE", [$productoId, $depositoId]);
        if (!$inv || (float) $inv['existencia'] < $cantidadBase - 0.0001) {
            $nombre = $inv['nombre'] ?? (Db::value("SELECT nombre FROM productos WHERE producto_id = ?", [$productoId]) ?? "#{$productoId}");
            throw new \RuntimeException("Stock insuficiente de \"{$nombre}\" (hay " . (float) ($inv['existencia'] ?? 0) . ', pides ' . (float) $cantidadBase . ').');
        }

        // ITBMS igual que el resto de la factura (si la venta no lo llevó, la línea tampoco)
        $itbmsRate  = (float) $v['itbms'] > 0 ? (float) env('ITBMS_RATE', 0.07) : 0.0;
        $totalLinea = round($cantidad * $precio, 2);
        $itbmsLinea = $totalLinea * $itbmsRate;
        $agregado   = round($totalLinea + $itbmsLinea, 2);
        $costo      = (float) $inv['ultimo_costo'];

        Db::insert('ventas_detalle', [
            'venta_id' => $ventaId, 'producto_id' => $productoId, 'cantidad' => $cantidad, 'precio' => $precio,
            'costo' => $costo, 'itbms' => $itbmsLinea, 'descuento' => 0, 'total_linea' => $agregado,
            'deposito_id' => $depositoId, 'unidad_id' => $unidadId, 'unidad_nombre' => $unidadNombre, 'factor' => $factor,
        ]);
        InventarioService::registrar((int) $inv['inventario_id'], 'salida', $cantidadBase, $ventaId, 'ventas', "Venta #{$v['numero_factura']} (producto agregado después)");

        // Pago del monto agregado
        $credito = Db::row("SELECT pago_id FROM ventas_pagos WHERE venta_id = ? AND forma_pago = 'CREDITO' LIMIT 1", [$ventaId]);
        $forma   = $credito ? 'CREDITO' : strtoupper(trim((string) ($formaPago ?: 'EFECTIVO')));
        if (!in_array($forma, ['EFECTIVO', 'YAPPY', 'TRANSFERENCIA', 'TARJETA', 'CREDITO'], true)) {
            throw new \RuntimeException('Forma de pago no válida.');
        }
        if ($forma === 'CREDITO') {
            if (!$v['cliente_id']) throw new \RuntimeException('La factura no tiene cliente: no se puede cargar a crédito.');
            if ($credito) {
                Db::query("UPDATE ventas_pagos SET monto = monto + ? WHERE pago_id = ?", [$agregado, $credito['pago_id']]);
            } else {
                Db::insert('ventas_pagos', ['venta_id' => $ventaId, 'forma_pago' => 'CREDITO', 'monto' => $agregado, 'referencia' => null]);
            }
            Db::query("UPDATE clientes SET saldo_pendiente = saldo_pendiente + ? WHERE cliente_id = ?", [$agregado, $v['cliente_id']]);
        } else {
            if ($forma === 'EFECTIVO') {
                $estadoCaja = $v['caja_id'] ? Db::value("SELECT estado FROM cajas WHERE caja_id = ?", [$v['caja_id']]) : null;
                if ($v['caja_id'] && $estadoCaja !== 'abierta') {
                    throw new \RuntimeException('La caja de esta venta ya se cerró: el efectivo no cuadraría. Usa otra forma de pago o crédito.');
                }
            }
            Db::insert('ventas_pagos', ['venta_id' => $ventaId, 'forma_pago' => $forma, 'monto' => $agregado, 'referencia' => null]);
        }

        $formas = array_column(Db::all("SELECT DISTINCT forma_pago FROM ventas_pagos WHERE venta_id = ?", [$ventaId]), 'forma_pago');
        $total  = round((float) $v['total'] + $agregado, 2);
        Db::update('ventas', [
            'subtotal'   => round((float) $v['subtotal'] + $totalLinea, 2),
            'itbms'      => round((float) $v['itbms'] + $itbmsLinea, 2),
            'total'      => $total,
            'costo'      => round((float) $v['costo'] + $costo * $cantidadBase, 2),
            'forma_pago' => count($formas) === 1 ? $formas[0] : 'MIXTO',
            'estado'     => in_array('CREDITO', $formas, true) ? 'pendiente' : $v['estado'],
        ], ['venta_id' => $ventaId]);

        if ($v['caja_id']) {
            CajaService::refrescarCierre((int) $v['caja_id']); // si la caja estaba cerrada (crédito / digital) actualiza su resumen
        }
        return ['total' => $total, 'agregado' => $agregado, 'numero_factura' => (string) $v['numero_factura']];
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
        if (CobroService::abonado($ventaId) > 0.004) {
            throw new \RuntimeException('Esta venta tiene abonos registrados ($' . number_format(CobroService::abonado($ventaId), 2) . '); no se puede anular sin devolver ese dinero al cliente.');
        }

        foreach (Db::all("SELECT producto_id, cantidad, deposito_id, factor FROM ventas_detalle WHERE venta_id = ?", [$ventaId]) as $d) {
            if (!$d['deposito_id']) continue;
            $inventarioId = InventarioService::inventarioId((int) $d['producto_id'], (int) $d['deposito_id']);
            if ($inventarioId) {
                // cantidad está en la presentación vendida; el stock se repone en unidades base
                InventarioService::registrar($inventarioId, 'entrada', (float) $d['cantidad'] * max(0.001, (float) $d['factor']), $ventaId, 'ventas_anulacion', "Anulación venta #{$venta['numero_factura']}");
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
    public static function precioLista(int $productoId, string $tipo = 'a', ?int $sucursalId = null): float
    {
        $sucursalId ??= PrecioTienda::sucursalActual();
        $tipo   = in_array($tipo, ['a', 'b', 'c'], true) ? $tipo : 'a';
        $precio = PrecioTienda::precio($productoId, $sucursalId, $tipo);
        if ($precio <= 0 && $tipo !== 'a') {
            $precio = PrecioTienda::precio($productoId, $sucursalId, 'a');
        }
        return $precio;
    }
}

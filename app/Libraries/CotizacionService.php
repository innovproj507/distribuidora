<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Cotizaciones: alta y conversión en venta. Lo usan la web y la API.
 * El llamador abre y cierra la transacción.
 */
final class CotizacionService
{
    public const ESTADOS = ['pendiente', 'aprobada', 'rechazada', 'convertida'];

    /**
     * $data: items[{producto_id, cantidad, precio, descuento?}], cliente_id?, notas?, descuento?, estado?
     * $ctx:  empresa_id, sucursal_id
     *
     * @return array{cotizacion_id: int, numero: string, total: float}
     */
    public static function crear(array $data, array $ctx): array
    {
        $items = $data['items'] ?? [];
        if (empty($items)) {
            throw new \RuntimeException('No hay productos en la cotización.');
        }

        $itbmsRate   = (float) env('ITBMS_RATE', 0.07);
        $diasValidez = (int) env('COTIZACION_DIAS_VALIDEZ', 7);
        $numero      = NumeracionService::siguiente('cotizacion', $ctx['empresa_id'], (int) $ctx['sucursal_id']);
        $estado      = in_array($data['estado'] ?? '', self::ESTADOS, true) ? $data['estado'] : 'pendiente';
        $subtotal    = 0.0;

        $cotizacionId = Db::insert('cotizaciones', [
            'sucursal_id'       => $ctx['sucursal_id'],
            'empresa_id'        => $ctx['empresa_id'],
            'numero'            => $numero,
            'token'             => bin2hex(random_bytes(16)),
            'fecha_vencimiento' => date('Y-m-d', strtotime("+{$diasValidez} days")),
            'cliente_id'        => !empty($data['cliente_id']) ? (int) $data['cliente_id'] : null,
            'vendedor_id'       => Auth::id(),
            'estado'            => $estado,
            'notas'             => $data['notas'] ?? '',
            'subtotal'          => 0,
            'descuento'         => (float) ($data['descuento'] ?? 0),
            'itbms'             => 0,
            'total'             => 0,
            'fecha'             => date('Y-m-d H:i:s'),
        ]);

        foreach ($items as $item) {
            $totalLinea = ((float) $item['cantidad'] * (float) $item['precio']) - (float) ($item['descuento'] ?? 0);
            $itbmsLinea = $totalLinea * $itbmsRate;
            $subtotal  += $totalLinea;

            Db::insert('cotizaciones_detalle', [
                'cotizacion_id' => $cotizacionId,
                'producto_id'   => (int) $item['producto_id'],
                'cantidad'      => (float) $item['cantidad'],
                'precio'        => (float) $item['precio'],
                'descuento'     => (float) ($item['descuento'] ?? 0),
                'itbms'         => $itbmsLinea,
                'total_linea'   => $totalLinea + $itbmsLinea,
            ]);
        }

        $itbmsTotal = $subtotal * $itbmsRate;
        $total      = round($subtotal + $itbmsTotal - (float) ($data['descuento'] ?? 0), 2);
        Db::update('cotizaciones', ['subtotal' => round($subtotal, 2), 'itbms' => round($itbmsTotal, 2), 'total' => $total], ['cotizacion_id' => $cotizacionId]);

        return ['cotizacion_id' => $cotizacionId, 'numero' => $numero, 'total' => $total];
    }

    /**
     * Convierte una cotización pendiente/aprobada en venta pagada en efectivo y descuenta
     * stock del depósito indicado (con kardex).
     *
     * @return array{venta_id: int, numero_factura: string}
     */
    public static function convertir(int $cotizacionId, array $ctx): array
    {
        $cot = Db::row("SELECT * FROM cotizaciones WHERE cotizacion_id = ? AND sucursal_id = ? FOR UPDATE", [$cotizacionId, $ctx['sucursal_id']]);
        if (!$cot) {
            throw new \RuntimeException('Cotización no encontrada.');
        }
        if (!in_array($cot['estado'], ['pendiente', 'aprobada'], true)) {
            throw new \RuntimeException('Solo se pueden convertir cotizaciones pendientes o aprobadas.');
        }

        $depositoId    = (int) ($ctx['deposito_id'] ?? 0);
        $numeroFactura = NumeracionService::siguiente('factura', $ctx['empresa_id'], (int) $cot['sucursal_id']);
        $cajaId        = CajaService::paraVenta(['sucursal_id' => $cot['sucursal_id']] + $ctx);

        $ventaId = Db::insert('ventas', [
            'caja_id'              => $cajaId,
            'sucursal_id'          => $cot['sucursal_id'],
            'empresa_id'           => $ctx['empresa_id'],
            'numero_factura'       => $numeroFactura,
            'cliente_id'           => $cot['cliente_id'],
            'vendedor_id'          => Auth::id(),
            'subtotal'             => $cot['subtotal'],
            'descuento'            => $cot['descuento'],
            'itbms'                => $cot['itbms'],
            'total'                => $cot['total'],
            'forma_pago'           => 'EFECTIVO',
            'estado'               => 'pagada',
            'cotizacion_origen_id' => $cotizacionId,
            'ip'                   => $ctx['ip'] ?? null,
            'costo'                => 0,
            'fecha'                => date('Y-m-d H:i:s'),
        ]);

        foreach (Db::all("SELECT * FROM cotizaciones_detalle WHERE cotizacion_id = ?", [$cotizacionId]) as $d) {
            Db::insert('ventas_detalle', [
                'venta_id'    => $ventaId,
                'producto_id' => $d['producto_id'],
                'cantidad'    => $d['cantidad'],
                'precio'      => $d['precio'],
                'descuento'   => $d['descuento'],
                'itbms'       => $d['itbms'],
                'total_linea' => $d['total_linea'],
                'deposito_id' => $depositoId ?: null,
            ]);

            $inventarioId = $depositoId ? InventarioService::inventarioId((int) $d['producto_id'], $depositoId) : null;
            if ($inventarioId) {
                InventarioService::registrar($inventarioId, 'salida', (float) $d['cantidad'], $ventaId, 'ventas', "Venta #{$numeroFactura} (cotización)");
            }
        }

        Db::insert('ventas_pagos', ['venta_id' => $ventaId, 'forma_pago' => 'EFECTIVO', 'monto' => $cot['total']]);
        Db::update('cotizaciones', ['estado' => 'convertida'], ['cotizacion_id' => $cotizacionId]);

        return ['venta_id' => $ventaId, 'numero_factura' => $numeroFactura];
    }
}

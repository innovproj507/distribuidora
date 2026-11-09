<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Abonos de clientes a ventas a crédito (parciales o totales). El llamador abre y cierra la transacción.
 *
 * Pendiente de una factura = su monto a crédito (ventas_pagos CREDITO) − lo aplicado en cobros_ventas.
 * El saldo del cliente (clientes.saldo_pendiente) baja con cada abono; la factura pasa a 'pagada' al llegar a 0.
 */
final class CobroService
{
    public const FORMAS = ['EFECTIVO', 'YAPPY', 'TARJETA', 'TRANSFERENCIA', 'CHEQUE'];

    /**
     * Deudas pendientes del cliente (más antigua primero) con lo abonado y lo que falta: facturas a
     * crédito (venta_id) y saldos anteriores al sistema (saldo_id, venta_id null, numero_factura = SA-…).
     */
    public static function pendientes(int $clienteId, int $empresaId): array
    {
        $filas = Db::all(
            "SELECT v.venta_id, NULL AS saldo_id, v.numero_factura, v.fecha, v.total, v.sucursal_id,
                    COALESCE((SELECT SUM(monto) FROM ventas_pagos WHERE venta_id = v.venta_id AND forma_pago = 'CREDITO'), 0) AS credito,
                    COALESCE((SELECT SUM(monto) FROM cobros_ventas WHERE venta_id = v.venta_id), 0) AS abonado
             FROM ventas v
             WHERE v.cliente_id = ? AND v.empresa_id = ? AND v.estado = 'pendiente'",
            [$clienteId, $empresaId]
        );
        $saldos = Db::all(
            "SELECT NULL AS venta_id, s.saldo_id, s.numero AS numero_factura, CONCAT(s.fecha, ' 00:00:00') AS fecha, s.monto AS total, s.sucursal_id,
                    s.monto AS credito,
                    COALESCE((SELECT SUM(monto) FROM cobros_ventas WHERE saldo_id = s.saldo_id), 0) AS abonado
             FROM saldos_anteriores s
             WHERE s.cliente_id = ? AND s.empresa_id = ? AND s.estado = 'pendiente'",
            [$clienteId, $empresaId]
        );
        $filas = array_merge($saldos, $filas);
        foreach ($filas as &$f) {
            $f['pendiente'] = round((float) $f['credito'] - (float) $f['abonado'], 2);
        }
        unset($f);
        // Más antigua primero; a igual fecha, el saldo anterior antes que la factura
        usort($filas, fn ($a, $b) => [$a['fecha'], $a['venta_id'] === null ? 0 : 1, (int) $a['venta_id'], (int) $a['saldo_id']]
                                  <=> [$b['fecha'], $b['venta_id'] === null ? 0 : 1, (int) $b['venta_id'], (int) $b['saldo_id']]);
        return array_values(array_filter($filas, fn ($f) => $f['pendiente'] > 0.004));
    }

    /**
     * @param ?int $ventaId aplicar solo a esa factura; null = a las más antiguas primero
     * @return array{cobro_id:int, numero:string, monto:float, aplicado:array, saldo:float}
     */
    public static function registrar(int $clienteId, float $monto, string $formaPago, ?string $referencia, ?string $notas, array $ctx, ?int $ventaId = null): array
    {
        $monto     = round($monto, 2);
        $formaPago = strtoupper(trim($formaPago));
        if ($monto <= 0) {
            throw new \RuntimeException('El monto del abono debe ser mayor que cero.');
        }
        if (!in_array($formaPago, self::FORMAS, true)) {
            throw new \RuntimeException('Forma de pago no válida.');
        }

        $cliente = Db::row("SELECT cliente_id, nombre, saldo_pendiente FROM clientes WHERE cliente_id = ? AND empresa_id = ? FOR UPDATE", [$clienteId, $ctx['empresa_id']]);
        if (!$cliente) {
            throw new \RuntimeException('Cliente no encontrado.');
        }

        $pendientes = self::pendientes($clienteId, (int) $ctx['empresa_id']);
        if ($ventaId !== null) {
            $pendientes = array_values(array_filter($pendientes, fn ($p) => $p['venta_id'] !== null && (int) $p['venta_id'] === $ventaId));
            if (!$pendientes) {
                throw new \RuntimeException('Esa factura no tiene saldo pendiente.');
            }
        }
        $deuda = round(array_sum(array_column($pendientes, 'pendiente')), 2);
        if ($monto > $deuda + 0.004) {
            throw new \RuntimeException('El abono ($' . number_format($monto, 2) . ') es mayor que lo pendiente ($' . number_format($deuda, 2) . ').');
        }

        $cobroId = Db::insert('cobros', [
            'empresa_id'  => $ctx['empresa_id'],
            'sucursal_id' => $ctx['sucursal_id'],
            'cliente_id'  => $clienteId,
            'monto'       => $monto,
            'forma_pago'  => $formaPago,
            'referencia'  => $referencia !== null && trim($referencia) !== '' ? mb_substr(trim($referencia), 0, 100) : null,
            'notas'       => $notas !== null && trim($notas) !== '' ? trim($notas) : null,
            'usuario_id'  => Auth::id(),
            // El abono entra en la caja abierta de quien cobra (si tiene una)
            'caja_id'     => CajaService::abiertaId(isset($ctx['sucursal_id']) ? (int) $ctx['sucursal_id'] : null),
        ]);
        $numero = 'REC-' . str_pad((string) $cobroId, 6, '0', STR_PAD_LEFT);
        Db::update('cobros', ['numero' => $numero], ['cobro_id' => $cobroId]);

        $restante = $monto;
        $aplicado = [];
        foreach ($pendientes as $p) {
            if ($restante <= 0.004) break;
            $parte = round(min($restante, (float) $p['pendiente']), 2);
            $esSaldo = $p['venta_id'] === null;
            Db::insert('cobros_ventas', [
                'cobro_id' => $cobroId,
                'venta_id' => $esSaldo ? null : $p['venta_id'],
                'saldo_id' => $esSaldo ? $p['saldo_id'] : null,
                'monto'    => $parte,
            ]);
            $restante -= $parte;
            $liquidada = $parte >= (float) $p['pendiente'] - 0.004;
            if ($liquidada) {
                $esSaldo
                    ? Db::update('saldos_anteriores', ['estado' => 'pagada'], ['saldo_id' => $p['saldo_id']])
                    : Db::update('ventas', ['estado' => 'pagada'], ['venta_id' => $p['venta_id']]);
            }
            $aplicado[] = [
                'venta_id' => $esSaldo ? null : (int) $p['venta_id'], 'saldo_id' => $esSaldo ? (int) $p['saldo_id'] : null,
                'numero_factura' => $p['numero_factura'], 'monto' => $parte, 'liquidada' => $liquidada,
            ];
        }

        Db::query("UPDATE clientes SET saldo_pendiente = GREATEST(0, saldo_pendiente - ?) WHERE cliente_id = ?", [$monto, $clienteId]);
        $saldo = (float) Db::value("SELECT saldo_pendiente FROM clientes WHERE cliente_id = ?", [$clienteId]);

        return ['cobro_id' => $cobroId, 'numero' => $numero, 'monto' => $monto, 'aplicado' => $aplicado, 'saldo' => $saldo];
    }

    /** Lo ya abonado a una factura (para impedir anularla con cobros aplicados). */
    public static function abonado(int $ventaId): float
    {
        return (float) Db::value("SELECT COALESCE(SUM(monto), 0) FROM cobros_ventas WHERE venta_id = ?", [$ventaId]);
    }
}

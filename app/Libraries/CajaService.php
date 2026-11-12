<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Caja con arqueo. Cada cajero abre su caja en la sucursal con un monto inicial; las ventas
 * (VentaService, CotizacionService::convertir) y los abonos (CobroService) quedan amarrados a
 * la caja abierta del usuario. Al cerrar se compara lo esperado contra lo declarado por forma
 * de pago y se guarda la foto del arqueo (no cambia aunque después se anule algo).
 *
 * Efectivo esperado = monto inicial + ventas en efectivo + abonos en efectivo + entradas − salidas.
 * El crédito no es dinero: se informa aparte.
 */
final class CajaService
{
    /** Formas de pago que se cuadran (el crédito no entra en caja). */
    public const FORMAS = ['EFECTIVO', 'YAPPY', 'TARJETA', 'TRANSFERENCIA', 'CHEQUE'];

    /** Billetes y monedas para el conteo del efectivo (USD/balboa). */
    public const DENOMINACIONES = [100, 50, 20, 10, 5, 1, 0.5, 0.25, 0.1, 0.05, 0.01];

    public static function abierta(int $usuarioId, int $sucursalId): ?array
    {
        return Db::row(
            "SELECT * FROM cajas WHERE usuario_id = ? AND sucursal_id = ? AND estado = 'abierta' ORDER BY caja_id DESC LIMIT 1",
            [$usuarioId, $sucursalId]
        );
    }

    /** Caja abierta del usuario actual en la sucursal, o null. */
    public static function abiertaId(?int $sucursalId): ?int
    {
        if (!$sucursalId || !Auth::id()) {
            return null;
        }
        $caja = self::abierta((int) Auth::id(), $sucursalId);
        return $caja ? (int) $caja['caja_id'] : null;
    }

    public static function exigida(int $empresaId): bool
    {
        return (bool) (Db::value("SELECT exigir_caja FROM companies WHERE empresa_id = ?", [$empresaId]) ?? 1);
    }

    /**
     * Caja donde se registra una venta del usuario actual. Lanza RuntimeException si la empresa
     * exige caja y no hay ninguna abierta. $ctx['omitir_caja'] solo lo usa el generador de datos.
     */
    public static function paraVenta(array $ctx): ?int
    {
        if (!empty($ctx['omitir_caja'])) {
            return null;
        }
        $id = self::abiertaId(isset($ctx['sucursal_id']) ? (int) $ctx['sucursal_id'] : null);
        if ($id === null && self::exigida((int) $ctx['empresa_id'])) {
            throw new \RuntimeException('Abre tu caja antes de vender (monto inicial en efectivo).');
        }
        return $id;
    }

    public static function abrir(int $empresaId, int $sucursalId, float $montoInicial, ?string $notas): int
    {
        if ($montoInicial < 0) {
            throw new \RuntimeException('El monto inicial no puede ser negativo.');
        }
        if (self::abierta((int) Auth::id(), $sucursalId)) {
            throw new \RuntimeException('Ya tienes una caja abierta en esta sucursal.');
        }
        return Db::insert('cajas', [
            'empresa_id'     => $empresaId,
            'sucursal_id'    => $sucursalId,
            'usuario_id'     => Auth::id(),
            'estado'         => 'abierta',
            'monto_inicial'  => round($montoInicial, 2),
            'notas_apertura' => self::texto($notas),
            'abierta_en'     => date('Y-m-d H:i:s'),
        ]);
    }

    /** Entrada (cambio, fondo) o salida (retiro, pago menor) de efectivo de la caja. */
    public static function movimiento(int $cajaId, string $tipo, float $monto, string $motivo): int
    {
        $caja = Db::row("SELECT estado FROM cajas WHERE caja_id = ?", [$cajaId]);
        if (!$caja || $caja['estado'] !== 'abierta') {
            throw new \RuntimeException('La caja no está abierta.');
        }
        if (!in_array($tipo, ['entrada', 'salida'], true)) {
            throw new \RuntimeException('Tipo de movimiento no válido.');
        }
        if ($monto <= 0) {
            throw new \RuntimeException('El monto debe ser mayor que cero.');
        }
        if (trim($motivo) === '') {
            throw new \RuntimeException('Indica el motivo.');
        }
        if ($tipo === 'salida' && round($monto, 2) > self::resumen($cajaId)['esperado']['EFECTIVO'] + 0.004) {
            throw new \RuntimeException('No hay suficiente efectivo en la caja para esa salida.');
        }
        return Db::insert('caja_movimientos', [
            'caja_id' => $cajaId, 'tipo' => $tipo, 'monto' => round($monto, 2),
            'motivo' => mb_substr(trim($motivo), 0, 200), 'usuario_id' => Auth::id(), 'fecha' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Resumen de la caja: ventas, abonos y movimientos, y lo esperado por forma de pago.
     *
     * @return array{caja: array, ventas: array, por_forma_ventas: array, credito: float, anuladas: array,
     *               cobros: array, entradas: float, salidas: float, movimientos: array, esperado: array<string,float>}
     */
    public static function resumen(int $cajaId): array
    {
        $caja = Db::row(
            "SELECT c.*, u.username AS cajero, uc.username AS cerrada_por_nombre, b.nombre AS sucursal
             FROM cajas c LEFT JOIN users u ON u.id = c.usuario_id LEFT JOIN users uc ON uc.id = c.cerrada_por
             LEFT JOIN branches b ON b.sucursal_id = c.sucursal_id WHERE c.caja_id = ?",
            [$cajaId]
        );
        if (!$caja) {
            throw new \RuntimeException('Caja no encontrada.');
        }

        $ventas = Db::row(
            "SELECT COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total FROM ventas WHERE caja_id = ? AND estado <> 'anulada'",
            [$cajaId]
        );
        $anuladas = Db::row(
            "SELECT COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total FROM ventas WHERE caja_id = ? AND estado = 'anulada'",
            [$cajaId]
        );
        $porFormaVentas = [];
        foreach (Db::all(
            "SELECT p.forma_pago, SUM(p.monto) AS monto FROM ventas_pagos p JOIN ventas v ON v.venta_id = p.venta_id
             WHERE v.caja_id = ? AND v.estado <> 'anulada' GROUP BY p.forma_pago",
            [$cajaId]
        ) as $r) {
            $porFormaVentas[strtoupper($r['forma_pago'])] = round((float) $r['monto'], 2);
        }
        $credito = $porFormaVentas['CREDITO'] ?? 0.0;
        unset($porFormaVentas['CREDITO']);

        $cobros = [];
        foreach (Db::all("SELECT forma_pago, COUNT(*) AS cantidad, SUM(monto) AS monto FROM cobros WHERE caja_id = ? GROUP BY forma_pago", [$cajaId]) as $r) {
            $cobros[strtoupper($r['forma_pago'])] = ['cantidad' => (int) $r['cantidad'], 'monto' => round((float) $r['monto'], 2)];
        }

        $movimientos = Db::all(
            "SELECT m.*, u.username FROM caja_movimientos m LEFT JOIN users u ON u.id = m.usuario_id WHERE m.caja_id = ? ORDER BY m.movimiento_id",
            [$cajaId]
        );
        $entradas = round(array_sum(array_map(fn ($m) => $m['tipo'] === 'entrada' ? (float) $m['monto'] : 0, $movimientos)), 2);
        $salidas  = round(array_sum(array_map(fn ($m) => $m['tipo'] === 'salida' ? (float) $m['monto'] : 0, $movimientos)), 2);

        $esperado = [];
        foreach (self::FORMAS as $f) {
            $esperado[$f] = round(($porFormaVentas[$f] ?? 0) + ($cobros[$f]['monto'] ?? 0), 2);
        }
        $esperado['EFECTIVO'] = round((float) $caja['monto_inicial'] + $esperado['EFECTIVO'] + $entradas - $salidas, 2);

        return [
            'caja'             => $caja,
            'ventas'           => ['cantidad' => (int) $ventas['cantidad'], 'total' => round((float) $ventas['total'], 2)],
            'por_forma_ventas' => $porFormaVentas,
            'credito'          => round($credito, 2),
            'anuladas'         => ['cantidad' => (int) $anuladas['cantidad'], 'total' => round((float) $anuladas['total'], 2)],
            'cobros'           => $cobros,
            'entradas'         => $entradas,
            'salidas'          => $salidas,
            'movimientos'      => $movimientos,
            'esperado'         => $esperado,
        ];
    }

    /**
     * Actualiza el resumen guardado de una caja ya cerrada después de agregarle una venta a crédito
     * (fecha anterior): ventas, crédito y anuladas. El efectivo contado y su diferencia no cambian.
     */
    public static function refrescarCierre(int $cajaId): void
    {
        $caja = Db::row("SELECT estado, arqueo FROM cajas WHERE caja_id = ? FOR UPDATE", [$cajaId]);
        if (!$caja || $caja['estado'] !== 'cerrada') {
            return;
        }
        $r = self::resumen($cajaId);
        $arqueo = json_decode((string) $caja['arqueo'], true) ?: [];
        $arqueo['credito']  = $r['credito'];
        $arqueo['ventas']   = $r['ventas'];
        $arqueo['anuladas'] = $r['anuladas'];
        Db::update('cajas', [
            'total_ventas' => $r['ventas']['total'],
            'arqueo'       => json_encode($arqueo, JSON_UNESCAPED_UNICODE),
        ], ['caja_id' => $cajaId]);
    }

    /**
     * Cierra la caja con lo declarado por forma de pago. El efectivo puede venir como conteo de
     * billetes ($billetes: denominación => cantidad) o como monto directo en $declarado['EFECTIVO'].
     *
     * @return array el arqueo guardado
     */
    public static function cerrar(int $cajaId, array $declarado, array $billetes, ?string $notas): array
    {
        $caja = Db::row("SELECT * FROM cajas WHERE caja_id = ? FOR UPDATE", [$cajaId]);
        if (!$caja || $caja['estado'] !== 'abierta') {
            throw new \RuntimeException('La caja ya está cerrada.');
        }

        $conteo = [];
        $totalBilletes = 0.0;
        foreach (self::DENOMINACIONES as $d) {
            $n = max(0, (int) ($billetes[(string) $d] ?? $billetes[number_format($d, 2, '.', '')] ?? 0));
            if ($n > 0) {
                $conteo[number_format($d, 2, '.', '')] = $n;
                $totalBilletes += $n * $d;
            }
        }

        $r       = self::resumen($cajaId);
        $formas  = [];
        foreach (self::FORMAS as $f) {
            $dec = $f === 'EFECTIVO' && $conteo
                ? round($totalBilletes, 2)
                : round(max(0, (float) ($declarado[$f] ?? 0)), 2);
            $formas[$f] = ['esperado' => $r['esperado'][$f], 'declarado' => $dec, 'diferencia' => round($dec - $r['esperado'][$f], 2)];
        }
        $arqueo = ['formas' => $formas, 'billetes' => $conteo, 'credito' => $r['credito'],
            'ventas' => $r['ventas'], 'anuladas' => $r['anuladas'], 'entradas' => $r['entradas'], 'salidas' => $r['salidas']];

        Db::update('cajas', [
            'estado'              => 'cerrada',
            'cerrada_en'          => date('Y-m-d H:i:s'),
            'cerrada_por'         => Auth::id(),
            'notas_cierre'        => self::texto($notas),
            'efectivo_esperado'   => $formas['EFECTIVO']['esperado'],
            'efectivo_contado'    => $formas['EFECTIVO']['declarado'],
            'diferencia_efectivo' => $formas['EFECTIVO']['diferencia'],
            'total_ventas'        => $r['ventas']['total'],
            'arqueo'              => json_encode($arqueo, JSON_UNESCAPED_UNICODE),
        ], ['caja_id' => $cajaId]);

        return $arqueo;
    }

    private static function texto(?string $s): ?string
    {
        $s = trim((string) $s);
        return $s === '' ? null : mb_substr($s, 0, 1000);
    }
}

<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Movimientos de stock. Todo cambio de existencia pasa por aquí para que quede en el
 * kardex (inventario_movimientos). Productos con maneja_lotes = 1 además descuentan
 * de la tabla lotes (FEFO: primero el que vence antes).
 */
final class InventarioService
{
    /** Tipos que suman stock; el resto resta. */
    private const TIPOS_ENTRADA = ['entrada', 'traslado_entrada', 'devolucion'];

    /**
     * Registra un movimiento y actualiza inventario.existencia. Devuelve el saldo nuevo,
     * o null si no existe el registro de inventario.
     */
    public static function registrar(
        int $inventarioId,
        string $tipo,
        float $cantidad,
        ?int $referenciaId = null,
        ?string $referenciaTipo = null,
        string $motivo = ''
    ): ?float {
        $inv = Db::row("SELECT existencia FROM inventario WHERE inventario_id = ?", [$inventarioId]);
        if (!$inv) {
            return null;
        }

        $saldoAnterior = (float) $inv['existencia'];
        $saldoNuevo    = in_array($tipo, self::TIPOS_ENTRADA, true)
            ? $saldoAnterior + $cantidad
            : $saldoAnterior - $cantidad;

        Db::query("UPDATE inventario SET existencia = ? WHERE inventario_id = ?", [$saldoNuevo, $inventarioId]);

        Db::insert('inventario_movimientos', [
            'inventario_id'   => $inventarioId,
            'tipo'            => $tipo,
            'cantidad'        => $cantidad,
            'saldo_anterior'  => $saldoAnterior,
            'saldo_nuevo'     => $saldoNuevo,
            'referencia_id'   => $referenciaId,
            'referencia_tipo' => $referenciaTipo,
            'motivo'          => $motivo,
            'usuario_id'      => Auth::id(),
        ]);

        return $saldoNuevo;
    }

    /** inventario_id de un producto en un depósito, o null si no existe. */
    public static function inventarioId(int $productoId, int $depositoId): ?int
    {
        $id = Db::value(
            "SELECT inventario_id FROM inventario WHERE producto_id = ? AND deposito_id = ? LIMIT 1",
            [$productoId, $depositoId]
        );
        return $id !== null ? (int) $id : null;
    }

    /**
     * Descuenta de los lotes activos según FEFO. Devuelve [lote_id => cantidad consumida].
     */
    public static function descontarLotesFefo(int $productoId, int $depositoId, float $necesario, int $refId, string $refTipo): array
    {
        $lotes = Db::all(
            "SELECT * FROM lotes
             WHERE producto_id = ? AND deposito_id = ? AND estado = 'activo' AND cantidad_actual > 0
             ORDER BY (fecha_vencimiento IS NULL) ASC, fecha_vencimiento ASC",
            [$productoId, $depositoId]
        );

        $consumido = [];
        $restante  = $necesario;

        foreach ($lotes as $lote) {
            if ($restante <= 0) {
                break;
            }

            $loteId     = (int) $lote['lote_id'];
            $disponible = (float) $lote['cantidad_actual'];
            $descontar  = min($disponible, $restante);
            $saldoNuevo = $disponible - $descontar;

            Db::query(
                "UPDATE lotes SET cantidad_actual = ?, estado = ? WHERE lote_id = ?",
                [$saldoNuevo, $saldoNuevo <= 0 ? 'agotado' : 'activo', $loteId]
            );
            Db::query(
                "INSERT INTO lote_movimientos
                    (lote_id, tipo, cantidad, saldo_anterior, saldo_nuevo, referencia_id, referencia_tipo)
                 VALUES (?, 'salida', ?, ?, ?, ?, ?)",
                [$loteId, $descontar, $disponible, $saldoNuevo, $refId, $refTipo]
            );

            $consumido[$loteId] = $descontar;
            $restante -= $descontar;
        }

        return $consumido;
    }
}

<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Core\Auth;

class Lote extends Model
{
    protected string $table = 'lotes';
    protected string $primaryKey = 'lote_id';
    protected array $fillable = [
        'producto_id', 'deposito_id', 'numero_lote', 'fecha_vencimiento',
        'fecha_fabricacion', 'cantidad_inicial', 'cantidad_actual',
        'compra_detalle_id', 'estado', 'notas'
    ];

    /**
     * Obtener lotes activos de un producto/depósito ordenados por FEFO
     * (First Expired, First Out): primero los que vencen antes
     */
    public static function fefo(int $productoId, int $depositoId): array
    {
        return Database::query(
            "SELECT * FROM lotes
             WHERE producto_id = ? AND deposito_id = ? AND estado = 'activo' AND cantidad_actual > 0
             ORDER BY (fecha_vencimiento IS NULL) ASC, fecha_vencimiento ASC",
            [$productoId, $depositoId]
        )->fetchAll();
    }

    /**
     * Descontar cantidad de lotes según FEFO
     * Retorna [lote_id => qty_consumed]
     */
    public static function deductFefo(
        int $productoId,
        int $depositoId,
        float $needed,
        int $refId,
        string $refTipo
    ): array {
        $lots = self::fefo($productoId, $depositoId);
        $consumed = [];
        $remaining = $needed;

        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $loteId = (int) $lot['lote_id'];
            $disponible = (float) $lot['cantidad_actual'];
            $deducir = min($disponible, $remaining);

            $saldoAnterior = $disponible;
            $saldoNuevo = $disponible - $deducir;

            // Actualizar cantidad del lote
            $nuevoEstado = $saldoNuevo <= 0 ? 'agotado' : 'activo';
            Database::query(
                "UPDATE lotes SET cantidad_actual = ?, estado = ? WHERE lote_id = ?",
                [$saldoNuevo, $nuevoEstado, $loteId]
            );

            // Registrar movimiento
            Database::query(
                "INSERT INTO lote_movimientos
                    (lote_id, tipo, cantidad, saldo_anterior, saldo_nuevo, referencia_id, referencia_tipo)
                 VALUES (?, 'salida', ?, ?, ?, ?, ?)",
                [$loteId, $deducir, $saldoAnterior, $saldoNuevo, $refId, $refTipo]
            );

            $consumed[$loteId] = $deducir;
            $remaining -= $deducir;
        }

        return $consumed;
    }
}

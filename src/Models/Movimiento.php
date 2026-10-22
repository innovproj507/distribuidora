<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Core\Auth;

class Movimiento extends Model
{
    protected string $table = 'inventario_movimientos';
    protected string $primaryKey = 'movimiento_id';
    protected array $fillable = [
        'inventario_id', 'tipo', 'cantidad', 'saldo_anterior', 
        'saldo_nuevo', 'referencia_id', 'referencia_tipo', 
        'motivo', 'usuario_id'
    ];

    /**
     * Registrar un movimiento de inventario
     */
    public static function registrar(int $inventarioId, string $tipo, float $cantidad, ?int $referenciaId = null, ?string $referenciaTipo = null, string $motivo = ''): bool
    {
        // Obtener saldo actual
        $inventario = Database::query(
            "SELECT existencia FROM inventario WHERE inventario_id = ?",
            [$inventarioId]
        )->fetch();

        if (!$inventario) return false;

        $saldoAnterior = (float)$inventario['existencia'];
        $saldoNuevo = $saldoAnterior;

        if (in_array($tipo, ['entrada', 'traslado_en', 'devolucion'])) {
            $saldoNuevo += $cantidad;
        } else {
            $saldoNuevo -= $cantidad;
        }

        // Actualizar stock en la tabla inventario
        Database::query(
            "UPDATE inventario SET existencia = ? WHERE inventario_id = ?",
            [$saldoNuevo, $inventarioId]
        );

        // Crear registro de movimiento
        return (bool) self::create([
            'inventario_id' => $inventarioId,
            'tipo' => $tipo,
            'cantidad' => $cantidad,
            'saldo_anterior' => $saldoAnterior,
            'saldo_nuevo' => $saldoNuevo,
            'referencia_id' => $referenciaId,
            'referencia_tipo' => $referenciaTipo,
            'motivo' => $motivo,
            'usuario_id' => Auth::id()
        ]);
    }
}

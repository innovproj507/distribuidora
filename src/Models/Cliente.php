<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class Cliente extends Model
{
    protected string $table = 'clientes';
    protected string $primaryKey = 'cliente_id';
    protected array $fillable = [
        'empresa_id', 'codigo', 'nombre', 'tipo', 'ruc', 'dv',
        'direccion', 'telefono', 'email', 'limite_credito', 'saldo',
        'cupo_credito', 'saldo_pendiente', 'dias_credito', 
        'itbms', 'estado', 'vendedor_id', 'fecha_registro'
    ];

    /**
     * Obtener direcciones del cliente
     */
    public function direcciones(): array
    {
        return DireccionCliente::where('cliente_id', $this->cliente_id);
    }

    /**
     * Obtener estadísticas
     */
    public static function getStats(string $where = '', array $params = []): array
    {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN estado = 'activo' THEN 1 ELSE 0 END) as activos,
                    SUM(CASE WHEN estado = 'inactivo' THEN 1 ELSE 0 END) as inactivos,
                    SUM(saldo_pendiente) as saldo_total
                FROM clientes";
        if ($where) $sql .= " WHERE {$where}";

        return Database::query($sql, $params)->fetch();
    }
}

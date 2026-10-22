<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class MantSoftware extends Model
{
    protected string $table = 'mant_software';
    protected string $primaryKey = 'software_id';
    protected array $fillable = [
        'empresa_id', 'nombre', 'version', 'proveedor', 'tipo',
        'servidor', 'fecha_instalacion', 'fecha_vencimiento_licencia',
        'contacto_soporte', 'notas', 'estado', 'fecha_registro',
    ];

    public static function getStats(int $empresaId): array
    {
        return Database::query(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'activo' THEN 1 ELSE 0 END) as activos,
                SUM(CASE WHEN estado = 'inactivo' THEN 1 ELSE 0 END) as inactivos,
                SUM(CASE WHEN fecha_vencimiento_licencia IS NOT NULL
                          AND fecha_vencimiento_licencia >= CURDATE()
                          AND fecha_vencimiento_licencia <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                     THEN 1 ELSE 0 END) as licencias_por_vencer
             FROM mant_software
             WHERE empresa_id = ?",
            [$empresaId]
        )->fetch();
    }
}

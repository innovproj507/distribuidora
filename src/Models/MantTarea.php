<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class MantTarea extends Model
{
    protected string $table = 'mant_tareas';
    protected string $primaryKey = 'tarea_id';
    protected array $fillable = [
        'software_id', 'empresa_id', 'nombre', 'descripcion',
        'frecuencia', 'prioridad', 'responsable', 'duracion_estimada',
        'activa', 'proxima_ejecucion', 'ultima_ejecucion', 'fecha_registro',
    ];

    public static function getStats(int $empresaId): array
    {
        return Database::query(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN activa = 1 THEN 1 ELSE 0 END) as activas,
                SUM(CASE WHEN activa = 1 AND proxima_ejecucion IS NOT NULL
                          AND proxima_ejecucion < CURDATE()
                     THEN 1 ELSE 0 END) as vencidas,
                SUM(CASE WHEN activa = 1 AND proxima_ejecucion IS NOT NULL
                          AND proxima_ejecucion >= CURDATE()
                          AND proxima_ejecucion <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                     THEN 1 ELSE 0 END) as proximas_semana
             FROM mant_tareas
             WHERE empresa_id = ?",
            [$empresaId]
        )->fetch();
    }
}

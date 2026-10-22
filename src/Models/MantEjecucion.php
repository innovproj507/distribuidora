<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class MantEjecucion extends Model
{
    protected string $table = 'mant_ejecuciones';
    protected string $primaryKey = 'ejecucion_id';
    protected array $fillable = [
        'tarea_id', 'empresa_id', 'usuario_id',
        'fecha_ejecucion', 'duracion_real', 'estado', 'notas', 'fecha_registro',
    ];
}

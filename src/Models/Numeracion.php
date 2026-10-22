<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Numeracion extends Model
{
    protected string $table = 'numeraciones';
    protected string $primaryKey = 'numeracion_id';
    protected array $fillable = [
        'empresa_id', 'tipo', 'prefijo', 'separador', 'digitos', 'reinicio',
    ];
}

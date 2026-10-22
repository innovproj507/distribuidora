<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class TrasladoDetalle extends Model
{
    protected string $table = 'traslados_detalle';
    protected string $primaryKey = 'detalle_id';
    protected array $fillable = [
        'traslado_id', 'producto_id', 'cantidad'
    ];
}

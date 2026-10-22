<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class CompraDetalle extends Model
{
    protected string $table = 'compras_detalle';
    protected string $primaryKey = 'detalle_id';
    protected array $fillable = [
        'compra_id', 'producto_id', 'cantidad', 'cantidad_recibida',
        'costo', 'itbms', 'total_linea',
        'numero_lote', 'fecha_vencimiento', 'fecha_fabricacion'
    ];
}

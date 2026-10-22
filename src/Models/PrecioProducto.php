<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class PrecioProducto extends Model
{
    protected string $table = 'precios_productos';
    protected string $primaryKey = 'precio_id';
    protected array $fillable = [
        'producto_id', 'tipo_precio', 'precio', 'fecha_inicio', 'fecha_fin'
    ];
}

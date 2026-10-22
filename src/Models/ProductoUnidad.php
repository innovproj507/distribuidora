<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class ProductoUnidad extends Model
{
    protected string $table = 'productos_unidades';
    protected string $primaryKey = 'unidad_id';
    protected array $fillable = [
        'producto_id', 'nombre', 'abreviatura', 'factor_conversion',
        'precio_a', 'precio_b', 'codigo_barras'
    ];

    /**
     * Obtener todas las unidades de un producto
     */
    public static function searchByProducto(int $productoId): array
    {
        return Database::query(
            "SELECT * FROM productos_unidades WHERE producto_id = ?",
            [$productoId]
        )->fetchAll();
    }
}

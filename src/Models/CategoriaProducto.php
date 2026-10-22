<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class CategoriaProducto extends Model
{
    protected string $table = 'categorias_productos';
    protected string $primaryKey = 'categoria_id';
    protected array $fillable = [
        'empresa_id', 'nombre', 'padre_id', 'descripcion', 'nivel', 'ruta', 'imagen'
    ];

    /**
     * Obtener subcategorías
     */
    public function subcategorias(): array
    {
        return self::where('padre_id', $this->categoria_id);
    }

    /**
     * Obtener cantidad de productos
     */
    public function productCount(): int
    {
        return Producto::count('categoria_id = ?', [$this->categoria_id]);
    }
}

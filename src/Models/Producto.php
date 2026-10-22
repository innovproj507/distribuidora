<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class Producto extends Model
{
    protected string $table = 'productos';
    protected string $primaryKey = 'producto_id';
    protected array $fillable = [
        'empresa_id', 'codigo', 'nombre', 'descripcion', 'categoria_id',
        'marca', 'codigo_barras', 'stock_minimo', 'supplier_part_no',
        'proveedor_id', 'costo', 'itbms', 'estado', 'imagen_principal',
        'fecha_creacion', 'fecha_modificacion', 'maneja_lotes'
    ];

    /**
     * Obtener stock total del producto
     */
    public function stockTotal(): float
    {
        $row = Database::query(
            "SELECT COALESCE(SUM(existencia), 0) as total FROM inventario WHERE producto_id = ?",
            [$this->producto_id]
        )->fetch();
        return (float) $row['total'];
    }

    /**
     * Obtener precios del producto
     */
    public function precios(): array
    {
        return PrecioProducto::where('producto_id', $this->producto_id);
    }

    /**
     * Obtener unidades de medida auxiliares
     */
    public function unidades(): array
    {
        return Database::query(
            "SELECT * FROM productos_unidades WHERE producto_id = ?",
            [$this->producto_id]
        )->fetchAll();
    }

    /**
     * Obtener inventario por depósito
     */
    public function inventario(): array
    {
        return Inventario::where('producto_id', $this->producto_id);
    }

    /**
     * Obtener categoría
     */
    public function categoria(): ?CategoriaProducto
    {
        return $this->categoria_id ? CategoriaProducto::find((int) $this->categoria_id) : null;
    }

    /**
     * Obtener proveedor
     */
    public function proveedor(): ?Proveedor
    {
        return $this->proveedor_id ? Proveedor::find((int) $this->proveedor_id) : null;
    }

    /**
     * Generar código de barras aleatorio
     */
    public static function generarCodigoBarras(): string
    {
        $digits = '';
        for ($i = 0; $i < 13; $i++) {
            $digits .= random_int(0, 9);
        }
        return $digits;
    }
}

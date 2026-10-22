<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class VentaDetalle extends Model
{
    protected string $table = 'ventas_detalle';
    protected string $primaryKey = 'detalle_id';
    protected array $fillable = [
        'venta_id', 'producto_id', 'cantidad', 'precio', 'costo',
        'itbms', 'descuento', 'total_linea', 'deposito_id', 'lote_id'
    ];

    /**
     * Calcular total de línea antes de guardar
     */
    public function calcularTotalLinea(): float
    {
        return ((float) $this->cantidad * (float) $this->precio)
             - (float) $this->descuento
             + (float) $this->itbms;
    }
}

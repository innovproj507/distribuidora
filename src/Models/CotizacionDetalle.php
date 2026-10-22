<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class CotizacionDetalle extends Model
{
    protected string $table = 'cotizaciones_detalle';
    protected string $primaryKey = 'detalle_id';
    protected array $fillable = [
        'cotizacion_id', 'producto_id', 'cantidad', 'precio',
        'descuento', 'itbms', 'total_linea'
    ];

    public function calcularTotalLinea(): float
    {
        return ((float) $this->cantidad * (float) $this->precio)
             - (float) $this->descuento
             + (float) $this->itbms;
    }
}

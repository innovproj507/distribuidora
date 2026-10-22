<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class Venta extends Model
{
    protected string $table = 'ventas';
    protected string $primaryKey = 'venta_id';
    protected array $fillable = [
        'sucursal_id', 'empresa_id', 'numero_factura', 'cliente_id', 'vendedor_id',
        'subtotal', 'descuento', 'itbms', 'total', 'costo',
        'forma_pago', 'estado', 'notas', 'ip', 'cotizacion_origen_id', 'fecha'
    ];

    /**
     * Generar número de factura
     */
    public static function generarNumeroFactura(): string
    {
        return \App\Services\NumeracionService::siguiente('factura');
    }

    /**
     * Obtener detalles de la venta
     */
    public function detalles(): array
    {
        return VentaDetalle::where('venta_id', $this->venta_id);
    }

    /**
     * Recalcular totales
     */
    public function calcularTotales(): void
    {
        $detalles = $this->detalles();
        $subtotal = 0;
        foreach ($detalles as $d) {
            $subtotal += (float) $d->total_linea;
        }
        $itbmsRate = (float) ($_ENV['ITBMS_RATE'] ?? 0.07);
        $this->update([
            'subtotal' => $subtotal,
            'itbms' => $subtotal * $itbmsRate,
            'total' => $subtotal + ($subtotal * $itbmsRate) - (float) $this->descuento,
        ]);
    }
}

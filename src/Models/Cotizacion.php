<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class Cotizacion extends Model
{
    protected string $table = 'cotizaciones';
    protected string $primaryKey = 'cotizacion_id';
    protected array $fillable = [
        'sucursal_id', 'empresa_id', 'numero', 'token', 'fecha_vencimiento',
        'cliente_id', 'vendedor_id', 'subtotal', 'descuento',
        'itbms', 'total', 'estado', 'notas', 'fecha'
    ];

    /**
     * Generar número de cotización
     */
    public static function generarNumeroCotizacion(): string
    {
        return \App\Services\NumeracionService::siguiente('cotizacion');
    }

    /**
     * Obtener detalles de la cotización
     */
    public function detalles(): array
    {
        return CotizacionDetalle::where('cotizacion_id', $this->cotizacion_id);
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

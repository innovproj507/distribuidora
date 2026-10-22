<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Compra extends Model
{
    protected string $table = 'compras';
    protected string $primaryKey = 'compra_id';
    protected array $fillable = [
        'proveedor_id', 'sucursal_id', 'empresa_id', 'deposito_id', 
        'numero_factura', 'numero_factura_proveedor', 'monto_subtotal', 'monto_itbms', 'monto_total', 
        'estado', 'notas', 'usuario_id', 'fecha_compra', 'fecha_recepcion'
    ];

    /**
     * Obtener detalles de la compra
     */
    public function detalles(): array
    {
        return CompraDetalle::where('compra_id', $this->compra_id);
    }
}

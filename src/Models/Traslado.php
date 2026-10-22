<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Traslado extends Model
{
    protected string $table = 'traslados';
    protected string $primaryKey = 'traslado_id';
    protected array $fillable = [
        'deposito_origen_id', 'deposito_destino_id', 
        'usuario_envia_id', 'usuario_recibe_id', 
        'estado', 'fecha_envio', 'fecha_recepcion', 'notas'
    ];

    /**
     * Obtener detalles del traslado
     */
    public function detalles(): array
    {
        return TrasladoDetalle::where('traslado_id', $this->traslado_id);
    }
}

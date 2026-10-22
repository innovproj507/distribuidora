<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Inventario extends Model
{
    protected string $table = 'inventario';
    protected string $primaryKey = 'inventario_id';
    protected array $fillable = [
        'producto_id', 'deposito_id', 'existencia', 'minimo', 'maximo',
        'ubicacion', 'costo_promedio', 'ultimo_costo', 'fecha_actualizacion_costo'
    ];

    /**
     * Obtener estado del stock
     */
    public function estadoStock(): string
    {
        $existencia = (float) $this->existencia;
        $minimo = (float) $this->minimo;

        if ($existencia <= 0) return 'agotado';
        if ($existencia <= $minimo) return 'bajo';
        return 'normal';
    }
}

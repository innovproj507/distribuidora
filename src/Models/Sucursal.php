<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Sucursal extends Model
{
    protected string $table = 'branches';
    protected string $primaryKey = 'sucursal_id';
    protected array $fillable = [
        'empresa_id', 'codigo', 'nombre', 'direccion', 'telefono',
        'email', 'es_principal', 'activa'
    ];
}

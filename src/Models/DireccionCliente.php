<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class DireccionCliente extends Model
{
    protected string $table = 'direcciones_cliente';
    protected string $primaryKey = 'direccion_id';
    protected array $fillable = [
        'cliente_id', 'direccion', 'telefono', 'celular', 'email', 'principal'
    ];
}

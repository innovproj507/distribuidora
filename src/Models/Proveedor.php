<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Proveedor extends Model
{
    protected string $table = 'proveedores';
    protected string $primaryKey = 'proveedor_id';
    protected array $fillable = [
        'empresa_id', 'codigo', 'nombre', 'ruc', 'dv', 'direccion',
        'telefono', 'email', 'contacto', 'estado', 'fecha_registro'
    ];
}

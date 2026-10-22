<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Deposito extends Model
{
    protected string $table = 'depositos';
    protected string $primaryKey = 'deposito_id';
    protected array $fillable = [
        'sucursal_id', 'codigo', 'nombre', 'descripcion', 
        'es_principal', 'estado'
    ];
}

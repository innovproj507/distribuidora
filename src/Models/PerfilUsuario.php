<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class PerfilUsuario extends Model
{
    protected string $table = 'user_profiles';
    protected string $primaryKey = 'perfil_id';
    protected array $fillable = [
        'user_id', 'empresa_id', 'sucursal_actual_id', 'cargo', 'foto'
    ];
}

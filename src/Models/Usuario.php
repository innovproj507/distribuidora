<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Usuario extends Model
{
    protected string $table = 'users';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'username', 'password', 'first_name', 'last_name', 'email',
        'is_active', 'is_staff', 'is_superuser'
    ];

    public function getFullName(): string
    {
        $name = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
        return $name ?: $this->username;
    }
}

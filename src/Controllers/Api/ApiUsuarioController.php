<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\Usuario;
use App\Core\Database;

/**
 * ApiUsuarioController - Endpoints para gestión de usuarios
 */
class ApiUsuarioController extends ApiController
{
    /**
     * Detalle de un usuario
     */
    public function detalle(int $id): void
    {
        if (!$this->user['is_staff']) {
            $this->errorResponse('No tiene permisos para ver este usuario', 403);
            return;
        }

        $usuario = Database::query(
            "SELECT id, username, first_name, last_name, email, is_active, is_staff, date_joined FROM users WHERE id = ?",
            [$id]
        )->fetch();

        if (!$usuario) {
            $this->notFound('Usuario no encontrado');
            return;
        }

        $this->successResponse($usuario);
    }

    /**
     * Crear nuevo usuario
     */
    public function guardar(): void
    {
        if (!$this->user['is_staff']) {
            $this->errorResponse('No tiene permisos para crear usuarios', 403);
            return;
        }

        $data = $this->getInputData();

        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            $this->errorResponse('Username, email y password son obligatorios');
            return;
        }

        // Verificar si el username ya existe
        $existe = Database::query("SELECT id FROM users WHERE username = ?", [$data['username']])->fetch();
        if ($existe) {
            $this->errorResponse('El nombre de usuario ya está en uso');
            return;
        }

        // Hash para Django (aunque simplificado aquí para el ejemplo, debería ser compatible)
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

        Database::query(
            "INSERT INTO users (username, first_name, last_name, email, password, is_active, is_staff, is_superuser, date_joined) 
             VALUES (?, ?, ?, ?, ?, 1, ?, 0, NOW())",
            [
                $data['username'], 
                $data['first_name'] ?? '', 
                $data['last_name'] ?? '', 
                $data['email'], 
                $passwordHash, 
                $data['is_staff'] ?? 0
            ]
        );

        $usuarioId = (int) Database::lastInsertId();

        $this->successResponse(['usuario_id' => $usuarioId], 'Usuario creado con éxito', 201);
    }

    /**
     * Actualizar usuario
     */
    public function actualizar(int $id): void
    {
        if (!$this->user['is_staff'] && $this->user_id !== $id) {
            $this->errorResponse('No tiene permisos para editar este usuario', 403);
            return;
        }

        $data = $this->getInputData();

        $updates = ["username = ?", "first_name = ?", "last_name = ?", "email = ?"];
        $params = [
            $data['username'] ?? $this->user['username'],
            $data['first_name'] ?? $this->user['first_name'],
            $data['last_name'] ?? $this->user['last_name'],
            $data['email'] ?? $this->user['email']
        ];

        if (!empty($data['password'])) {
            $updates[] = "password = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if (isset($data['is_staff']) && $this->user['is_staff']) {
            $updates[] = "is_staff = ?";
            $params[] = $data['is_staff'];
        }

        $params[] = $id;
        $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
        
        $success = Database::query($sql, $params);

        if ($success) {
            $this->successResponse([], 'Usuario actualizado correctamente');
        } else {
            $this->errorResponse('Error al actualizar el usuario');
        }
    }

    /**
     * Perfil del usuario actual
     */
    public function perfil(): void
    {
        $this->successResponse($this->user);
    }
}

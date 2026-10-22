<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Auth;

/**
 * ApiAuthController - Autenticación para la API REST (empresa única)
 */
class ApiAuthController extends Controller
{
    /**
     * Login y generación de API Token
     * POST /api/v1/login
     */
    public function login(): void
    {
        $data     = $this->request->json();
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';

        if (!$username || !$password) {
            $this->json(['status' => 'error', 'message' => 'Usuario y contraseña son requeridos'], 400);
            return;
        }

        $user = Database::query(
            "SELECT u.*, p.empresa_id, p.sucursal_actual_id AS sucursal_id, p.cargo
             FROM users u
             LEFT JOIN user_profiles p ON u.id = p.user_id
             WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1
             LIMIT 1",
            [$username, $username]
        )->fetch();

        if (!$user || !Auth::verifyPassword($password, $user['password'])) {
            $this->json(['status' => 'error', 'message' => 'Credenciales inválidas'], 401);
            return;
        }

        // Generar nuevo token en cada login
        $token = bin2hex(random_bytes(32));
        Database::query("UPDATE users SET api_token = ? WHERE id = ?", [$token, $user['id']]);

        $this->json([
            'status'  => 'success',
            'message' => 'Login exitoso',
            'data'    => [
                'token' => $token,
                'user'  => [
                    'id'           => (int) $user['id'],
                    'username'     => $user['username'],
                    'first_name'   => $user['first_name'],
                    'last_name'    => $user['last_name'],
                    'fullname'     => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['username'],
                    'empresa_id'   => $user['empresa_id']  ? (int) $user['empresa_id']  : null,
                    'sucursal_id'  => $user['sucursal_id'] ? (int) $user['sucursal_id'] : null,
                    'cargo'        => $user['cargo']       ?? null,
                    'is_superuser' => (bool) ($user['is_superuser'] ?? false),
                    'is_staff'     => (bool) ($user['is_staff']     ?? false),
                ],
            ]
        ]);
    }

    /**
     * Logout - revocar token
     * POST /api/v1/logout
     */
    public function logout(): void
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            Database::query("UPDATE users SET api_token = NULL WHERE api_token = ?", [$matches[1]]);
        }

        $this->json(['status' => 'success', 'message' => 'Token revocado correctamente']);
    }
}

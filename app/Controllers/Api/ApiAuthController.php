<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Filters\ApiTokenFilter;
use App\Libraries\AuditService;
use App\Libraries\Auth;
use App\Libraries\Db;
use App\Libraries\LimiteIntentos;
use App\Libraries\TotpService;

/** Login/logout de la API (token Bearer). */
class ApiAuthController extends ApiController
{
    private const ROLES_2FA = ['gerente', 'auditor', 'superadmin'];

    private const MAX_INTENTOS = 10;

    /** GET /api/v1 — estado (público). */
    public function estado()
    {
        return $this->ok(['version' => '1.0.0', 'nombre' => env('APP_NAME', 'Sistema POS')], 'API POS v1');
    }

    /**
     * POST /api/v1/login {username (o email), password, codigo_2fa?}
     * Los roles con 2FA activo deben enviar codigo_2fa (la API anterior se lo saltaba).
     * Máximo 10 intentos fallidos por IP cada 15 minutos.
     */
    public function login()
    {
        $clave = LimiteIntentos::clave('api_login_ip', $this->request->getIPAddress());
        if (LimiteIntentos::bloqueado($clave, self::MAX_INTENTOS)) {
            return $this->fallo('Demasiados intentos fallidos. Espera 15 minutos.', 429);
        }

        $data     = $this->entrada();
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($username === '' || $password === '') {
            return $this->fallo('Usuario y contraseña son requeridos.');
        }

        $user = Db::row(
            "SELECT u.*, p.empresa_id, p.sucursal_actual_id AS sucursal_id, p.cargo
             FROM users u LEFT JOIN user_profiles p ON u.id = p.user_id
             WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1
             LIMIT 1",
            [$username, $username]
        );

        if (!$user || !Auth::verifyPassword($password, $user['password'])) {
            LimiteIntentos::fallo($clave);
            return $this->fallo('Credenciales inválidas.', 401);
        }

        if (in_array($user['rol'], self::ROLES_2FA, true) && $user['totp_habilitado']) {
            $codigo = trim((string) ($data['codigo_2fa'] ?? ''));
            if ($codigo === '') {
                return $this->fallo('Este usuario requiere el código de verificación en dos pasos (codigo_2fa).', 401, ['requiere_2fa' => true]);
            }
            // Mismo contador de 2FA por usuario que la web (5 fallos / 15 min)
            $clave2fa = LimiteIntentos::clave('2fa_usuario', (string) $user['id']);
            if (LimiteIntentos::bloqueado($clave2fa, 5)) {
                return $this->fallo('Demasiados códigos incorrectos. Espera 15 minutos.', 429);
            }
            if (!TotpService::verify((string) $user['totp_secret'], $codigo)) {
                LimiteIntentos::fallo($clave);
                LimiteIntentos::fallo($clave2fa);
                return $this->fallo('Código de verificación incorrecto.', 401, ['requiere_2fa' => true]);
            }
            LimiteIntentos::limpiar($clave2fa);
        }

        LimiteIntentos::limpiar($clave);

        // Token nuevo en cada login (invalida el anterior), mismo formato que el sistema anterior
        $token = bin2hex(random_bytes(32));
        Db::update('users', ['api_token' => $token], ['id' => $user['id']]);

        Auth::usarUsuarioApi(['id' => (int) $user['id'], 'user_fullname' => $user['username'], 'rol' => $user['rol'], 'empresa_id' => $user['empresa_id']]);
        AuditService::log('auth.api_login', "Login API: {$user['username']}", (int) $user['id'], 'usuario');

        return $this->ok([
            'token' => $token,
            'user'  => [
                'id'           => (int) $user['id'],
                'username'     => $user['username'],
                'first_name'   => $user['first_name'],
                'last_name'    => $user['last_name'],
                'fullname'     => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['username'],
                'rol'          => $user['rol'],
                'empresa_id'   => $user['empresa_id'] ? (int) $user['empresa_id'] : null,
                'sucursal_id'  => $user['sucursal_id'] ? (int) $user['sucursal_id'] : null,
                'cargo'        => $user['cargo'] ?? null,
                'is_superuser' => (bool) $user['is_superuser'],
                'is_staff'     => (bool) $user['is_staff'],
            ],
        ], 'Login exitoso');
    }

    /** POST /api/v1/logout — revoca el token enviado. */
    public function logout()
    {
        $token = ApiTokenFilter::token($this->request);
        if ($token !== null) {
            Db::query("UPDATE users SET api_token = NULL WHERE api_token = ?", [$token]);
        }
        return $this->ok([], 'Token revocado correctamente');
    }
}

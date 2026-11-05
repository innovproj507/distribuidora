<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Autenticación y permisos por rol.
 *
 * Misma API estática que el sistema anterior (Auth::check(), Auth::can(), ...) para que
 * vistas y controladores se porten sin cambios. Contraseñas compatibles con Django
 * (pbkdf2_sha256) y bcrypt; 2FA TOTP para los roles de ROLES_2FA.
 */
final class Auth
{
    /** Roles que deben usar 2FA. */
    private const ROLES_2FA = ['gerente', 'auditor', 'superadmin'];

    private static ?array $permisos = null;

    /**
     * Verifica credenciales. Si el rol requiere 2FA y el usuario lo tiene configurado, deja
     * los datos en sesión como pendientes y retorna true sin completar el login: el caller
     * debe revisar Auth::needs2fa().
     */
    public static function attempt(string $username, string $password): bool
    {
        $user = Db::row(
            "SELECT id, username, password, first_name, last_name, email,
                    is_active, is_staff, is_superuser, rol, totp_habilitado, totp_secret
             FROM users WHERE username = ?",
            [$username]
        );

        if (!$user || !$user['is_active']) return false;
        if (!self::verifyPassword($password, $user['password'])) return false;

        $rol = $user['rol'] ?? 'cajero';

        if (in_array($rol, self::ROLES_2FA, true) && $user['totp_habilitado']) {
            session()->set('2fa_pending', [
                'user_id'       => $user['id'],
                'username'      => $user['username'],
                'user_fullname' => trim($user['first_name'] . ' ' . $user['last_name']) ?: $user['username'],
                'is_superuser'  => (bool) $user['is_superuser'],
                'is_staff'      => (bool) $user['is_staff'],
                'rol'           => $rol,
                'totp_secret'   => $user['totp_secret'],
            ]);
            return true;
        }

        self::completeLogin($user);
        return true;
    }

    /** Completa la sesión (tras verificar 2FA o en login sin 2FA). */
    public static function completeLogin(array $user): void
    {
        $session = session();
        $session->regenerate(true); // evita session fixation
        $session->remove('2fa_pending');
        $session->set([
            'user_id'       => (int) $user['id'],
            'username'      => $user['username'],
            'user_fullname' => $user['user_fullname'] ?? (trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['username']),
            'is_superuser'  => (bool) $user['is_superuser'],
            'is_staff'      => (bool) ($user['is_staff'] ?? false),
            'rol'           => $user['rol'] ?? 'cajero',
        ]);
        AuditService::log('auth.login', "Inicio de sesión: {$user['username']}");
    }

    public static function needs2fa(): bool
    {
        return session()->has('2fa_pending');
    }

    public static function pending2faData(): ?array
    {
        return session()->get('2fa_pending');
    }

    // ── Contraseñas ──────────────────────────────────────────────────────────

    /**
     * Política de contraseñas para todo alta o cambio (web y API). Devuelve el mensaje de
     * error o null si es válida: mínimo 8 caracteres, al menos una letra y un número, y
     * distinta del nombre de usuario.
     */
    public static function errorPassword(string $password, string $username = ''): ?string
    {
        return match (true) {
            mb_strlen($password) < 8                                       => 'La contraseña debe tener al menos 8 caracteres.',
            mb_strlen($password) > 128                                     => 'La contraseña no puede pasar de 128 caracteres.',
            !preg_match('/\pL/u', $password) || !preg_match('/\d/', $password) => 'La contraseña debe tener al menos una letra y un número.',
            $username !== '' && mb_strtolower($password) === mb_strtolower($username) => 'La contraseña no puede ser igual al nombre de usuario.',
            default                                                        => null,
        };
    }

    public static function verifyPassword(string $password, string $encoded): bool
    {
        if (str_starts_with($encoded, '$2y$') || str_starts_with($encoded, '$2b$')) {
            return password_verify($password, $encoded);
        }
        return self::verifyDjangoPassword($password, $encoded);
    }

    public static function verifyDjangoPassword(string $password, string $encoded): bool
    {
        $parts = explode('$', $encoded);
        if (count($parts) !== 4) return false;
        [$algorithm, $iterations, $salt, $hash] = $parts;
        if ($algorithm !== 'pbkdf2_sha256') return false;
        $derivedKey = hash_pbkdf2('sha256', $password, $salt, (int) $iterations, 32, true);
        return hash_equals($hash, base64_encode($derivedKey));
    }

    // ── Sesión ───────────────────────────────────────────────────────────────

    public static function logout(): void
    {
        AuditService::log('auth.logout', 'Cierre de sesión: ' . self::name());
        session()->destroy();
    }

    // ── Modo API (token Bearer, sin sesión PHP) ──────────────────────────────

    /** Usuario autenticado por token en la petición actual (lo fija ApiTokenFilter). */
    private static ?array $api = null;

    /**
     * Activa el modo API: los datos del usuario salen de aquí en vez de la sesión.
     * $usuario: id, username, user_fullname, rol, is_superuser, is_staff, empresa_id, sucursal_id, deposito_id
     */
    public static function usarUsuarioApi(array $usuario): void
    {
        self::$api = $usuario;
    }

    public static function esApi(): bool
    {
        return self::$api !== null;
    }

    /** Empresa/sucursal/depósito del usuario API (null fuera del modo API). */
    public static function contextoApi(): ?array
    {
        return self::$api;
    }

    /** Lee un dato del usuario: del token en modo API, de la sesión en la web. */
    private static function dato(string $clave): mixed
    {
        return self::$api !== null ? (self::$api[$clave] ?? null) : session()->get($clave);
    }

    public static function check(): bool
    {
        return self::$api !== null || session()->has('user_id');
    }

    public static function id(): ?int
    {
        $id = self::$api !== null ? self::$api['id'] : session()->get('user_id');
        return $id !== null ? (int) $id : null;
    }

    public static function name(): string
    {
        return self::dato('user_fullname') ?? 'Usuario';
    }

    public static function user(): ?array
    {
        if (!self::check()) return null;
        return Db::row(
            "SELECT id, username, first_name, last_name, email,
                    is_active, is_staff, is_superuser, rol, totp_habilitado, totp_secret
             FROM users WHERE id = ?",
            [self::id()]
        );
    }

    // ── Roles y permisos ─────────────────────────────────────────────────────

    public static function rol(): string
    {
        return self::dato('rol') ?? 'cajero';
    }

    public static function isSuperuser(): bool
    {
        return (bool) self::dato('is_superuser');
    }

    public static function isStaff(): bool
    {
        return (bool) self::dato('is_staff');
    }

    public static function hasRole(string|array $roles): bool
    {
        $rol = self::rol();
        if ($rol === 'superadmin') return true;
        return in_array($rol, (array) $roles, true);
    }

    /** Permiso 'modulo.accion' según la matriz de app/Config/Permisos.php. */
    public static function can(string $permiso): bool
    {
        $rol = self::rol();
        if ($rol === 'superadmin') return true;

        self::$permisos ??= config('Permisos')->roles;
        return in_array($permiso, self::$permisos[$rol] ?? [], true);
    }

    public static function cannot(string $permiso): bool
    {
        return !self::can($permiso);
    }

    /**
     * IDs de depósitos a los que el usuario puede acceder. Vacío = sin restricción.
     */
    public static function depositosPermitidos(): array
    {
        if (self::isSuperuser() || self::rol() === 'superadmin') {
            return [];
        }
        $rows = Db::all("SELECT deposito_id FROM user_depositos_permitidos WHERE user_id = ?", [self::id()]);
        return array_map('intval', array_column($rows, 'deposito_id'));
    }
}

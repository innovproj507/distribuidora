<?php
declare(strict_types=1);

namespace App\Core;

class Auth
{
    private static ?array $permissions = null;

    /** Roles que deben usar 2FA. */
    private const ROLES_2FA = ['gerente', 'auditor', 'superadmin'];

    /**
     * Verifica credenciales.
     * Si el rol requiere 2FA y el usuario lo tiene configurado, almacena
     * los datos en sesión como 'pendiente' y retorna true sin completar la sesión.
     * El caller debe revisar Auth::needs2fa() para saber si debe redirigir.
     */
    public static function attempt(string $username, string $password): bool
    {
        $stmt = Database::query(
            "SELECT id, username, password, first_name, last_name, email,
                    is_active, is_staff, is_superuser, rol, totp_habilitado, totp_secret
             FROM users WHERE username = ?",
            [$username]
        );

        $user = $stmt->fetch();
        if (!$user || !$user['is_active']) return false;
        if (!self::verifyPassword($password, $user['password'])) return false;

        $rol     = $user['rol'] ?? 'cajero';
        $session = Application::getInstance()->getSession();

        // 2FA requerido y configurado → sesión pendiente
        if (in_array($rol, self::ROLES_2FA, true) && $user['totp_habilitado']) {
            $session->set('2fa_pending', [
                'user_id'      => $user['id'],
                'username'     => $user['username'],
                'user_fullname'=> trim($user['first_name'] . ' ' . $user['last_name']) ?: $user['username'],
                'is_superuser' => (bool) $user['is_superuser'],
                'is_staff'     => (bool) $user['is_staff'],
                'rol'          => $rol,
                'totp_secret'  => $user['totp_secret'],
            ]);
            return true;
        }

        // Login completo
        self::completeLogin($user, $session);

        return true;
    }

    /** Completa la sesión con los datos del usuario (llamado tras verificar 2FA o en login sin 2FA). */
    public static function completeLogin(array $user, $session = null): void
    {
        $session ??= Application::getInstance()->getSession();
        // Regenerar session ID para prevenir session fixation
        session_regenerate_id(true);
        $session->remove('2fa_pending');
        $session->set('user_id',       $user['id']);
        $session->set('username',      $user['username']);
        $session->set('user_fullname', $user['user_fullname'] ?? (trim($user['first_name'] . ' ' . $user['last_name']) ?: $user['username']));
        $session->set('is_superuser',  (bool) $user['is_superuser']);
        $session->set('is_staff',      (bool) ($user['is_staff'] ?? false));
        $session->set('rol',           $user['rol'] ?? 'cajero');
        \App\Services\AuditService::log('auth.login', "Inicio de sesión: {$user['username']}");
    }

    /** True si hay una sesión de 2FA pendiente de verificar. */
    public static function needs2fa(): bool
    {
        return Application::getInstance()->getSession()->has('2fa_pending');
    }

    /** True si el usuario autenticado necesita configurar 2FA antes de continuar. */
    public static function needs2faSetup(): bool
    {
        return Application::getInstance()->getSession()->get('2fa_setup_required', false);
    }

    /** Retorna los datos del usuario pendiente de 2FA, o null si no hay ninguno. */
    public static function pending2faData(): ?array
    {
        return Application::getInstance()->getSession()->get('2fa_pending');
    }

    // ── Verificación de password ──────────────────────────────────────────────

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

    // ── Sesión ────────────────────────────────────────────────────────────────

    public static function logout(): void
    {
        \App\Services\AuditService::log('auth.logout', 'Cierre de sesión: ' . self::name());
        Application::getInstance()->getSession()->destroy();
    }

    public static function check(): bool
    {
        return Application::getInstance()->getSession()->has('user_id');
    }

    public static function id(): ?int
    {
        return Application::getInstance()->getSession()->get('user_id');
    }

    public static function name(): string
    {
        return Application::getInstance()->getSession()->get('user_fullname', 'Usuario');
    }

    public static function user(): ?array
    {
        if (!self::check()) return null;
        return Database::query(
            "SELECT id, username, first_name, last_name, email,
                    is_active, is_staff, is_superuser, rol, totp_habilitado, totp_secret
             FROM users WHERE id = ?",
            [self::id()]
        )->fetch() ?: null;
    }

    // ── Roles ─────────────────────────────────────────────────────────────────

    public static function rol(): string
    {
        return Application::getInstance()->getSession()->get('rol', 'cajero');
    }

    public static function isSuperuser(): bool
    {
        return Application::getInstance()->getSession()->get('is_superuser', false);
    }

    public static function isStaff(): bool
    {
        return Application::getInstance()->getSession()->get('is_staff', false);
    }

    /** Verifica si el usuario tiene alguno de los roles indicados. */
    public static function hasRole(string|array $roles): bool
    {
        $rol = self::rol();
        if ($rol === 'superadmin') return true;
        return in_array($rol, (array) $roles, true);
    }

    // ── Permisos ──────────────────────────────────────────────────────────────

    /** Verifica si el usuario tiene un permiso específico. */
    public static function can(string $permiso): bool
    {
        $rol = self::rol();
        if ($rol === 'superadmin') return true;

        $matrix = self::loadPermissions();
        return in_array($permiso, $matrix[$rol] ?? [], true);
    }

    /** Verifica si el usuario NO tiene un permiso. */
    public static function cannot(string $permiso): bool
    {
        return !self::can($permiso);
    }

    /**
     * IDs de depósitos a los que el usuario actual puede acceder.
     * Array vacío = sin restricciones (acceso a todos).
     * Superadmin siempre retorna vacío (sin restricciones).
     */
    public static function depositosPermitidos(): array
    {
        if (self::isSuperuser() || self::rol() === 'superadmin') {
            return [];
        }
        $rows = Database::query(
            "SELECT deposito_id FROM user_depositos_permitidos WHERE user_id = ?",
            [self::id()]
        )->fetchAll(\PDO::FETCH_COLUMN);
        return array_map('intval', $rows);
    }

    private static function loadPermissions(): array
    {
        if (self::$permissions === null) {
            self::$permissions = require dirname(__DIR__, 2) . '/config/permissions.php';
        }
        return self::$permissions;
    }
}

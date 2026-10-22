<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Session - Gestión de sesiones PHP
 */
class Session
{
    public function __construct()
    {
        // No iniciar sesión automáticamente para peticiones de API con Token Bearer
        // Esto evita bloqueos de sesión (Deadlock) con el frontend
        if (session_status() === PHP_SESSION_NONE) {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $isApiRequest = str_contains($uri, '/api/v1') || str_contains($uri, '/api/');
            $hasToken = isset($_SERVER['HTTP_AUTHORIZATION']) || isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
            
            // Iniciar sesión si no se usa un token de autorización (Bearers)
            // Permitir sesión para llamadas de /api/ desde el navegador
            if (!$hasToken) {
                session_start();
            }
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function destroy(): void
    {
        session_destroy();
        $_SESSION = [];
    }

    /**
     * Mensajes flash (disponibles solo en el siguiente request)
     */
    public function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type][] = $message;
    }

    /**
     * Obtener mensajes flash y limpiarlos
     */
    public function getFlash(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $messages;
    }

    /**
     * Verificar si hay mensajes flash
     */
    public function hasFlash(): bool
    {
        return !empty($_SESSION['_flash']);
    }

    /**
     * Generar token CSRF
     */
    public function csrfToken(): string
    {
        if (!$this->has('_csrf_token')) {
            $this->set('_csrf_token', bin2hex(random_bytes(32)));
        }
        return $this->get('_csrf_token');
    }

    /**
     * Verificar token CSRF
     */
    public function verifyCsrf(string $token): bool
    {
        return hash_equals($this->get('_csrf_token', ''), $token);
    }
}

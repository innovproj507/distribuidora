<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Request - Abstracción del HTTP Request
 */
class Request
{
    private array $get;
    private array $post;
    private array $files;
    private array $server;

    public function __construct()
    {
        $this->get = $_GET;
        $this->post = $_POST;
        $this->files = $_FILES;
        $this->server = $_SERVER;
    }

    /**
     * Obtener método HTTP
     */
    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Obtener URI limpia
     */
    public function uri(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        // Remover query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        // Normalizar: remover prefijos de subdirectorio si están presentes
        if (str_starts_with($uri, '/backend/public')) {
            $uri = substr($uri, 15);
        } elseif (str_starts_with($uri, '/backend')) {
            $uri = substr($uri, 8);
        } elseif (str_starts_with($uri, '/api/v1')) {
            // No remover, el router espera /api/v1
        }

        $uri = '/' . trim($uri, '/');
        return $uri === '/' ? '/' : $uri;
    }

    /**
     * Obtener parámetro GET
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $default;
    }

    /**
     * Obtener parámetro POST
     */
    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    /**
     * Obtener input (GET o POST)
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->get[$key] ?? $default;
    }

    /**
     * Obtener todos los datos POST
     */
    public function all(): array
    {
        return array_merge($this->get, $this->post);
    }

    /**
     * Obtener archivo subido
     */
    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    /**
     * Verificar si tiene archivo
     */
    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) && $this->files[$key]['error'] === UPLOAD_ERR_OK;
    }

    /**
     * Obtener IP del cliente
     */
    public function ip(): string
    {
        return $this->server['HTTP_X_FORWARDED_FOR']
            ?? $this->server['HTTP_CLIENT_IP']
            ?? $this->server['REMOTE_ADDR']
            ?? '0.0.0.0';
    }

    /**
     * Verificar si es AJAX
     */
    public function isAjax(): bool
    {
        return ($this->server['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    /**
     * ¿Es POST?
     */
    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    /**
     * ¿Es GET?
     */
    public function isGet(): bool
    {
        return $this->method() === 'GET';
    }

    /**
     * Obtener el Referer (URL de procedencia)
     */
    public function getReferer(): ?string
    {
        return $this->server['HTTP_REFERER'] ?? null;
    }

    /**
     * Obtener JSON body
     */
    public function json(): array
    {
        $body = file_get_contents('php://input');
        return json_decode($body, true) ?? [];
    }

    /**
     * Obtener variable del servidor
     */
    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }
}

<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Response - Respuestas HTTP
 */
class Response
{
    /**
     * Respuesta JSON
     */
    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Redirección
     */
    public static function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }

    /**
     * Redirigir a la URL anterior
     */
    public static function back(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        self::redirect($referer);
    }

    /**
     * Enviar error HTTP
     */
    public static function error(int $code, string $message = ''): void
    {
        http_response_code($code);
        echo "<h1>Error {$code}</h1>";
        if ($message) echo "<p>{$message}</p>";
        exit;
    }
}

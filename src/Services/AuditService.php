<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

/**
 * Registra eventos de auditoría en audit_log.
 * Uso: AuditService::log('ventas.anular', 'Venta #42 anulada', 42, 'venta');
 */
final class AuditService
{
    public static function log(
        string  $accion,
        string  $descripcion,
        ?int    $referenciaId   = null,
        ?string $referenciaTipo = null,
        array   $datos          = [],
        ?int    $empresaId      = null,
    ): void {
        try {
            $modulo = explode('.', $accion)[0];

            Database::query(
                "INSERT INTO audit_log
                    (usuario_id, username, empresa_id, accion, modulo,
                     descripcion, referencia_id, referencia_tipo, ip, datos)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    Auth::id(),
                    Auth::check() ? Auth::name() : 'sistema',
                    $empresaId ?? self::empresaId(),
                    $accion,
                    $modulo,
                    $descripcion,
                    $referenciaId,
                    $referenciaTipo,
                    self::ip(),
                    !empty($datos) ? json_encode($datos, JSON_UNESCAPED_UNICODE) : null,
                ]
            );
        } catch (\Throwable) {
            // Nunca romper el flujo principal por un error de auditoría
        }
    }

    private static function ip(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
    }

    private static function empresaId(): ?int
    {
        try {
            $app     = \App\Core\Application::getInstance();
            $empresa = $app->getSession()->get('empresa_actual');
            return $empresa ? (int) $empresa['empresa_id'] : null;
        } catch (\Throwable) {
            return null;
        }
    }
}

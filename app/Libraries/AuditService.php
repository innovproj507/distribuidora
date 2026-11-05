<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Registra eventos de auditoría en audit_log.
 * Uso: AuditService::log('ventas.anular', 'Venta #42 anulada', 42, 'venta');
 */
final class AuditService
{
    public static function log(
        string  $accion,
        string  $descripcion,
        ?int    $referenciaId = null,
        ?string $referenciaTipo = null,
        array   $datos = [],
        ?int    $empresaId = null,
    ): void {
        try {
            // En la API no hay sesión: la empresa sale del usuario del token
            $empresaId ??= Auth::esApi()
                ? (Auth::contextoApi()['empresa_id'] ?? null)
                : (session()->get('empresa_actual')['empresa_id'] ?? null);

            Db::query(
                "INSERT INTO audit_log
                    (usuario_id, username, empresa_id, accion, modulo,
                     descripcion, referencia_id, referencia_tipo, ip, datos)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    Auth::id(),
                    Auth::check() ? Auth::name() : 'sistema',
                    $empresaId !== null ? (int) $empresaId : null,
                    $accion,
                    explode('.', $accion)[0],
                    $descripcion,
                    $referenciaId,
                    $referenciaTipo,
                    service('request')->getIPAddress(),
                    !empty($datos) ? json_encode($datos, JSON_UNESCAPED_UNICODE) : null,
                ]
            );
        } catch (\Throwable) {
            // Nunca romper el flujo principal por un error de auditoría
        }
    }
}

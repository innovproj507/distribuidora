<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Contador de intentos fallidos en la caché del servidor (no en la sesión: borrar la cookie
 * no lo reinicia). La ventana arranca con el primer fallo y no se alarga con los siguientes.
 *
 *   $clave = LimiteIntentos::clave('login_ip', $ip);
 *   if (LimiteIntentos::bloqueado($clave, 10)) { ... }
 *   LimiteIntentos::fallo($clave, 900);   // al fallar
 *   LimiteIntentos::limpiar($clave);      // al acertar
 */
final class LimiteIntentos
{
    public const VENTANA_SEG = 900;

    /** Clave de caché segura (la caché de archivos no admite cualquier carácter). */
    public static function clave(string $tipo, string $valor): string
    {
        return 'intentos_' . $tipo . '_' . md5(strtolower($valor));
    }

    public static function bloqueado(string $clave, int $maximo): bool
    {
        return (int) cache($clave) >= $maximo;
    }

    public static function fallo(string $clave, int $ventanaSeg = self::VENTANA_SEG): int
    {
        $fallos = (int) cache($clave) + 1;
        $expira = $fallos === 1 ? null : (cache()->getMetaData($clave)['expire'] ?? null);
        $ttl    = $expira ? max(1, (int) $expira - time()) : $ventanaSeg;
        cache()->save($clave, $fallos, $ttl);
        return $fallos;
    }

    public static function limpiar(string ...$claves): void
    {
        foreach ($claves as $clave) {
            cache()->delete($clave);
        }
    }
}

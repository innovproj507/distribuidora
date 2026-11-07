<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;

/**
 * Atajos estáticos sobre la conexión por defecto de CodeIgniter.
 *
 * El SQL del sistema anterior usa placeholders "?" — el mismo formato de bindings
 * de CI4 — así que las consultas se portan tal cual:
 *   Db::row($sql, $params)   → una fila (array) o null
 *   Db::all($sql, $params)   → lista de filas (array)
 *   Db::value($sql, $params) → primera columna de la primera fila
 */
final class Db
{
    private static bool $zonaFijada = false;

    public static function conn(): BaseConnection
    {
        $conexion = db_connect();

        if (!self::$zonaFijada) {
            // PHP trabaja en la zona de la app (America/Panama) pero MySQL en UTC: NOW(), CURDATE() y las columnas
            // TIMESTAMP (fecha_registro del kardex…) salían 5 h adelantadas respecto a las ventas, y "hoy" cambiaba
            // a las 7 pm. La sesión de MySQL usa el mismo desplazamiento que la app.
            $conexion->simpleQuery("SET time_zone = '" . self::desplazamientoApp() . "'");
            self::$zonaFijada = true;
        }

        return $conexion;
    }

    /** Desplazamiento actual de la zona de la app respecto a UTC, en formato MySQL ("-05:00"). */
    public static function desplazamientoApp(): string
    {
        $zona    = new \DateTimeZone(config('App')->appTimezone);
        $segundos = $zona->getOffset(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $signo   = $segundos < 0 ? '-' : '+';
        $abs     = abs($segundos);

        return sprintf('%s%02d:%02d', $signo, intdiv($abs, 3600), intdiv($abs % 3600, 60));
    }

    /** @return BaseResult|bool */
    public static function query(string $sql, array $params = [])
    {
        return self::conn()->query($sql, $params);
    }

    public static function row(string $sql, array $params = []): ?array
    {
        return self::conn()->query($sql, $params)->getRowArray();
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::conn()->query($sql, $params)->getResultArray();
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $row = self::row($sql, $params);
        return $row ? reset($row) : null;
    }

    /** INSERT con Query Builder; devuelve el id generado. */
    public static function insert(string $table, array $data): int
    {
        self::conn()->table($table)->insert($data);
        return (int) self::conn()->insertID();
    }

    public static function update(string $table, array $data, array $where): void
    {
        self::conn()->table($table)->where($where)->update($data);
    }

    public static function insertId(): int
    {
        return (int) self::conn()->insertID();
    }

    // ── Transacciones (con DBDebug activo los errores lanzan excepción) ──────

    public static function begin(): void
    {
        self::conn()->transBegin();
    }

    public static function commit(): void
    {
        self::conn()->transCommit();
    }

    public static function rollback(): void
    {
        self::conn()->transRollback();
    }
}

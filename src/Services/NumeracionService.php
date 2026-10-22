<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Nomenclatura configurable de documentos y códigos (cotización, factura, cliente, proveedor).
 *
 * Cada tipo tiene: prefijo, separador, cantidad de dígitos del secuencial y una regla de
 * reinicio (nunca / diario / mensual / anual). El número se arma como:
 *
 *   {prefijo}{separador}{fecha si reinicio != nunca}{separador si hay fecha}{secuencial}
 *
 * Por defecto los cuatro son consecutivos simples, sin fecha (reinicio "nunca"):
 *   cotizacion  -> C0001
 *   factura     -> F0001
 *   cliente     -> CLI-0001
 *   proveedor   -> PROV-0001
 */
class NumeracionService
{
    /** Tipos válidos y la tabla/columna donde se busca el último número usado. */
    private const TABLAS = [
        'cotizacion' => ['tabla' => 'cotizaciones', 'columna' => 'numero'],
        'factura'    => ['tabla' => 'ventas',        'columna' => 'numero_factura'],
        'cliente'    => ['tabla' => 'clientes',      'columna' => 'codigo'],
        'proveedor'  => ['tabla' => 'proveedores',   'columna' => 'codigo'],
    ];

    private const REINICIOS_VALIDOS = ['nunca', 'diario', 'mensual', 'anual'];

    /**
     * Genera el siguiente número/código para un tipo, según su configuración.
     * Si no se indica $empresaId, usa la única empresa registrada (sistema de una sola empresa).
     */
    public static function siguiente(string $tipo, ?int $empresaId = null): string
    {
        $empresaId = $empresaId ?? self::empresaPorDefecto();
        $cfg = self::config($tipo, $empresaId);
        [$tabla, $columna] = [self::TABLAS[$tipo]['tabla'], self::TABLAS[$tipo]['columna']];

        $fecha = match ($cfg['reinicio']) {
            'diario'  => date('Ymd'),
            'mensual' => date('Ym'),
            'anual'   => date('Y'),
            default   => '',
        };

        $partes = array_filter([$cfg['prefijo'], $fecha], fn($v) => $v !== '');
        // El separador se aplica entre prefijo/fecha y también antes del secuencial;
        // con separador vacío el resultado queda pegado (ej. "C" + "20260929" + "0001").
        $base = implode($cfg['separador'], $partes) . $cfg['separador'];
        $digitos = max(1, $cfg['digitos']);

        // Se exige que el código tenga exactamente "base + $digitos" caracteres, para no
        // confundir el secuencial con códigos de un formato anterior (ej. con fecha incrustada
        // de cuando el reinicio era diario/mensual/anual y ahora es "nunca", o viceversa).
        $baseEscapada = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $base);
        $patron = $baseEscapada . str_repeat('_', $digitos);

        $row = Database::query(
            "SELECT {$columna} AS c FROM {$tabla}
             WHERE empresa_id = ? AND {$columna} LIKE ? ESCAPE '\\\\' AND LENGTH({$columna}) = ?
             ORDER BY {$columna} DESC LIMIT 1",
            [$empresaId, $patron, strlen($base) + $digitos]
        )->fetch();

        $siguiente = 1;
        if ($row) {
            $sufijo = substr((string) $row['c'], strlen($base));
            if ($sufijo !== '' && ctype_digit($sufijo)) {
                $siguiente = (int) $sufijo + 1;
            }
        }

        return $base . str_pad((string) $siguiente, $digitos, '0', STR_PAD_LEFT);
    }

    /**
     * Devuelve un ejemplo del número que se generaría ahora mismo, sin consultar la BD
     * (para la vista previa en pantalla mientras se edita la configuración).
     */
    public static function ejemplo(array $cfg): string
    {
        $fecha = match ($cfg['reinicio'] ?? 'nunca') {
            'diario'  => date('Ymd'),
            'mensual' => date('Ym'),
            'anual'   => date('Y'),
            default   => '',
        };
        $separador = $cfg['separador'] ?? '';
        $partes = array_filter([$cfg['prefijo'] ?? '', $fecha], fn($v) => $v !== '');
        $base = implode($separador, $partes) . $separador;
        $digitos = max(1, (int) ($cfg['digitos'] ?? 4));
        return $base . str_pad('1', $digitos, '0', STR_PAD_LEFT);
    }

    /** Configuración de un tipo para una empresa; crea el valor por defecto si no existe. */
    public static function config(string $tipo, ?int $empresaId = null): array
    {
        self::validarTipo($tipo);
        $empresaId = $empresaId ?? self::empresaPorDefecto();

        $row = Database::query(
            "SELECT * FROM numeraciones WHERE empresa_id = ? AND tipo = ?",
            [$empresaId, $tipo]
        )->fetch();

        if ($row) {
            return $row;
        }

        $defecto = self::defecto($tipo);
        Database::query(
            "INSERT INTO numeraciones (empresa_id, tipo, prefijo, separador, digitos, reinicio) VALUES (?, ?, ?, ?, ?, ?)",
            [$empresaId, $tipo, $defecto['prefijo'], $defecto['separador'], $defecto['digitos'], $defecto['reinicio']]
        );

        return array_merge(['empresa_id' => $empresaId, 'tipo' => $tipo], $defecto);
    }

    /** Configuración de los 4 tipos para una empresa, en el orden en que se muestran. */
    public static function configuracionCompleta(?int $empresaId = null): array
    {
        $empresaId = $empresaId ?? self::empresaPorDefecto();
        $out = [];
        foreach (array_keys(self::TABLAS) as $tipo) {
            $out[$tipo] = self::config($tipo, $empresaId);
        }
        return $out;
    }

    /** Guarda la configuración de un tipo, validando los valores recibidos del formulario. */
    public static function guardar(string $tipo, int $empresaId, array $datos): void
    {
        self::validarTipo($tipo);

        $prefijo = trim((string) ($datos['prefijo'] ?? ''));
        $separador = substr(trim((string) ($datos['separador'] ?? '')), 0, 5);
        $digitos = max(1, min(10, (int) ($datos['digitos'] ?? 4)));
        $reinicio = in_array($datos['reinicio'] ?? '', self::REINICIOS_VALIDOS, true) ? $datos['reinicio'] : 'nunca';

        $existe = Database::query(
            "SELECT numeracion_id FROM numeraciones WHERE empresa_id = ? AND tipo = ?",
            [$empresaId, $tipo]
        )->fetch();

        if ($existe) {
            Database::query(
                "UPDATE numeraciones SET prefijo = ?, separador = ?, digitos = ?, reinicio = ? WHERE numeracion_id = ?",
                [$prefijo, $separador, $digitos, $reinicio, $existe['numeracion_id']]
            );
        } else {
            Database::query(
                "INSERT INTO numeraciones (empresa_id, tipo, prefijo, separador, digitos, reinicio) VALUES (?, ?, ?, ?, ?, ?)",
                [$empresaId, $tipo, $prefijo, $separador, $digitos, $reinicio]
            );
        }
    }

    private static function validarTipo(string $tipo): void
    {
        if (!isset(self::TABLAS[$tipo])) {
            throw new \InvalidArgumentException("Tipo de numeración desconocido: {$tipo}");
        }
    }

    private static function defecto(string $tipo): array
    {
        return match ($tipo) {
            'cotizacion' => ['prefijo' => 'C',    'separador' => '', 'digitos' => 4, 'reinicio' => 'nunca'],
            'factura'    => ['prefijo' => 'F',    'separador' => '', 'digitos' => 4, 'reinicio' => 'nunca'],
            'cliente'    => ['prefijo' => 'CLI',  'separador' => '-', 'digitos' => 4, 'reinicio' => 'nunca'],
            'proveedor'  => ['prefijo' => 'PROV', 'separador' => '-', 'digitos' => 4, 'reinicio' => 'nunca'],
        };
    }

    private static function empresaPorDefecto(): int
    {
        $row = Database::query("SELECT empresa_id FROM companies ORDER BY empresa_id LIMIT 1")->fetch();
        return $row ? (int) $row['empresa_id'] : 1;
    }
}

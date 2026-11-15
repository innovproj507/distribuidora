<?php
declare(strict_types=1);

namespace App\Libraries;


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
        // Número interno de la compra; el de la factura del proveedor va en compras.numero_factura_proveedor
        'compra'     => ['tabla' => 'compras',       'columna' => 'numero_factura'],
    ];

    private const REINICIOS_VALIDOS = ['nunca', 'diario', 'mensual', 'anual'];

    /** Tipos cuya serie se separa por sucursal (prefijo de branches.prefijo). */
    private const TIPOS_POR_SUCURSAL = ['factura', 'cotizacion', 'compra'];

    private static function prefijoSucursal(?int $sucursalId): string
    {
        if (!$sucursalId) {
            return '';
        }
        return strtoupper(trim((string) Db::value("SELECT prefijo FROM branches WHERE sucursal_id = ?", [$sucursalId])));
    }

    /**
     * Genera el siguiente número/código para un tipo, según su configuración.
     * Si no se indica $empresaId, usa la única empresa registrada (sistema de una sola empresa).
     */
    public static function siguiente(string $tipo, ?int $empresaId = null, ?int $sucursalId = null): string
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

        // Facturas y cotizaciones pueden llevar serie propia por sucursal: el prefijo de la sucursal
        // va delante ("MET-FAC-000001") si con_sucursal está activo. Sin él (o sucursal sin prefijo)
        // todas las tiendas comparten la serie de la empresa ("FAC-2026-00001").
        $prefijoSucursal = in_array($tipo, self::TIPOS_POR_SUCURSAL, true) && (int) ($cfg['con_sucursal'] ?? 1) === 1
            ? self::prefijoSucursal($sucursalId) : '';

        $partes = self::partes($cfg, $prefijoSucursal, $fecha);
        // El separador se aplica entre prefijo/fecha y también antes del secuencial;
        // con separador vacío el resultado queda pegado (ej. "C" + "20260929" + "0001").
        $base = implode($cfg['separador'], $partes) . $cfg['separador'];
        $digitos = max(1, (int) $cfg['digitos']);

        // Se exige que el código tenga exactamente "base + $digitos" caracteres, para no
        // confundir el secuencial con códigos de un formato anterior (ej. con fecha incrustada
        // de cuando el reinicio era diario/mensual/anual y ahora es "nunca", o viceversa).
        $baseEscapada = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $base);
        $patron = $baseEscapada . str_repeat('_', $digitos);

        $row = Db::query(
            "SELECT {$columna} AS c FROM {$tabla}
             WHERE empresa_id = ? AND {$columna} LIKE ? ESCAPE '\\\\' AND LENGTH({$columna}) = ?
             ORDER BY {$columna} DESC LIMIT 1",
            [$empresaId, $patron, strlen($base) + $digitos]
        )->getRowArray();

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
    public static function ejemplo(array $cfg, string $prefijoSucursal = ''): string
    {
        $fecha = match ($cfg['reinicio'] ?? 'nunca') {
            'diario'  => date('Ymd'),
            'mensual' => date('Ym'),
            'anual'   => date('Y'),
            default   => '',
        };
        $separador = $cfg['separador'] ?? '';
        $partes = self::partes($cfg, (int) ($cfg['con_sucursal'] ?? 1) === 1 ? $prefijoSucursal : '', $fecha);
        $base = implode($separador, $partes) . $separador;
        $digitos = max(1, (int) ($cfg['digitos'] ?? 4));
        return $base . str_pad('1', $digitos, '0', STR_PAD_LEFT);
    }

    /**
     * Piezas del número antes del secuencial. El prefijo de la sucursal va antes del prefijo
     * del documento (TIG-FAC-2026-) o después (FAC-T-2026-) según posicion_sucursal.
     */
    private static function partes(array $cfg, string $prefijoSucursal, string $fecha): array
    {
        $orden = ($cfg['posicion_sucursal'] ?? 'antes') === 'despues'
            ? [$cfg['prefijo'] ?? '', $prefijoSucursal, $fecha]
            : [$prefijoSucursal, $cfg['prefijo'] ?? '', $fecha];
        return array_values(array_filter($orden, fn($v) => $v !== ''));
    }

    /** Configuración de un tipo para una empresa; crea el valor por defecto si no existe. */
    public static function config(string $tipo, ?int $empresaId = null): array
    {
        self::validarTipo($tipo);
        $empresaId = $empresaId ?? self::empresaPorDefecto();

        $row = Db::query(
            "SELECT * FROM numeraciones WHERE empresa_id = ? AND tipo = ?",
            [$empresaId, $tipo]
        )->getRowArray();

        if ($row) {
            return $row;
        }

        $defecto = self::defecto($tipo) + ['con_sucursal' => 1, 'posicion_sucursal' => 'antes'];
        Db::query(
            "INSERT INTO numeraciones (empresa_id, tipo, prefijo, separador, digitos, reinicio, con_sucursal, posicion_sucursal) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$empresaId, $tipo, $defecto['prefijo'], $defecto['separador'], $defecto['digitos'], $defecto['reinicio'], $defecto['con_sucursal'], $defecto['posicion_sucursal']]
        );

        return array_merge(['empresa_id' => $empresaId, 'tipo' => $tipo], $defecto);
    }

    /** Configuración de todos los tipos para una empresa, en el orden en que se muestran. */
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

        $existe = Db::query(
            "SELECT numeracion_id FROM numeraciones WHERE empresa_id = ? AND tipo = ?",
            [$empresaId, $tipo]
        )->getRowArray();

        if ($existe) {
            Db::query(
                "UPDATE numeraciones SET prefijo = ?, separador = ?, digitos = ?, reinicio = ? WHERE numeracion_id = ?",
                [$prefijo, $separador, $digitos, $reinicio, $existe['numeracion_id']]
            );
            // Prefijo de sucursal: solo si el formulario lo trae (otras pantallas no lo mandan)
            if (array_key_exists('con_sucursal', $datos)) {
                Db::query("UPDATE numeraciones SET con_sucursal = ? WHERE numeracion_id = ?", [$datos['con_sucursal'] ? 1 : 0, $existe['numeracion_id']]);
            }
            if (array_key_exists('posicion_sucursal', $datos)) {
                $pos = $datos['posicion_sucursal'] === 'despues' ? 'despues' : 'antes';
                Db::query("UPDATE numeraciones SET posicion_sucursal = ? WHERE numeracion_id = ?", [$pos, $existe['numeracion_id']]);
            }
        } else {
            Db::query(
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
            'compra'     => ['prefijo' => 'COMP', 'separador' => '-', 'digitos' => 6, 'reinicio' => 'anual', 'con_sucursal' => 1, 'posicion_sucursal' => 'despues'],
        };
    }

    private static function empresaPorDefecto(): int
    {
        $row = Db::query("SELECT empresa_id FROM companies ORDER BY empresa_id LIMIT 1")->getRowArray();
        return $row ? (int) $row['empresa_id'] : 1;
    }
}

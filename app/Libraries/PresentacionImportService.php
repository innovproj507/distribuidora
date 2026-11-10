<?php
declare(strict_types=1);

namespace App\Libraries;

/**
 * Importa presentaciones (caja, bulto, paca…) desde Excel: una fila por presentación con
 * Código del producto · Presentación · Trae (unidades) · Precio presentación.
 * Solo agrega o actualiza (por producto + nombre de la presentación); nunca borra —
 * quitar una presentación se hace en Editar producto. Actualiza en sitio para conservar
 * el unidad_id que guardan las ventas.
 */
final class PresentacionImportService
{
    public const ENCABEZADOS = ['Código', 'Producto', 'Precio unidad (referencia)', 'Presentación', 'Trae (unidades)', 'Precio presentación'];

    /** Filas de la plantilla: las presentaciones actuales y una fila vacía por producto sin ninguna. */
    public static function plantilla(int $empresaId): array
    {
        $rows = Db::all(
            "SELECT p.codigo, p.nombre, COALESCE(pa.precio, 0) AS precio, u.nombre AS presentacion, u.factor_conversion, u.precio_a
             FROM productos p
             LEFT JOIN precios_productos pa ON pa.producto_id = p.producto_id AND pa.tipo_precio = 'a'
             LEFT JOIN categorias_productos c ON c.categoria_id = p.categoria_id
             LEFT JOIN productos_unidades u ON u.producto_id = p.producto_id
             WHERE p.empresa_id = ? AND p.estado = 'activo'
             ORDER BY c.nombre, p.nombre, u.factor_conversion",
            [$empresaId]
        );
        return array_map(fn (array $r): array => [
            $r['codigo'],
            $r['nombre'],
            (float) $r['precio'],
            $r['presentacion'] ?? '',
            $r['presentacion'] !== null ? (float) $r['factor_conversion'] : '',
            $r['presentacion'] !== null && (float) $r['precio_a'] > 0 ? (float) $r['precio_a'] : '',
        ], $rows);
    }

    /**
     * Valida las filas leídas del archivo. Devuelve líneas con 'accion' nueva|actualizar|igual|error.
     *
     * @param array<int, array<int, string>> $filas
     */
    public static function analizar(int $empresaId, array $filas): array
    {
        if (!$filas) {
            throw new \DomainException('El archivo está vacío.');
        }
        $cols = self::columnas($filas[0]);

        // Productos y presentaciones actuales, en memoria (una sola consulta cada uno)
        $productos = [];
        foreach (Db::all("SELECT producto_id, codigo, nombre FROM productos WHERE empresa_id = ?", [$empresaId]) as $p) {
            $productos[mb_strtolower(trim((string) $p['codigo']))] = $p;
        }
        $existentes = [];
        foreach (Db::all(
            "SELECT u.unidad_id, u.producto_id, u.nombre, u.factor_conversion, u.precio_a
             FROM productos_unidades u JOIN productos p ON p.producto_id = u.producto_id WHERE p.empresa_id = ?",
            [$empresaId]
        ) as $u) {
            $existentes[$u['producto_id'] . '|' . mb_strtolower($u['nombre'])] = $u;
        }

        $lineas = [];
        $vistos = [];
        foreach (array_slice($filas, 1, null, true) as $i => $f) {
            $codigo = trim((string) ($f[$cols['codigo']] ?? ''));
            $nombre = trim((string) ($f[$cols['presentacion']] ?? ''));
            $trae   = trim((string) ($f[$cols['trae']] ?? ''));
            $precio = trim((string) ($f[$cols['precio']] ?? ''));
            if ($nombre === '' && $trae === '' && $precio === '') {
                continue; // fila del listado sin presentación: se ignora
            }

            $l = ['fila' => $i + 1, 'codigo' => $codigo, 'producto' => '', 'producto_id' => 0, 'unidad_id' => 0,
                  'nombre' => $nombre, 'factor' => 0.0, 'precio' => 0.0, 'antes' => '', 'accion' => 'error', 'error' => ''];
            $p = $productos[mb_strtolower($codigo)] ?? null;
            if (!$p && ctype_digit($codigo)) { // Excel quita los ceros a la izquierda de los códigos numéricos
                foreach ($productos as $clave => $cand) {
                    if (ctype_digit($clave) && ltrim($clave, '0') === ltrim($codigo, '0')) { $p = $cand; break; }
                }
            }
            $factor = self::decimal($trae);
            $precioN = $precio === '' ? 0.0 : self::decimal($precio);

            if ($codigo === '') {
                $l['error'] = 'Falta el código del producto.';
            } elseif (!$p) {
                $l['error'] = 'No existe un producto con ese código.';
            } elseif ($nombre === '') {
                $l['error'] = 'Falta el nombre de la presentación (Caja, Bulto…).';
            } elseif (mb_strlen($nombre) > 50) {
                $l['error'] = 'El nombre de la presentación es muy largo (máx. 50).';
            } elseif ($factor === null || $factor <= 0) {
                $l['error'] = '"Trae" debe ser un número mayor que cero.';
            } elseif ($precioN === null || $precioN < 0) {
                $l['error'] = 'El precio no es un número válido.';
            }

            if ($p) {
                $l['producto'] = $p['nombre'];
                $l['producto_id'] = (int) $p['producto_id'];
            }
            if ($l['error'] === '') {
                $clave = $p['producto_id'] . '|' . mb_strtolower($nombre);
                $l['factor'] = round($factor, 2);
                $l['precio'] = round($precioN, 10);
                if (isset($vistos[$clave])) {
                    $l['error'] = "Repetida: \"{$nombre}\" ya está en la fila {$vistos[$clave]} para este producto.";
                } elseif ($u = $existentes[$clave] ?? null) {
                    $l['unidad_id'] = (int) $u['unidad_id'];
                    $igual = abs((float) $u['factor_conversion'] - $l['factor']) < 0.001 && abs((float) $u['precio_a'] - $l['precio']) < 0.001;
                    $l['accion'] = $igual ? 'igual' : 'actualizar';
                    $l['antes'] = '×' . self::num((float) $u['factor_conversion']) . ' a $' . number_format((float) $u['precio_a'], 2);
                } else {
                    $l['accion'] = 'nueva';
                }
                $vistos[$clave] = $l['fila'];
            }
            $lineas[] = $l;
        }
        return $lineas;
    }

    /** Aplica las líneas válidas (nueva/actualizar). Devuelve [nuevas, actualizadas]. El llamador abre la transacción. */
    public static function aplicar(int $empresaId, array $lineas): array
    {
        $nuevas = $actualizadas = 0;
        foreach ($lineas as $l) {
            if (!in_array($l['accion'], ['nueva', 'actualizar'], true)) continue;
            // El producto debe seguir siendo de la empresa (las líneas vienen de la sesión)
            if (!Db::value("SELECT 1 FROM productos WHERE producto_id = ? AND empresa_id = ?", [$l['producto_id'], $empresaId])) continue;

            $datos = ['nombre' => $l['nombre'], 'factor_conversion' => $l['factor'], 'precio_a' => $l['precio']];
            $actual = Db::value(
                "SELECT unidad_id FROM productos_unidades WHERE producto_id = ? AND LOWER(nombre) = LOWER(?) LIMIT 1",
                [$l['producto_id'], $l['nombre']]
            );
            if ($actual) {
                Db::update('productos_unidades', $datos, ['unidad_id' => (int) $actual]);
                $actualizadas++;
            } else {
                Db::insert('productos_unidades', $datos + ['producto_id' => $l['producto_id'], 'precio_b' => 0, 'precio_c' => 0, 'codigo_barras' => '']);
                $nuevas++;
            }
        }
        return [$nuevas, $actualizadas];
    }

    /** Ubica las columnas por el encabezado (tolera mayúsculas, tildes y textos parecidos). */
    private static function columnas(array $encabezado): array
    {
        $cols = [];
        foreach ($encabezado as $i => $h) {
            $h = self::normalizar((string) $h);
            if (!isset($cols['codigo']) && (str_starts_with($h, 'codigo') || $h === 'sku')) $cols['codigo'] = $i;
            elseif (!isset($cols['presentacion']) && (str_starts_with($h, 'presentacion') || $h === 'unidad' || $h === 'empaque')) $cols['presentacion'] = $i;
            elseif (!isset($cols['trae']) && (str_starts_with($h, 'trae') || str_starts_with($h, 'cantidad') || str_starts_with($h, 'factor') || str_starts_with($h, 'unidades'))) $cols['trae'] = $i;
            elseif (!isset($cols['precio']) && str_starts_with($h, 'precio') && str_contains($h, 'presentacion')) $cols['precio'] = $i;
        }
        // "Precio presentación" puede venir solo como "Precio" si no hay columna de precio unidad
        if (!isset($cols['precio'])) {
            foreach ($encabezado as $i => $h) {
                $h = self::normalizar((string) $h);
                if ($h === 'precio') $cols['precio'] = $i;
            }
        }
        $faltan = array_diff_key(['codigo' => 'Código', 'presentacion' => 'Presentación', 'trae' => 'Trae (unidades)', 'precio' => 'Precio presentación'], $cols);
        if ($faltan) {
            throw new \DomainException('Faltan columnas en la primera fila: ' . implode(', ', $faltan) . '. Usa la plantilla descargada.');
        }
        return $cols;
    }

    private static function normalizar(string $s): string
    {
        $s = mb_strtolower(trim($s));
        return strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
    }

    /** "9", "9.00", "9,50", "$9.50" → float; null si no es número. */
    private static function decimal(string $v): ?float
    {
        $v = str_replace(['$', ' ', "\u{00A0}"], '', $v);
        if (preg_match('/^\d+,\d+$/', $v)) $v = str_replace(',', '.', $v); // coma decimal
        $v = str_replace(',', '', $v);                                      // separador de miles
        return is_numeric($v) ? (float) $v : null;
    }

    private static function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }
}

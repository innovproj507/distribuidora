<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Códigos de barras de productos y presentaciones.
 *
 *  - Del fabricante: se escanean (pistola D33 o cámara) en Inventario → Códigos de barras.
 *  - Internos (lo que no trae código: granel, yuca, alitas): EAN-13 en el rango de uso interno
 *    de tienda (empieza en 2): 200 + producto_id (9 dígitos) + dígito de control para productos,
 *    201 + unidad_id para presentaciones. No chocan con códigos de fabricantes ni entre sí.
 *  - Un código no puede repetirse entre productos ni presentaciones (el escáner no sabría cuál es).
 *
 * La referencia del proveedor vive en productos.supplier_part_no.
 */
final class CodigoBarras
{
    public const PREFIJO_PRODUCTO = '200';
    public const PREFIJO_PRESENTACION = '201';

    public static function normalizar(?string $codigo): string
    {
        return preg_replace('/\s+/', '', trim((string) $codigo)) ?? '';
    }

    /** Dígito de control EAN-13 de los 12 primeros dígitos. */
    public static function digitoControl(string $doce): int
    {
        $suma = 0;
        for ($i = 0; $i < 12; $i++) {
            $suma += (int) $doce[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        return (10 - $suma % 10) % 10;
    }

    public static function interno(int $id, string $prefijo = self::PREFIJO_PRODUCTO): string
    {
        $base = $prefijo . str_pad((string) $id, 9, '0', STR_PAD_LEFT);
        return $base . self::digitoControl($base);
    }

    public static function esInterno(?string $codigo): bool
    {
        return (bool) preg_match('/^20[01]\d{10}$/', (string) $codigo);
    }

    /** EAN-13 con dígito de control correcto (los demás se imprimen como CODE128). */
    public static function esEan13(?string $codigo): bool
    {
        $c = (string) $codigo;
        return (bool) preg_match('/^\d{13}$/', $c) && self::digitoControl(substr($c, 0, 12)) === (int) $c[12];
    }

    /**
     * Quién usa ya ese código (otro producto u otra presentación). null = libre.
     * $productoId / $unidadId = el dueño que se está guardando (no cuenta como repetido).
     */
    public static function usadoPor(int $empresaId, string $codigo, ?int $productoId = null, ?int $unidadId = null): ?string
    {
        if ($codigo === '') return null;
        $p = Db::row(
            "SELECT nombre FROM productos WHERE empresa_id = ? AND codigo_barras = ? AND producto_id <> ? LIMIT 1",
            [$empresaId, $codigo, $productoId ?? 0]
        );
        if ($p) return "el producto «{$p['nombre']}»";
        $u = Db::row(
            "SELECT u.nombre, p.nombre AS producto FROM productos_unidades u JOIN productos p ON p.producto_id = u.producto_id
             WHERE p.empresa_id = ? AND u.codigo_barras = ? AND u.unidad_id <> ? LIMIT 1",
            [$empresaId, $codigo, $unidadId ?? 0]
        );
        if ($u) return "la presentación «{$u['nombre']}» de «{$u['producto']}»";
        return null;
    }

    /**
     * Guarda código de barras, referencia del proveedor y códigos de las presentaciones de un producto.
     * Solo cambia las claves que vienen: codigo_barras, supplier_part_no, unidades [unidad_id => código].
     * Valor 'interno' = generar el código interno. Lanza DomainException si un código ya está en uso.
     *
     * @return array{codigo_barras: ?string, supplier_part_no: ?string, unidades: array<int, string>}
     */
    public static function guardar(int $empresaId, int $productoId, array $datos): array
    {
        $producto = Db::row("SELECT producto_id, nombre, codigo_barras, supplier_part_no FROM productos WHERE producto_id = ? AND empresa_id = ?", [$productoId, $empresaId]);
        if (!$producto) throw new \DomainException('Producto no encontrado.');

        Db::begin();
        try {
            $cambios = [];
            if (array_key_exists('codigo_barras', $datos)) {
                $c = $datos['codigo_barras'] === 'interno' ? self::interno($productoId) : self::normalizar($datos['codigo_barras']);
                if (mb_strlen($c) > 100) throw new \DomainException('El código de barras es demasiado largo.');
                if ($quien = self::usadoPor($empresaId, $c, $productoId)) {
                    throw new \DomainException("El código {$c} ya lo tiene {$quien}.");
                }
                $cambios['codigo_barras'] = $c !== '' ? $c : null;
            }
            if (array_key_exists('supplier_part_no', $datos)) {
                $r = trim((string) $datos['supplier_part_no']);
                $cambios['supplier_part_no'] = $r !== '' ? mb_substr($r, 0, 100) : null;
            }
            if ($cambios) {
                $cambios['fecha_modificacion'] = date('Y-m-d H:i:s');
                Db::update('productos', $cambios, ['producto_id' => $productoId]);
            }

            foreach ((array) ($datos['unidades'] ?? []) as $unidadId => $codigo) {
                $unidadId = (int) $unidadId;
                if (!Db::value("SELECT COUNT(*) FROM productos_unidades WHERE unidad_id = ? AND producto_id = ?", [$unidadId, $productoId])) {
                    continue; // la presentación no es de este producto
                }
                $c = $codigo === 'interno' ? self::interno($unidadId, self::PREFIJO_PRESENTACION) : self::normalizar((string) $codigo);
                if ($quien = self::usadoPor($empresaId, $c, null, $unidadId)) {
                    throw new \DomainException("El código {$c} ya lo tiene {$quien}.");
                }
                Db::update('productos_unidades', ['codigo_barras' => $c], ['unidad_id' => $unidadId]);
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        $fila = Db::row("SELECT codigo_barras, supplier_part_no FROM productos WHERE producto_id = ?", [$productoId]);
        $unidades = [];
        foreach (Db::all("SELECT unidad_id, codigo_barras FROM productos_unidades WHERE producto_id = ?", [$productoId]) as $u) {
            $unidades[(int) $u['unidad_id']] = (string) $u['codigo_barras'];
        }
        if ($fila['codigo_barras'] !== $producto['codigo_barras'] || isset($datos['unidades'])) {
            AuditService::log('productos.codigos', "Códigos de barras de «{$producto['nombre']}»: " . ($fila['codigo_barras'] ?? 'sin código'), $productoId, 'producto', [], $empresaId);
        }
        return ['codigo_barras' => $fila['codigo_barras'], 'supplier_part_no' => $fila['supplier_part_no'], 'unidades' => $unidades];
    }

    /**
     * Productos activos para la pantalla de códigos, con sus presentaciones.
     * $filtro: 'sin' (sin código), 'interno' (con código interno), 'todos'.
     */
    public static function lista(int $empresaId, string $filtro = 'sin', string $q = '', ?int $categoriaId = null, int $limite = 1000): array
    {
        $where  = ["p.empresa_id = ?", "p.estado = 'activo'"];
        $params = [$empresaId];
        if ($filtro === 'sin') {
            $where[] = "(p.codigo_barras IS NULL OR p.codigo_barras = ''
                         OR EXISTS (SELECT 1 FROM productos_unidades u WHERE u.producto_id = p.producto_id AND u.factor_conversion > 1 AND (u.codigo_barras IS NULL OR u.codigo_barras = '')))";
        } elseif ($filtro === 'interno') {
            $where[] = "p.codigo_barras REGEXP '^20[01][0-9]{10}$'";
        }
        if ($q !== '') {
            $where[]  = "(p.nombre LIKE ? OR p.codigo LIKE ? OR p.codigo_barras LIKE ? OR p.supplier_part_no LIKE ?)";
            array_push($params, "%{$q}%", "%{$q}%", "%{$q}%", "%{$q}%");
        }
        if ($categoriaId) {
            $where[]  = 'p.categoria_id = ?';
            $params[] = $categoriaId;
        }
        $productos = Db::all(
            "SELECT p.producto_id, p.codigo, p.nombre, p.codigo_barras, p.supplier_part_no, p.unidad_medida, c.nombre AS categoria
             FROM productos p LEFT JOIN categorias_productos c ON c.categoria_id = p.categoria_id
             WHERE " . implode(' AND ', $where) . " ORDER BY c.nombre, p.nombre LIMIT " . max(1, $limite),
            $params
        );
        if (!$productos) return [];
        $ids = array_column($productos, 'producto_id');
        $unidades = [];
        foreach (Db::all(
            "SELECT unidad_id, producto_id, nombre, factor_conversion, codigo_barras FROM productos_unidades
             WHERE factor_conversion > 1 AND producto_id IN (" . implode(',', array_map('intval', $ids)) . ") ORDER BY factor_conversion",
            []
        ) as $u) {
            $unidades[(int) $u['producto_id']][] = [
                'unidad_id' => (int) $u['unidad_id'], 'nombre' => $u['nombre'],
                'factor' => (float) $u['factor_conversion'], 'codigo_barras' => (string) $u['codigo_barras'],
            ];
        }
        foreach ($productos as &$p) {
            $p['producto_id'] = (int) $p['producto_id'];
            $p['codigo_barras'] = (string) ($p['codigo_barras'] ?? '');
            $p['supplier_part_no'] = (string) ($p['supplier_part_no'] ?? '');
            $p['unidades'] = $unidades[$p['producto_id']] ?? [];
        }
        unset($p);
        return $productos;
    }
}

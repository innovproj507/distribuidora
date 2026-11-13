<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Precios y costo por tienda (migración 2026-11-13-000001). Cada sucursal tiene su precio de venta
 * (precios_productos.sucursal_id), sus precios por presentación (productos_unidades_precios) y su costo
 * (inventario.costo_promedio de sus depósitos). Una tienda nunca cambia los de la otra.
 *
 * Producto o presentación nuevos arrancan con el mismo precio en todas las tiendas (para que se puedan
 * vender en cualquiera); de ahí cada una cambia el suyo.
 */
final class PrecioTienda
{
    /** Sucursal activa del usuario: la del token en la API, la de la sesión en la web y la PWA. */
    public static function sucursalActual(): int
    {
        if (Auth::esApi()) {
            return (int) (Auth::contextoApi()['sucursal_id'] ?? 0);
        }
        return (int) (session()->get('sucursal_actual')['sucursal_id'] ?? 0);
    }

    /** @return int[] todas las sucursales (activas o no: si se reactivan, tienen precio) */
    public static function sucursales(): array
    {
        return array_map('intval', array_column(Db::all("SELECT sucursal_id FROM branches"), 'sucursal_id'));
    }

    /** Sucursal dueña de un depósito. */
    public static function sucursalDeDeposito(int $depositoId): int
    {
        return (int) (Db::value("SELECT sucursal_id FROM depositos WHERE deposito_id = ?", [$depositoId]) ?? 0);
    }

    public static function precio(int $productoId, int $sucursalId, string $tipo = 'a'): float
    {
        return (float) (Db::value(
            "SELECT precio FROM precios_productos WHERE producto_id = ? AND sucursal_id = ? AND tipo_precio = ?",
            [$productoId, $sucursalId, $tipo]
        ) ?? 0);
    }

    /** [tipo => precio] de la tienda. */
    public static function precios(int $productoId, int $sucursalId): array
    {
        return array_map('floatval', array_column(
            Db::all("SELECT tipo_precio, precio FROM precios_productos WHERE producto_id = ? AND sucursal_id = ?", [$productoId, $sucursalId]),
            'precio', 'tipo_precio'
        ));
    }

    /**
     * Fija un precio en una tienda, o en todas con $sucursalId = null. Monto 0 o negativo lo borra.
     */
    public static function fijar(int $productoId, string $tipo, float $monto, ?int $sucursalId): void
    {
        foreach ($sucursalId === null ? self::sucursales() : [$sucursalId] as $s) {
            if ($monto > 0) {
                Db::query(
                    "INSERT INTO precios_productos (producto_id, sucursal_id, tipo_precio, precio, fecha_inicio) VALUES (?, ?, ?, ?, CURDATE())
                     ON DUPLICATE KEY UPDATE precio = VALUES(precio), fecha_inicio = VALUES(fecha_inicio)",
                    [$productoId, $s, $tipo, $monto]
                );
            } else {
                Db::query("DELETE FROM precios_productos WHERE producto_id = ? AND sucursal_id = ? AND tipo_precio = ?", [$productoId, $s, $tipo]);
            }
        }
    }

    /** Precio de cada presentación en la tienda: [unidad_id => [precio_a, precio_b, precio_c]]. */
    public static function preciosUnidades(int $productoId, int $sucursalId): array
    {
        $r = [];
        foreach (Db::all(
            "SELECT u.unidad_id, COALESCE(x.precio_a, 0) AS precio_a, COALESCE(x.precio_b, 0) AS precio_b, COALESCE(x.precio_c, 0) AS precio_c
             FROM productos_unidades u LEFT JOIN productos_unidades_precios x ON x.unidad_id = u.unidad_id AND x.sucursal_id = ?
             WHERE u.producto_id = ?",
            [$sucursalId, $productoId]
        ) as $f) {
            $r[(int) $f['unidad_id']] = ['precio_a' => (float) $f['precio_a'], 'precio_b' => (float) $f['precio_b'], 'precio_c' => (float) $f['precio_c']];
        }
        return $r;
    }

    /**
     * Presentaciones del producto con el precio de la tienda en precio_a/b/c (mismas columnas que antes
     * tenía productos_unidades, para que las vistas no cambien).
     */
    public static function unidades(int $productoId, int $sucursalId, string $orden = 'u.factor_conversion'): array
    {
        return Db::all(
            "SELECT u.unidad_id, u.producto_id, u.nombre, u.abreviatura, u.factor_conversion, u.codigo_barras,
                    COALESCE(x.precio_a, 0) AS precio_a, COALESCE(x.precio_b, 0) AS precio_b, COALESCE(x.precio_c, 0) AS precio_c
             FROM productos_unidades u LEFT JOIN productos_unidades_precios x ON x.unidad_id = u.unidad_id AND x.sucursal_id = ?
             WHERE u.producto_id = ? ORDER BY {$orden}",
            [$sucursalId, $productoId]
        );
    }

    /** Precio de una presentación en una tienda, o en todas con $sucursalId = null. */
    public static function fijarUnidad(int $unidadId, ?int $sucursalId, float $a, ?float $b = null, ?float $c = null): void
    {
        foreach ($sucursalId === null ? self::sucursales() : [$sucursalId] as $s) {
            Db::query(
                "INSERT INTO productos_unidades_precios (unidad_id, sucursal_id, precio_a, precio_b, precio_c) VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE precio_a = VALUES(precio_a)"
                    . ($b !== null ? ', precio_b = VALUES(precio_b)' : '')
                    . ($c !== null ? ', precio_c = VALUES(precio_c)' : ''),
                [$unidadId, $s, max(0, $a), max(0, (float) $b), max(0, (float) $c)]
            );
        }
    }

    /**
     * Costo del producto en la tienda: costo promedio de su depósito principal (o el primero con costo);
     * si la tienda no tiene, el último costo general del producto.
     */
    public static function costo(int $productoId, int $sucursalId): float
    {
        $c = Db::value(
            "SELECT i.costo_promedio FROM inventario i JOIN depositos d ON d.deposito_id = i.deposito_id
             WHERE i.producto_id = ? AND d.sucursal_id = ? AND i.costo_promedio > 0
             ORDER BY d.es_principal DESC, d.deposito_id LIMIT 1",
            [$productoId, $sucursalId]
        );
        return $c !== null ? (float) $c : (float) (Db::value("SELECT costo FROM productos WHERE producto_id = ?", [$productoId]) ?? 0);
    }

    /**
     * SQL del costo en la tienda para usar dentro de un SELECT con la tabla productos como "p"
     * (sin $sucursalId deja un "?" para la sucursal).
     */
    public static function sqlCosto(?int $sucursalId = null, string $col = 'p.producto_id'): string
    {
        $s = $sucursalId === null ? '?' : (string) (int) $sucursalId;
        return "COALESCE((SELECT ic.costo_promedio FROM inventario ic JOIN depositos dc ON dc.deposito_id = ic.deposito_id
                  WHERE ic.producto_id = {$col} AND dc.sucursal_id = {$s} AND ic.costo_promedio > 0
                  ORDER BY dc.es_principal DESC, dc.deposito_id LIMIT 1), p.costo, 0)";
    }

    /** Fija el costo en los depósitos de la tienda (no toca la otra). También queda como último costo general. */
    public static function fijarCosto(int $productoId, int $sucursalId, float $costo): void
    {
        Db::query(
            "UPDATE inventario i JOIN depositos d ON d.deposito_id = i.deposito_id
             SET i.costo_promedio = ?, i.ultimo_costo = ?, i.fecha_actualizacion_costo = NOW()
             WHERE i.producto_id = ? AND d.sucursal_id = ?",
            [$costo, $costo, $productoId, $sucursalId]
        );
        Db::query("UPDATE productos SET costo = ? WHERE producto_id = ?", [$costo, $productoId]);
    }

    /** Sucursal nueva: arranca con los precios de la principal. */
    public static function copiarASucursal(int $nuevaId): void
    {
        $origen = (int) (Db::value("SELECT sucursal_id FROM branches WHERE sucursal_id <> ? ORDER BY es_principal DESC, sucursal_id LIMIT 1", [$nuevaId]) ?? 0);
        if (!$origen) return;
        Db::query(
            "INSERT IGNORE INTO precios_productos (producto_id, sucursal_id, tipo_precio, precio, fecha_inicio)
             SELECT producto_id, ?, tipo_precio, precio, CURDATE() FROM precios_productos WHERE sucursal_id = ?",
            [$nuevaId, $origen]
        );
        Db::query(
            "INSERT IGNORE INTO productos_unidades_precios (unidad_id, sucursal_id, precio_a, precio_b, precio_c)
             SELECT unidad_id, ?, precio_a, precio_b, precio_c FROM productos_unidades_precios WHERE sucursal_id = ?",
            [$nuevaId, $origen]
        );
    }
}

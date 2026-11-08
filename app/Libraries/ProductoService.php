<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Altas, cambios y bajas de productos con sus precios, stock por depósito y unidades
 * auxiliares. Reemplaza la capa DDD del sistema anterior (Register/Update/Delete/AddStock
 * UseCases + repositorios) con las mismas reglas.
 */
final class ProductoService
{
    /** Columnas de productos que se escriben desde el formulario. */
    private const CAMPOS = [
        'codigo', 'nombre', 'descripcion', 'categoria_id', 'costo', 'itbms', 'estado',
        'maneja_lotes', 'stock_minimo', 'codigo_barras', 'marca', 'proveedor_id',
        'supplier_part_no', 'imagen_principal', 'unidad_medida',
    ];

    /**
     * Unidad en que se vende y se cuenta el producto (unidad base del inventario).
     * clave => [nombre, abreviatura para mostrar, se vende con decimales]
     */
    public const UNIDADES_MEDIDA = [
        'und' => ['Unidad', 'uds', false],
        'lb'  => ['Libra', 'lb', true],
        'kg'  => ['Kilo', 'kg', true],
        'oz'  => ['Onza', 'oz', true],
        'gal' => ['Galón', 'gal', true],
        'lt'  => ['Litro', 'lt', true],
        'm'   => ['Metro', 'm', true],
        'pie' => ['Pie', 'pie', true],
        'yd'  => ['Yarda', 'yd', true],
    ];

    /** Clave válida de unidad de medida ('und' si no es una conocida). */
    public static function unidadMedida(?string $valor): string
    {
        $valor = strtolower(trim((string) $valor));
        return isset(self::UNIDADES_MEDIDA[$valor]) ? $valor : 'und';
    }

    /** Abreviatura para mostrar: "uds", "lb"… */
    public static function abreviatura(?string $unidad): string
    {
        return self::UNIDADES_MEDIDA[self::unidadMedida($unidad)][1];
    }

    /**
     * Crea el producto, sus precios y su registro de inventario en cada depósito.
     * El stock inicial entra como movimiento "entrada" (queda en el kardex).
     *
     * @param array $precios   [tipo => monto]
     * @param array $stocks    [[deposito_id, cantidad], ...]
     */
    public static function crear(int $empresaId, array $datos, array $precios, array $stocks): int
    {
        if (self::codigoEnUso($empresaId, $datos['codigo'])) {
            throw new \DomainException("Ya existe un producto con el código: {$datos['codigo']}");
        }
        $datos['itbms'] = 0; // ningún producto lleva ITBMS
        $datos['unidad_medida'] = self::unidadMedida($datos['unidad_medida'] ?? 'und');

        $productoId = Db::insert('productos', array_intersect_key($datos, array_flip(self::CAMPOS)) + [
            'empresa_id'         => $empresaId,
            'fecha_creacion'     => date('Y-m-d H:i:s'),
            'fecha_modificacion' => date('Y-m-d H:i:s'),
        ]);

        foreach ($precios as $tipo => $monto) {
            if ($monto > 0) {
                self::reemplazarPrecio($productoId, $tipo, $monto);
            }
        }

        $costo  = (float) ($datos['costo'] ?? 0);
        $minimo = (float) ($datos['stock_minimo'] ?? 0);
        foreach ($stocks as [$depositoId, $cantidad]) {
            $inventarioId = Db::insert('inventario', [
                'producto_id'               => $productoId,
                'deposito_id'               => $depositoId,
                'existencia'                => 0,
                'minimo'                    => $minimo,
                'maximo'                    => 0,
                'costo_promedio'            => $costo,
                'ultimo_costo'              => $costo,
                'fecha_actualizacion_costo' => date('Y-m-d H:i:s'),
            ]);
            if ($cantidad > 0) {
                InventarioService::registrar($inventarioId, 'entrada', $cantidad, $productoId, 'producto', 'Stock inicial');
            }
        }

        // Fila en existencia 0 en el resto de depósitos (de todas las sucursales)
        SucursalService::poblarProducto($productoId);
        // Si hay un conteo completo en proceso, el producto nuevo también se cuenta
        ConteoService::incluirProductoNuevo($productoId);

        return $productoId;
    }

    /**
     * Actualiza datos y precios. Si no viene imagen nueva se conserva la actual.
     *
     * @param array $precios [tipo => monto]; monto 0 borra ese precio
     */
    public static function actualizar(int $productoId, int $empresaId, array $datos, array $precios): void
    {
        $actual = self::buscar($productoId, $empresaId);
        if (!$actual) {
            throw new \DomainException('Producto no encontrado o no autorizado.');
        }
        if (($datos['codigo'] ?? $actual['codigo']) !== $actual['codigo'] && self::codigoEnUso($empresaId, $datos['codigo'], $productoId)) {
            throw new \DomainException("Ya existe un producto con el código: {$datos['codigo']}");
        }
        if (empty($datos['imagen_principal'])) {
            unset($datos['imagen_principal']);
        }
        $datos['itbms'] = 0; // ningún producto lleva ITBMS (se cobra por tasa global, hoy 0)
        if (array_key_exists('unidad_medida', $datos)) {
            $datos['unidad_medida'] = self::unidadMedida($datos['unidad_medida']);
        }

        Db::update('productos', array_intersect_key($datos, array_flip(self::CAMPOS)) + [
            'fecha_modificacion' => date('Y-m-d H:i:s'),
        ], ['producto_id' => $productoId]);

        // Un solo costo por producto: si se cambia aquí, también es el costo de cada tienda (las ventas toman el
        // costo del inventario). Antes solo cambiaba la ficha y los reportes seguían con el costo viejo.
        if (isset($datos['costo']) && abs((float) $datos['costo'] - (float) $actual['costo']) > 0.00001) {
            Db::query(
                "UPDATE inventario SET costo_promedio = ?, ultimo_costo = ?, fecha_actualizacion_costo = NOW() WHERE producto_id = ?",
                [(float) $datos['costo'], (float) $datos['costo'], $productoId]
            );
        }

        foreach ($precios as $tipo => $monto) {
            self::reemplazarPrecio($productoId, $tipo, $monto);
        }
    }

    /**
     * Borra el producto con su inventario, precios y unidades. Si ya tiene movimientos en el
     * kardex se rechaza (hay que desactivarlo) para no perder la trazabilidad.
     */
    public static function eliminar(int $productoId, int $empresaId): void
    {
        if (!self::buscar($productoId, $empresaId)) {
            throw new \DomainException('Producto no encontrado o no autorizado.');
        }

        $movimientos = (int) Db::value(
            "SELECT COUNT(*) FROM inventario_movimientos m JOIN inventario i ON m.inventario_id = i.inventario_id WHERE i.producto_id = ?",
            [$productoId]
        );
        if ($movimientos > 0) {
            throw new \DomainException("No se puede eliminar: el producto tiene {$movimientos} movimiento(s) de inventario. Desactívalo en su lugar.");
        }

        Db::begin();
        Db::query("DELETE FROM inventario WHERE producto_id = ?", [$productoId]);
        Db::query("DELETE FROM precios_productos WHERE producto_id = ?", [$productoId]);
        Db::query("DELETE FROM productos_unidades WHERE producto_id = ?", [$productoId]);
        Db::query("DELETE FROM productos WHERE producto_id = ?", [$productoId]);
        Db::commit();
    }

    /**
     * Agrega stock a un depósito recalculando el costo promedio ponderado.
     * Crea el registro de inventario si el producto aún no está en ese depósito.
     */
    public static function agregarStock(int $productoId, int $depositoId, float $cantidad, float $costo): void
    {
        if ($cantidad <= 0) {
            throw new \InvalidArgumentException('La cantidad debe ser mayor a 0.');
        }
        if ($costo <= 0) {
            // Sin costo escrito se usa el del producto: con 0 el costo promedio se diluía y quedaba en $0
            $costo = (float) (Db::value("SELECT costo FROM productos WHERE producto_id = ?", [$productoId]) ?? 0);
        }

        $inv = Db::row("SELECT inventario_id, existencia, costo_promedio FROM inventario WHERE producto_id = ? AND deposito_id = ?", [$productoId, $depositoId]);

        if (!$inv) {
            $minimo = (float) (Db::value("SELECT stock_minimo FROM productos WHERE producto_id = ?", [$productoId]) ?? 0);
            $inventarioId = Db::insert('inventario', [
                'producto_id' => $productoId, 'deposito_id' => $depositoId, 'existencia' => 0, 'minimo' => $minimo,
                'maximo' => 0, 'costo_promedio' => $costo, 'ultimo_costo' => $costo, 'fecha_actualizacion_costo' => date('Y-m-d H:i:s'),
            ]);
            $promedio = $costo;
        } else {
            $inventarioId = (int) $inv['inventario_id'];
            $existencia   = (float) $inv['existencia'];
            $nueva        = $existencia + $cantidad;
            $promedio     = $nueva > 0 ? (($existencia * (float) $inv['costo_promedio']) + ($cantidad * $costo)) / $nueva : $costo;
        }

        InventarioService::registrar($inventarioId, 'entrada', $cantidad, $productoId, 'producto', 'Ingreso manual de stock');
        Db::query(
            "UPDATE inventario SET costo_promedio = ?, ultimo_costo = ?, fecha_actualizacion_costo = NOW() WHERE inventario_id = ?",
            [round($promedio, 4), $costo, $inventarioId]
        );
    }

    /**
     * Reemplaza las unidades auxiliares (caja, docena...) con las del formulario.
     * $unidades: [{nombre, factor_conversion, precio_a, precio_b, precio_c, codigo_barras}]
     */
    public static function guardarUnidades(int $productoId, array $unidades): void
    {
        Db::query("DELETE FROM productos_unidades WHERE producto_id = ?", [$productoId]);

        foreach ($unidades as $u) {
            $nombre = trim((string) ($u['nombre'] ?? ''));
            if ($nombre === '') continue;

            Db::insert('productos_unidades', [
                'producto_id'       => $productoId,
                'nombre'            => $nombre,
                'factor_conversion' => max(0.001, (float) ($u['factor_conversion'] ?? 1)),
                'precio_a'          => (float) ($u['precio_a'] ?? 0),
                'precio_b'          => (float) ($u['precio_b'] ?? 0),
                'precio_c'          => (float) ($u['precio_c'] ?? 0),
                'codigo_barras'     => trim((string) ($u['codigo_barras'] ?? '')),
            ]);
        }
    }

    public static function buscar(int $productoId, int $empresaId): ?array
    {
        return Db::row("SELECT * FROM productos WHERE producto_id = ? AND empresa_id = ?", [$productoId, $empresaId]);
    }

    /** Precio 0 o negativo borra ese tipo de precio. */
    public static function reemplazarPrecio(int $productoId, string $tipo, float $monto): void
    {
        Db::query("DELETE FROM precios_productos WHERE producto_id = ? AND tipo_precio = ?", [$productoId, $tipo]);
        if ($monto > 0) {
            Db::insert('precios_productos', [
                'producto_id' => $productoId, 'tipo_precio' => $tipo, 'precio' => $monto, 'fecha_inicio' => date('Y-m-d'),
            ]);
        }
    }

    // ── Código automático por categoría ──────────────────────────────────────

    /**
     * Siguiente código libre según la categoría: prefijo de la categoría + consecutivo de 3
     * dígitos (ALI158, ASE084...). El consecutivo es el mayor ya usado con ese prefijo + 1.
     */
    public static function siguienteCodigo(int $empresaId, ?int $categoriaId): string
    {
        $prefijo = self::prefijoCategoria($empresaId, $categoriaId);
        $largo   = strlen($prefijo);

        $max = (int) Db::value(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(codigo, ?) AS UNSIGNED)), 0) FROM productos
             WHERE empresa_id = ? AND codigo REGEXP ?",
            [$largo + 1, $empresaId, '^' . $prefijo . '[0-9]+$']
        );

        do {
            $codigo = $prefijo . str_pad((string) ++$max, 3, '0', STR_PAD_LEFT);
        } while (self::codigoEnUso($empresaId, $codigo));

        return $codigo;
    }

    /**
     * Prefijo de la categoría: el que ya usan la mayoría de sus productos (ALI, ESC, PRO en
     * "Otros"...), o si no hay uno claro, las 3 primeras letras del nombre (REP, LUB).
     * Sin categoría: PRO.
     */
    public static function prefijoCategoria(int $empresaId, ?int $categoriaId): string
    {
        if (!$categoriaId) {
            return 'PRO';
        }
        $nombre = Db::value("SELECT nombre FROM categorias_productos WHERE categoria_id = ? AND empresa_id = ?", [$categoriaId, $empresaId]);
        if ($nombre === null) {
            return 'PRO';
        }

        $total  = 0;
        $conteo = [];
        foreach (Db::all("SELECT codigo FROM productos WHERE empresa_id = ? AND categoria_id = ?", [$empresaId, $categoriaId]) as $p) {
            $total++;
            if (preg_match('/^([A-Za-z]{2,5})[0-9]+$/', (string) $p['codigo'], $m)) {
                $pref = strtoupper($m[1]);
                $conteo[$pref] = ($conteo[$pref] ?? 0) + 1;
            }
        }
        if ($conteo) {
            arsort($conteo);
            $pref = (string) array_key_first($conteo);
            if ($conteo[$pref] >= 5 && $conteo[$pref] * 2 >= $total) {
                return $pref;
            }
        }

        $limpio = strtoupper(strtr((string) $nombre, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']));
        $letras = preg_replace('/[^A-Z]/', '', $limpio) ?? '';
        return strlen($letras) >= 2 ? substr($letras, 0, 3) : 'PRO';
    }

    private static function codigoEnUso(int $empresaId, string $codigo, ?int $exceptoId = null): bool
    {
        return (bool) Db::value(
            "SELECT 1 FROM productos WHERE empresa_id = ? AND codigo = ? AND producto_id <> ? LIMIT 1",
            [$empresaId, $codigo, $exceptoId ?? 0]
        );
    }
}

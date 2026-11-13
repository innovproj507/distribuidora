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
        'supplier_part_no', 'imagen_principal', 'unidad_medida', 'controla_vencimiento',
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

    /**
     * Reparte una cantidad en unidades base entre las presentaciones, de mayor a menor, sin mostrar
     * las que no alcanzan: 300 huevos (bandeja 30, caja 360) → "10 bandejas"; 290 → "9 bandejas y 20 unidades";
     * 120 lb (saco 50) → "2 sacos y 20 lb". '' si no hay presentaciones o no alcanza para la más chica.
     * Misma lógica que desglose() en public/movil/app.js.
     *
     * @param array $presentaciones filas con nombre + factor_conversion (o factor)
     */
    public static function desglose(float $cantidad, array $presentaciones, ?string $unidad = 'und'): string
    {
        $pres = [];
        foreach ($presentaciones as $p) {
            $f = (float) ($p['factor_conversion'] ?? $p['factor'] ?? 0);
            if ($f > 1) $pres[] = ['nombre' => (string) $p['nombre'], 'factor' => $f];
        }
        usort($pres, fn ($a, $b) => $b['factor'] <=> $a['factor']);
        $resto = round($cantidad, 2);
        if (!$pres || $resto < end($pres)['factor']) return '';

        $partes = [];
        foreach ($pres as $p) {
            $n = (int) floor(($resto + 0.0001) / $p['factor']);
            if ($n > 0) {
                $nom = mb_strtolower($p['nombre']);
                $partes[] = $n . ' ' . ($n > 1 && !str_ends_with($nom, 's') ? $nom . (preg_match('/[aeiouáéíóú]$/u', $nom) ? 's' : 'es') : $nom);
                $resto = round($resto - $n * $p['factor'], 2);
            }
        }
        if ($resto > 0) {
            $um = self::unidadMedida($unidad);
            $partes[] = rtrim(rtrim(number_format($resto, 2, '.', ''), '0'), '.') . ' ' . ($um === 'und' ? ($resto == 1 ? 'unidad' : 'unidades') : self::abreviatura($um));
        }
        $ultimo = array_pop($partes);
        return $partes ? implode(', ', $partes) . ' y ' . $ultimo : $ultimo;
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

        // Producto nuevo: el mismo precio en todas las tiendas; después cada una cambia el suyo
        foreach ($precios as $tipo => $monto) {
            if ($monto > 0) {
                PrecioTienda::fijar($productoId, $tipo, (float) $monto, null);
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
     * Precios y costo son los de la tienda $sucursalId (por defecto la activa): no toca la otra tienda.
     *
     * @param array $precios [tipo => monto]; monto 0 borra ese precio
     */
    public static function actualizar(int $productoId, int $empresaId, array $datos, array $precios, ?int $sucursalId = null): void
    {
        $sucursalId ??= PrecioTienda::sucursalActual();
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

        // El costo es de cada tienda (inventario de sus depósitos): se fija aparte, no en la ficha general
        $costoNuevo = array_key_exists('costo', $datos) ? (float) $datos['costo'] : null;
        unset($datos['costo']);

        Db::update('productos', array_intersect_key($datos, array_flip(self::CAMPOS)) + [
            'fecha_modificacion' => date('Y-m-d H:i:s'),
        ], ['producto_id' => $productoId]);

        // Las ventas toman el costo del inventario de la tienda
        if ($costoNuevo !== null && $sucursalId && abs($costoNuevo - PrecioTienda::costo($productoId, $sucursalId)) > 0.00001) {
            PrecioTienda::fijarCosto($productoId, $sucursalId, $costoNuevo);
        }

        foreach ($precios as $tipo => $monto) {
            PrecioTienda::fijar($productoId, $tipo, (float) $monto, $sucursalId);
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
        Db::query("DELETE x FROM productos_unidades_precios x JOIN productos_unidades u ON u.unidad_id = x.unidad_id WHERE u.producto_id = ?", [$productoId]);
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
            // Sin costo escrito se usa el de la tienda del depósito: con 0 el costo promedio se diluía y quedaba en $0
            $costo = PrecioTienda::costo($productoId, PrecioTienda::sucursalDeDeposito($depositoId));
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
     * Guarda las presentaciones (caja, docena...) del formulario: actualiza en su lugar las que traen
     * unidad_id (conservan el id y el precio de la otra tienda), crea las nuevas (con el mismo precio en
     * todas las tiendas) y borra las que ya no vienen. El precio escrito es el de la tienda $sucursalId.
     * $unidades: [{unidad_id?, nombre, factor_conversion, precio_a, precio_b, precio_c, codigo_barras}]
     */
    public static function guardarUnidades(int $productoId, array $unidades, ?int $sucursalId = null): void
    {
        $sucursalId ??= PrecioTienda::sucursalActual();
        $actuales = array_map('intval', array_column(Db::all("SELECT unidad_id FROM productos_unidades WHERE producto_id = ?", [$productoId]), 'unidad_id'));
        $quedan   = [];

        foreach ($unidades as $u) {
            $nombre = trim((string) ($u['nombre'] ?? ''));
            if ($nombre === '') continue;

            $datos = [
                'nombre'            => $nombre,
                'factor_conversion' => max(0.001, (float) ($u['factor_conversion'] ?? 1)),
                'codigo_barras'     => trim((string) ($u['codigo_barras'] ?? '')),
            ];
            $a = (float) ($u['precio_a'] ?? 0);
            $b = (float) ($u['precio_b'] ?? 0);
            $c = (float) ($u['precio_c'] ?? 0);

            $id = (int) ($u['unidad_id'] ?? 0);
            if ($id && in_array($id, $actuales, true) && !in_array($id, $quedan, true)) {
                Db::update('productos_unidades', $datos, ['unidad_id' => $id]);
                PrecioTienda::fijarUnidad($id, $sucursalId ?: null, $a, $b, $c);
            } else {
                $id = Db::insert('productos_unidades', $datos + ['producto_id' => $productoId, 'precio_a' => 0, 'precio_b' => 0, 'precio_c' => 0]);
                PrecioTienda::fijarUnidad($id, null, $a, $b, $c);
            }
            $quedan[] = $id;
        }

        $borrar = array_diff($actuales, $quedan);
        if ($borrar) {
            $marcas = implode(',', array_fill(0, count($borrar), '?'));
            Db::query("DELETE FROM productos_unidades_precios WHERE unidad_id IN ({$marcas})", array_values($borrar));
            Db::query("DELETE FROM productos_unidades WHERE unidad_id IN ({$marcas})", array_values($borrar));
        }
    }

    public static function buscar(int $productoId, int $empresaId): ?array
    {
        return Db::row("SELECT * FROM productos WHERE producto_id = ? AND empresa_id = ?", [$productoId, $empresaId]);
    }

    /** Precio de la tienda (por defecto la activa). Precio 0 o negativo borra ese tipo de precio. */
    public static function reemplazarPrecio(int $productoId, string $tipo, float $monto, ?int $sucursalId = null): void
    {
        PrecioTienda::fijar($productoId, $tipo, $monto, $sucursalId ?? PrecioTienda::sucursalActual());
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

<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Pedido de la tienda a la persona que compra en la ciudad: la tienda arma la lista con los
 * productos del sistema (en caja, bulto, unidad…), se envía por WhatsApp y queda como compra
 * pendiente (compras.origen = 'pedido') en el depósito de la tienda, lista para recibirla
 * cuando llegue la mercancía. Lo que no existe en el sistema va como "producto nuevo" solo en
 * el texto y en las notas de la compra.
 */
final class PedidoTiendaService
{
    /** Días de cobertura para la sugerencia (demanda diaria de los últimos 30 días × días). */
    public const DIAS_SUGERENCIA = 7;

    /** Marcador del número de compra dentro del texto; el servidor lo reemplaza al guardar. */
    public const MARCA_NUMERO = '{NUMERO}';

    /**
     * Catálogo para armar el pedido: productos activos con su categoría, stock de la tienda
     * (todos sus depósitos), mínimo y ventas de 30 días en la tienda, presentaciones y una
     * cantidad sugerida en unidades base.
     */
    public static function catalogo(int $empresaId, int $sucursalId): array
    {
        $filas = Db::all(
            "SELECT p.producto_id, p.codigo, p.nombre, p.codigo_barras, p.supplier_part_no, p.unidad_medida,
                    p.imagen_principal, COALESCE(c.nombre, 'Sin categoría') AS categoria,
                    COALESCE(SUM(i.existencia), 0) AS existencia,
                    COALESCE(MAX(i.minimo), 0) AS minimo,
                    COALESCE((SELECT SUM(m.cantidad) FROM inventario_movimientos m
                              JOIN inventario im ON im.inventario_id = m.inventario_id
                              JOIN depositos dm ON dm.deposito_id = im.deposito_id
                              WHERE im.producto_id = p.producto_id AND dm.sucursal_id = ?
                                AND m.tipo = 'salida' AND m.referencia_tipo = 'ventas'
                                AND m.fecha_registro >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS vendido30
             FROM productos p
             LEFT JOIN categorias_productos c ON c.categoria_id = p.categoria_id
             LEFT JOIN inventario i ON i.producto_id = p.producto_id
                  AND i.deposito_id IN (SELECT deposito_id FROM depositos WHERE sucursal_id = ? AND estado = 'activo')
             WHERE p.empresa_id = ? AND p.estado = 'activo'
             GROUP BY p.producto_id
             ORDER BY categoria, p.nombre",
            [$sucursalId, $sucursalId, $empresaId]
        );
        if (!$filas) return [];

        $ids = array_map(fn ($f) => (int) $f['producto_id'], $filas);
        $in  = implode(',', $ids);

        $pres = [];
        foreach (Db::all("SELECT producto_id, unidad_id, nombre, factor_conversion FROM productos_unidades
                          WHERE producto_id IN ({$in}) AND factor_conversion > 1 ORDER BY factor_conversion") as $u) {
            $pres[(int) $u['producto_id']][] = ['id' => (int) $u['unidad_id'], 'n' => $u['nombre'], 'f' => (float) $u['factor_conversion']];
        }

        // Presentación con que esta tienda pidió/compró la última vez cada producto
        $ultima = [];
        foreach (Db::all(
            "SELECT d.producto_id, d.presentacion
             FROM compras_detalle d JOIN compras c ON c.compra_id = d.compra_id
             WHERE c.sucursal_id = ? AND c.estado <> 'cancelada' AND d.presentacion IS NOT NULL
             ORDER BY c.compra_id DESC", [$sucursalId]) as $r) {
            $ultima[(int) $r['producto_id']] ??= mb_strtolower((string) $r['presentacion']);
        }

        $out = [];
        foreach ($filas as $f) {
            $pid      = (int) $f['producto_id'];
            $exist    = (float) $f['existencia'];
            $minimo   = (float) $f['minimo'];
            $demanda  = (float) $f['vendido30'] / 30;
            $objetivo = max($demanda * self::DIAS_SUGERENCIA, $minimo);
            $sug      = $objetivo > 0 ? max(0.0, ceil($objetivo - $exist)) : 0.0;

            $p = $pres[$pid] ?? [];
            $porDefecto = 0;
            if ($p) {
                $porDefecto = end($p)['id']; // la mayor (caja, bulto…)
                foreach ($p as $u) {
                    if (isset($ultima[$pid]) && mb_strtolower($u['n']) === $ultima[$pid]) $porDefecto = $u['id'];
                }
            }

            $out[] = [
                'id'   => $pid,
                'c'    => $f['codigo'],
                'n'    => $f['nombre'],
                'cat'  => $f['categoria'],
                'b'    => trim(($f['codigo_barras'] ?? '') . ' ' . ($f['supplier_part_no'] ?? '')),
                'um'   => ProductoService::abreviatura((string) ($f['unidad_medida'] ?: 'und')),
                'img'  => $f['imagen_principal'] ? '/assets/uploads/' . $f['imagen_principal'] : null,
                'ex'   => round($exist, 2),
                'min'  => round($minimo, 2),
                'v30'  => round((float) $f['vendido30'], 2),
                'sug'  => $sug,
                'pres' => $p,
                'pd'   => $porDefecto,
            ];
        }
        return $out;
    }

    /**
     * Guarda el pedido como compra pendiente en el depósito de la tienda.
     *
     * @param array  $lineas [{producto_id, cantidad, unidad_id?, nota?}] cantidad en la presentación elegida
     * @param array  $nuevos [{nombre, cantidad, unidad}] productos que no están en el sistema
     * @param string $texto  mensaje de WhatsApp con MARCA_NUMERO donde va el número de la compra
     * @param array  $ctx    empresa_id, sucursal_id, deposito_id
     * @return array{compra_id:int, numero:string, texto:string}
     */
    public static function crear(array $lineas, array $nuevos, string $notas, string $texto, array $ctx): array
    {
        $deposito = (int) $ctx['deposito_id'];
        SucursalService::exigirDepositoDeSucursal($deposito, (int) $ctx['sucursal_id']);

        $items  = [];
        $vistos = [];
        foreach ($lineas as $l) {
            $pid  = (int) ($l['producto_id'] ?? 0);
            $cant = round((float) ($l['cantidad'] ?? 0), 2);
            if ($pid <= 0 || $cant <= 0) continue;
            if (isset($vistos[$pid])) throw new \RuntimeException('Un producto está repetido en el pedido.');
            $vistos[$pid] = true;

            if (!Db::value("SELECT COUNT(*) FROM productos WHERE producto_id = ? AND empresa_id = ? AND estado = 'activo'", [$pid, $ctx['empresa_id']])) {
                throw new \RuntimeException('Un producto del pedido ya no existe o está inactivo. Recarga la página.');
            }
            $unidadId = (int) ($l['unidad_id'] ?? 0);
            $factor   = 1.0;
            $presNom  = '';
            if ($unidadId > 0) {
                $u = Db::row("SELECT nombre, factor_conversion FROM productos_unidades WHERE unidad_id = ? AND producto_id = ?", [$unidadId, $pid]);
                if (!$u) throw new \RuntimeException('La presentación elegida no es de ese producto. Recarga la página.');
                $factor  = max(1.0, (float) $u['factor_conversion']);
                $presNom = (string) $u['nombre'];
            }
            $costoBase = PrecioTienda::costo($pid, (int) $ctx['sucursal_id']);
            $items[] = [
                'producto_id' => $pid,
                'cantidad'    => $cant,                 // en la presentación
                'costo'       => $costoBase * $factor,  // costo de la presentación (estimado)
                'unidad_id'   => $unidadId ?: null,
                'pres_nombre' => $presNom,
                'pres_factor' => $factor,
                'deposito_id' => $deposito,
            ];
        }

        $extra = [];
        foreach ($nuevos as $n) {
            $nombre = mb_substr(trim((string) ($n['nombre'] ?? '')), 0, 120);
            $cant   = round((float) ($n['cantidad'] ?? 0), 2);
            if ($nombre === '' || $cant <= 0) continue;
            $extra[] = '- ' . rtrim(rtrim(number_format($cant, 2, '.', ''), '0'), '.') . ' ' . mb_substr(trim((string) ($n['unidad'] ?? '')), 0, 20) . ' ' . $nombre;
        }
        if (!$items) {
            throw new \RuntimeException($extra
                ? 'Agrega al menos un producto del sistema; los productos nuevos van junto a ellos (o créalos primero en Productos).'
                : 'Agrega al menos un producto con cantidad.');
        }

        $nota = 'Pedido de la tienda por WhatsApp (' . date('d/m/Y') . ')';
        if (trim($notas) !== '') $nota .= "\nNota: " . mb_substr(trim($notas), 0, 500);
        if ($extra) $nota .= "\nProductos nuevos (no están en el sistema):\n" . implode("\n", $extra);

        $compraId = CompraService::registrar([
            'guardar_como_pendiente' => true,
            'notas'                  => $nota,
            'items'                  => $items,
        ], ['empresa_id' => (int) $ctx['empresa_id'], 'sucursal_id' => (int) $ctx['sucursal_id'], 'deposito_id' => $deposito]);

        $numero = (string) Db::value("SELECT numero_factura FROM compras WHERE compra_id = ?", [$compraId]);
        $texto  = mb_substr(str_replace(self::MARCA_NUMERO, $numero, $texto), 0, 20000);
        Db::update('compras', ['origen' => 'pedido', 'pedido_texto' => $texto], ['compra_id' => $compraId]);

        return ['compra_id' => $compraId, 'numero' => $numero, 'texto' => $texto];
    }

    /** Últimos pedidos de la tienda con su estado. */
    public static function historial(int $empresaId, int $sucursalId, int $limite = 30): array
    {
        return Db::all(
            "SELECT c.compra_id, c.numero_factura, c.estado, c.fecha_registro, c.fecha_compra, u.username,
                    (SELECT COUNT(*) FROM compras_detalle d WHERE d.compra_id = c.compra_id) AS lineas
             FROM compras c LEFT JOIN users u ON u.id = c.usuario_id
             WHERE c.empresa_id = ? AND c.sucursal_id = ? AND c.origen = 'pedido'
             ORDER BY c.compra_id DESC LIMIT " . max(1, min(100, $limite)),
            [$empresaId, $sucursalId]
        );
    }

    /** Líneas de un pedido para repetirlo (en la presentación en que se pidió) y su texto. */
    public static function detalle(int $compraId, int $empresaId, int $sucursalId): ?array
    {
        $c = Db::row(
            "SELECT compra_id, numero_factura, estado, pedido_texto FROM compras
             WHERE compra_id = ? AND empresa_id = ? AND sucursal_id = ? AND origen = 'pedido'",
            [$compraId, $empresaId, $sucursalId]
        );
        if (!$c) return null;
        $c['lineas'] = [];
        foreach (Db::all("SELECT d.producto_id, d.cantidad, d.presentacion, d.factor_presentacion FROM compras_detalle d WHERE d.compra_id = ?", [$compraId]) as $d) {
            $factor   = max(1.0, (float) $d['factor_presentacion']);
            $unidadId = null;
            if ($factor > 1 && $d['presentacion'] !== null) {
                $unidadId = Db::value(
                    "SELECT unidad_id FROM productos_unidades WHERE producto_id = ? AND LOWER(nombre) = LOWER(?) LIMIT 1",
                    [(int) $d['producto_id'], $d['presentacion']]
                );
            }
            $c['lineas'][] = [
                'producto_id' => (int) $d['producto_id'],
                'unidad_id'   => $unidadId ? (int) $unidadId : 0,
                'cantidad'    => round((float) $d['cantidad'] / ($unidadId ? $factor : 1), 2),
            ];
        }
        return $c;
    }

    /** A quién se le manda el pedido (comprador en la ciudad). */
    public static function destinatario(int $empresaId): array
    {
        $r = Db::row("SELECT pedido_contacto, pedido_telefono FROM companies WHERE empresa_id = ?", [$empresaId]) ?? [];
        $tel = (string) ($r['pedido_telefono'] ?? '');
        return [
            'contacto' => (string) ($r['pedido_contacto'] ?? ''),
            'telefono' => $tel,
            'wa'       => $tel !== '' ? EstadoCuenta::telefonoWhatsapp($tel) : '',
        ];
    }
}

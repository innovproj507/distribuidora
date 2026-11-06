<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\AuditService;
use App\Libraries\Auth;
use App\Libraries\Db;
use App\Libraries\InventarioService;
use App\Libraries\TrasladoService;

/**
 * JSON propio de la PWA (/m/api/*): lista paginada de productos con el stock del depósito
 * activo y registro de entradas/salidas. El CSRF lo valida el filtro global (X-CSRF-TOKEN).
 */
class MovilApiController extends BaseController
{
    private const POR_PAGINA = 40;

    /** GET /m/api/productos?q=&categoria=&stock_bajo=1&offset= */
    public function productos()
    {
        if (!Auth::can('pos.usar') && !Auth::can('productos.ver') && !Auth::can('inventario.ver') && !Auth::can('compras.crear') && !Auth::can('traslados.crear')) {
            return $this->json(['error' => 'No tienes permiso para ver productos.'], 403);
        }

        $q           = trim((string) ($this->request->getGet('q') ?? ''));
        $categoriaId = (int) ($this->request->getGet('categoria') ?? 0);
        $soloBajo    = $this->request->getGet('stock_bajo') === '1';
        $offset      = max(0, (int) ($this->request->getGet('offset') ?? 0));

        $where  = ["p.estado = 'activo'", 'p.empresa_id = ?'];
        $params = [$this->depositoId(), $this->empresaId()];

        if ($q !== '') {
            // Código / código de barras con la frase completa, o nombre con todas las palabras
            $terminos   = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [$q];
            $condNombre = implode(' AND ', array_fill(0, count($terminos), 'p.nombre LIKE ?'));
            $where[]    = "(p.codigo LIKE ? OR p.codigo_barras LIKE ? OR ({$condNombre}))";
            $params[]   = "%{$q}%";
            $params[]   = "%{$q}%";
            foreach ($terminos as $t) {
                $params[] = "%{$t}%";
            }
        }
        if ($categoriaId > 0) {
            $where[]  = 'p.categoria_id = ?';
            $params[] = $categoriaId;
        }
        if ($soloBajo) {
            $where[] = 'COALESCE(i.existencia, 0) <= COALESCE(i.minimo, 0)';
        }

        $limite = self::POR_PAGINA + 1; // uno extra para saber si hay más
        $filas  = Db::all(
            "SELECT p.producto_id, p.codigo, p.codigo_barras, p.nombre, p.costo, p.maneja_lotes,
                    p.imagen_principal, c.nombre AS categoria_nombre,
                    COALESCE(i.existencia, 0) AS existencia, COALESCE(i.minimo, 0) AS minimo, COALESCE(i.ubicacion, '') AS ubicacion,
                    COALESCE(pa.precio, 0) AS precio_a,
                    COALESCE(pb.precio, 0) AS precio_b,
                    COALESCE(pc.precio, 0) AS precio_c
             FROM productos p
             LEFT JOIN inventario i ON i.producto_id = p.producto_id AND i.deposito_id = ?
             LEFT JOIN categorias_productos c ON c.categoria_id = p.categoria_id
             LEFT JOIN precios_productos pa ON pa.producto_id = p.producto_id AND pa.tipo_precio = 'a'
             LEFT JOIN precios_productos pb ON pb.producto_id = p.producto_id AND pb.tipo_precio = 'b'
             LEFT JOIN precios_productos pc ON pc.producto_id = p.producto_id AND pc.tipo_precio = 'c'
             WHERE " . implode(' AND ', $where) . "
             ORDER BY p.nombre
             LIMIT {$limite} OFFSET {$offset}",
            $params
        );

        $hayMas = count($filas) > self::POR_PAGINA;
        $filas  = array_slice($filas, 0, self::POR_PAGINA);

        // Presentaciones (docena, caja…) de los productos de la página; precio de la presentación
        // = el propio de la presentación, o precio unitario × factor si no tiene.
        $presentaciones = [];
        if ($filas) {
            $ids = array_map(fn ($f) => (int) $f['producto_id'], $filas);
            $mapaPrecio = array_column($filas, 'precio_a', 'producto_id');
            foreach (Db::all(
                "SELECT unidad_id, producto_id, nombre, factor_conversion, precio_a FROM productos_unidades
                 WHERE producto_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ") ORDER BY factor_conversion",
                $ids
            ) as $u) {
                $factor = (float) $u['factor_conversion'];
                $propio = (float) $u['precio_a'];
                $presentaciones[(int) $u['producto_id']][] = [
                    'unidad_id' => (int) $u['unidad_id'],
                    'nombre'    => $u['nombre'],
                    'factor'    => $factor,
                    'precio'    => $propio > 0 ? $propio : round((float) ($mapaPrecio[$u['producto_id']] ?? 0) * $factor, 2),
                ];
            }
        }

        $verCosto  = Auth::can('productos.editar') || Auth::can('compras.crear');
        $productos = array_map(function (array $p) use ($verCosto, $presentaciones): array {
            $item = [
                'producto_id'      => (int) $p['producto_id'],
                'codigo'           => $p['codigo'],
                'codigo_barras'    => $p['codigo_barras'],
                'nombre'           => $p['nombre'],
                'categoria_nombre' => $p['categoria_nombre'],
                'imagen'           => $p['imagen_principal'] ? '/assets/uploads/' . $p['imagen_principal'] : null,
                'maneja_lotes'     => (bool) $p['maneja_lotes'],
                'existencia'       => (float) $p['existencia'],
                'minimo'           => (float) $p['minimo'],
                'ubicacion'        => (string) $p['ubicacion'],
                'precio_a'         => (float) $p['precio_a'],
                'precio_b'         => (float) $p['precio_b'],
                'precio_c'         => (float) $p['precio_c'],
                'unidades'         => $presentaciones[(int) $p['producto_id']] ?? [],
            ];
            if ($verCosto) {
                $item['costo'] = (float) $p['costo'];
            }
            return $item;
        }, $filas);

        return $this->json([
            'productos'   => $productos,
            'hay_mas'     => $hayMas,
            'offset'      => $offset + count($productos),
            'deposito_id' => $this->depositoId(),
        ]);
    }

    /** POST /m/api/movimiento {producto_id, tipo: entrada|salida, cantidad, motivo} */
    public function movimiento()
    {
        if (!Auth::can('inventario.ajustar')) {
            return $this->json(['error' => 'No tienes permiso para ajustar inventario.'], 403);
        }

        $data       = $this->jsonInput();
        $productoId = (int) ($data['producto_id'] ?? 0);
        $tipo       = ($data['tipo'] ?? '') === 'salida' ? 'salida' : 'entrada';
        $cantidad   = (float) ($data['cantidad'] ?? 0);
        $motivo     = trim((string) ($data['motivo'] ?? ''));
        $depositoId = $this->depositoId();

        if ($cantidad <= 0) {
            return $this->json(['error' => 'La cantidad debe ser mayor que cero.'], 422);
        }

        $producto = Db::row("SELECT producto_id, nombre, maneja_lotes FROM productos WHERE producto_id = ? AND empresa_id = ?", [$productoId, $this->empresaId()]);
        if (!$producto || !$depositoId) {
            return $this->json(['error' => 'Producto o depósito no encontrado.'], 404);
        }
        if ($producto['maneja_lotes']) {
            // El stock de productos con lotes se mueve solo desde la gestión de lotes
            return $this->json(['error' => 'Este producto maneja lotes; registra el movimiento desde la versión web.'], 422);
        }

        try {
            Db::begin();

            $inv = Db::row("SELECT inventario_id, existencia FROM inventario WHERE producto_id = ? AND deposito_id = ? FOR UPDATE", [$productoId, $depositoId]);
            if (!$inv) {
                $inv = ['inventario_id' => Db::insert('inventario', ['producto_id' => $productoId, 'deposito_id' => $depositoId, 'existencia' => 0]), 'existencia' => 0];
            }

            if ($tipo === 'salida' && $cantidad > (float) $inv['existencia']) {
                Db::rollback();
                return $this->json(['error' => 'No hay stock suficiente. Existencia actual: ' . (float) $inv['existencia']], 422);
            }

            $existencia = InventarioService::registrar(
                (int) $inv['inventario_id'], $tipo, $cantidad, null, 'movil',
                $motivo !== '' ? $motivo : ($tipo === 'entrada' ? 'Entrada desde móvil' : 'Salida desde móvil')
            );

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            log_message('error', 'Movimiento móvil: ' . $e->getMessage());
            return $this->json(['error' => 'No se pudo registrar el movimiento.'], 500);
        }

        AuditService::log(
            'inventario.ajustar',
            ucfirst($tipo) . " de {$cantidad} uds de {$producto['nombre']} (móvil)",
            $productoId, 'producto',
            ['tipo' => $tipo, 'cantidad' => $cantidad, 'deposito_id' => $depositoId, 'existencia' => $existencia],
            $this->empresaId()
        );

        return $this->json(['success' => true, 'existencia' => $existencia]);
    }

    /** GET /m/api/venta/{id} — cabecera, ítems y pagos de una venta de la sucursal. */
    public function venta(int $id)
    {
        if (!Auth::can('ventas.ver')) return $this->json(['error' => 'Sin permiso.'], 403);
        $v = Db::row(
            "SELECT v.venta_id, v.numero_factura, v.subtotal, v.itbms, v.descuento, v.total, v.estado, v.forma_pago, v.fecha, c.nombre AS cliente_nombre
             FROM ventas v LEFT JOIN clientes c ON v.cliente_id = c.cliente_id WHERE v.venta_id = ? AND v.sucursal_id = ?",
            [$id, $this->sucursalId()]
        );
        if (!$v) return $this->json(['error' => 'Venta no encontrada.'], 404);
        $v['items'] = Db::all(
            "SELECT p.nombre, d.cantidad, d.unidad_nombre, d.precio, d.total_linea FROM ventas_detalle d JOIN productos p ON p.producto_id = d.producto_id WHERE d.venta_id = ?",
            [$id]
        );
        $v['pagos'] = Db::all("SELECT forma_pago, monto FROM ventas_pagos WHERE venta_id = ?", [$id]);
        return $this->json($v);
    }

    /** POST /m/api/compra/{id}/recibir {recibidas: {detalle_id: cantidad}} — entra al depósito de la compra. */
    public function recibirCompra(int $id)
    {
        if (!Auth::can('compras.recibir')) return $this->json(['error' => 'Sin permiso.'], 403);
        $compra = Db::row("SELECT * FROM compras WHERE compra_id = ? AND sucursal_id = ?", [$id, $this->sucursalId()]);
        if (!$compra || in_array($compra['estado'], ['recibida', 'cancelada'], true)) {
            return $this->json(['error' => 'Esta compra no admite recepción.'], 422);
        }
        $recibidas = [];
        foreach ((array) ($this->jsonInput()['recibidas'] ?? []) as $detalleId => $cantidad) {
            $recibidas[(int) $detalleId] = (float) $cantidad;
        }
        try {
            Db::begin();
            $estado = \App\Libraries\CompraService::recibirPendientes($id, $compra, $recibidas, (int) $compra['deposito_id']);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->json(['error' => $e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo registrar la recepción.'], 422);
        }
        AuditService::log('compras.recibir', "Recepción {$estado} — Compra #{$id} (móvil)", $id, 'compra', ['estado' => $estado], $this->empresaId());
        return $this->json(['success' => true, 'estado' => $estado]);
    }

    /** POST /m/api/compra/{id}/reparto {destino_id, cantidades: {producto_id: cantidad}} */
    public function repartoCompra(int $id)
    {
        if (!Auth::can('traslados.crear')) return $this->json(['error' => 'Sin permiso.'], 403);
        if (!Db::value("SELECT 1 FROM compras WHERE compra_id = ? AND sucursal_id = ?", [$id, $this->sucursalId()])) {
            return $this->json(['error' => 'Compra no encontrada.'], 404);
        }
        $data      = $this->jsonInput();
        $cantidades = [];
        foreach ((array) ($data['cantidades'] ?? []) as $productoId => $cantidad) {
            $cantidades[(int) $productoId] = (float) $cantidad;
        }
        try {
            Db::begin();
            $trasladoId = \App\Libraries\PedidoCiudadService::enviarReparto($id, (int) ($data['destino_id'] ?? 0), $cantidades, ['empresa_id' => $this->empresaId()]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->json(['error' => $e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo crear el traslado.'], 422);
        }
        AuditService::log('inventario.traslado', "Traslado #{$trasladoId} enviado desde la compra #{$id} (móvil)", $trasladoId, 'traslado', [], $this->empresaId());
        return $this->json(['success' => true, 'traslado_id' => $trasladoId]);
    }

    /** Presentaciones de un producto con el mismo formato que /m/api/productos. */
    private function presentacionesDe(int $productoId): array
    {
        $precioUnidad = (float) Db::value("SELECT precio FROM precios_productos WHERE producto_id = ? AND tipo_precio = 'a'", [$productoId]);
        return array_map(function (array $u) use ($precioUnidad): array {
            $factor = (float) $u['factor_conversion'];
            $propio = (float) $u['precio_a'];
            return [
                'unidad_id' => (int) $u['unidad_id'], 'nombre' => $u['nombre'], 'factor' => $factor,
                'precio'    => $propio > 0 ? $propio : round($precioUnidad * $factor, 2),
            ];
        }, Db::all("SELECT unidad_id, nombre, factor_conversion, precio_a FROM productos_unidades WHERE producto_id = ? ORDER BY factor_conversion", [$productoId]));
    }

    /** POST /m/api/producto/{id}/presentacion {nombre, factor, precio?} */
    public function agregarPresentacion(int $id)
    {
        if (!Auth::can('productos.editar')) return $this->json(['error' => 'Sin permiso.'], 403);
        if (!Db::value("SELECT 1 FROM productos WHERE producto_id = ? AND empresa_id = ?", [$id, $this->empresaId()])) {
            return $this->json(['error' => 'Producto no encontrado.'], 404);
        }
        $d      = $this->jsonInput();
        $nombre = trim((string) ($d['nombre'] ?? ''));
        $factor = round((float) ($d['factor'] ?? 0), 2);
        if ($nombre === '' || mb_strlen($nombre) > 50 || $factor <= 0) {
            return $this->json(['error' => 'Indica el nombre y cuántas unidades trae (mayor que cero).'], 422);
        }
        if (Db::value("SELECT 1 FROM productos_unidades WHERE producto_id = ? AND LOWER(nombre) = LOWER(?)", [$id, $nombre])) {
            return $this->json(['error' => "Ya existe una presentación \"{$nombre}\" para este producto."], 422);
        }
        Db::insert('productos_unidades', [
            'producto_id' => $id, 'nombre' => $nombre, 'factor_conversion' => $factor,
            'precio_a' => max(0, round((float) ($d['precio'] ?? 0), 2)), 'precio_b' => 0, 'precio_c' => 0, 'codigo_barras' => '',
        ]);
        AuditService::log('productos.editar', "Presentación \"{$nombre}\" (×{$factor}) agregada (móvil)", $id, 'producto', [], $this->empresaId());
        return $this->json(['success' => true, 'unidades' => $this->presentacionesDe($id)]);
    }

    /** POST /m/api/presentacion/{id}/eliminar */
    public function quitarPresentacion(int $id)
    {
        if (!Auth::can('productos.editar')) return $this->json(['error' => 'Sin permiso.'], 403);
        $u = Db::row(
            "SELECT u.unidad_id, u.producto_id, u.nombre FROM productos_unidades u JOIN productos p ON p.producto_id = u.producto_id
             WHERE u.unidad_id = ? AND p.empresa_id = ?", [$id, $this->empresaId()]
        );
        if (!$u) return $this->json(['error' => 'Presentación no encontrada.'], 404);
        Db::query("DELETE FROM productos_unidades WHERE unidad_id = ?", [$id]);
        AuditService::log('productos.editar', "Presentación \"{$u['nombre']}\" quitada (móvil)", (int) $u['producto_id'], 'producto', [], $this->empresaId());
        return $this->json(['success' => true, 'unidades' => $this->presentacionesDe((int) $u['producto_id'])]);
    }

    /** POST /m/api/conteo {alcance: todo|ubicacion|categoria, ubicacion?, categoria_id?, descripcion?} — sobre el depósito activo. */
    public function crearConteo()
    {
        if (!Auth::can('conteos.crear')) return $this->json(['error' => 'Sin permiso.'], 403);
        $d       = $this->jsonInput();
        $alcance = (string) ($d['alcance'] ?? 'todo');
        $filtro  = [];
        $texto   = 'Conteo completo';
        if ($alcance === 'ubicacion') {
            $filtro['ubicacion'] = (string) ($d['ubicacion'] ?? '');
            $texto = 'Conteo de ' . ($filtro['ubicacion'] !== '' ? $filtro['ubicacion'] : 'productos sin ubicación');
        } elseif ($alcance === 'categoria') {
            $cat = Db::row("SELECT categoria_id, nombre FROM categorias_productos WHERE categoria_id = ? AND empresa_id = ?", [(int) ($d['categoria_id'] ?? 0), $this->empresaId()]);
            if (!$cat) return $this->json(['error' => 'Categoría no encontrada.'], 422);
            $filtro['categoria_id'] = (int) $cat['categoria_id'];
            $texto = 'Conteo de ' . $cat['nombre'];
        }
        $descripcion = trim((string) ($d['descripcion'] ?? '')) ?: $texto . ' · ' . date('d/m/Y');

        try {
            Db::begin();
            $id = \App\Libraries\ConteoService::crear($this->empresaId(), $this->sucursalId(), $this->depositoId(), $alcance === 'todo' ? 'completo' : 'ciclico', $descripcion, $filtro);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->json(['error' => $e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo abrir el conteo.'], 422);
        }
        AuditService::log('inventario.conteo', "Conteo #{$id} abierto (móvil): {$descripcion}", $id, 'conteo', [], $this->empresaId());
        return $this->json(['success' => true, 'conteo_id' => $id]);
    }

    /** POST /m/api/conteo/{id}/linea {producto_id, cantidad|null, ubicacion?} */
    public function anotarConteo(int $id)
    {
        if (!Auth::can('conteos.crear')) return $this->json(['error' => 'Sin permiso.'], 403);
        if (!\App\Libraries\ConteoService::buscar($id, $this->empresaId(), $this->sucursalId())) return $this->json(['error' => 'Conteo no encontrado.'], 404);
        $d        = $this->jsonInput();
        $cantidad = array_key_exists('cantidad', $d) && $d['cantidad'] !== null && $d['cantidad'] !== '' ? (float) $d['cantidad'] : null;
        try {
            Db::begin();
            $linea = \App\Libraries\ConteoService::anotar($id, (int) ($d['producto_id'] ?? 0), $cantidad, isset($d['ubicacion']) ? (string) $d['ubicacion'] : null);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->json(['error' => $e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo guardar.'], 422);
        }
        return $this->json(['success' => true, 'linea' => $linea, 'resumen' => \App\Libraries\ConteoService::resumen($id)]);
    }

    /** POST /m/api/conteo/{id}/aplicar — lleva el stock a lo contado y cierra. */
    public function aplicarConteo(int $id)
    {
        if (!Auth::can('conteos.reconciliar')) return $this->json(['error' => 'Sin permiso para aplicar ajustes.'], 403);
        try {
            Db::begin();
            $n = \App\Libraries\ConteoService::aplicarAjustes($id, $this->empresaId(), $this->sucursalId());
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->json(['error' => $e instanceof \RuntimeException ? $e->getMessage() : 'No se pudieron aplicar los ajustes.'], 422);
        }
        AuditService::log('inventario.ajustar', "Ajuste de inventario por conteo #{$id}: {$n} líneas ajustadas (móvil)", $id, 'conteo', ['diferencias' => $n], $this->empresaId());
        return $this->json(['success' => true, 'ajustadas' => $n]);
    }

    /** POST /m/api/conteo/{id}/cancelar */
    public function cancelarConteo(int $id)
    {
        if (!Auth::can('conteos.crear')) return $this->json(['error' => 'Sin permiso.'], 403);
        try {
            \App\Libraries\ConteoService::cancelar($id, $this->empresaId(), $this->sucursalId());
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
        return $this->json(['success' => true]);
    }

    /** GET /m/api/cliente/{id}/pendientes — facturas a crédito por cobrar y últimos recibos. */
    public function clientePendientes(int $id)
    {
        if (!Auth::can('clientes.ver')) return $this->json(['error' => 'Sin permiso.'], 403);
        $cliente = Db::row("SELECT cliente_id, nombre, telefono, saldo_pendiente FROM clientes WHERE cliente_id = ? AND empresa_id = ?", [$id, $this->empresaId()]);
        if (!$cliente) return $this->json(['error' => 'Cliente no encontrado.'], 404);
        return $this->json([
            'cliente'  => $cliente,
            'facturas' => \App\Libraries\CobroService::pendientes($id, $this->empresaId()),
            'recibos'  => Db::all("SELECT numero, monto, forma_pago, fecha FROM cobros WHERE cliente_id = ? ORDER BY cobro_id DESC LIMIT 5", [$id]),
        ]);
    }

    /** POST /m/api/cobro {cliente_id, monto, forma_pago, referencia?, venta_id?} */
    public function crearCobro()
    {
        if (!Auth::can('ventas.crear') && !Auth::can('pos.usar')) return $this->json(['error' => 'Sin permiso para registrar cobros.'], 403);
        $d = $this->jsonInput();
        try {
            Db::begin();
            $res = \App\Libraries\CobroService::registrar(
                (int) ($d['cliente_id'] ?? 0), (float) ($d['monto'] ?? 0), (string) ($d['forma_pago'] ?? 'EFECTIVO'),
                isset($d['referencia']) ? (string) $d['referencia'] : null, null,
                ['empresa_id' => $this->empresaId(), 'sucursal_id' => $this->sucursalId()],
                !empty($d['venta_id']) ? (int) $d['venta_id'] : null
            );
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->json(['error' => $e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo registrar el cobro.'], 422);
        }
        AuditService::log('ventas.cobro', "Cobro {$res['numero']} de \${$res['monto']} (móvil)", $res['cobro_id'], 'cobro', ['cliente_id' => (int) ($d['cliente_id'] ?? 0)], $this->empresaId());
        return $this->json(['success' => true] + $res);
    }

    /** POST /m/api/pedido {lineas: [{producto_id, cantidades: {deposito_id: cantidad}}]} */
    public function crearPedido()
    {
        if (!Auth::can('compras.crear')) return $this->json(['error' => 'Sin permiso.'], 403);
        try {
            Db::begin();
            $ordenes = \App\Libraries\PedidoCiudadService::crearOrdenes(
                (array) ($this->jsonInput()['lineas'] ?? []),
                ['empresa_id' => $this->empresaId(), 'sucursal_id' => $this->sucursalId()]
            );
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->json(['error' => $e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo crear el pedido.'], 422);
        }
        foreach ($ordenes as $o) {
            AuditService::log('compras.crear', "Orden #{$o['compra_id']} del pedido consolidado (móvil)", $o['compra_id'], 'compra', [], $this->empresaId());
        }
        return $this->json(['success' => true, 'ordenes' => $ordenes]);
    }

    /** GET /m/api/traslado/{id} — líneas de un traslado de la sucursal (origen o destino). */
    public function traslado(int $id)
    {
        if (!Auth::can('traslados.ver')) return $this->json(['error' => 'Sin permiso.'], 403);
        $suc = $this->sucursalId();
        $t = Db::row(
            "SELECT t.traslado_id, t.numero, t.estado, t.notas, dd.sucursal_id AS destino_sucursal_id
             FROM traslados t
             JOIN depositos do ON t.deposito_origen_id = do.deposito_id
             JOIN depositos dd ON t.deposito_destino_id = dd.deposito_id
             WHERE t.traslado_id = ? AND (do.sucursal_id = ? OR dd.sucursal_id = ?)",
            [$id, $suc, $suc]
        );
        if (!$t) return $this->json(['error' => 'Traslado no encontrado.'], 404);
        $t['items'] = Db::all(
            "SELECT d.producto_id, p.nombre, p.codigo, d.cantidad, d.cantidad_recibida
             FROM traslados_detalle d JOIN productos p ON p.producto_id = d.producto_id WHERE d.traslado_id = ? ORDER BY p.nombre",
            [$id]
        );
        return $this->json($t);
    }

    /** POST /m/api/traslado {destino_id, notas, items[{producto_id, cantidad}]} — sale del depósito activo. */
    public function enviarTraslado()
    {
        if (!Auth::can('traslados.crear')) return $this->json(['error' => 'Sin permiso.'], 403);
        $data = $this->jsonInput();
        try {
            Db::begin();
            $id = TrasladoService::enviar(
                $this->depositoId(), (int) ($data['destino_id'] ?? 0), $data['items'] ?? [],
                trim((string) ($data['notas'] ?? '')),
                ['empresa_id' => $this->empresaId(), 'sucursal_id' => $this->sucursalId()]
            );
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->json(['error' => $e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo registrar el traslado.'], 422);
        }
        AuditService::log('inventario.traslado', "Traslado #{$id} enviado (móvil)", $id, 'traslado', [], $this->empresaId());
        return $this->json(['success' => true, 'traslado_id' => $id]);
    }

    /** POST /m/api/traslado/{id}/recibir {recibidas: {producto_id: cantidad}} — solo la sucursal destino. */
    public function recibirTraslado(int $id)
    {
        if (!Auth::can('traslados.crear') && !Auth::can('traslados.aprobar')) return $this->json(['error' => 'Sin permiso.'], 403);
        $esDestino = Db::value(
            "SELECT 1 FROM traslados t JOIN depositos dd ON t.deposito_destino_id = dd.deposito_id WHERE t.traslado_id = ? AND dd.sucursal_id = ?",
            [$id, $this->sucursalId()]
        );
        if (!$esDestino) return $this->json(['error' => 'Este traslado no va hacia tu tienda.'], 403);

        $recibidas = [];
        foreach ((array) ($this->jsonInput()['recibidas'] ?? []) as $productoId => $cantidad) {
            $recibidas[(int) $productoId] = (float) $cantidad;
        }
        try {
            Db::begin();
            TrasladoService::recibir($id, ['empresa_id' => $this->empresaId(), 'sucursal_id' => $this->sucursalId()], $recibidas);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->json(['error' => $e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo recibir el traslado.'], 422);
        }
        AuditService::log('inventario.traslado', "Traslado #{$id} recibido (móvil)", $id, 'traslado', ['recibidas' => $recibidas], $this->empresaId());
        return $this->json(['success' => true]);
    }

    /** POST /m/api/alerta/{id}/resolver */
    public function resolverAlerta(int $id)
    {
        if (!Auth::can('alertas.resolver')) return $this->json(['error' => 'Sin permiso.'], 403);
        Db::query("UPDATE alertas_inventario SET estado = 'resuelta', fecha_lectura = NOW() WHERE alerta_id = ? AND empresa_id = ?", [$id, $this->empresaId()]);
        return $this->json(['ok' => true]);
    }
}

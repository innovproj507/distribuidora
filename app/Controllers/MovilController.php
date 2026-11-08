<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Auth;
use App\Libraries\Db;

/**
 * Páginas de la PWA móvil (/m). Las operaciones (vender, crear cliente, compra)
 * reutilizan los endpoints JSON del sistema web; aquí solo se arman las pantallas.
 */
class MovilController extends BaseController
{
    // ── Públicas ─────────────────────────────────────────────────────────────

    public function login()
    {
        if (Auth::check()) {
            return redirect()->to('/m');
        }
        return $this->render('movil.login', ['page_title' => 'Iniciar sesión']);
    }

    public function logout()
    {
        Auth::logout();
        return redirect()->to('/m/login');
    }

    /** Service worker servido desde /m/sw.js para que su alcance sea /m/. */
    public function serviceWorker()
    {
        return $this->response
            ->setHeader('Content-Type', 'application/javascript; charset=utf-8')
            ->setHeader('Cache-Control', 'no-cache')
            ->setBody((string) file_get_contents(FCPATH . 'movil/sw.js'));
    }

    public function manifest()
    {
        return $this->response
            ->setHeader('Content-Type', 'application/manifest+json; charset=utf-8')
            ->setBody(json_encode([
                'name'             => env('APP_NAME', 'Distribuidora'),
                'short_name'       => 'Distribuidora',
                'description'      => 'Ventas, inventario, clientes y compras',
                'id'               => '/m/',
                'start_url'        => '/m/',
                'scope'            => '/m/',
                'display'          => 'standalone',
                'orientation'      => 'portrait',
                'background_color' => '#F2F4F9',
                'theme_color'      => '#0F2E6B',
                'icons'            => [
                    ['src' => '/movil/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                    ['src' => '/movil/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    // ── Pantallas ────────────────────────────────────────────────────────────

    public function inicio()
    {
        if (!Auth::can('dashboard.ver')) {
            return redirect()->to('/m/vender');
        }

        $sucursalId = $this->sucursalId();

        $salud = Db::row(
            "SELECT
                SUM(CASE WHEN COALESCE(i.existencia, 0) <= 0 THEN 1 ELSE 0 END) AS agotado,
                SUM(CASE WHEN i.existencia > 0 AND i.existencia <= i.minimo THEN 1 ELSE 0 END) AS bajo,
                SUM(CASE WHEN i.existencia > i.minimo THEN 1 ELSE 0 END) AS ok,
                COALESCE(SUM(GREATEST(i.existencia, 0) * i.costo_promedio), 0) AS valor
             FROM productos p
             LEFT JOIN inventario i ON i.producto_id = p.producto_id AND i.deposito_id = ?
             WHERE p.estado = 'activo' AND p.empresa_id = ?",
            [$this->depositoId(), $this->empresaId()]
        );

        return $this->render('movil.inicio', [
            'page_title'  => 'Inicio',
            'headerTitle' => $this->session->get('empresa_actual')['nombre_comercial'] ?? 'Inicio',
            'activeTab'   => 'inicio',
            'ventasHoy'   => Db::row(
                "SELECT COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total
                 FROM ventas WHERE DATE(fecha) = CURDATE() AND estado != 'anulada' AND sucursal_id = ?",
                [$sucursalId]
            ),
            'ventasMes' => (float) Db::value(
                "SELECT COALESCE(SUM(total), 0) FROM ventas
                 WHERE fecha >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND estado != 'anulada' AND sucursal_id = ?",
                [$sucursalId]
            ),
            'salud' => [
                'ok'      => (int) $salud['ok'],
                'bajo'    => (int) $salud['bajo'],
                'agotado' => (int) $salud['agotado'],
            ],
            'valorInventario' => (float) $salud['valor'],
            'ultimasVentas'   => Db::all(
                "SELECT v.venta_id, v.numero_factura, v.total, v.estado, v.forma_pago, v.fecha, c.nombre AS cliente_nombre
                 FROM ventas v
                 LEFT JOIN clientes c ON v.cliente_id = c.cliente_id
                 WHERE v.sucursal_id = ?
                 ORDER BY v.fecha DESC LIMIT 5",
                [$sucursalId]
            ),
        ]);
    }

    public function vender()
    {
        if ($r = $this->exigir('pos.usar', '/m/mas')) return $r;

        return $this->render('movil.vender', [
            'page_title'  => 'Vender',
            'headerTitle' => 'Punto de venta',
            'activeTab'   => 'vender',
            'clientes'    => Db::all(
                "SELECT cliente_id, codigo, nombre, telefono, limite_credito, saldo_pendiente, dias_credito
                 FROM clientes WHERE empresa_id = ? AND estado = 'activo' ORDER BY nombre",
                [$this->empresaId()]
            ),
            'itbmsRate'         => (float) env('ITBMS_RATE', 0.07),
            'puedeCrearCliente' => Auth::can('clientes.crear'),
        ]);
    }

    public function inventario()
    {
        if (!Auth::can('productos.ver') && !Auth::can('inventario.ver')) {
            return $this->exigir('productos.ver', '/m/mas');
        }

        return $this->render('movil.inventario', [
            'page_title'    => 'Inventario',
            'headerTitle'   => 'Inventario',
            'activeTab'     => 'inventario',
            'categorias'    => Db::all(
                "SELECT c.categoria_id, c.nombre, COUNT(p.producto_id) AS total
                 FROM categorias_productos c
                 JOIN productos p ON p.categoria_id = c.categoria_id AND p.estado = 'activo'
                 WHERE c.empresa_id = ?
                 GROUP BY c.categoria_id, c.nombre
                 ORDER BY c.nombre",
                [$this->empresaId()]
            ),
            'soloStockBajo' => $this->request->getGet('stock_bajo') !== null,
            'porVencer'     => $this->request->getGet('por_vencer') !== null,
            'puedeAjustar'  => Auth::can('inventario.ajustar'),
            'puedeEditar'   => Auth::can('productos.editar'),
            'puedeCrear'    => Auth::can('productos.crear'),
        ]);
    }

    /** Ficha del producto: precio, stock por depósito, kardex y lotes del depósito activo. */
    public function producto(int $id)
    {
        if (!Auth::can('productos.ver') && !Auth::can('inventario.ver')) {
            return $this->exigir('productos.ver', '/m/mas');
        }
        $p = $this->productoDeEmpresa($id);
        if (!$p) {
            $this->error('Producto no encontrado.');
            return redirect()->to('/m/inventario');
        }

        $depositoId = $this->depositoId();
        $inv        = Db::row("SELECT * FROM inventario WHERE producto_id = ? AND deposito_id = ?", [$id, $depositoId]);
        $sucursales = array_map('intval', array_column($this->session->get('sucursales_disponibles') ?? [], 'sucursal_id')) ?: [0];

        return $this->render('movil.producto', [
            'page_title'  => $p['nombre'],
            'headerTitle' => 'Producto',
            'activeTab'   => 'inventario',
            'p'           => $p,
            'precio'      => (float) (Db::value("SELECT precio FROM precios_productos WHERE producto_id = ? AND tipo_precio = 'a'", [$id]) ?? 0),
            'inv'         => $inv,
            'unidades'    => Db::all("SELECT nombre, factor_conversion, precio_a FROM productos_unidades WHERE producto_id = ? ORDER BY factor_conversion", [$id]),
            // Stock en todas las tiendas que el usuario puede ver
            'stocks'      => Db::all(
                "SELECT d.deposito_id, d.nombre AS deposito, b.nombre AS sucursal, i.existencia, i.minimo, i.ubicacion, i.fecha_vencimiento
                 FROM inventario i JOIN depositos d ON d.deposito_id = i.deposito_id JOIN branches b ON b.sucursal_id = d.sucursal_id
                 WHERE i.producto_id = ? AND d.estado = 'activo' AND b.sucursal_id IN (" . implode(',', array_fill(0, count($sucursales), '?')) . ")
                 ORDER BY b.es_principal DESC, b.nombre, d.es_principal DESC",
                array_merge([$id], $sucursales)
            ),
            'movimientos' => $inv ? Db::all(
                "SELECT m.tipo, m.cantidad, m.saldo_nuevo, m.motivo, m.fecha_registro, u.username
                 FROM inventario_movimientos m LEFT JOIN users u ON u.id = m.usuario_id
                 WHERE m.inventario_id = ? ORDER BY m.movimiento_id DESC LIMIT 15",
                [$inv['inventario_id']]
            ) : [],
            'lotes' => $p['maneja_lotes'] ? Db::all(
                "SELECT numero_lote, cantidad_actual, fecha_vencimiento, DATEDIFF(fecha_vencimiento, CURDATE()) AS dias
                 FROM lotes WHERE producto_id = ? AND deposito_id = ? AND cantidad_actual > 0 ORDER BY fecha_vencimiento",
                [$id, $depositoId]
            ) : [],
            'vendido30' => (float) Db::value(
                "SELECT COALESCE(SUM(d.cantidad * COALESCE(NULLIF(d.factor, 0), 1)), 0)
                 FROM ventas_detalle d JOIN ventas v ON v.venta_id = d.venta_id
                 WHERE d.producto_id = ? AND v.sucursal_id = ? AND v.estado <> 'anulada' AND v.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
                [$id, $this->sucursalId()]
            ),
            'verCosto'     => ver_costos(),
            'puedeEditar'  => self::puedeEditarProducto(),
            'puedeAjustar' => Auth::can('inventario.ajustar'),
        ]);
    }

    public function editarProducto(int $id)
    {
        if (!self::puedeEditarProducto()) return $this->exigir('productos.editar', "/m/productos/{$id}");
        $p = $this->productoDeEmpresa($id);
        if (!$p) {
            $this->error('Producto no encontrado.');
            return redirect()->to('/m/inventario');
        }

        $empresaId = $this->empresaId();
        return $this->render('movil.producto_editar', [
            // Sin productos.editar (vendedor): solo nombre, foto y ubicación
            'completo'    => Auth::can('productos.editar'),
            'page_title'  => 'Editar producto',
            'headerTitle' => 'Editar producto',
            'activeTab'   => 'inventario',
            'p'           => $p,
            'precio'      => (float) (Db::value("SELECT precio FROM precios_productos WHERE producto_id = ? AND tipo_precio = 'a'", [$id]) ?? 0),
            'inv'         => Db::row("SELECT minimo, ubicacion, fecha_vencimiento FROM inventario WHERE producto_id = ? AND deposito_id = ?", [$id, $this->depositoId()]),
            'categorias'  => Db::all("SELECT categoria_id, nombre FROM categorias_productos WHERE empresa_id = ? ORDER BY nombre", [$empresaId]),
            'proveedores' => Db::all("SELECT proveedor_id, nombre FROM proveedores WHERE empresa_id = ? AND estado = 'activo' ORDER BY nombre", [$empresaId]),
            'marcas'      => array_column(Db::all("SELECT DISTINCT nombre FROM marcas WHERE empresa_id = ? ORDER BY nombre", [$empresaId]), 'nombre'),
        ]);
    }

    /**
     * POST: guarda solo los campos del formulario móvil (no toca maneja_lotes, unidades, precios
     * B/C ni la descripción), con las mismas reglas de ProductoService que la web.
     */
    public function guardarProducto(int $id)
    {
        if (!self::puedeEditarProducto()) return $this->exigir('productos.editar', "/m/productos/{$id}");
        $p = $this->productoDeEmpresa($id);
        if (!$p) {
            $this->error('Producto no encontrado.');
            return redirect()->to('/m/inventario');
        }

        $post = fn (string $k) => trim((string) ($this->request->getPost($k) ?? ''));

        // Edición básica (vendedor): solo nombre, foto y ubicación en su depósito; lo demás se ignora
        if (!Auth::can('productos.editar')) {
            $nombre = $post('nombre');
            if ($nombre === '') {
                $this->error('El nombre es obligatorio.');
                return redirect()->to("/m/productos/{$id}/editar")->withInput();
            }
            $datos = ['nombre' => mb_substr($nombre, 0, 200)];
            if ($imagen = $this->subirImagen('imagen', 'productos')) {
                $datos['imagen_principal'] = $imagen;
            }
            try {
                $vence = \App\Libraries\InventarioService::fechaVencimiento($post('fecha_vencimiento'));
            } catch (\DomainException $e) {
                $this->error($e->getMessage());
                return redirect()->to("/m/productos/{$id}/editar")->withInput();
            }
            Db::begin();
            \App\Libraries\ProductoService::actualizar($id, (int) $this->empresaId(), $datos, []);
            if ($inventarioId = \App\Libraries\InventarioService::inventarioId($id, $this->depositoId())) {
                Db::update('inventario', ['ubicacion' => $post('ubicacion') ?: null, 'fecha_vencimiento' => $vence], ['inventario_id' => $inventarioId]);
            }
            Db::commit();
            \App\Libraries\AuditService::log('productos.editar', "Producto actualizado desde móvil (básico): {$p['nombre']} → {$datos['nombre']}", $id, 'producto', [], $this->empresaId());
            $this->success('Producto actualizado.');
            return redirect()->to("/m/productos/{$id}");
        }

        $nombre = $post('nombre');
        $codigo = $post('codigo');
        if ($nombre === '' || $codigo === '') {
            $this->error('El nombre y el código son obligatorios.');
            return redirect()->to("/m/productos/{$id}/editar")->withInput();
        }

        $categoriaId = (int) $post('categoria_id');
        $proveedorId = (int) $post('proveedor_id');
        $datos = [
            'nombre'        => mb_substr($nombre, 0, 200),
            'codigo'        => mb_substr($codigo, 0, 50),
            'codigo_barras' => $post('codigo_barras') ?: null,
            'marca'         => $post('marca') ?: null,
            'categoria_id'  => $categoriaId && Db::value("SELECT 1 FROM categorias_productos WHERE categoria_id = ? AND empresa_id = ?", [$categoriaId, $this->empresaId()]) ? $categoriaId : null,
            'proveedor_id'  => $proveedorId && Db::value("SELECT 1 FROM proveedores WHERE proveedor_id = ? AND empresa_id = ?", [$proveedorId, $this->empresaId()]) ? $proveedorId : null,
            'costo'         => ver_costos() ? max(0, (float) $post('costo')) : null,
            'estado'        => $post('estado') === 'inactivo' ? 'inactivo' : 'activo',
        ];
        if ($datos['costo'] === null) {
            unset($datos['costo']); // sin costos.ver se conserva el costo actual
        }
        if ($imagen = $this->subirImagen('imagen', 'productos')) {
            $datos['imagen_principal'] = $imagen;
        }
        $precio = $post('precio');

        try {
            $vence = \App\Libraries\InventarioService::fechaVencimiento($post('fecha_vencimiento'));
            Db::begin();
            \App\Libraries\ProductoService::actualizar($id, (int) $this->empresaId(), $datos, $precio !== '' ? ['a' => max(0, (float) $precio)] : []);

            // Mínimo, ubicación y vencimiento son por depósito: los del depósito activo
            if ($inventarioId = \App\Libraries\InventarioService::inventarioId($id, $this->depositoId())) {
                Db::update('inventario', [
                    'minimo'            => max(0, (float) $post('minimo')),
                    'ubicacion'         => $post('ubicacion') ?: null,
                    'fecha_vencimiento' => $vence,
                ], ['inventario_id' => $inventarioId]);
            }
            Db::commit();
        } catch (\DomainException $e) {
            Db::rollback();
            $this->error($e->getMessage());
            return redirect()->to("/m/productos/{$id}/editar")->withInput();
        }

        \App\Libraries\AuditService::log('productos.editar', "Producto actualizado desde móvil: {$datos['nombre']} (SKU: {$datos['codigo']})", $id, 'producto', [], $this->empresaId());
        $this->success('Producto actualizado.');
        return redirect()->to("/m/productos/{$id}");
    }

    /** Formulario de producto nuevo (productos.crear: admin, gerente, encargado). */
    public function nuevoProducto()
    {
        if ($r = $this->exigir('productos.crear', '/m/inventario')) return $r;
        $empresaId = (int) $this->empresaId();

        return $this->render('movil.producto_nuevo', [
            'page_title'  => 'Nuevo producto',
            'headerTitle' => 'Nuevo producto',
            'activeTab'   => 'inventario',
            'categorias'  => Db::all("SELECT categoria_id, nombre FROM categorias_productos WHERE empresa_id = ? ORDER BY nombre", [$empresaId]),
            'proveedores' => Db::all("SELECT proveedor_id, nombre FROM proveedores WHERE empresa_id = ? AND estado = 'activo' ORDER BY nombre", [$empresaId]),
            'marcas'      => array_column(Db::all("SELECT DISTINCT nombre FROM marcas WHERE empresa_id = ? ORDER BY nombre", [$empresaId]), 'nombre'),
            'codigo'      => \App\Libraries\ProductoService::siguienteCodigo($empresaId, null),
            'codigoBarras' => trim((string) ($this->request->getGet('barras') ?? '')),
        ]);
    }

    /**
     * POST: crea el producto con ProductoService::crear (igual que la web). El stock inicial entra
     * solo al depósito activo (con kardex); el resto de depósitos queda en 0.
     */
    public function crearProducto()
    {
        if ($r = $this->exigir('productos.crear', '/m/inventario')) return $r;
        $empresaId  = (int) $this->empresaId();
        $depositoId = $this->depositoId();
        $post       = fn (string $k) => trim((string) ($this->request->getPost($k) ?? ''));

        $nombre = $post('nombre');
        if ($nombre === '') {
            $this->error('El nombre es obligatorio.');
            return redirect()->to('/m/productos/nuevo')->withInput();
        }
        $categoriaId = (int) $post('categoria_id');
        $categoriaId = $categoriaId && Db::value("SELECT 1 FROM categorias_productos WHERE categoria_id = ? AND empresa_id = ?", [$categoriaId, $empresaId]) ? $categoriaId : null;
        $proveedorId = (int) $post('proveedor_id');
        $minimo      = max(0, (float) $post('minimo'));
        $stock       = max(0, (float) $post('stock'));

        $datos = [
            'codigo'        => mb_substr($post('codigo'), 0, 50) ?: \App\Libraries\ProductoService::siguienteCodigo($empresaId, $categoriaId),
            'nombre'        => mb_substr($nombre, 0, 200),
            'categoria_id'  => $categoriaId,
            'codigo_barras' => $post('codigo_barras') ?: null,
            'marca'         => $post('marca') ?: null,
            'proveedor_id'  => $proveedorId && Db::value("SELECT 1 FROM proveedores WHERE proveedor_id = ? AND empresa_id = ?", [$proveedorId, $empresaId]) ? $proveedorId : null,
            'costo'         => ver_costos() ? max(0, (float) $post('costo')) : 0.0, // el encargado crea sin costo
            'itbms'         => 0.0,
            'stock_minimo'  => $minimo,
            'maneja_lotes'  => 0,
            'estado'        => 'activo',
        ];
        if ($imagen = $this->subirImagen('imagen', 'productos')) {
            $datos['imagen_principal'] = $imagen;
        }
        $precio = max(0, (float) $post('precio'));

        try {
            $vence = \App\Libraries\InventarioService::fechaVencimiento($post('fecha_vencimiento'));
            Db::begin();
            $productoId = \App\Libraries\ProductoService::crear($empresaId, $datos, $precio > 0 ? ['a' => $precio] : [], $depositoId ? [[$depositoId, $stock]] : []);
            if ($depositoId && ($inventarioId = \App\Libraries\InventarioService::inventarioId($productoId, $depositoId))) {
                Db::update('inventario', ['ubicacion' => $post('ubicacion') ?: null, 'fecha_vencimiento' => $vence], ['inventario_id' => $inventarioId]);
            }
            Db::commit();
        } catch (\DomainException $e) {
            Db::rollback();
            $this->error($e->getMessage());
            return redirect()->to('/m/productos/nuevo')->withInput();
        }

        \App\Libraries\AuditService::log('productos.crear', "Producto creado desde móvil: {$datos['nombre']} (SKU: {$datos['codigo']})", $productoId, 'producto', [], $empresaId);
        $this->success("Producto {$datos['codigo']} creado." . ($precio <= 0 ? ' Ponle precio para poder venderlo.' : ''));
        return redirect()->to("/m/productos/{$productoId}");
    }

    /** Edición completa (productos.editar) o básica: nombre, foto y ubicación (productos.editar_basico). */
    public static function puedeEditarProducto(): bool
    {
        return Auth::can('productos.editar') || Auth::can('productos.editar_basico');
    }

    private function productoDeEmpresa(int $id): ?array
    {
        return Db::row(
            "SELECT p.*, c.nombre AS categoria_nombre, pr.nombre AS proveedor_nombre
             FROM productos p
             LEFT JOIN categorias_productos c ON c.categoria_id = p.categoria_id
             LEFT JOIN proveedores pr ON pr.proveedor_id = p.proveedor_id
             WHERE p.producto_id = ? AND p.empresa_id = ?",
            [$id, $this->empresaId()]
        );
    }

    /** Caja del cajero en la PWA: abrir, resumen, entradas/salidas y cierre (usa las rutas /caja/*). */
    public function caja()
    {
        if ($r = $this->exigir('caja.usar', '/m/mas')) return $r;
        $caja = \App\Libraries\CajaService::abierta((int) Auth::id(), (int) $this->sucursalId());

        return $this->render('movil.caja', [
            'page_title'  => 'Caja',
            'headerTitle' => 'Mi caja',
            'activeTab'   => 'mas',
            'resumen'     => $caja ? \App\Libraries\CajaService::resumen((int) $caja['caja_id']) : null,
            'ultima'      => $caja ? null : Db::row(
                "SELECT caja_id, cerrada_en, efectivo_esperado, efectivo_contado, diferencia_efectivo FROM cajas
                 WHERE usuario_id = ? AND sucursal_id = ? AND estado = 'cerrada' ORDER BY caja_id DESC LIMIT 1",
                [Auth::id(), $this->sucursalId()]
            ),
            'formas'      => \App\Libraries\CajaService::FORMAS,
        ]);
    }

    public function clientes()
    {
        if ($r = $this->exigir('clientes.ver', '/m/mas')) return $r;

        return $this->render('movil.clientes', [
            'page_title'  => 'Clientes',
            'headerTitle' => 'Clientes',
            'activeTab'   => 'clientes',
            'clientes'    => Db::all(
                "SELECT cliente_id, codigo, nombre, telefono, email, ruc, limite_credito, saldo_pendiente, dias_credito
                 FROM clientes WHERE empresa_id = ? AND estado = 'activo' ORDER BY nombre",
                [$this->empresaId()]
            ),
            'puedeCrear' => Auth::can('clientes.crear'),
        ]);
    }

    public function compras()
    {
        if ($r = $this->exigir('compras.ver', '/m/mas')) return $r;

        return $this->render('movil.compras', [
            'page_title'  => 'Compras',
            'headerTitle' => 'Compras',
            'activeTab'   => 'mas',
            'compras'     => Db::all(
                "SELECT c.compra_id, c.numero_factura, c.numero_factura_proveedor, c.monto_total,
                        c.estado, c.fecha_compra, p.nombre AS proveedor_nombre,
                        (SELECT COUNT(*) FROM compras_detalle d WHERE d.compra_id = c.compra_id) AS items
                 FROM compras c
                 LEFT JOIN proveedores p ON c.proveedor_id = p.proveedor_id
                 WHERE c.empresa_id = ? AND c.sucursal_id = ?
                 ORDER BY c.compra_id DESC LIMIT 30",
                [$this->empresaId(), $this->sucursalId()]
            ),
            'proveedores' => Db::all(
                "SELECT proveedor_id, nombre FROM proveedores WHERE empresa_id = ? AND estado = 'activo' ORDER BY nombre",
                [$this->empresaId()]
            ),
            'puedeCrear' => Auth::can('compras.crear'),
        ]);
    }

    public function ventas()
    {
        if ($r = $this->exigir('ventas.ver', '/m/mas')) return $r;

        return $this->render('movil.ventas', [
            'page_title'  => 'Ventas',
            'headerTitle' => 'Historial de ventas',
            'activeTab'   => 'mas',
            'ventas'      => Db::all(
                "SELECT v.venta_id, v.numero_factura, v.total, v.estado, v.forma_pago, v.fecha, c.nombre AS cliente_nombre
                 FROM ventas v LEFT JOIN clientes c ON v.cliente_id = c.cliente_id
                 WHERE v.sucursal_id = ? ORDER BY v.fecha DESC LIMIT 60",
                [$this->sucursalId()]
            ),
        ]);
    }

    public function cotizaciones()
    {
        if ($r = $this->exigir('cotizaciones.ver', '/m/mas')) return $r;

        return $this->render('movil.cotizaciones', [
            'page_title'   => 'Cotizaciones',
            'headerTitle'  => 'Cotizaciones',
            'activeTab'    => 'mas',
            'cotizaciones' => Db::all(
                "SELECT q.cotizacion_id, q.numero, q.token, q.total, q.estado, q.fecha, q.fecha_vencimiento, c.nombre AS cliente_nombre
                 FROM cotizaciones q LEFT JOIN clientes c ON q.cliente_id = c.cliente_id
                 WHERE q.sucursal_id = ? ORDER BY q.fecha DESC LIMIT 40",
                [$this->sucursalId()]
            ),
        ]);
    }

    public function cobrar()
    {
        if ($r = $this->exigir('clientes.ver', '/m/mas')) return $r;

        return $this->render('movil.cobrar', [
            'page_title'  => 'Por cobrar',
            'headerTitle' => 'Cuentas por cobrar',
            'activeTab'   => 'mas',
            'cuentas'     => Db::all(
                "SELECT c.cliente_id, c.nombre, c.telefono, c.limite_credito, c.saldo_pendiente,
                        (SELECT MAX(fecha) FROM ventas WHERE cliente_id = c.cliente_id AND forma_pago = 'CREDITO') AS ultima
                 FROM clientes c WHERE c.saldo_pendiente > 0 AND c.empresa_id = ? ORDER BY c.saldo_pendiente DESC",
                [$this->empresaId()]
            ),
            'cobrosHoy'   => Db::all(
                "SELECT forma_pago, COUNT(*) AS recibos, SUM(monto) AS total FROM cobros
                 WHERE sucursal_id = ? AND DATE(fecha) = CURDATE() GROUP BY forma_pago ORDER BY total DESC",
                [$this->sucursalId()]
            ),
            'puedeCobrar' => Auth::can('ventas.crear') || Auth::can('pos.usar'),
        ]);
    }

    public function alertas()
    {
        if ($r = $this->exigir('alertas.ver', '/m/mas')) return $r;

        (new \App\Libraries\AlertaInventarioService($this->empresaId()))->regenerar();

        return $this->render('movil.alertas', [
            'page_title'    => 'Alertas',
            'headerTitle'   => 'Alertas de inventario',
            'activeTab'     => 'mas',
            'alertas'       => Db::all(
                "SELECT a.alerta_id, a.tipo, a.mensaje, a.prioridad, p.nombre AS producto_nombre, d.nombre AS deposito_nombre
                 FROM alertas_inventario a
                 LEFT JOIN productos p ON a.producto_id = p.producto_id
                 LEFT JOIN depositos d ON a.deposito_id = d.deposito_id
                 WHERE a.empresa_id = ? AND a.estado = 'activa'
                 ORDER BY FIELD(a.prioridad, 'critica', 'alta', 'media', 'baja') LIMIT 100",
                [$this->empresaId()]
            ),
            'puedeResolver' => Auth::can('alertas.resolver'),
        ]);
    }

    public function compra(int $id)
    {
        if ($r = $this->exigir('compras.ver', '/m/mas')) return $r;

        $compra = Db::row(
            "SELECT c.*, p.nombre AS proveedor_nombre FROM compras c LEFT JOIN proveedores p ON p.proveedor_id = c.proveedor_id
             WHERE c.compra_id = ? AND c.sucursal_id = ?",
            [$id, $this->sucursalId()]
        );
        if (!$compra) {
            return redirect()->to('/m/compras');
        }

        return $this->render('movil.compra', [
            'page_title'   => 'Compra',
            'headerTitle'  => 'Compra ' . ($compra['numero_factura_proveedor'] ?: $compra['numero_factura']),
            'activeTab'    => 'mas',
            'compra'       => $compra,
            'lineas'       => $this->lineasConPresentaciones($id),
            'porEnviar'    => \App\Libraries\PedidoCiudadService::porEnviar($id),
            'puedeRecibir' => Auth::can('compras.recibir') && !in_array($compra['estado'], ['recibida', 'cancelada'], true),
            'puedeEnviar'  => Auth::can('traslados.crear'),
        ]);
    }

    public function conteos()
    {
        if ($r = $this->exigir('conteos.ver', '/m/mas')) return $r;

        return $this->render('movil.conteos', [
            'page_title'   => 'Conteo físico',
            'headerTitle'  => 'Conteo físico',
            'activeTab'    => 'mas',
            'conteos'      => Db::all(
                "SELECT c.conteo_id, c.descripcion, c.tipo, c.estado, c.fecha_inicio, c.total_productos, c.total_diferencias, d.nombre AS deposito,
                        (SELECT COUNT(*) FROM conteo_detalle x WHERE x.conteo_id = c.conteo_id AND x.estado <> 'pendiente') AS contados
                 FROM inventario_conteos c JOIN depositos d ON d.deposito_id = c.deposito_id
                 WHERE c.sucursal_id = ? AND c.estado IN ('en_proceso', 'completado')
                 ORDER BY (c.estado = 'en_proceso') DESC, c.conteo_id DESC LIMIT 15",
                [$this->sucursalId()]
            ),
            'ubicaciones'  => \App\Libraries\ConteoService::ubicaciones($this->depositoId()),
            'categorias'   => Db::all(
                "SELECT c.categoria_id, c.nombre, COUNT(*) AS total FROM categorias_productos c
                 JOIN productos p ON p.categoria_id = c.categoria_id AND p.estado = 'activo'
                 WHERE c.empresa_id = ? GROUP BY c.categoria_id, c.nombre ORDER BY c.nombre",
                [$this->empresaId()]
            ),
            'puedeCrear'   => Auth::can('conteos.crear'),
        ]);
    }

    public function conteo(int $id)
    {
        if ($r = $this->exigir('conteos.ver', '/m/mas')) return $r;

        $conteo = \App\Libraries\ConteoService::buscar($id, $this->empresaId(), $this->sucursalId());
        if (!$conteo || $conteo['estado'] === 'cancelado') {
            return redirect()->to('/m/conteos');
        }
        $abierto = $conteo['estado'] === 'en_proceso';

        $items = Db::all(
            "SELECT cd.producto_id, cd.cantidad_sistema, cd.cantidad_contada, cd.diferencia, cd.estado, cd.costo_unitario,
                    p.nombre, p.codigo, p.codigo_barras, COALESCE(i.ubicacion, '') AS ubicacion, i.fecha_vencimiento
             FROM conteo_detalle cd
             JOIN productos p ON p.producto_id = cd.producto_id
             LEFT JOIN inventario i ON i.producto_id = cd.producto_id AND i.deposito_id = ?
             WHERE cd.conteo_id = ?
             ORDER BY (COALESCE(i.ubicacion, '') = ''), i.ubicacion, p.nombre",
            [$conteo['deposito_id'], $id]
        );
        // Conteo a ciegas: mientras el producto no esté contado no se envía lo que dice el sistema
        $verCostos = ver_costos();
        $items = array_map(function (array $i) use ($abierto, $verCostos): array {
            $contado = $i['estado'] !== 'pendiente';
            return [
                'id'         => (int) $i['producto_id'],
                'nombre'     => $i['nombre'],
                'codigo'     => $i['codigo'],
                'barras'     => $i['codigo_barras'],
                'ubicacion'  => $i['ubicacion'],
                'costo'      => $verCostos ? (float) $i['costo_unitario'] : 0.0,
                'estado'     => $i['estado'],
                'contada'    => $i['cantidad_contada'] !== null ? (float) $i['cantidad_contada'] : null,
                'sistema'    => $contado || !$abierto ? (float) $i['cantidad_sistema'] : null,
                'diferencia' => $contado && $i['diferencia'] !== null ? (float) $i['diferencia'] : null,
                'vence'      => $i['fecha_vencimiento'],
                'venceInfo'  => \App\Libraries\InventarioService::estadoVencimiento($i['fecha_vencimiento']),
            ];
        }, $items);

        return $this->render('movil.conteo', [
            'page_title'     => 'Conteo',
            'headerTitle'    => $conteo['descripcion'] ?: 'Conteo #' . $id,
            'activeTab'      => 'mas',
            'conteo'         => $conteo,
            'abierto'        => $abierto,
            'items'          => $items,
            'resumen'        => \App\Libraries\ConteoService::resumen($id),
            'ubicaciones'    => array_values(array_filter(array_column(\App\Libraries\ConteoService::ubicaciones((int) $conteo['deposito_id']), 'ubicacion'))),
            'puedeContar'    => Auth::can('conteos.crear'),
            'puedeAjustar'   => Auth::can('conteos.reconciliar'),
        ]);
    }

    /** Líneas de una compra con las presentaciones (docena, caja…) de cada producto, para contar en bultos. */
    private function lineasConPresentaciones(int $compraId): array
    {
        $lineas = Db::all(
            "SELECT d.detalle_id, d.producto_id, d.cantidad, d.cantidad_recibida, d.costo, p.nombre, p.codigo
             FROM compras_detalle d JOIN productos p ON p.producto_id = d.producto_id WHERE d.compra_id = ? ORDER BY p.nombre",
            [$compraId]
        );
        if (!$lineas) return [];

        $ids = array_map(fn ($l) => (int) $l['producto_id'], $lineas);
        $pres = [];
        foreach (Db::all(
            "SELECT producto_id, nombre, factor_conversion FROM productos_unidades
             WHERE producto_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ") AND factor_conversion > 1 ORDER BY factor_conversion",
            $ids
        ) as $u) {
            $pres[(int) $u['producto_id']][] = ['nombre' => $u['nombre'], 'factor' => (float) $u['factor_conversion']];
        }
        foreach ($lineas as &$l) {
            $l['pres'] = $pres[(int) $l['producto_id']] ?? [];
        }
        return $lineas;
    }

    public function pedido()
    {
        if ($r = $this->exigir('compras.crear', '/m/mas')) return $r;

        $dias = (int) ($this->request->getGet('dias') ?? 7);
        $plan = \App\Libraries\PedidoCiudadService::calcular($this->empresaId(), $dias);

        return $this->render('movil.pedido', [
            'page_title'  => 'Pedido a la ciudad',
            'headerTitle' => 'Pedido a la ciudad',
            'activeTab'   => 'mas',
            'dias'        => max(1, min(60, $dias)),
            'plan'        => $plan,
        ]);
    }

    public function traslados()
    {
        if ($r = $this->exigir('traslados.ver', '/m/mas')) return $r;

        $base = "SELECT t.traslado_id, t.numero, t.estado, t.fecha_envio, t.fecha_recepcion, t.notas,
                        bo.nombre AS origen_sucursal, bd.nombre AS destino_sucursal, dd.nombre AS destino_deposito,
                        (SELECT COUNT(*) FROM traslados_detalle x WHERE x.traslado_id = t.traslado_id) AS lineas,
                        (SELECT COALESCE(SUM(x.cantidad), 0) FROM traslados_detalle x WHERE x.traslado_id = t.traslado_id) AS unidades
                 FROM traslados t
                 JOIN depositos do ON t.deposito_origen_id = do.deposito_id
                 JOIN depositos dd ON t.deposito_destino_id = dd.deposito_id
                 JOIN branches bo ON do.sucursal_id = bo.sucursal_id
                 JOIN branches bd ON dd.sucursal_id = bd.sucursal_id";

        return $this->render('movil.traslados', [
            'page_title'  => 'Traslados',
            'headerTitle' => 'Traslados entre tiendas',
            'activeTab'   => 'mas',
            'porRecibir'  => Db::all("{$base} WHERE dd.sucursal_id = ? AND t.estado = 'en_transito' ORDER BY t.fecha_envio", [$this->sucursalId()]),
            'enviados'    => Db::all("{$base} WHERE do.sucursal_id = ? ORDER BY t.traslado_id DESC LIMIT 20", [$this->sucursalId()]),
            'destinos'    => Db::all(
                "SELECT d.deposito_id, d.nombre AS deposito, b.nombre AS sucursal
                 FROM depositos d JOIN branches b ON d.sucursal_id = b.sucursal_id
                 WHERE b.empresa_id = ? AND d.estado = 'activo' AND d.sucursal_id <> ? ORDER BY b.nombre, d.nombre",
                [$this->empresaId(), $this->sucursalId()]
            ),
            'puedeEnviar'  => Auth::can('traslados.crear'),
            'puedeRecibir' => Auth::can('traslados.crear') || Auth::can('traslados.aprobar') || Auth::can('traslados.recibir'),
        ]);
    }

    public function mas(): string
    {
        return $this->render('movil.mas', [
            'page_title'  => 'Más',
            'headerTitle' => 'Más',
            'activeTab'   => 'mas',
            'rol'         => Auth::rol(),
        ]);
    }

    /** Emoji + color por nombre de categoría (copia en public/movil/app.js). */
    public static function iconoCategoria(?string $nombre): array
    {
        $mapa = [
            'aliment'    => ['emoji' => '🍎', 'bg' => 'var(--green-light)'],
            'aseo'       => ['emoji' => '🧼', 'bg' => 'var(--teal-light)'],
            'cocina'     => ['emoji' => '🍳', 'bg' => 'var(--orange-light)'],
            'escolar'    => ['emoji' => '✏️', 'bg' => 'var(--purple-light)'],
            'snack'      => ['emoji' => '🍬', 'bg' => 'var(--amber-light)'],
            'repuesto'   => ['emoji' => '⚙️', 'bg' => 'var(--navy-light)'],
            'lubricante' => ['emoji' => '🛢️', 'bg' => 'var(--line)'],
            'otro'       => ['emoji' => '🔧', 'bg' => 'var(--line)'],
        ];

        $clave = mb_strtolower($nombre ?? '', 'UTF-8');
        foreach ($mapa as $palabra => $icono) {
            if ($clave !== '' && str_contains($clave, $palabra)) {
                return $icono;
            }
        }
        return ['emoji' => '📦', 'bg' => 'var(--line)'];
    }
}

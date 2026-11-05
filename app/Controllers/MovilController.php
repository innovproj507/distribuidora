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
            'puedeAjustar'  => Auth::can('inventario.ajustar'),
            'puedeEditar'   => Auth::can('productos.editar'),
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
            'lineas'       => Db::all(
                "SELECT d.detalle_id, d.producto_id, d.cantidad, d.cantidad_recibida, d.costo, p.nombre, p.codigo
                 FROM compras_detalle d JOIN productos p ON p.producto_id = d.producto_id WHERE d.compra_id = ? ORDER BY p.nombre",
                [$id]
            ),
            'porEnviar'    => \App\Libraries\PedidoCiudadService::porEnviar($id),
            'puedeRecibir' => Auth::can('compras.recibir') && !in_array($compra['estado'], ['recibida', 'cancelada'], true),
            'puedeEnviar'  => Auth::can('traslados.crear'),
        ]);
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
            'puedeRecibir' => Auth::can('traslados.crear') || Auth::can('traslados.aprobar'),
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

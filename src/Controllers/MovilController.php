<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\View;

/**
 * MovilController - Páginas de la PWA móvil (/m)
 *
 * Las operaciones (vender, crear cliente, registrar compra) reutilizan los
 * endpoints JSON del sistema web; aquí solo se arman las pantallas.
 */
class MovilController extends Controller
{
    // ── Públicas ──────────────────────────────────────────────────────────────

    public function login(): void
    {
        if (Auth::check()) {
            $this->redirect('/m/');
            return;
        }
        $this->view('movil.login', ['page_title' => 'Iniciar sesión']);
    }

    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/m/login');
    }

    /** Service worker servido desde /m/sw.js para que su alcance sea /m/. */
    public function serviceWorker(): void
    {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: no-cache');
        readfile(BASE_PATH . '/public/movil/sw.js');
    }

    public function manifest(): void
    {
        header('Content-Type: application/manifest+json; charset=utf-8');
        echo json_encode([
            'name'             => $_ENV['APP_NAME'] ?? 'Distribuidora',
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
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    // ── Pantallas ─────────────────────────────────────────────────────────────

    public function inicio(): void
    {
        if (!Auth::can('dashboard.ver')) {
            $this->redirect('/m/vender');
            return;
        }

        $sucursalId = $this->sucursalId();
        $depositoId = $this->depositoId();

        $ventasHoy = Database::query(
            "SELECT COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total
             FROM ventas WHERE DATE(fecha) = CURDATE() AND estado != 'anulada' AND sucursal_id = ?",
            [$sucursalId]
        )->fetch();

        $ventasMes = Database::query(
            "SELECT COALESCE(SUM(total), 0) AS total
             FROM ventas
             WHERE fecha >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND estado != 'anulada' AND sucursal_id = ?",
            [$sucursalId]
        )->fetch();

        $salud = Database::query(
            "SELECT
                SUM(CASE WHEN COALESCE(i.existencia, 0) <= 0 THEN 1 ELSE 0 END) AS agotado,
                SUM(CASE WHEN i.existencia > 0 AND i.existencia <= i.minimo THEN 1 ELSE 0 END) AS bajo,
                SUM(CASE WHEN i.existencia > i.minimo THEN 1 ELSE 0 END) AS ok,
                COALESCE(SUM(GREATEST(i.existencia, 0) * i.costo_promedio), 0) AS valor
             FROM productos p
             LEFT JOIN inventario i ON i.producto_id = p.producto_id AND i.deposito_id = ?
             WHERE p.estado = 'activo' AND p.empresa_id = ?",
            [$depositoId, $this->empresaId()]
        )->fetch();

        $ultimasVentas = Database::query(
            "SELECT v.venta_id, v.numero_factura, v.total, v.estado, v.forma_pago, v.fecha,
                    c.nombre AS cliente_nombre
             FROM ventas v
             LEFT JOIN clientes c ON v.cliente_id = c.cliente_id
             WHERE v.sucursal_id = ?
             ORDER BY v.fecha DESC LIMIT 5",
            [$sucursalId]
        )->fetchAll();

        $this->view('movil.inicio', [
            'page_title'    => 'Inicio',
            'headerTitle'   => $this->empresaActual()['nombre_comercial'] ?? 'Inicio',
            'activeTab'     => 'inicio',
            'ventasHoy'     => $ventasHoy,
            'ventasMes'     => (float) $ventasMes['total'],
            'salud'         => [
                'ok'      => (int) $salud['ok'],
                'bajo'    => (int) $salud['bajo'],
                'agotado' => (int) $salud['agotado'],
            ],
            'valorInventario' => (float) $salud['valor'],
            'ultimasVentas' => $ultimasVentas,
        ]);
    }

    public function vender(): void
    {
        if (!$this->exigir('pos.usar')) return;

        $clientes = Database::query(
            "SELECT cliente_id, codigo, nombre, telefono, limite_credito, saldo_pendiente, dias_credito
             FROM clientes WHERE empresa_id = ? AND estado = 'activo' ORDER BY nombre",
            [$this->empresaId()]
        )->fetchAll();

        $this->view('movil.vender', [
            'page_title'  => 'Vender',
            'headerTitle' => 'Punto de venta',
            'activeTab'   => 'vender',
            'clientes'    => $clientes,
            'itbmsRate'   => (float) ($_ENV['ITBMS_RATE'] ?? 0.07),
            'puedeCrearCliente' => Auth::can('clientes.crear'),
        ]);
    }

    public function inventario(): void
    {
        if (!Auth::can('productos.ver') && !Auth::can('inventario.ver')) {
            $this->exigir('productos.ver');
            return;
        }

        $categorias = Database::query(
            "SELECT c.categoria_id, c.nombre, COUNT(p.producto_id) AS total
             FROM categorias_productos c
             JOIN productos p ON p.categoria_id = c.categoria_id AND p.estado = 'activo'
             WHERE c.empresa_id = ?
             GROUP BY c.categoria_id, c.nombre
             ORDER BY c.nombre",
            [$this->empresaId()]
        )->fetchAll();

        $this->view('movil.inventario', [
            'page_title'   => 'Inventario',
            'headerTitle'  => 'Inventario',
            'activeTab'    => 'inventario',
            'categorias'   => $categorias,
            'soloStockBajo' => $this->request->get('stock_bajo') !== null,
            'puedeAjustar' => Auth::can('inventario.ajustar'),
            'puedeEditar'  => Auth::can('productos.editar'),
        ]);
    }

    public function clientes(): void
    {
        if (!$this->exigir('clientes.ver')) return;

        $clientes = Database::query(
            "SELECT cliente_id, codigo, nombre, telefono, email, ruc,
                    limite_credito, saldo_pendiente, dias_credito
             FROM clientes WHERE empresa_id = ? AND estado = 'activo' ORDER BY nombre",
            [$this->empresaId()]
        )->fetchAll();

        $this->view('movil.clientes', [
            'page_title'  => 'Clientes',
            'headerTitle' => 'Clientes',
            'activeTab'   => 'clientes',
            'clientes'    => $clientes,
            'puedeCrear'  => Auth::can('clientes.crear'),
        ]);
    }

    public function compras(): void
    {
        if (!$this->exigir('compras.ver')) return;

        $compras = Database::query(
            "SELECT c.compra_id, c.numero_factura, c.numero_factura_proveedor, c.monto_total,
                    c.estado, c.fecha_compra, p.nombre AS proveedor_nombre,
                    (SELECT COUNT(*) FROM compras_detalle d WHERE d.compra_id = c.compra_id) AS items
             FROM compras c
             LEFT JOIN proveedores p ON c.proveedor_id = p.proveedor_id
             WHERE c.empresa_id = ? AND c.sucursal_id = ?
             ORDER BY c.compra_id DESC LIMIT 30",
            [$this->empresaId(), $this->sucursalId()]
        )->fetchAll();

        $proveedores = Database::query(
            "SELECT proveedor_id, nombre FROM proveedores WHERE empresa_id = ? AND estado = 'activo' ORDER BY nombre",
            [$this->empresaId()]
        )->fetchAll();

        $this->view('movil.compras', [
            'page_title'  => 'Compras',
            'headerTitle' => 'Compras',
            'activeTab'   => 'mas',
            'compras'     => $compras,
            'proveedores' => $proveedores,
            'puedeCrear'  => Auth::can('compras.crear'),
        ]);
    }

    public function mas(): void
    {
        $this->view('movil.mas', [
            'page_title'  => 'Más',
            'headerTitle' => 'Más',
            'activeTab'   => 'mas',
            'rol'         => Auth::rol(),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Emoji + color de fondo por nombre de categoría (mismo mapa que la app arango).
     * Hay una copia en public/movil/app.js para las tarjetas que se pintan por JS.
     */
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

    /** Como requirePermission(), pero redirige dentro de la PWA en vez de al dashboard web. */
    private function exigir(string $permiso): bool
    {
        if (Auth::can($permiso)) return true;
        $this->error('No tienes permiso para entrar a esa sección.');
        $this->redirect('/m/mas');
        return false;
    }

    private function depositoId(): int
    {
        $deposito = $this->session->get('deposito_actual');
        return $deposito ? (int) $deposito['deposito_id'] : 0;
    }
}

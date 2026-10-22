<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Response;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\Cliente;

class CotizacionController extends Controller
{
    public function index(): void
    {
        $page = (int) ($this->request->get('page', '1'));
        $buscar = $this->request->get('buscar', '');
        $estado = $this->request->get('estado', '');
        $fecha_desde = $this->request->get('fecha_inicio', '');
        $fecha_hasta = $this->request->get('fecha_fin', '');
        $cliente_id = $this->request->get('cliente', '');
        $perPage = 25;

        $sucursalId = $this->sucursalId();
        $where = 'co.sucursal_id = ?';
        $params = [$sucursalId];
        
        if ($buscar) {
            $where .= " AND (co.numero LIKE ? OR c.nombre LIKE ?)";
            $params[] = "%{$buscar}%";
            $params[] = "%{$buscar}%";
        }
        if ($estado) {
            $where .= " AND co.estado = ?";
            $params[] = $estado;
        }
        if ($fecha_desde) {
            $where .= " AND DATE(co.fecha) >= ?";
            $params[] = $fecha_desde;
        }
        if ($fecha_hasta) {
            $where .= " AND DATE(co.fecha) <= ?";
            $params[] = $fecha_hasta;
        }
        if ($cliente_id) {
            $where .= " AND co.cliente_id = ?";
            $params[] = $cliente_id;
        }

        $offset = ($page - 1) * $perPage;
        
        // Paginación y lista de cotizaciones
        $total = Database::query("SELECT COUNT(*) as t FROM cotizaciones co LEFT JOIN clientes c ON co.cliente_id = c.cliente_id WHERE {$where}", $params)->fetch()['t'];
        
        $cotizaciones = Database::query(
            "SELECT co.*, c.nombre as cliente_nombre, u.username as vendedor_nombre
             FROM cotizaciones co
             LEFT JOIN clientes c ON co.cliente_id = c.cliente_id
             LEFT JOIN users u ON co.vendedor_id = u.id
             WHERE {$where}
             ORDER BY co.fecha DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        )->fetchAll();

        $totalPages = (int) ceil((int)$total / $perPage);

        // Métricas
        $metricas = Database::query(
            "SELECT 
                COUNT(*) as cantidad_cotizaciones, 
                COALESCE(SUM(total), 0) as total_cotizaciones 
             FROM cotizaciones co LEFT JOIN clientes c ON co.cliente_id = c.cliente_id 
             WHERE {$where} AND co.estado != 'rechazada'", 
            $params
        )->fetch();
        
        $cotizacionesHoy = Database::query("SELECT COUNT(*) as t FROM cotizaciones WHERE DATE(fecha) = CURDATE() AND estado != 'rechazada'")->fetch()['t'];
        
        $cantidadCotizaciones = (int) $metricas['cantidad_cotizaciones'];
        $totalCotizaciones = (float) $metricas['total_cotizaciones'];
        $promedioCotizacion = $cantidadCotizaciones > 0 ? $totalCotizaciones / $cantidadCotizaciones : 0;

        // Lista de clientes para el filtro
        $clientesList = Cliente::where('estado', 'activo');

        $this->view('cotizaciones.lista', [
            'page_title' => 'Cotizaciones',
            'page_subtitle' => 'Gestión de cotizaciones',
            'cotizaciones' => $cotizaciones,
            'clientes' => $clientesList,
            'buscar' => $buscar,
            'estado_actual' => $estado,
            'fecha_inicio_actual' => $fecha_desde,
            'fecha_fin_actual' => $fecha_hasta,
            'cliente_actual' => $cliente_id,
            'total_cotizaciones' => $totalCotizaciones,
            'cantidad_cotizaciones' => $cantidadCotizaciones,
            'promedio_cotizacion' => $promedioCotizacion,
            'cotizaciones_hoy_count' => $cotizacionesHoy,
            'pagination' => [
                'total' => (int)$total,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'has_previous' => $page > 1,
                'has_next' => $page < $totalPages,
                'previous_page' => $page - 1,
                'next_page' => $page + 1,
            ],
        ]);
    }

    public function crear(): void
    {
        $clientes = Cliente::where('estado', 'activo');
        $depositos = \App\Models\Deposito::where('estado', 'activo');

        $this->view('ventas.crear', [
            'page_title' => 'Nueva Cotización',
            'page_subtitle' => 'Crear cotización',
            'tipo_documento' => 'cotizacion',
            'clientes' => $clientes,
            'depositos' => $depositos,
            'siguiente_factura' => \App\Models\Venta::generarNumeroFactura(),
            'siguiente_cotizacion' => \App\Models\Cotizacion::generarNumeroCotizacion(),
            'hoy' => date('Y-m-d'),
            'fecha_validez' => date('Y-m-d', strtotime('+30 days')),
        ]);
    }

    public function guardar(): void
    {
        if (!$this->request->isPost()) return;

        $data = $this->request->json();
        $items = $data['items'] ?? [];

        if (empty($items)) {
            $this->json(['error' => 'No hay productos en la cotización.'], 400);
            return;
        }

        try {
            Database::beginTransaction();

            $itbmsRate = (float) ($_ENV['ITBMS_RATE'] ?? 0.07);
            $subtotal = 0;
            $numeroCot = Cotizacion::generarNumeroCotizacion();
            $diasValidez = (int) ($_ENV['COTIZACION_DIAS_VALIDEZ'] ?? 7);

            $cotizacion = Cotizacion::create([
                'sucursal_id' => $this->sucursalId(),
                'empresa_id' => $this->empresaId(),
                'numero' => $numeroCot,
                'token' => bin2hex(random_bytes(16)),
                'fecha_vencimiento' => date('Y-m-d', strtotime("+{$diasValidez} days")),
                'cliente_id' => !empty($data['cliente_id']) ? $data['cliente_id'] : null,
                'vendedor_id' => Auth::id(),
                'estado' => $data['estado'] ?? 'pendiente',
                'notas' => $data['notas'] ?? '',
                'subtotal' => 0,
                'descuento' => $data['descuento'] ?? 0,
                'itbms' => 0,
                'total' => 0,
                'fecha' => date('Y-m-d H:i:s'),
            ]);

            foreach ($items as $item) {
                $totalLinea = ((float)$item['cantidad'] * (float)$item['precio']) - (float)($item['descuento'] ?? 0);
                $itbmsLinea = $totalLinea * $itbmsRate;
                $subtotal += $totalLinea;

                CotizacionDetalle::create([
                    'cotizacion_id' => $cotizacion->cotizacion_id,
                    'producto_id' => $item['producto_id'],
                    'cantidad' => $item['cantidad'],
                    'precio' => $item['precio'],
                    'descuento' => $item['descuento'] ?? 0,
                    'itbms' => $itbmsLinea,
                    'total_linea' => $totalLinea + $itbmsLinea,
                ]);
            }

            $descuentoGlobal = (float)($data['descuento'] ?? 0);
            $itbmsTotal = $subtotal * $itbmsRate;

            $cotizacion->update([
                'subtotal' => round($subtotal, 2),
                'itbms' => round($itbmsTotal, 2),
                'total' => round($subtotal + $itbmsTotal - $descuentoGlobal, 2),
            ]);

            Database::commit();
            $this->json([
                'success' => true,
                'cotizacion_id' => $cotizacion->cotizacion_id,
                'numero' => $numeroCot,
            ]);
        } catch (\Exception $e) {
            Database::rollback();
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * SELECT base para traer una cotización con los datos del cliente y vendedor
     * necesarios tanto para el detalle interno como para la proforma imprimible/pública.
     */
    private function cotizacionConCliente(): string
    {
        return "SELECT co.*, c.nombre as cliente_nombre, c.ruc as cliente_ruc, c.direccion as cliente_direccion,
                       c.telefono as cliente_telefono, c.email as cliente_email, c.dias_credito as cliente_dias_credito,
                       u.username as vendedor_nombre
                FROM cotizaciones co
                LEFT JOIN clientes c ON co.cliente_id = c.cliente_id
                LEFT JOIN users u ON co.vendedor_id = u.id";
    }

    private function detallesDeCotizacion(int $cotizacion_id): array
    {
        return Database::query(
            "SELECT d.*, p.nombre as producto_nombre, p.codigo as producto_codigo
             FROM cotizaciones_detalle d
             JOIN productos p ON d.producto_id = p.producto_id
             WHERE d.cotizacion_id = ?",
            [$cotizacion_id]
        )->fetchAll();
    }

    public function detalle(int $cotizacion_id): void
    {
        $cotizacion = Database::query(
            $this->cotizacionConCliente() . " WHERE co.cotizacion_id = ?",
            [$cotizacion_id]
        )->fetch();

        if (!$cotizacion || $cotizacion['sucursal_id'] != $this->sucursalId()) {
            $this->error('Cotización no encontrada o no autorizada.');
            $this->redirect('/ventas/cotizaciones');
            return;
        }

        $this->view('cotizaciones.detalle', [
            'page_title' => 'Cotización ' . $cotizacion['numero'],
            'page_subtitle' => 'Detalle de cotización',
            'cotizacion' => $cotizacion,
            'detalles' => $this->detallesDeCotizacion($cotizacion_id),
        ]);
    }

    /**
     * Proforma imprimible de la cotización (página independiente, sin layout de la app)
     */
    public function imprimir(int $cotizacion_id): void
    {
        $cotizacion = Database::query(
            $this->cotizacionConCliente() . " WHERE co.cotizacion_id = ?",
            [$cotizacion_id]
        )->fetch();

        if (!$cotizacion || $cotizacion['sucursal_id'] != $this->sucursalId()) {
            $this->error('Cotización no encontrada o no autorizada.');
            $this->redirect('/ventas/cotizaciones');
            return;
        }

        $this->renderProforma($cotizacion, esPublico: false);
    }

    /**
     * Proforma pública (sin autenticación): accesible con el token único de la cotización.
     * Pensada para compartirse por WhatsApp o correo con el cliente.
     */
    public function verPublico(): void
    {
        $token = trim($this->request->get('t', ''));

        $cotizacion = $token !== ''
            ? Database::query($this->cotizacionConCliente() . " WHERE co.token = ?", [$token])->fetch()
            : null;

        if (!$cotizacion) {
            Response::error(404, 'Cotización no encontrada. Verifique el enlace recibido.');
            return;
        }

        $this->renderProforma($cotizacion, esPublico: true);
    }

    /** @return array{0: array, 1: array} [$empresa, $sucursal] */
    private function empresaYSucursal(array $cotizacion): array
    {
        $empresa = Database::query(
            "SELECT * FROM companies WHERE empresa_id = ?",
            [$cotizacion['empresa_id']]
        )->fetch() ?: [];

        $sucursal = Database::query(
            "SELECT * FROM branches WHERE sucursal_id = ?",
            [$cotizacion['sucursal_id']]
        )->fetch() ?: [];

        return [$empresa, $sucursal];
    }

    private function renderProforma(array $cotizacion, bool $esPublico): void
    {
        [$empresa, $sucursal] = $this->empresaYSucursal($cotizacion);

        $this->view('cotizaciones.imprimir', [
            'cotizacion' => $cotizacion,
            'detalles' => $this->detallesDeCotizacion((int) $cotizacion['cotizacion_id']),
            'empresa' => $empresa,
            'sucursal' => $sucursal,
            'esPublico' => $esPublico,
        ]);
    }

    /**
     * Descarga la proforma como PDF real, generado en el servidor con Dompdf
     * (no depende del "Guardar como PDF" del navegador).
     */
    public function descargarPdf(int $cotizacion_id): void
    {
        $cotizacion = Database::query(
            $this->cotizacionConCliente() . " WHERE co.cotizacion_id = ?",
            [$cotizacion_id]
        )->fetch();

        if (!$cotizacion || $cotizacion['sucursal_id'] != $this->sucursalId()) {
            $this->error('Cotización no encontrada o no autorizada.');
            $this->redirect('/ventas/cotizaciones');
            return;
        }

        [$empresa, $sucursal] = $this->empresaYSucursal($cotizacion);
        \App\Services\CotizacionPdfService::descargar(
            $cotizacion,
            $this->detallesDeCotizacion($cotizacion_id),
            $empresa,
            $sucursal
        );
    }

    /**
     * Descarga el PDF de la proforma pública (sin autenticación), con el mismo token que
     * protege verPublico(). Usada por el botón "Descargar PDF" cuando lo abre el cliente.
     */
    public function descargarPdfPublico(): void
    {
        $token = trim($this->request->get('t', ''));

        $cotizacion = $token !== ''
            ? Database::query($this->cotizacionConCliente() . " WHERE co.token = ?", [$token])->fetch()
            : null;

        if (!$cotizacion) {
            Response::error(404, 'Cotización no encontrada. Verifique el enlace recibido.');
            return;
        }

        [$empresa, $sucursal] = $this->empresaYSucursal($cotizacion);
        \App\Services\CotizacionPdfService::descargar(
            $cotizacion,
            $this->detallesDeCotizacion((int) $cotizacion['cotizacion_id']),
            $empresa,
            $sucursal
        );
    }

    public function convertir(int $cotizacion_id): void
    {
        if (!$this->verifyCsrf()) return;

        $cotizacion = Cotizacion::findOrFail($cotizacion_id);
        if ($cotizacion->sucursal_id != $this->sucursalId()) {
            $this->error('No autorizado.');
            $this->redirect('/ventas/cotizaciones');
            return;
        }

        if ($cotizacion->estado !== 'pendiente' && $cotizacion->estado !== 'aprobada') {
            $this->error('Solo se pueden convertir cotizaciones pendientes o aprobadas.');
            $this->redirect("/ventas/cotizaciones/{$cotizacion_id}");
            return;
        }

        try {
            Database::beginTransaction();

            $numeroFactura = Venta::generarNumeroFactura();

            $venta = Venta::create([
                'sucursal_id' => $cotizacion->sucursal_id,
                'empresa_id' => $this->empresaId(),
                'numero_factura' => $numeroFactura,
                'cliente_id' => $cotizacion->cliente_id,
                'vendedor_id' => Auth::id(),
                'subtotal' => $cotizacion->subtotal,
                'descuento' => $cotizacion->descuento,
                'itbms' => $cotizacion->itbms,
                'total' => $cotizacion->total,
                'forma_pago' => 'efectivo',
                'estado' => 'pagada',
                'cotizacion_origen_id' => $cotizacion_id,
                'ip' => $this->request->ip(),
                'costo' => 0,
                'fecha' => date('Y-m-d H:i:s'),
            ]);

            $detalles = CotizacionDetalle::where('cotizacion_id', $cotizacion_id);
            foreach ($detalles as $d) {
                VentaDetalle::create([
                    'venta_id' => $venta->venta_id,
                    'producto_id' => $d->producto_id,
                    'cantidad' => $d->cantidad,
                    'precio' => $d->precio,
                    'descuento' => $d->descuento,
                    'itbms' => $d->itbms,
                    'total_linea' => $d->total_linea,
                ]);

                // Descontar inventario del primer depósito disponible
                Database::query(
                    "UPDATE inventario SET existencia = existencia - ? 
                     WHERE producto_id = ? AND existencia >= ? LIMIT 1",
                    [$d->cantidad, $d->producto_id, $d->cantidad]
                );
            }

            $cotizacion->update(['estado' => 'convertida']);
            Database::commit();

            $this->success('Cotización convertida a venta ' . $numeroFactura);
            $this->redirect("/ventas/venta/{$venta->venta_id}");
        } catch (\Exception $e) {
            Database::rollback();
            $this->error('Error: ' . $e->getMessage());
            $this->redirect("/ventas/cotizaciones/{$cotizacion_id}");
        }
    }

    public function pendientesPos(): void
    {
        $q = $this->request->get('q', '');
        $params = [$this->sucursalId()];
        $where = "co.sucursal_id = ? AND co.estado IN ('pendiente', 'aprobada')";

        if ($q) {
            $where .= " AND (co.numero LIKE ? OR c.nombre LIKE ?)";
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
        }

        $cotizaciones = Database::query(
            "SELECT co.cotizacion_id, co.numero, co.fecha, co.total, co.estado, co.fecha_vencimiento,
                    COALESCE(c.nombre, 'Consumidor Final') as cliente_nombre
             FROM cotizaciones co
             LEFT JOIN clientes c ON co.cliente_id = c.cliente_id
             WHERE {$where}
             ORDER BY co.fecha DESC
             LIMIT 40",
            $params
        )->fetchAll();

        $this->json(['cotizaciones' => $cotizaciones]);
    }

    public function itemsPos(int $cotizacion_id): void
    {
        $cotizacion = Database::query(
            "SELECT co.cotizacion_id, co.numero, co.cliente_id, co.estado,
                    COALESCE(c.nombre, 'Consumidor Final') as cliente_nombre
             FROM cotizaciones co
             LEFT JOIN clientes c ON co.cliente_id = c.cliente_id
             WHERE co.cotizacion_id = ? AND co.sucursal_id = ?",
            [$cotizacion_id, $this->sucursalId()]
        )->fetch();

        if (!$cotizacion) {
            $this->json(['error' => 'No encontrada'], 404);
            return;
        }

        $items = Database::query(
            "SELECT d.producto_id, d.cantidad, d.precio, d.descuento,
                    p.codigo, p.nombre, p.itbms as aplica_itbms
             FROM cotizaciones_detalle d
             JOIN productos p ON p.producto_id = d.producto_id
             WHERE d.cotizacion_id = ?",
            [$cotizacion_id]
        )->fetchAll();

        $this->json(['cotizacion' => $cotizacion, 'items' => $items]);
    }

    public function cambiarEstado(int $cotizacion_id): void
    {
        if (!$this->verifyCsrf()) return;

        $cotizacion = Cotizacion::findOrFail($cotizacion_id);
        if ($cotizacion->sucursal_id != $this->sucursalId()) {
            $this->error('No autorizado.');
            $this->redirect('/ventas/cotizaciones');
            return;
        }
        $nuevoEstado = $this->request->post('estado', 'pendiente');
        $cotizacion->update(['estado' => $nuevoEstado]);

        $this->success("Estado cambiado a {$nuevoEstado}.");
        $this->redirect("/ventas/cotizaciones/{$cotizacion_id}");
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\AuditService;
use App\Libraries\Auth;
use App\Libraries\CotizacionPdf;
use App\Libraries\Db;
use App\Libraries\CotizacionService;
use App\Libraries\NumeracionService;

class CotizacionController extends BaseController
{
    private const POR_PAGINA = 25;

    private const ESTADOS = CotizacionService::ESTADOS;

    public function index(): string
    {
        $page       = max(1, (int) ($this->request->getGet('page') ?? 1));
        $buscar     = (string) ($this->request->getGet('buscar') ?? '');
        $estado     = (string) ($this->request->getGet('estado') ?? '');
        $fechaDesde = (string) ($this->request->getGet('fecha_inicio') ?? '');
        $fechaHasta = (string) ($this->request->getGet('fecha_fin') ?? '');
        $clienteId  = (string) ($this->request->getGet('cliente') ?? '');
        $sucursalId = $this->sucursalId();

        $where  = 'co.sucursal_id = ?';
        $params = [$sucursalId];

        if ($buscar !== '') {
            $where .= ' AND (co.numero LIKE ? OR c.nombre LIKE ?)';
            $params[] = "%{$buscar}%";
            $params[] = "%{$buscar}%";
        }
        if ($estado !== '') {
            $where .= ' AND co.estado = ?';
            $params[] = $estado;
        }
        if ($fechaDesde !== '') {
            $where .= ' AND DATE(co.fecha) >= ?';
            $params[] = $fechaDesde;
        }
        if ($fechaHasta !== '') {
            $where .= ' AND DATE(co.fecha) <= ?';
            $params[] = $fechaHasta;
        }
        if ($clienteId !== '') {
            $where .= ' AND co.cliente_id = ?';
            $params[] = (int) $clienteId;
        }

        $offset = ($page - 1) * self::POR_PAGINA;
        $total  = (int) Db::value("SELECT COUNT(*) FROM cotizaciones co LEFT JOIN clientes c ON co.cliente_id = c.cliente_id WHERE {$where}", $params);

        $cotizaciones = Db::all(
            "SELECT co.*, c.nombre AS cliente_nombre, u.username AS vendedor_nombre
             FROM cotizaciones co
             LEFT JOIN clientes c ON co.cliente_id = c.cliente_id
             LEFT JOIN users u ON co.vendedor_id = u.id
             WHERE {$where}
             ORDER BY co.fecha DESC
             LIMIT " . self::POR_PAGINA . " OFFSET {$offset}",
            $params
        );

        $metricas = Db::row(
            "SELECT COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total
             FROM cotizaciones co LEFT JOIN clientes c ON co.cliente_id = c.cliente_id
             WHERE {$where} AND co.estado != 'rechazada'",
            $params
        );

        $hoy = (int) Db::value(
            "SELECT COUNT(*) FROM cotizaciones WHERE sucursal_id = ? AND DATE(fecha) = CURDATE() AND estado != 'rechazada'",
            [$sucursalId]
        );

        $cantidad = (int) $metricas['cantidad'];
        $totalC   = (float) $metricas['total'];

        return $this->render('cotizaciones.lista', [
            'page_title'             => 'Cotizaciones',
            'page_subtitle'          => 'Gestión de cotizaciones',
            'cotizaciones'           => $cotizaciones,
            'clientes'               => Db::all("SELECT * FROM clientes WHERE estado = 'activo' AND empresa_id = ? ORDER BY nombre", [$this->empresaId()]),
            'buscar'                 => $buscar,
            'estado_actual'          => $estado,
            'fecha_inicio_actual'    => $fechaDesde,
            'fecha_fin_actual'       => $fechaHasta,
            'cliente_actual'         => $clienteId,
            'total_cotizaciones'     => $totalC,
            'cantidad_cotizaciones'  => $cantidad,
            'promedio_cotizacion'    => $cantidad > 0 ? $totalC / $cantidad : 0,
            'cotizaciones_hoy_count' => $hoy,
            'pagination'             => paginacion($total, $page, self::POR_PAGINA),
        ]);
    }

    public function crear(): string
    {
        return $this->render('ventas.crear', [
            'page_title'           => 'Nueva Cotización',
            'page_subtitle'        => 'Crear cotización',
            'tipo_documento'       => 'cotizacion',
            'clientes'             => Db::all("SELECT * FROM clientes WHERE estado = 'activo' AND empresa_id = ? ORDER BY nombre", [$this->empresaId()]),
            'depositos'            => Db::all("SELECT * FROM depositos WHERE estado = 'activo' AND sucursal_id = ? ORDER BY es_principal DESC, nombre", [$this->sucursalId()]),
            'siguiente_factura'    => NumeracionService::siguiente('factura', $this->empresaId(), $this->sucursalId()),
            'siguiente_cotizacion' => NumeracionService::siguiente('cotizacion', $this->empresaId(), $this->sucursalId()),
            'hoy'                  => date('Y-m-d'),
            'fecha_validez'        => date('Y-m-d', strtotime('+30 days')),
        ]);
    }

    /** POST JSON. */
    public function guardar()
    {
        try {
            Db::begin();
            $res = CotizacionService::crear($this->jsonInput(), ['empresa_id' => $this->empresaId(), 'sucursal_id' => $this->sucursalId()]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            log_message('error', 'Cotización: ' . $e->getMessage());
            return $this->json(['error' => $e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo guardar la cotización.'], $e instanceof \RuntimeException ? 422 : 500);
        }

        return $this->json(['success' => true, 'cotizacion_id' => $res['cotizacion_id'], 'numero' => $res['numero']]);
    }

    public function detalle(int $cotizacionId)
    {
        $cotizacion = $this->buscar($cotizacionId);
        if (!$cotizacion) {
            $this->error('Cotización no encontrada o no autorizada.');
            return redirect()->to('/ventas/cotizaciones');
        }

        return $this->render('cotizaciones.detalle', [
            'page_title'    => 'Cotización ' . $cotizacion['numero'],
            'page_subtitle' => 'Detalle de cotización',
            'cotizacion'    => $cotizacion,
            'detalles'      => $this->detalles($cotizacionId),
        ]);
    }

    /** Proforma imprimible (sin layout). */
    public function imprimir(int $cotizacionId)
    {
        $cotizacion = $this->buscar($cotizacionId);
        if (!$cotizacion) {
            $this->error('Cotización no encontrada o no autorizada.');
            return redirect()->to('/ventas/cotizaciones');
        }
        return $this->proforma($cotizacion, false);
    }

    /** Proforma pública por token (?t=...), para compartir por WhatsApp o correo. */
    public function verPublico()
    {
        $cotizacion = $this->porToken();
        if (!$cotizacion) {
            return $this->response->setStatusCode(404)->setBody('Cotización no encontrada. Verifique el enlace recibido.');
        }
        return $this->proforma($cotizacion, true);
    }

    public function descargarPdf(int $cotizacionId)
    {
        $cotizacion = $this->buscar($cotizacionId);
        if (!$cotizacion) {
            $this->error('Cotización no encontrada o no autorizada.');
            return redirect()->to('/ventas/cotizaciones');
        }
        return $this->pdf($cotizacion);
    }

    public function descargarPdfPublico()
    {
        $cotizacion = $this->porToken();
        if (!$cotizacion) {
            return $this->response->setStatusCode(404)->setBody('Cotización no encontrada. Verifique el enlace recibido.');
        }
        return $this->pdf($cotizacion);
    }

    /** Convierte la cotización en venta y descuenta el stock del depósito activo. */
    public function convertir(int $cotizacionId)
    {
        try {
            Db::begin();
            $venta = CotizacionService::convertir($cotizacionId, [
                'empresa_id'  => $this->empresaId(),
                'sucursal_id' => $this->sucursalId(),
                'deposito_id' => $this->depositoId(),
                'ip'          => $this->request->getIPAddress(),
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error($e instanceof \RuntimeException ? $e->getMessage() : 'Error al convertir la cotización.');
            return redirect()->to("/ventas/cotizaciones/{$cotizacionId}");
        }

        AuditService::log('ventas.crear', "Cotización #{$cotizacionId} convertida en venta {$venta['numero_factura']}", $venta['venta_id'], 'venta', [], $this->empresaId());
        $this->success('Cotización convertida a venta ' . $venta['numero_factura']);
        return redirect()->to("/ventas/venta/{$venta['venta_id']}");
    }

    public function pendientesPos()
    {
        $q      = (string) ($this->request->getGet('q') ?? '');
        $where  = "co.sucursal_id = ? AND co.estado IN ('pendiente', 'aprobada')";
        $params = [$this->sucursalId()];

        if ($q !== '') {
            $where .= ' AND (co.numero LIKE ? OR c.nombre LIKE ?)';
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
        }

        return $this->json(['cotizaciones' => Db::all(
            "SELECT co.cotizacion_id, co.numero, co.fecha, co.total, co.estado, co.fecha_vencimiento,
                    COALESCE(c.nombre, 'Consumidor Final') AS cliente_nombre
             FROM cotizaciones co
             LEFT JOIN clientes c ON co.cliente_id = c.cliente_id
             WHERE {$where}
             ORDER BY co.fecha DESC LIMIT 40",
            $params
        )]);
    }

    public function itemsPos(int $cotizacionId)
    {
        $cotizacion = Db::row(
            "SELECT co.cotizacion_id, co.numero, co.cliente_id, co.estado,
                    COALESCE(c.nombre, 'Consumidor Final') AS cliente_nombre
             FROM cotizaciones co
             LEFT JOIN clientes c ON co.cliente_id = c.cliente_id
             WHERE co.cotizacion_id = ? AND co.sucursal_id = ?",
            [$cotizacionId, $this->sucursalId()]
        );

        if (!$cotizacion) {
            return $this->json(['error' => 'No encontrada'], 404);
        }

        return $this->json([
            'cotizacion' => $cotizacion,
            'items'      => Db::all(
                "SELECT d.producto_id, d.cantidad, d.precio, d.descuento, p.codigo, p.nombre, p.itbms AS aplica_itbms
                 FROM cotizaciones_detalle d
                 JOIN productos p ON p.producto_id = d.producto_id
                 WHERE d.cotizacion_id = ?",
                [$cotizacionId]
            ),
        ]);
    }

    public function cambiarEstado(int $cotizacionId)
    {
        $cotizacion = Db::row("SELECT cotizacion_id FROM cotizaciones WHERE cotizacion_id = ? AND sucursal_id = ?", [$cotizacionId, $this->sucursalId()]);
        if (!$cotizacion) {
            $this->error('No autorizado.');
            return redirect()->to('/ventas/cotizaciones');
        }

        $estado = (string) $this->request->getPost('estado');
        if (!in_array($estado, self::ESTADOS, true)) {
            $this->error('Estado no válido.');
            return redirect()->to("/ventas/cotizaciones/{$cotizacionId}");
        }

        Db::update('cotizaciones', ['estado' => $estado], ['cotizacion_id' => $cotizacionId]);
        $this->success("Estado cambiado a {$estado}.");
        return redirect()->to("/ventas/cotizaciones/{$cotizacionId}");
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private const SELECT_CON_CLIENTE = "SELECT co.*, c.nombre AS cliente_nombre, c.ruc AS cliente_ruc, c.direccion AS cliente_direccion,
                c.telefono AS cliente_telefono, c.email AS cliente_email, c.dias_credito AS cliente_dias_credito,
                u.username AS vendedor_nombre
         FROM cotizaciones co
         LEFT JOIN clientes c ON co.cliente_id = c.cliente_id
         LEFT JOIN users u ON co.vendedor_id = u.id";

    private function buscar(int $cotizacionId): ?array
    {
        return Db::row(self::SELECT_CON_CLIENTE . ' WHERE co.cotizacion_id = ? AND co.sucursal_id = ?', [$cotizacionId, $this->sucursalId()]);
    }

    private function porToken(): ?array
    {
        $token = trim((string) ($this->request->getGet('t') ?? ''));
        return $token !== '' ? Db::row(self::SELECT_CON_CLIENTE . ' WHERE co.token = ?', [$token]) : null;
    }

    private function detalles(int $cotizacionId): array
    {
        return Db::all(
            "SELECT d.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo
             FROM cotizaciones_detalle d
             JOIN productos p ON d.producto_id = p.producto_id
             WHERE d.cotizacion_id = ?",
            [$cotizacionId]
        );
    }

    /** @return array{0: array, 1: array} */
    private function empresaYSucursal(array $cotizacion): array
    {
        return [
            Db::row("SELECT * FROM companies WHERE empresa_id = ?", [$cotizacion['empresa_id']]) ?? [],
            Db::row("SELECT * FROM branches WHERE sucursal_id = ?", [$cotizacion['sucursal_id']]) ?? [],
        ];
    }

    private function proforma(array $cotizacion, bool $esPublico): string
    {
        [$empresa, $sucursal] = $this->empresaYSucursal($cotizacion);

        return view('cotizaciones/imprimir', [
            'cotizacion' => $cotizacion,
            'detalles'   => $this->detalles((int) $cotizacion['cotizacion_id']),
            'empresa'    => $empresa,
            'sucursal'   => $sucursal,
            'esPublico'  => $esPublico,
        ]);
    }

    private function pdf(array $cotizacion)
    {
        [$empresa, $sucursal] = $this->empresaYSucursal($cotizacion);
        $pdf = CotizacionPdf::generar($cotizacion, $this->detalles((int) $cotizacion['cotizacion_id']), $empresa, $sucursal);

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . CotizacionPdf::nombreArchivo($cotizacion) . '"')
            ->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->setBody($pdf);
    }
}

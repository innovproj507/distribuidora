<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\AuditService;
use App\Libraries\Auth;
use App\Libraries\CajaService;
use App\Libraries\Db;
use App\Libraries\InventarioService;
use App\Libraries\NumeracionService;
use App\Libraries\VentaService;

class VentaController extends BaseController
{
    private const POR_PAGINA = 25;

    public function index(): string
    {
        $page        = max(1, (int) ($this->request->getGet('page') ?? 1));
        $buscar      = (string) ($this->request->getGet('buscar') ?? '');
        $estado      = (string) ($this->request->getGet('estado') ?? '');
        $fechaDesde  = (string) ($this->request->getGet('fecha_inicio') ?? '');
        $fechaHasta  = (string) ($this->request->getGet('fecha_fin') ?? '');
        $clienteId   = (string) ($this->request->getGet('cliente') ?? '');
        $sucursalId  = $this->sucursalId();

        $where  = 'v.sucursal_id = ?';
        $params = [$sucursalId];

        if ($buscar !== '') {
            $where .= ' AND (v.numero_factura LIKE ? OR c.nombre LIKE ?)';
            $params[] = "%{$buscar}%";
            $params[] = "%{$buscar}%";
        }
        if ($estado !== '') {
            $where .= ' AND v.estado = ?';
            $params[] = $estado;
        }
        if ($fechaDesde !== '') {
            $where .= ' AND DATE(v.fecha) >= ?';
            $params[] = $fechaDesde;
        }
        if ($fechaHasta !== '') {
            $where .= ' AND DATE(v.fecha) <= ?';
            $params[] = $fechaHasta;
        }
        if ($clienteId !== '') {
            $where .= ' AND v.cliente_id = ?';
            $params[] = (int) $clienteId;
        }

        $offset = ($page - 1) * self::POR_PAGINA;
        $total  = (int) Db::value("SELECT COUNT(*) FROM ventas v LEFT JOIN clientes c ON v.cliente_id = c.cliente_id WHERE {$where}", $params);

        $ventas = Db::all(
            "SELECT v.*, c.nombre AS cliente_nombre, u.username AS vendedor_nombre,
                    (SELECT GROUP_CONCAT(DISTINCT forma_pago ORDER BY monto DESC SEPARATOR ',') FROM ventas_pagos WHERE venta_id = v.venta_id) AS formas_pago
             FROM ventas v
             LEFT JOIN clientes c ON v.cliente_id = c.cliente_id
             LEFT JOIN users u ON v.vendedor_id = u.id
             WHERE {$where}
             ORDER BY v.venta_id DESC
             LIMIT " . self::POR_PAGINA . " OFFSET {$offset}",
            $params
        );

        $metricas = Db::row(
            "SELECT COUNT(*) AS cantidad_ventas, COALESCE(SUM(total), 0) AS total_ventas
             FROM ventas v LEFT JOIN clientes c ON v.cliente_id = c.cliente_id
             WHERE {$where} AND v.estado != 'anulada'",
            $params
        );

        $ventasHoy = (int) Db::value(
            "SELECT COUNT(*) FROM ventas WHERE sucursal_id = ? AND DATE(fecha) = CURDATE() AND estado != 'anulada'",
            [$sucursalId]
        );

        $cantidad = (int) $metricas['cantidad_ventas'];
        $totalV   = (float) $metricas['total_ventas'];

        return $this->render('ventas.lista', [
            'page_title'          => 'Ventas',
            'page_subtitle'       => 'Gestión de ventas y cotizaciones',
            'ventas'              => $ventas,
            'clientes'            => $this->clientesActivos(),
            'buscar'              => $buscar,
            'estado_actual'       => $estado,
            'fecha_inicio_actual' => $fechaDesde,
            'fecha_fin_actual'    => $fechaHasta,
            'cliente_actual'      => $clienteId,
            'total_ventas'        => $totalV,
            'cantidad_ventas'     => $cantidad,
            'promedio_venta'      => $cantidad > 0 ? $totalV / $cantidad : 0,
            'ventas_hoy_count'    => $ventasHoy,
            'pagination'          => paginacion($total, $page, self::POR_PAGINA),
        ]);
    }

    /** Formulario de venta tradicional (también precarga una cotización con ?cotizacion_id=). */
    public function crear(): string
    {
        $cotizacionId = (int) ($this->request->getGet('cotizacion_id') ?? 0);
        $preload      = null;

        if ($cotizacionId > 0) {
            $cot = Db::row(
                "SELECT co.*, c.nombre AS cliente_nombre, c.ruc AS cliente_ruc,
                        c.telefono AS cliente_telefono, c.direccion AS cliente_direccion
                 FROM cotizaciones co
                 LEFT JOIN clientes c ON co.cliente_id = c.cliente_id
                 WHERE co.cotizacion_id = ? AND co.sucursal_id = ?",
                [$cotizacionId, $this->sucursalId()]
            );

            if ($cot) {
                $detalles = Db::all(
                    "SELECT d.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo, p.itbms AS p_itbms
                     FROM cotizaciones_detalle d
                     JOIN productos p ON d.producto_id = p.producto_id
                     WHERE d.cotizacion_id = ?",
                    [$cotizacionId]
                );

                $preload = [
                    'cotizacion_id'     => (int) $cot['cotizacion_id'],
                    'cliente_id'        => $cot['cliente_id'],
                    'cliente_nombre'    => $cot['cliente_nombre'] ?? 'Consumidor Final',
                    'cliente_ruc'       => $cot['cliente_ruc'] ?? 'N/A',
                    'cliente_telefono'  => $cot['cliente_telefono'] ?? 'N/A',
                    'cliente_direccion' => $cot['cliente_direccion'] ?? 'Consumidor Final',
                    'notas'             => $cot['notas'] ?? '',
                    'items'             => array_map(fn ($d) => [
                        'id'            => (int) $d['producto_id'],
                        'codigo'        => $d['producto_codigo'],
                        'nombre'        => $d['producto_nombre'],
                        'cantidad'      => (float) $d['cantidad'],
                        'precio'        => (float) $d['precio'],
                        'descuento'     => (float) $d['descuento'],
                        'applica_itbms' => $d['p_itbms'] == 1,
                    ], $detalles),
                ];
            }
        }

        return $this->render('ventas.crear', [
            'page_title'           => 'Nueva Venta',
            'page_subtitle'        => 'Crear factura',
            'tipo_documento'       => 'venta',
            'clientes'             => $this->clientesActivos(),
            'depositos'            => $this->depositosActivos(),
            'siguiente_factura'    => NumeracionService::siguiente('factura', $this->empresaId(), $this->sucursalId()),
            'siguiente_cotizacion' => NumeracionService::siguiente('cotizacion', $this->empresaId(), $this->sucursalId()),
            'hoy'                  => date('Y-m-d'),
            'fecha_validez'        => date('Y-m-d', strtotime('+30 days')),
            'cotizacion_preload'   => $preload,
        ]);
    }

    public function puntoVenta(): string
    {
        $empresa = Db::row(
            "SELECT pos_desc_cajero, pos_desc_gerente FROM companies WHERE empresa_id = ? LIMIT 1",
            [$this->empresaId()]
        );

        $rol          = Auth::rol();
        $maxDescuento = in_array($rol, ['superadmin', 'gerente', 'administrador'], true)
            ? (int) ($empresa['pos_desc_gerente'] ?? 50)
            : (int) ($empresa['pos_desc_cajero'] ?? 10);

        return $this->render('ventas.pos', [
            'page_title'        => 'Punto de Venta',
            'page_subtitle'     => 'POS',
            'depositos'         => $this->depositosActivos(),
            'clientes'          => $this->clientesActivos(),
            'max_descuento_pct' => $maxDescuento,
            'rol_usuario'       => $rol,
        ]);
    }

    /**
     * Descuento máximo (% del subtotal) que puede dar el usuario: el de gerente de la empresa con
     * descuentos.aplicar, si no el de cajero (companies.pos_desc_gerente / pos_desc_cajero).
     */
    public static function maxDescuentoPct(int $empresaId): int
    {
        $empresa = Db::row("SELECT pos_desc_cajero, pos_desc_gerente FROM companies WHERE empresa_id = ? LIMIT 1", [$empresaId]);
        return Auth::can('descuentos.aplicar')
            ? (int) ($empresa['pos_desc_gerente'] ?? 50)
            : (int) ($empresa['pos_desc_cajero'] ?? 10);
    }

    /** POST JSON — usado por el POS web, el formulario de venta y la PWA móvil. */
    public function procesar()
    {
        $data = $this->jsonInput();

        if (($data['tipo'] ?? 'venta') !== 'venta') {
            return $this->json(['error' => 'Use /ventas/cotizaciones/guardar para cotizaciones'], 422);
        }

        // Los descuentos (por línea + al total) no pueden pasar del máximo del usuario
        $bruto = 0.0;
        $desc  = max(0, (float) ($data['descuento'] ?? 0));
        foreach ((array) ($data['items'] ?? []) as $it) {
            $bruto += (float) ($it['cantidad'] ?? 0) * (float) ($it['precio'] ?? 0);
            $desc  += max(0, (float) ($it['descuento'] ?? 0));
        }
        if ($desc > 0.005) {
            $max = self::maxDescuentoPct((int) $this->empresaId());
            if ($bruto <= 0 || $desc * 100 / $bruto > $max + 0.01) {
                return $this->json(['error' => "El descuento pasa del máximo permitido ({$max}%)."], 422);
            }
        }

        // Venta de un día anterior (crédito anotado a mano que no se cargó ese día): va a la caja de ese día
        $fecha = trim((string) ($data['fecha'] ?? ''));
        $anterior = null;
        if ($fecha !== '' && $fecha !== date('Y-m-d')) {
            $anterior = $this->validarFechaAnterior($fecha, $data);
            if (is_string($anterior)) {
                return $this->json(['error' => $anterior], 422);
            }
        }

        // Venta de hoy toda a crédito sin caja propia abierta (p. ej. el admin en "Nueva venta"):
        // no entra dinero, así que no hace falta abrir caja. Se anota en la caja abierta de la tienda
        // (si hay) para que salga en el crédito del día; no cambia su efectivo.
        $creditoSinCaja = false;
        $cajaTienda     = null;
        if ($anterior === null && self::soloCredito($data) && !CajaService::abiertaId($this->sucursalId())) {
            $creditoSinCaja = true;
            $cajaTienda = Db::value(
                "SELECT caja_id FROM cajas WHERE sucursal_id = ? AND estado = 'abierta' ORDER BY caja_id DESC LIMIT 1",
                [$this->sucursalId()]
            );
        }

        try {
            Db::begin();
            $resultado = VentaService::procesar($data, [
                'empresa_id'  => $this->empresaId(),
                'sucursal_id' => $this->sucursalId(),
                'deposito_id' => $this->depositoId(),
                'ip'          => $this->request->getIPAddress(),
                // Con fecha anterior la caja es la de ese día (se fija abajo), no la abierta del usuario
                'omitir_caja' => $anterior !== null || $creditoSinCaja,
            ]);
            if ($creditoSinCaja && $cajaTienda) {
                Db::update('ventas', ['caja_id' => (int) $cajaTienda], ['venta_id' => $resultado['venta_id']]);
            }
            if ($anterior !== null) {
                Db::update('ventas', ['fecha' => $anterior['hora'], 'caja_id' => $anterior['caja_id']], ['venta_id' => $resultado['venta_id']]);
                Db::query(
                    "UPDATE inventario_movimientos SET fecha_registro = ? WHERE referencia_tipo = 'ventas' AND referencia_id = ?",
                    [$anterior['hora'], $resultado['venta_id']]
                );
                if ($anterior['caja_id'] && $anterior['cerrada']) {
                    CajaService::refrescarCierre((int) $anterior['caja_id']);
                }
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            log_message('error', 'Venta: ' . $e->getMessage());
            return $this->json(['error' => $e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo registrar la venta.'], 500);
        }

        AuditService::log(
            'ventas.crear',
            "Venta #{$resultado['venta_id']} creada por $" . number_format($resultado['total'], 2)
                . ($anterior ? " con fecha {$fecha}" . ($anterior['caja_id'] ? " (caja #{$anterior['caja_id']})" : ' (sin caja ese día)') : ''),
            $resultado['venta_id'], 'venta', ['total' => $resultado['total'], 'fecha' => $anterior ? $fecha : null], $this->empresaId()
        );

        return $this->json($resultado);
    }

    /**
     * Valida una venta con fecha anterior y busca la caja de ese día en la sucursal activa.
     * Solo a crédito: una caja cerrada ya contó su efectivo, el crédito solo se informa.
     *
     * @return array{caja_id: ?int, cerrada: bool, hora: string}|string mensaje de error
     */
    /** ¿Todos los pagos de la venta son a CREDITO? */
    private static function soloCredito(array $data): bool
    {
        $formas = !empty($data['pagos']) && is_array($data['pagos'])
            ? array_map(fn ($p) => strtoupper(trim((string) ($p['forma_pago'] ?? ''))), $data['pagos'])
            : [strtoupper(trim((string) ($data['forma_pago'] ?? '')))];
        return $formas !== [] && !array_diff($formas, ['CREDITO']);
    }

    private function validarFechaAnterior(string $fecha, array $data): array|string
    {
        if (!Auth::can('ventas.fecha_anterior')) {
            return 'No tienes permiso para registrar ventas de días anteriores.';
        }
        $d = \DateTime::createFromFormat('!Y-m-d', $fecha);
        if (!$d || $d->format('Y-m-d') !== $fecha) {
            return 'Fecha no válida.';
        }
        if ($fecha > date('Y-m-d')) {
            return 'La fecha no puede ser futura.';
        }
        if ($fecha < date('Y-m-d', strtotime('-365 days'))) {
            return 'La fecha es de hace más de un año.';
        }
        if (!self::soloCredito($data)) {
            return 'Una venta de un día anterior solo puede ser a crédito (el efectivo de esa caja ya se contó).';
        }
        if (empty($data['cliente_id'])) {
            return 'Elige el cliente del crédito.';
        }

        // Caja de esa tienda que estaba abierta ese día (preferir la abierta ese mismo día y ya cerrada)
        $caja = Db::row(
            "SELECT caja_id, estado, abierta_en, cerrada_en FROM cajas
             WHERE sucursal_id = ? AND DATE(abierta_en) <= ? AND (cerrada_en IS NULL OR DATE(cerrada_en) >= ?)
             ORDER BY DATE(abierta_en) = ? DESC, estado = 'cerrada' DESC, caja_id DESC LIMIT 1",
            [$this->sucursalId(), $fecha, $fecha, $fecha]
        );

        // Hora: justo antes del cierre si la caja cerró ese día; si no, mediodía
        $hora = "{$fecha} 12:00:00";
        if ($caja && $caja['cerrada_en'] && substr((string) $caja['cerrada_en'], 0, 10) === $fecha) {
            $hora = date('Y-m-d H:i:s', strtotime((string) $caja['cerrada_en']) - 60);
        }
        if ($caja && $hora < (string) $caja['abierta_en']) {
            $hora = (string) $caja['abierta_en'];
        }

        return [
            'caja_id' => $caja ? (int) $caja['caja_id'] : null,
            'cerrada' => $caja && $caja['estado'] === 'cerrada',
            'hora'    => $hora,
        ];
    }

    public function detalle(int $ventaId)
    {
        $venta = Db::row(
            "SELECT v.*, c.nombre AS cliente_nombre, c.ruc AS cliente_ruc, u.username AS vendedor_nombre,
                    b.nombre AS sucursal_nombre, b.direccion AS sucursal_direccion, b.telefono AS sucursal_telefono,
                    b.mapa_url, b.latitud, b.longitud
             FROM ventas v
             LEFT JOIN clientes c ON v.cliente_id = c.cliente_id
             LEFT JOIN users u ON v.vendedor_id = u.id
             LEFT JOIN branches b ON b.sucursal_id = v.sucursal_id
             WHERE v.venta_id = ? AND v.sucursal_id = ?",
            [$ventaId, $this->sucursalId()]
        );

        if (!$venta) {
            $this->error('Venta no encontrada.');
            return redirect()->to('/ventas');
        }

        return $this->render('ventas.detalle', [
            'page_title'    => 'Venta ' . $venta['numero_factura'],
            'page_subtitle' => 'Detalle de la venta',
            'venta'         => $venta,
            'detalles'      => Db::all(
                "SELECT d.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo, dep.nombre AS deposito_nombre
                 FROM ventas_detalle d
                 JOIN productos p ON d.producto_id = p.producto_id
                 LEFT JOIN depositos dep ON d.deposito_id = dep.deposito_id
                 WHERE d.venta_id = ?",
                [$ventaId]
            ),
            'pagos_venta' => Db::all("SELECT forma_pago, monto, referencia FROM ventas_pagos WHERE venta_id = ? ORDER BY pago_id", [$ventaId]),
        ]);
    }

    public function anular(int $ventaId)
    {
        $motivoTipo = (string) ($this->request->getPost('motivo_tipo') ?? '');
        $motivo     = $motivoTipo === 'otro'
            ? (string) ($this->request->getPost('motivo') ?? 'Anulación de venta')
            : ($motivoTipo !== '' ? $motivoTipo : 'Anulación de venta');

        try {
            Db::begin();
            $numeroNota = VentaService::anular($ventaId, (int) $this->sucursalId(), (int) $this->empresaId(), $motivo);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error($e instanceof \RuntimeException ? $e->getMessage() : 'Error al anular la venta.');
            return redirect()->to("/ventas/venta/{$ventaId}");
        }

        AuditService::log('ventas.anular', "Venta #{$ventaId} anulada — NC {$numeroNota}", $ventaId, 'venta', ['nota_credito' => $numeroNota], $this->empresaId());
        $this->success("Venta anulada. Nota de crédito {$numeroNota} generada.");
        return redirect()->to("/ventas/venta/{$ventaId}");
    }

    /** Marca como pagada una venta a crédito pendiente. */
    public function registrarPago(int $ventaId)
    {
        $venta = Db::row("SELECT * FROM ventas WHERE venta_id = ? AND sucursal_id = ?", [$ventaId, $this->sucursalId()]);
        if (!$venta || $venta['estado'] !== 'pendiente') {
            $this->error('Solo se pueden pagar ventas en estado pendiente.');
            return redirect()->to("/ventas/venta/{$ventaId}");
        }

        try {
            if (!$venta['cliente_id']) {
                throw new \RuntimeException('La venta no tiene cliente.');
            }
            // Cobra lo que falta de esta factura (descontando abonos previos) y deja recibo
            $pendiente = array_values(array_filter(
                \App\Libraries\CobroService::pendientes((int) $venta['cliente_id'], (int) $this->empresaId()),
                fn ($p) => (int) $p['venta_id'] === $ventaId
            ));
            $falta = $pendiente ? (float) $pendiente[0]['pendiente'] : 0.0;
            if ($falta <= 0) {
                throw new \RuntimeException('Esta factura no tiene saldo pendiente.');
            }
            $forma = (string) ($this->request->getPost('forma_pago') ?? 'EFECTIVO');

            Db::begin();
            $recibo = \App\Libraries\CobroService::registrar(
                (int) $venta['cliente_id'], $falta, $forma, null, 'Pago total desde detalle de venta',
                ['empresa_id' => $this->empresaId(), 'sucursal_id' => $this->sucursalId()], $ventaId
            );
            Db::commit();
            $this->success('Pago registrado (recibo ' . $recibo['numero'] . '). Venta marcada como pagada.');
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error('Error al registrar el pago: ' . $e->getMessage());
        }

        return redirect()->to("/ventas/venta/{$ventaId}");
    }

    // ── Mini-API del POS (sesión, JSON) ──────────────────────────────────────

    /**
     * Búsqueda: primero coincidencia exacta de código / código de barras (lector),
     * si no hay, búsqueda parcial (nombre con todas las palabras, en cualquier orden).
     */
    public function apiBuscarProductos()
    {
        $q          = trim((string) ($this->request->getGet('q') ?? ''));
        $sucursalId = $this->sucursalId();
        $s          = (int) $sucursalId; // precios de esta tienda
        $costo      = ver_costos() ? \App\Libraries\PrecioTienda::sqlCosto($s) . ' AS costo,' : '';

        $base = "SELECT p.producto_id, p.codigo, p.nombre, p.codigo_barras, p.itbms, p.unidad_medida, {$costo}
                        p.maneja_lotes, p.imagen_principal, p.marca AS lugar2,
                        (SELECT d.nombre FROM inventario i
                           JOIN depositos d ON i.deposito_id = d.deposito_id
                          WHERE i.producto_id = p.producto_id AND d.sucursal_id = ? AND i.existencia > 0
                          ORDER BY d.es_principal DESC LIMIT 1) AS lugar,
                        COALESCE((SELECT SUM(i.existencia) FROM inventario i
                                    JOIN depositos d ON i.deposito_id = d.deposito_id
                                   WHERE i.producto_id = p.producto_id AND d.sucursal_id = ?), 0) AS stock,
                        COALESCE(pp_a.precio, 0) AS precio_a,
                        COALESCE(pp_b.precio, 0) AS precio_b,
                        COALESCE(pp_c.precio, 0) AS precio_c
                 FROM productos p
                 LEFT JOIN precios_productos pp_a ON p.producto_id = pp_a.producto_id AND pp_a.tipo_precio = 'a' AND pp_a.sucursal_id = {$s}
                 LEFT JOIN precios_productos pp_b ON p.producto_id = pp_b.producto_id AND pp_b.tipo_precio = 'b' AND pp_b.sucursal_id = {$s}
                 LEFT JOIN precios_productos pp_c ON p.producto_id = pp_c.producto_id AND pp_c.tipo_precio = 'c' AND pp_c.sucursal_id = {$s}
                 WHERE p.estado = 'activo' AND p.empresa_id = ?
                   AND EXISTS (SELECT 1 FROM inventario i2
                                 JOIN depositos d2 ON i2.deposito_id = d2.deposito_id
                                WHERE i2.producto_id = p.producto_id AND d2.sucursal_id = ?)";
        $baseParams = [$sucursalId, $sucursalId, $this->empresaId(), $sucursalId];

        $productos = Db::all($base . ' AND (p.codigo_barras = ? OR p.codigo = ?) LIMIT 1', [...$baseParams, $q, $q]);

        if (empty($productos)) {
            $terminos   = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [$q];
            $condNombre = implode(' AND ', array_fill(0, count($terminos), 'p.nombre LIKE ?'));
            $params     = [...$baseParams, "%{$q}%", "%{$q}%"];
            foreach ($terminos as $t) {
                $params[] = "%{$t}%";
            }
            $productos = Db::all($base . " AND (p.codigo_barras LIKE ? OR p.codigo LIKE ? OR ({$condNombre})) LIMIT 100", $params);
        }

        foreach ($productos as &$p) {
            $p['unidades'] = \App\Libraries\PrecioTienda::unidades((int) $p['producto_id'], $s, 'u.unidad_id');
        }
        unset($p);

        return $this->json(['productos' => $productos]);
    }

    public function apiInfoCliente(int $clienteId)
    {
        $row = Db::row(
            "SELECT limite_credito, saldo_pendiente, dias_credito FROM clientes WHERE cliente_id = ? AND empresa_id = ? LIMIT 1",
            [$clienteId, $this->empresaId()]
        );

        if (!$row) {
            return $this->json(['error' => 'Cliente no encontrado'], 404);
        }

        $limite    = (float) $row['limite_credito'];
        $pendiente = (float) $row['saldo_pendiente'];
        $dias      = (int) $row['dias_credito'];

        return $this->json([
            'limite_credito'     => $limite,
            'saldo_pendiente'    => $pendiente,
            'credito_disponible' => max(0, $limite - $pendiente),
            'dias_credito'       => $dias,
            'tiene_credito'      => $limite > 0 && $dias > 0,
        ]);
    }

    /** Cliente rápido desde el POS (también lo usa la PWA). */
    public function crearClienteRapido()
    {
        $data   = $this->jsonInput();
        $nombre = trim((string) ($data['nombre'] ?? ''));

        if ($nombre === '') {
            return $this->json(['error' => 'El nombre es requerido'], 422);
        }

        $codigo = trim((string) ($data['codigo'] ?? '')) ?: NumeracionService::siguiente('cliente', $this->empresaId());

        try {
            $clienteId = Db::insert('clientes', [
                'empresa_id'     => $this->empresaId(),
                'codigo'         => $codigo,
                'nombre'         => $nombre,
                'tipo'           => $data['tipo'] ?? 'natural',
                'ruc'            => trim((string) ($data['ruc'] ?? '')),
                'dv'             => trim((string) ($data['dv'] ?? '')),
                'direccion'      => trim((string) ($data['direccion'] ?? '')),
                'telefono'       => trim((string) ($data['telefono'] ?? '')),
                'email'          => trim((string) ($data['email'] ?? '')),
                // El límite de crédito solo lo fija quien puede editar clientes (un cajero no se da crédito a sí mismo)
                'limite_credito' => Auth::can('clientes.editar') && is_numeric($data['limite_credito'] ?? null) ? max(0, round((float) $data['limite_credito'], 2)) : 0,
                'dias_credito'   => is_numeric($data['dias_credito'] ?? null) ? $data['dias_credito'] : 0,
                'itbms'          => (($data['itbms'] ?? 'SI') === 'NO') ? 0.00 : 7.00,
                'estado'         => ($data['estado'] ?? 'activo') === 'inactivo' ? 'inactivo' : 'activo',
                'saldo'          => 0,
                'vendedor_id'    => Auth::id(),
                'fecha_registro' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Cliente rápido: ' . $e->getMessage());
            return $this->json(['error' => 'Error al crear el cliente'], 500);
        }

        AuditService::log('clientes.crear', "Cliente creado: {$nombre}", $clienteId, 'cliente', [], $this->empresaId());
        $limite = (float) Db::value("SELECT limite_credito FROM clientes WHERE cliente_id = ?", [$clienteId]);
        return $this->json(['success' => true, 'cliente_id' => $clienteId, 'nombre' => $nombre, 'codigo' => $codigo, 'limite_credito' => $limite]);
    }

    /** POST JSON {limite_credito} — sube/cambia el límite de crédito desde la venta (clientes.editar). */
    public function limiteCredito(int $clienteId)
    {
        $cliente = Db::row("SELECT cliente_id, nombre, limite_credito FROM clientes WHERE cliente_id = ? AND empresa_id = ?", [$clienteId, $this->empresaId()]);
        if (!$cliente) {
            return $this->json(['error' => 'Cliente no encontrado.'], 404);
        }
        $limite = (float) ($this->jsonInput()['limite_credito'] ?? -1);
        if ($limite < 0 || $limite > 1000000) {
            return $this->json(['error' => 'Límite no válido.'], 422);
        }
        $limite = round($limite, 2);
        Db::update('clientes', ['limite_credito' => $limite], ['cliente_id' => $clienteId]);
        AuditService::log('clientes.editar', "Límite de crédito de {$cliente['nombre']}: $" . number_format((float) $cliente['limite_credito'], 2) . " → $" . number_format($limite, 2),
            $clienteId, 'cliente', ['antes' => (float) $cliente['limite_credito'], 'despues' => $limite], $this->empresaId());
        return $this->json(['success' => true, 'limite_credito' => $limite]);
    }

    public function apiVerificarStock(int $productoId)
    {
        $inventarios = Db::all(
            "SELECT i.*, d.nombre AS deposito_nombre
             FROM inventario i
             JOIN depositos d ON i.deposito_id = d.deposito_id
             JOIN productos p ON i.producto_id = p.producto_id
             WHERE i.producto_id = ? AND d.sucursal_id = ? AND p.empresa_id = ?",
            [$productoId, $this->sucursalId(), $this->empresaId()]
        );

        return $this->json([
            'producto_id' => $productoId,
            'stock_total' => array_sum(array_column($inventarios, 'existencia')),
            'inventarios' => $inventarios,
        ]);
    }

    public function apiDepositos()
    {
        return $this->json(['depositos' => Db::all(
            "SELECT deposito_id, codigo, nombre, es_principal FROM depositos WHERE estado = 'activo' AND sucursal_id = ? ORDER BY nombre",
            [$this->sucursalId()]
        )]);
    }

    /**
     * Ventas por día (vista reportes/ventas_periodo). Por defecto el día de hoy;
     * acepta ?desde=&hasta= como el reporte "Ventas por período".
     */
    public function reporteVentasDiarias(): string
    {
        $desde  = (string) ($this->request->getGet('desde') ?: date('Y-m-d'));
        $hasta  = (string) ($this->request->getGet('hasta') ?: date('Y-m-d'));
        $params = [$desde, $hasta, $this->sucursalId()];

        return $this->render('reportes.ventas_periodo', [
            'page_title'    => 'Ventas por día',
            'page_subtitle' => $desde === $hasta ? "Ventas del {$desde}" : "{$desde} - {$hasta}",
            'desde'         => $desde,
            'hasta'         => $hasta,
            'ventas'        => Db::all(
                "SELECT DATE(fecha) AS fecha_emision, COUNT(*) AS total_transacciones,
                        SUM(total) AS total, SUM(subtotal) AS subtotal, SUM(itbms) AS itbms, SUM(costo) AS costo
                 FROM ventas
                 WHERE DATE(fecha) BETWEEN ? AND ? AND estado != 'anulada' AND sucursal_id = ?
                 GROUP BY DATE(fecha) ORDER BY fecha_emision",
                $params
            ),
            'totales' => Db::row(
                "SELECT COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total, COALESCE(SUM(subtotal), 0) AS subtotal,
                        COALESCE(SUM(itbms), 0) AS itbms, COALESCE(SUM(costo), 0) AS costo
                 FROM ventas WHERE DATE(fecha) BETWEEN ? AND ? AND estado != 'anulada' AND sucursal_id = ?",
                $params
            ),
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function clientesActivos(): array
    {
        return Db::all("SELECT * FROM clientes WHERE estado = 'activo' AND empresa_id = ? ORDER BY nombre", [$this->empresaId()]);
    }

    private function depositosActivos(): array
    {
        return Db::all("SELECT * FROM depositos WHERE estado = 'activo' AND sucursal_id = ? ORDER BY es_principal DESC, nombre", [$this->sucursalId()]);
    }
}

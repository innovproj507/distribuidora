<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Libraries\AuditService;
use App\Libraries\Auth;
use App\Libraries\CotizacionService;
use App\Libraries\Db;
use App\Libraries\VentaService;

/**
 * Ventas y cotizaciones por API. El precio SIEMPRE sale de la lista de precios del producto
 * (tipo_precio a/b/c); la API anterior aceptaba el precio enviado por el cliente.
 * Los descuentos solo se aceptan si el rol tiene descuentos.aplicar.
 */
class ApiVentaController extends ApiController
{
    // ── Ventas (/api/v1/ventas) ──────────────────────────────────────────────

    /** GET ?page=&per_page=&desde=&hasta=&estado= */
    public function historial()
    {
        [$pagina, $porPagina, $offset] = $this->paginacion();
        $where  = ['v.empresa_id = ?'];
        $params = [$this->empresaId()];
        if ($desde = $this->request->getGet('desde')) { $where[] = 'DATE(v.fecha) >= ?'; $params[] = $desde; }
        if ($hasta = $this->request->getGet('hasta')) { $where[] = 'DATE(v.fecha) <= ?'; $params[] = $hasta; }
        if ($estado = $this->request->getGet('estado')) { $where[] = 'v.estado = ?'; $params[] = $estado; }

        return $this->ok([
            'ventas' => Db::all(
                "SELECT v.*, c.nombre AS cliente_nombre FROM ventas v LEFT JOIN clientes c ON v.cliente_id = c.cliente_id
                 WHERE " . implode(' AND ', $where) . " ORDER BY v.fecha DESC LIMIT {$porPagina} OFFSET {$offset}",
                $params
            ),
            'page'     => $pagina,
            'per_page' => $porPagina,
        ]);
    }

    public function detalle(int $id)
    {
        $venta = Db::row(
            "SELECT v.*, c.nombre AS cliente_nombre, c.ruc AS cliente_ruc, u.username AS vendedor_nombre
             FROM ventas v LEFT JOIN clientes c ON v.cliente_id = c.cliente_id LEFT JOIN users u ON v.vendedor_id = u.id
             WHERE v.venta_id = ? AND v.empresa_id = ?",
            [$id, $this->empresaId()]
        );
        if (!$venta) {
            return $this->noEncontrado('Venta no encontrada');
        }
        $venta['detalles'] = Db::all(
            "SELECT d.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo FROM ventas_detalle d
             JOIN productos p ON d.producto_id = p.producto_id WHERE d.venta_id = ?",
            [$id]
        );
        $venta['pagos'] = Db::all("SELECT forma_pago, monto FROM ventas_pagos WHERE venta_id = ? ORDER BY pago_id", [$id]);
        return $this->ok($venta);
    }

    /**
     * POST {items[{producto_id, cantidad, tipo_precio?=a, descuento?}], deposito_id?, cliente_id?,
     *       pagos?[{forma_pago, monto}] | forma_pago?, descuento?, notas?}
     */
    public function procesar()
    {
        $data       = $this->entrada();
        $depositoId = $this->depositoId($data['deposito_id'] ?? null);
        if (!$depositoId) {
            return $this->fallo('Depósito no válido.', 422);
        }

        [$items, $error] = $this->prepararItems($data, $depositoId, true);
        if ($error) {
            return $error;
        }
        if (!empty($data['cliente_id']) && !Db::value("SELECT 1 FROM clientes WHERE cliente_id = ? AND empresa_id = ?", [(int) $data['cliente_id'], $this->empresaId()])) {
            return $this->fallo('Cliente no válido.', 422);
        }

        try {
            Db::begin();
            $resultado = VentaService::procesar([
                'items'         => $items,
                'pagos'         => $data['pagos'] ?? null,
                'forma_pago'    => $data['forma_pago'] ?? 'EFECTIVO',
                'cliente_id'    => $data['cliente_id'] ?? null,
                'descuento'     => Auth::can('descuentos.aplicar') ? (float) ($data['descuento'] ?? 0) : 0,
                'notas'         => $data['notas'] ?? 'Venta desde API',
                'aplicar_itbms' => true,
            ], [
                'empresa_id'  => $this->empresaId(),
                'sucursal_id' => $this->sucursalId(),
                'deposito_id' => $depositoId,
                'ip'          => $this->request->getIPAddress(),
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->fallo($this->mensajeError($e, 'No se pudo registrar la venta.'), 422);
        }

        AuditService::log('ventas.crear', "Venta #{$resultado['venta_id']} creada por API por $" . number_format($resultado['total'], 2), $resultado['venta_id'], 'venta', ['total' => $resultado['total']]);
        unset($resultado['success']);
        return $this->ok($resultado, 'Venta procesada exitosamente', 201);
    }

    /** POST {motivo?} */
    public function anular(int $id)
    {
        $venta = Db::row("SELECT sucursal_id FROM ventas WHERE venta_id = ? AND empresa_id = ?", [$id, $this->empresaId()]);
        if (!$venta) {
            return $this->noEncontrado('Venta no encontrada');
        }

        try {
            Db::begin();
            $nota = VentaService::anular($id, (int) $venta['sucursal_id'], $this->empresaId(), trim((string) ($this->entrada()['motivo'] ?? '')) ?: 'Anulación desde API');
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->fallo($this->mensajeError($e, 'No se pudo anular la venta.'), 422);
        }

        AuditService::log('ventas.anular', "Venta #{$id} anulada por API — NC {$nota}", $id, 'venta', ['nota_credito' => $nota]);
        return $this->ok(['nota_credito' => $nota], 'Venta anulada');
    }

    // ── Cotizaciones (/api/v1/cotizaciones) ──────────────────────────────────

    public function cotizaciones()
    {
        [$pagina, $porPagina, $offset] = $this->paginacion();
        $params = [$this->empresaId()];
        $estado = '';
        if ($e = $this->request->getGet('estado')) {
            $estado   = ' AND co.estado = ?';
            $params[] = $e;
        }

        return $this->ok([
            'cotizaciones' => Db::all(
                "SELECT co.*, cl.nombre AS cliente_nombre FROM cotizaciones co
                 LEFT JOIN clientes cl ON co.cliente_id = cl.cliente_id
                 WHERE co.empresa_id = ?{$estado} ORDER BY co.fecha DESC LIMIT {$porPagina} OFFSET {$offset}",
                $params
            ),
            'page'     => $pagina,
            'per_page' => $porPagina,
        ]);
    }

    public function cotizacion(int $id)
    {
        $cot = Db::row(
            "SELECT co.*, cl.nombre AS cliente_nombre FROM cotizaciones co LEFT JOIN clientes cl ON co.cliente_id = cl.cliente_id
             WHERE co.cotizacion_id = ? AND co.empresa_id = ?",
            [$id, $this->empresaId()]
        );
        if (!$cot) {
            return $this->noEncontrado('Cotización no encontrada');
        }
        $cot['detalles']     = Db::all(
            "SELECT d.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo FROM cotizaciones_detalle d
             JOIN productos p ON d.producto_id = p.producto_id WHERE d.cotizacion_id = ?",
            [$id]
        );
        $cot['enlace_publico'] = base_url('c?t=' . urlencode((string) $cot['token']));
        return $this->ok($cot);
    }

    /** POST {items[{producto_id, cantidad, tipo_precio?, descuento?}], cliente_id?, notas?, descuento?} */
    public function crearCotizacion()
    {
        $data = $this->entrada();
        [$items, $error] = $this->prepararItems($data, 0, false);
        if ($error) {
            return $error;
        }

        try {
            Db::begin();
            $res = CotizacionService::crear([
                'items'      => $items,
                'cliente_id' => $data['cliente_id'] ?? null,
                'notas'      => $data['notas'] ?? '',
                'descuento'  => Auth::can('descuentos.aplicar') ? (float) ($data['descuento'] ?? 0) : 0,
            ], ['empresa_id' => $this->empresaId(), 'sucursal_id' => $this->sucursalId()]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->fallo($this->mensajeError($e, 'No se pudo crear la cotización.'), 422);
        }

        return $this->ok($res, 'Cotización creada', 201);
    }

    /** POST {deposito_id?} — la convierte en venta y descuenta stock. */
    public function convertirCotizacion(int $id)
    {
        $cot = Db::row("SELECT sucursal_id FROM cotizaciones WHERE cotizacion_id = ? AND empresa_id = ?", [$id, $this->empresaId()]);
        if (!$cot) {
            return $this->noEncontrado('Cotización no encontrada');
        }
        $depositoId = $this->depositoId($this->entrada()['deposito_id'] ?? null);

        try {
            Db::begin();
            $venta = CotizacionService::convertir($id, [
                'empresa_id'  => $this->empresaId(),
                'sucursal_id' => (int) $cot['sucursal_id'],
                'deposito_id' => $depositoId,
                'ip'          => $this->request->getIPAddress(),
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->fallo($this->mensajeError($e, 'No se pudo convertir la cotización.'), 422);
        }

        AuditService::log('ventas.crear', "Cotización #{$id} convertida por API en venta {$venta['numero_factura']}", $venta['venta_id'], 'venta');
        return $this->ok($venta, 'Cotización convertida en venta', 201);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Valida los ítems y les asigna el precio de lista. Con $validarStock exige existencia
     * suficiente en el depósito.
     *
     * @return array{0: array, 1: ?\CodeIgniter\HTTP\ResponseInterface}
     */
    private function prepararItems(array $data, int $depositoId, bool $validarStock): array
    {
        $items = $data['items'] ?? [];
        if (!is_array($items) || empty($items)) {
            return [[], $this->fallo('No hay productos.', 422)];
        }
        $puedeDescontar = Auth::can('descuentos.aplicar');

        $preparados = [];
        foreach ($items as $i => $item) {
            $productoId = (int) ($item['producto_id'] ?? 0);
            $cantidad   = (float) ($item['cantidad'] ?? 0);
            if ($productoId <= 0 || $cantidad <= 0) {
                return [[], $this->fallo("Ítem {$i}: producto_id y cantidad (> 0) son obligatorios.", 422)];
            }

            $producto = Db::row("SELECT nombre FROM productos WHERE producto_id = ? AND empresa_id = ? AND estado = 'activo'", [$productoId, $this->empresaId()]);
            if (!$producto) {
                return [[], $this->fallo("Ítem {$i}: producto #{$productoId} no existe o está inactivo.", 422)];
            }

            $precio = VentaService::precioLista($productoId, strtolower((string) ($item['tipo_precio'] ?? 'a')));
            if ($precio <= 0) {
                return [[], $this->fallo("\"{$producto['nombre']}\" no tiene precio de venta asignado.", 422)];
            }

            if ($validarStock) {
                $existencia = (float) (Db::value("SELECT existencia FROM inventario WHERE producto_id = ? AND deposito_id = ?", [$productoId, $depositoId]) ?? 0);
                if ($cantidad > $existencia) {
                    return [[], $this->fallo("Stock insuficiente de \"{$producto['nombre']}\" (hay {$existencia}).", 422)];
                }
            }

            $descuento = (float) ($item['descuento'] ?? 0);
            if ($descuento > 0 && !$puedeDescontar) {
                return [[], $this->fallo('Tu rol no puede aplicar descuentos.', 403)];
            }

            $preparados[] = [
                'producto_id' => $productoId,
                'cantidad'    => $cantidad,
                'precio'      => $precio,
                'descuento'   => min($descuento, $precio * $cantidad),
                'deposito_id' => $depositoId ?: null,
            ];
        }

        return [$preparados, null];
    }
}

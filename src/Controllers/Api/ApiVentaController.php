<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\Cliente;

/**
 * ApiVentaController - Endpoints de ventas para la App Móvil y Web
 */
class ApiVentaController extends ApiController
{
    /**
     * Procesar una nueva venta
     */
    public function procesar(): void
    {
        $data = $this->getInputData();
        $items = $data['items'] ?? [];
        $depositoId = $data['deposito_id'] ?? null;

        if (empty($items)) {
            $this->errorResponse('No hay productos en la venta.');
            return;
        }

        if (!$depositoId) {
            $this->errorResponse('El depósito es obligatorio para procesar la venta.');
            return;
        }

        try {
            Database::beginTransaction();

            $itbmsRate = (float) ($_ENV['ITBMS_RATE'] ?? 0.07);
            $subtotal = 0;
            $costoTotal = 0;

            // Generar número de factura
            $numeroFactura = Venta::generarNumeroFactura();

            // Crear venta inicial
            $venta = Venta::create([
                'sucursal_id' => $this->sucursalId(),
                'numero_factura' => $numeroFactura,
                'cliente_id' => !empty($data['cliente_id']) ? (int)$data['cliente_id'] : null,
                'vendedor_id' => $this->user_id,
                'forma_pago' => $data['forma_pago'] ?? 'efectivo',
                'estado' => $data['estado'] ?? 'pagada',
                'notas' => $data['notas'] ?? 'Venta desde API',
                'ip' => $this->request->ip(),
                'subtotal' => 0,
                'descuento' => (float)($data['descuento'] ?? 0),
                'itbms' => 0,
                'total' => 0,
                'costo' => 0,
                'fecha' => date('Y-m-d H:i:s'),
                'empresa_id' => $this->empresaId()
            ]);

            foreach ($items as $item) {
                $productoId = (int) $item['producto_id'];
                $cantidad = (float) $item['cantidad'];
                $precio = (float) $item['precio'];
                $descuentoItem = (float) ($item['descuento'] ?? 0);

                // 1. Verificar stock en el depósito
                $stockStmt = Database::query(
                    "SELECT inventario_id, existencia FROM inventario WHERE producto_id = ? AND deposito_id = ?",
                    [$productoId, $depositoId]
                );
                $inventario = $stockStmt->fetch();

                if (!$inventario || (float)$inventario['existencia'] < $cantidad) {
                    throw new \Exception("Stock insuficiente para el producto ID: {$productoId}");
                }

                // 2. Calcular valores de línea
                $totalLinea = ($cantidad * $precio) - $descuentoItem;
                $itbmsLinea = $totalLinea * $itbmsRate;
                $subtotal += $totalLinea;

                // Obtener costo actual del producto
                $prodStmt = Database::query("SELECT costo FROM productos WHERE producto_id = ?", [$productoId]);
                $producto = $prodStmt->fetch();
                $costo = (float) ($producto['costo'] ?? 0);
                $costoTotal += $costo * $cantidad;

                // 3. Crear detalle de venta
                VentaDetalle::create([
                    'venta_id' => $venta->venta_id,
                    'producto_id' => $productoId,
                    'cantidad' => $cantidad,
                    'precio' => $precio,
                    'costo' => $costo,
                    'itbms' => $itbmsLinea,
                    'descuento' => $descuentoItem,
                    'total_linea' => $totalLinea + $itbmsLinea,
                    'deposito_id' => $depositoId,
                ]);

                // 4. Descontar inventario
                $saldoAnterior = (float)$inventario['existencia'];
                $saldoNuevo = $saldoAnterior - $cantidad;

                Database::query(
                    "UPDATE inventario SET existencia = ?, fecha_actualizacion = NOW() WHERE inventario_id = ?",
                    [$saldoNuevo, $inventario['inventario_id']]
                );

                // 5. Registrar movimiento de inventario
                Database::query(
                    "INSERT INTO inventario_movimientos (inventario_id, tipo, cantidad, saldo_anterior, saldo_nuevo, referencia_id, referencia_tipo, motivo, usuario_id, fecha_registro) 
                     VALUES (?, 'salida', ?, ?, ?, ?, 'ventas', ?, ?, NOW())",
                    [
                        $inventario['inventario_id'], 
                        $cantidad, 
                        $saldoAnterior, 
                        $saldoNuevo, 
                        (int)$venta->venta_id, 
                        "Venta Factura #{$numeroFactura}",
                        $this->user_id
                    ]
                );
            }

            // Actualizar totales de la cabecera de venta
            $descuentoGlobal = (float) ($data['descuento'] ?? 0);
            $itbmsTotal = $subtotal * $itbmsRate;
            $total = $subtotal + $itbmsTotal - $descuentoGlobal;

            $venta->update([
                'subtotal' => round($subtotal, 2),
                'itbms' => round($itbmsTotal, 2),
                'total' => round($total, 2),
                'costo' => round($costoTotal, 2)
            ]);

            Database::commit();

            $this->successResponse([
                'venta_id' => $venta->venta_id,
                'numero_factura' => $numeroFactura,
                'total' => round($total, 2)
            ], 'Venta procesada exitosamente');

        } catch (\Exception $e) {
            Database::rollback();
            $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Historial de ventas recientes
     */
    public function historial(): void
    {
        $limit = (int) ($this->request->get('limit', '20'));
        $empresaId = (int) $this->empresaId();

        $ventas = Database::query(
            "SELECT v.*, c.nombre as cliente_nombre 
             FROM ventas v
             LEFT JOIN clientes c ON v.cliente_id = c.cliente_id
             JOIN branches s ON v.sucursal_id = s.sucursal_id
             WHERE s.empresa_id = ?
             ORDER BY v.fecha DESC
             LIMIT ?",
            [$empresaId, $limit]
        )->fetchAll();

        $this->successResponse(['ventas' => $ventas]);
    }
}

<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Models\Producto;

/**
 * ApiCompraController - Gestión de entradas de mercancía vía API
 */
class ApiCompraController extends ApiController
{
    /**
     * Listar compras recientes
     */
    public function index(): void
    {
        $empresaId = $this->empresaId();
        $limit = (int) ($this->request->get('limit', '20'));

        $compras = Database::query(
            "SELECT c.*, p.nombre as proveedor_nombre 
             FROM compras c
             LEFT JOIN proveedores p ON c.proveedor_id = p.proveedor_id
             WHERE c.empresa_id = ?
             ORDER BY c.fecha_compra DESC
             LIMIT ?",
            [$empresaId, $limit]
        )->fetchAll();

        $this->successResponse(['compras' => $compras]);
    }

    /**
     * Registrar una nueva compra (entrada de stock)
     */
    public function guardar(): void
    {
        $data = $this->getInputData();
        $empresaId = $this->empresaId();
        $items = $data['items'] ?? [];

        if (empty($data['proveedor_id']) || empty($items)) {
            $this->errorResponse('Proveedor e ítems son obligatorios');
            return;
        }

        try {
            Database::beginTransaction();

            $total = 0;
            foreach ($items as $item) {
                $total += ($item['cantidad'] * $item['costo']);
            }

            // Crear cabecera de compra
            Database::query(
                "INSERT INTO compras (empresa_id, proveedor_id, usuario_id, numero_factura, total, fecha_compra, estado) 
                 VALUES (?, ?, ?, ?, ?, NOW(), 'completado')",
                [
                    $empresaId,
                    $data['proveedor_id'],
                    $this->user_id,
                    $data['numero_factura'] ?? 'COMP-' . time(),
                    $total
                ]
            );

            $compraId = (int) Database::lastInsertId();

            // Registrar detalles y actualizar stock
            foreach ($items as $item) {
                $productoId = $item['producto_id'];
                $cantidad = $item['cantidad'];
                $costo = $item['costo'];
                $depositoId = $data['deposito_id'] ?? null;

                Database::query(
                    "INSERT INTO compras_detalle (compra_id, producto_id, cantidad, costo) 
                     VALUES (?, ?, ?, ?)",
                    [$compraId, $productoId, $cantidad, $costo]
                );

                // Actualizar costo en productos
                Database::query(
                    "UPDATE productos SET costo = ? WHERE producto_id = ? AND empresa_id = ?",
                    [$costo, $productoId, $empresaId]
                );
                
                // Actualizar stock en inventario (por depósito si se especificó)
                if ($depositoId) {
                    $existe = Database::query(
                        "SELECT 1 FROM inventario WHERE producto_id = ? AND deposito_id = ?",
                        [$productoId, $depositoId]
                    )->fetch();

                    if ($existe) {
                        Database::query(
                            "UPDATE inventario SET existencia = existencia + ? WHERE producto_id = ? AND deposito_id = ?",
                            [$cantidad, $productoId, $depositoId]
                        );
                    } else {
                        Database::query(
                            "INSERT INTO inventario (producto_id, deposito_id, existencia, fecha_movimiento) VALUES (?, ?, ?, NOW())",
                            [$productoId, $depositoId, $cantidad]
                        );
                    }
                }
            }

            Database::commit();
            $this->successResponse(['compra_id' => $compraId], 'Compra registrada exitosamente', 201);

        } catch (\Exception $e) {
            Database::rollback();
            $this->errorResponse($e->getMessage(), 500);
        }
    }
}

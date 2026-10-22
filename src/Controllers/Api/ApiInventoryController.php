<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;

/**
 * ApiInventoryController - Gestión de stock e inventario vía API
 */
class ApiInventoryController extends ApiController
{
    /**
     * Obtener stock de un producto en todos los depósitos
     */
    public function stockByProduct(int $productId): void
    {
        $stmt = Database::query(
            "SELECT i.*, d.nombre as deposito_nombre 
             FROM inventario i
             JOIN depositos d ON i.deposito_id = d.deposito_id
             WHERE i.producto_id = ?
             ORDER BY d.nombre ASC",
            [$productId]
        );
        $stock = $stmt->fetchAll();

        $this->successResponse($stock);
    }

    /**
     * Obtener stock de un producto en un depósito específico
     */
    public function stockByProductAndDeposit(int $productId, int $depositId): void
    {
        $stmt = Database::query(
            "SELECT i.*, d.nombre as deposito_nombre 
             FROM inventario i
             JOIN depositos d ON i.deposito_id = d.deposito_id
             WHERE i.producto_id = ? AND i.deposito_id = ?",
            [$productId, $depositId]
        );
        $stock = $stmt->fetch();

        if (!$stock) {
            $this->successResponse(['existencia' => 0], 'Sin existencia registrada en este depósito');
            return;
        }

        $this->successResponse($stock);
    }

    /**
     * Listar inventario completo de un depósito
     */
    public function inventoryByDeposit(int $depositId): void
    {
        $stmt = Database::query(
            "SELECT i.*, p.nombre as producto_nombre, p.codigo as producto_codigo
             FROM inventario i
             JOIN productos p ON i.producto_id = p.producto_id
             WHERE i.deposito_id = ? AND p.estado = 'activo'
             ORDER BY p.nombre ASC",
            [$depositId]
        );
        $inventory = $stmt->fetchAll();

        $this->successResponse($inventory);
    }

    /**
     * Ajuste manual de stock (Uso administrativo/Superadmin del tenant)
     */
    public function adjustStock(): void
    {
        $data = $this->getInputData();
        $productId = (int) ($data['producto_id'] ?? 0);
        $depositId = (int) ($data['deposito_id'] ?? 0);
        $cantidad = (float) ($data['cantidad'] ?? 0);
        $motivo = $data['motivo'] ?? 'Ajuste manual';

        if (!$productId || !$depositId) {
            $this->errorResponse('Producto y Depósito son requeridos');
            return;
        }

        // Verificar si existe el registro en inventario
        $stmt = Database::query(
            "SELECT inventario_id, existencia FROM inventario WHERE producto_id = ? AND deposito_id = ?",
            [$productId, $depositId]
        );
        $inventario = $stmt->fetch();

        Database::beginTransaction();
        try {
            $saldoAnterior = 0;
            if ($inventario) {
                $saldoAnterior = (float)$inventario['existencia'];
                Database::query(
                    "UPDATE inventario SET existencia = ?, fecha_actualizacion = NOW() WHERE inventario_id = ?",
                    [$cantidad, $inventario['inventario_id']]
                );
                $inventarioId = (int)$inventario['inventario_id'];
            } else {
                Database::query(
                    "INSERT INTO inventario (producto_id, deposito_id, existencia, fecha_actualizacion) VALUES (?, ?, ?, NOW())",
                    [$productId, $depositId, $cantidad]
                );
                $inventarioId = (int)Database::lastInsertId();
            }

            // Registrar movimiento
            Database::query(
                "INSERT INTO inventario_movimientos (inventario_id, tipo, cantidad, saldo_anterior, saldo_nuevo, referencia_id, referencia_tipo, motivo, usuario_id, fecha_registro) 
                 VALUES (?, 'ajuste', ?, ?, ?, ?, 'ajuste_manual', ?, ?, NOW())",
                [
                    $inventarioId, 
                    $cantidad, 
                    $saldoAnterior, 
                    $cantidad, 
                    $productId, 
                    $motivo,
                    $this->user_id
                ]
            );

            Database::commit();
            $this->successResponse([], 'Ajuste de stock realizado correctamente');

        } catch (\Exception $e) {
            Database::rollback();
            $this->errorResponse('Error al ajustar stock: ' . $e->getMessage());
        }
    }
}

<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;

/**
 * ApiDepositoController - CRUD de depósitos/bodegas
 * Los depósitos pertenecen a una sucursal; se filtra por empresa a través del JOIN.
 */
class ApiDepositoController extends ApiController
{
    /**
     * Listado de depósitos activos de la empresa
     */
    public function index(): void
    {
        $empresaId = $this->empresaId();
        $sucursalId = (int) ($_GET['sucursal_id'] ?? 0);

        $sql = "SELECT d.*, b.nombre AS sucursal_nombre
                FROM depositos d
                JOIN branches b ON d.sucursal_id = b.sucursal_id
                WHERE b.empresa_id = ? AND d.estado = 'activo'";
        $params = [$empresaId];

        if ($sucursalId) {
            $sql .= " AND d.sucursal_id = ?";
            $params[] = $sucursalId;
        }

        $sql .= " ORDER BY b.nombre, d.nombre";

        $depositos = Database::query($sql, $params)->fetchAll();
        $this->successResponse(['depositos' => $depositos]);
    }

    /**
     * Detalle de un depósito
     */
    public function detalle(int $id): void
    {
        $empresaId = $this->empresaId();

        $deposito = Database::query(
            "SELECT d.*, b.nombre AS sucursal_nombre
             FROM depositos d
             JOIN branches b ON d.sucursal_id = b.sucursal_id
             WHERE d.deposito_id = ? AND b.empresa_id = ?",
            [$id, $empresaId]
        )->fetch();

        if (!$deposito) {
            $this->notFound('Depósito no encontrado');
            return;
        }

        // Incluir stock total del depósito
        $stock = Database::query(
            "SELECT COUNT(*) as total_productos, COALESCE(SUM(existencia),0) as total_unidades
             FROM inventario WHERE deposito_id = ?",
            [$id]
        )->fetch();

        $this->successResponse(array_merge($deposito, ['stock' => $stock]));
    }

    /**
     * Crear depósito
     */
    public function guardar(): void
    {
        $data      = $this->getInputData();
        $empresaId = $this->empresaId();

        if (empty($data['nombre']) || empty($data['sucursal_id'])) {
            $this->errorResponse('El nombre y la sucursal son obligatorios');
            return;
        }

        // Verificar que la sucursal pertenece a la empresa
        $sucursal = Database::query(
            "SELECT sucursal_id FROM branches WHERE sucursal_id = ? AND empresa_id = ? AND activa = 1",
            [(int) $data['sucursal_id'], $empresaId]
        )->fetch();

        if (!$sucursal) {
            $this->errorResponse('Sucursal no válida', 422);
            return;
        }

        Database::query(
            "INSERT INTO depositos (sucursal_id, codigo, nombre, descripcion, es_principal, estado)
             VALUES (?, ?, ?, ?, ?, 'activo')",
            [
                (int) $data['sucursal_id'],
                $data['codigo']      ?? null,
                $data['nombre'],
                $data['descripcion'] ?? null,
                !empty($data['es_principal']) ? 1 : 0,
            ]
        );

        $depositoId = (int) Database::lastInsertId();
        $this->successResponse(['deposito_id' => $depositoId], 'Depósito creado exitosamente', 201);
    }

    /**
     * Actualizar depósito
     */
    public function actualizar(int $id): void
    {
        $data      = $this->getInputData();
        $empresaId = $this->empresaId();

        // Verificar propiedad
        $existe = Database::query(
            "SELECT d.deposito_id FROM depositos d JOIN branches b ON d.sucursal_id = b.sucursal_id
             WHERE d.deposito_id = ? AND b.empresa_id = ?",
            [$id, $empresaId]
        )->fetch();

        if (!$existe) {
            $this->notFound('Depósito no encontrado');
            return;
        }

        Database::query(
            "UPDATE depositos SET nombre = ?, descripcion = ?, codigo = ?, es_principal = ?
             WHERE deposito_id = ?",
            [
                $data['nombre'],
                $data['descripcion'] ?? null,
                $data['codigo']      ?? null,
                !empty($data['es_principal']) ? 1 : 0,
                $id,
            ]
        );

        $this->successResponse([], 'Depósito actualizado correctamente');
    }

    /**
     * Eliminar (desactivar) depósito
     */
    public function eliminar(int $id): void
    {
        $empresaId = $this->empresaId();

        $existe = Database::query(
            "SELECT d.deposito_id FROM depositos d JOIN branches b ON d.sucursal_id = b.sucursal_id
             WHERE d.deposito_id = ? AND b.empresa_id = ?",
            [$id, $empresaId]
        )->fetch();

        if (!$existe) {
            $this->notFound('Depósito no encontrado');
            return;
        }

        Database::query("UPDATE depositos SET estado = 'inactivo' WHERE deposito_id = ?", [$id]);
        $this->successResponse([], 'Depósito desactivado correctamente');
    }
}

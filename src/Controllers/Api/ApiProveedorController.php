<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\Proveedor;

/**
 * ApiProveedorController - Endpoints para proveedores
 */
class ApiProveedorController extends ApiController
{
    /**
     * Listado de proveedores
     */
    public function index(): void
    {
        $page = (int) ($this->request->get('page', '1'));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $empresaId = $this->empresaId();

        $proveedores = \App\Core\Database::query(
            "SELECT * FROM proveedores 
             WHERE empresa_id = ? AND estado = 'activo'
             ORDER BY nombre
             LIMIT {$perPage} OFFSET {$offset}",
            [$empresaId]
        )->fetchAll();

        $this->successResponse([
            'proveedores' => $proveedores,
            'page' => $page,
            'per_page' => $perPage
        ]);
    }

    /**
     * Búsqueda de proveedores
     */
    public function buscar(): void
    {
        $query = $this->request->get('q', '');
        $empresaId = $this->empresaId();

        if (strlen($query) < 2) {
            $this->successResponse(['proveedores' => []]);
            return;
        }

        $search = "%{$query}%";
        $proveedores = \App\Core\Database::query(
            "SELECT * FROM proveedores 
             WHERE empresa_id = ? 
             AND (nombre LIKE ? OR ruc LIKE ?)
             AND estado = 'activo'
             LIMIT 15",
            [$empresaId, $search, $search]
        )->fetchAll();

        $this->successResponse(['proveedores' => $proveedores]);
    }

    /**
     * Obtener detalle de un proveedor
     */
    public function detalle(int $id): void
    {
        $empresaId = $this->empresaId();

        $proveedor = \App\Core\Database::query(
            "SELECT * FROM proveedores WHERE proveedor_id = ? AND empresa_id = ?",
            [$id, $empresaId]
        )->fetch();

        if (!$proveedor) {
            $this->notFound('Proveedor no encontrado');
            return;
        }

        $this->successResponse($proveedor);
    }

    /**
     * Crear nuevo proveedor
     */
    public function guardar(): void
    {
        $data = $this->getInputData();
        $empresaId = $this->empresaId();

        if (empty($data['nombre']) || empty($data['ruc'])) {
            $this->errorResponse('Nombre y RUC son obligatorios');
            return;
        }

        \App\Core\Database::query(
            "INSERT INTO proveedores (empresa_id, nombre, ruc, email, telefono, direccion, estado) 
             VALUES (?, ?, ?, ?, ?, ?, 'activo')",
            [
                $empresaId,
                $data['nombre'],
                $data['ruc'],
                $data['email'] ?? null,
                $data['telefono'] ?? null,
                $data['direccion'] ?? null
            ]
        );

        $proveedorId = (int) \App\Core\Database::lastInsertId();

        $this->successResponse(['proveedor_id' => $proveedorId], 'Proveedor creado con éxito', 201);
    }

    /**
     * Actualizar proveedor
     */
    public function actualizar(int $id): void
    {
        $data = $this->getInputData();
        $empresaId = $this->empresaId();

        $success = \App\Core\Database::query(
            "UPDATE proveedores SET 
                nombre = ?, 
                ruc = ?, 
                email = ?, 
                telefono = ?, 
                direccion = ?
             WHERE proveedor_id = ? AND empresa_id = ?",
            [
                $data['nombre'],
                $data['ruc'],
                $data['email'] ?? null,
                $data['telefono'] ?? null,
                $data['direccion'] ?? null,
                $id,
                $empresaId
            ]
        );

        if ($success) {
            $this->successResponse([], 'Proveedor actualizado correctamente');
        } else {
            $this->errorResponse('Error al actualizar el proveedor');
        }
    }

    /**
     * Eliminar proveedor
     */
    public function eliminar(int $id): void
    {
        $empresaId = $this->empresaId();

        $success = \App\Core\Database::query(
            "UPDATE proveedores SET estado = 'inactivo' WHERE proveedor_id = ? AND empresa_id = ?",
            [$id, $empresaId]
        );

        if ($success) {
            $this->successResponse([], 'Proveedor eliminado correctamente');
        } else {
            $this->errorResponse('Error al eliminar el proveedor');
        }
    }
}

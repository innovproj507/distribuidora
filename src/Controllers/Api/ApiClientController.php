<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;

/**
 * ApiClientController - Endpoints de clientes para la App Móvil
 */
class ApiClientController extends ApiController
{
    /**
     * Listado de clientes con paginación
     */
    public function index(): void
    {
        $page = (int) ($this->request->get('page', '1'));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $empresaId = (int) $this->empresaId();

        $clientes = Database::query(
            "SELECT * FROM clientes 
             WHERE empresa_id = ? AND estado = 'activo'
             ORDER BY nombre
             LIMIT {$perPage} OFFSET {$offset}",
            [$empresaId]
        )->fetchAll();

        $this->successResponse([
            'clientes' => $clientes,
            'page' => $page,
            'per_page' => $perPage
        ]);
    }

    /**
     * Buscar clientes por nombre o teléfono
     */
    public function buscar(): void
    {
        $q = $this->request->get('q', '');
        $empresaId = (int) $this->empresaId();

        if (strlen($q) < 1) {
            $this->successResponse(['clientes' => []]);
            return;
        }

        $search = "%{$q}%";
        $clientes = Database::query(
            "SELECT cliente_id, nombre, telefono, email, estado
             FROM clientes
             WHERE empresa_id = ? AND estado = 'activo'
             AND (nombre LIKE ? OR telefono LIKE ?)
             ORDER BY nombre LIMIT 20",
            [$empresaId, $search, $search]
        )->fetchAll();

        $this->successResponse(['clientes' => $clientes]);
    }

    /**
     * Detalle de un cliente
     */
    public function detalle(int $id): void
    {
        $empresaId = (int) $this->empresaId();

        $cliente = Database::query(
            "SELECT * FROM clientes WHERE cliente_id = ? AND empresa_id = ?",
            [$id, $empresaId]
        )->fetch();

        if (!$cliente) {
            $this->notFound('Cliente no encontrado');
            return;
        }

        $this->successResponse($cliente);
    }

    /**
     * Crear un nuevo cliente
     */
    public function guardar(): void
    {
        $data = $this->getInputData();
        $empresaId = (int) $this->empresaId();

        if (empty($data['nombre']) || empty($data['ruc'])) {
            $this->errorResponse('Nombre y RUC son obligatorios');
            return;
        }

        // Verificar si el RUC ya existe
        $existe = Database::query(
            "SELECT cliente_id FROM clientes WHERE ruc = ? AND empresa_id = ?",
            [$data['ruc'], $empresaId]
        )->fetch();

        if ($existe) {
            $this->errorResponse("Un cliente con el RUC '{$data['ruc']}' ya existe");
            return;
        }

        Database::query(
            "INSERT INTO clientes (empresa_id, nombre, ruc, dv, email, telefono, direccion, estado, fecha_registro) 
             VALUES (?, ?, ?, ?, ?, ?, ?, 'activo', NOW())",
            [
                $empresaId,
                $data['nombre'],
                $data['ruc'],
                $data['dv'] ?? '00',
                $data['email'] ?? null,
                $data['telefono'] ?? null,
                $data['direccion'] ?? null
            ]
        );

        $clienteId = (int) Database::lastInsertId();

        if ($clienteId) {
            $this->successResponse(['cliente_id' => $clienteId], 'Cliente creado correctamente', 201);
        } else {
            $this->errorResponse('Error al crear el cliente');
        }
    }

    /**
     * Actualizar un cliente existente
     */
    public function actualizar(int $id): void
    {
        $data = $this->getInputData();
        $empresaId = (int) $this->empresaId();

        $cliente = Database::query(
            "SELECT cliente_id FROM clientes WHERE cliente_id = ? AND empresa_id = ?",
            [$id, $empresaId]
        )->fetch();

        if (!$cliente) {
            $this->notFound('Cliente no encontrado');
            return;
        }

        if (empty($data['nombre']) || empty($data['ruc'])) {
            $this->errorResponse('Nombre y RUC son obligatorios');
            return;
        }

        // Verificar si el RUC ya existe en otro cliente
        $existe = Database::query(
            "SELECT cliente_id FROM clientes WHERE ruc = ? AND empresa_id = ? AND cliente_id != ?",
            [$data['ruc'], $empresaId, $id]
        )->fetch();

        if ($existe) {
            $this->errorResponse("Un cliente con el RUC '{$data['ruc']}' ya existe");
            return;
        }

        $success = Database::query(
            "UPDATE clientes SET 
                nombre = ?, 
                ruc = ?, 
                dv = ?,
                email = ?, 
                telefono = ?, 
                direccion = ?
             WHERE cliente_id = ? AND empresa_id = ?",
            [
                $data['nombre'],
                $data['ruc'],
                $data['dv'] ?? '00',
                $data['email'] ?? null,
                $data['telefono'] ?? null,
                $data['direccion'] ?? null,
                $id,
                $empresaId
            ]
        );

        if ($success) {
            $this->successResponse([], 'Cliente actualizado correctamente');
        } else {
            $this->errorResponse('Error al actualizar el cliente');
        }
    }

    /**
     * Eliminar (desactivar) un cliente
     */
    public function eliminar(int $id): void
    {
        $empresaId = (int) $this->empresaId();

        $success = Database::query(
            "UPDATE clientes SET estado = 'inactivo' WHERE cliente_id = ? AND empresa_id = ?",
            [$id, $empresaId]
        );

        if ($success) {
            $this->successResponse([], 'Cliente eliminado correctamente');
        } else {
            $this->errorResponse('Error al eliminar el cliente');
        }
    }
}

<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;

/**
 * ApiSucursalController - CRUD de sucursales de la empresa
 */
class ApiSucursalController extends ApiController
{
    /**
     * Listado de sucursales activas
     */
    public function index(): void
    {
        $empresaId = $this->empresaId();

        $sucursales = Database::query(
            "SELECT b.*,
                    (SELECT COUNT(*) FROM depositos d WHERE d.sucursal_id = b.sucursal_id AND d.estado = 'activo') AS total_depositos
             FROM branches b
             WHERE b.empresa_id = ? AND b.activa = 1
             ORDER BY b.es_principal DESC, b.nombre",
            [$empresaId]
        )->fetchAll();

        $this->successResponse(['sucursales' => $sucursales]);
    }

    /**
     * Detalle de una sucursal con sus depósitos
     */
    public function detalle(int $id): void
    {
        $empresaId = $this->empresaId();

        $sucursal = Database::query(
            "SELECT * FROM branches WHERE sucursal_id = ? AND empresa_id = ? AND activa = 1",
            [$id, $empresaId]
        )->fetch();

        if (!$sucursal) {
            $this->notFound('Sucursal no encontrada');
            return;
        }

        $depositos = Database::query(
            "SELECT * FROM depositos WHERE sucursal_id = ? AND estado = 'activo' ORDER BY es_principal DESC, nombre",
            [$id]
        )->fetchAll();

        $this->successResponse(array_merge($sucursal, ['depositos' => $depositos]));
    }

    /**
     * Crear sucursal
     */
    public function guardar(): void
    {
        $data      = $this->getInputData();
        $empresaId = $this->empresaId();

        if (empty($data['nombre'])) {
            $this->errorResponse('El nombre de la sucursal es obligatorio');
            return;
        }

        Database::query(
            "INSERT INTO branches (empresa_id, codigo, nombre, direccion, telefono, email, es_principal, activa)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)",
            [
                $empresaId,
                $data['codigo']    ?? null,
                $data['nombre'],
                $data['direccion'] ?? null,
                $data['telefono']  ?? null,
                $data['email']     ?? null,
                !empty($data['es_principal']) ? 1 : 0,
            ]
        );

        $sucursalId = (int) Database::lastInsertId();
        $this->successResponse(['sucursal_id' => $sucursalId], 'Sucursal creada exitosamente', 201);
    }

    /**
     * Actualizar sucursal
     */
    public function actualizar(int $id): void
    {
        $data      = $this->getInputData();
        $empresaId = $this->empresaId();

        $existe = Database::query(
            "SELECT sucursal_id FROM branches WHERE sucursal_id = ? AND empresa_id = ?",
            [$id, $empresaId]
        )->fetch();

        if (!$existe) {
            $this->notFound('Sucursal no encontrada');
            return;
        }

        Database::query(
            "UPDATE branches SET nombre = ?, codigo = ?, direccion = ?, telefono = ?, email = ?, es_principal = ?
             WHERE sucursal_id = ?",
            [
                $data['nombre'],
                $data['codigo']    ?? null,
                $data['direccion'] ?? null,
                $data['telefono']  ?? null,
                $data['email']     ?? null,
                !empty($data['es_principal']) ? 1 : 0,
                $id,
            ]
        );

        $this->successResponse([], 'Sucursal actualizada correctamente');
    }

    /**
     * Desactivar sucursal (no se puede eliminar la principal)
     */
    public function eliminar(int $id): void
    {
        $empresaId = $this->empresaId();

        $existe = Database::query(
            "SELECT sucursal_id FROM branches WHERE sucursal_id = ? AND empresa_id = ? AND es_principal = 0",
            [$id, $empresaId]
        )->fetch();

        if (!$existe) {
            $this->notFound('Sucursal no encontrada o no se puede eliminar la sucursal principal');
            return;
        }

        Database::query("UPDATE branches SET activa = 0 WHERE sucursal_id = ?", [$id]);
        $this->successResponse([], 'Sucursal desactivada correctamente');
    }

    /**
     * Cambiar sucursal activa del usuario (útil para la app móvil)
     */
    public function cambiar(int $id): void
    {
        $empresaId = $this->empresaId();

        $sucursal = Database::query(
            "SELECT sucursal_id FROM branches WHERE sucursal_id = ? AND empresa_id = ? AND activa = 1",
            [$id, $empresaId]
        )->fetch();

        if (!$sucursal) {
            $this->notFound('Sucursal no válida');
            return;
        }

        Database::query(
            "UPDATE user_profiles SET sucursal_actual_id = ? WHERE user_id = ?",
            [$id, $this->user_id]
        );

        $this->sucursal_id = $id;
        $this->successResponse(['sucursal_id' => $id], 'Sucursal cambiada correctamente');
    }
}

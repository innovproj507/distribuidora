<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;

/**
 * ApiConfiguracionController - Endpoints para configuración del sistema y sucursales
 */
class ApiConfiguracionController extends ApiController
{
    /**
     * Obtener datos de la empresa y sus sucursales
     */
    public function empresa(): void
    {
        $empresaId = $this->empresaId();

        $empresa = Database::query(
            "SELECT * FROM companies WHERE empresa_id = ?",
            [$empresaId]
        )->fetch();

        $sucursales = Database::query(
            "SELECT * FROM branches WHERE empresa_id = ? AND activa = 1",
            [$empresaId]
        )->fetchAll();

        $this->successResponse([
            'empresa' => $empresa,
            'sucursales' => $sucursales
        ]);
    }

    /**
     * Obtener detalle de una sucursal
     */
    public function sucursal(int $id): void
    {
        $empresaId = $this->empresaId();

        $sucursal = Database::query(
            "SELECT * FROM branches WHERE sucursal_id = ? AND empresa_id = ?",
            [$id, $empresaId]
        )->fetch();

        if (!$sucursal) {
            $this->notFound('Sucursal no encontrada');
            return;
        }

        $this->successResponse($sucursal);
    }

    /**
     * Actualizar configuración de la empresa
     */
    public function actualizarEmpresa(): void
    {
        if (!$this->user['is_staff']) {
            $this->errorResponse('No tiene permisos para modificar la configuración global', 403);
            return;
        }

        $data = $this->getInputData();
        $empresaId = $this->empresaId();

        // Usar los nombres de columna reales: razon_social, ruc, etc.
        $success = Database::query(
            "UPDATE companies SET 
                razon_social = ?, 
                nombre_comercial = ?,
                ruc = ?, 
                email = ?, 
                telefono = ?, 
                direccion = ?
             WHERE empresa_id = ?",
            [
                $data['razon_social'] ?? $data['nombre'] ?? '',
                $data['nombre_comercial'] ?? '',
                $data['ruc'] ?? $data['identificacion'] ?? '',
                $data['email'] ?? null,
                $data['telefono'] ?? null,
                $data['direccion'] ?? null,
                $empresaId
            ]
        );

        if ($success) {
            $this->successResponse([], 'Configuración de empresa actualizada');
        } else {
            $this->errorResponse('Error al actualizar la configuración');
        }
    }
}

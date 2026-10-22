<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\CategoriaProducto;

/**
 * ApiCategoriaController - Endpoints para categorías de productos
 */
class ApiCategoriaController extends ApiController
{
    /**
     * Listar todas las categorías
     */
    public function index(): void
    {
        $categorias = CategoriaProducto::all();
        $this->successResponse(['categorias' => $categorias]);
    }

    /**
     * Obtener detalle de una categoría
     */
    public function detalle(int $id): void
    {
        $categoria = CategoriaProducto::find($id);
        if (!$categoria) {
            $this->errorResponse('Categoría no encontrada', 404);
            return;
        }
        $this->successResponse(['categoria' => $categoria]);
    }

    /**
     * Crear nueva categoría
     */
    public function guardar(): void
    {
        $data = $this->getInputData();
        $empresaId = $this->empresaId();
        
        if (empty($data['nombre'])) {
            $this->errorResponse('El nombre es obligatorio');
            return;
        }

        \App\Core\Database::query(
            "INSERT INTO categories_producto (empresa_id, nombre, descripcion, is_active) 
             VALUES (?, ?, ?, 1)",
            [
                $empresaId,
                $data['nombre'],
                $data['descripcion'] ?? ''
            ]
        );

        $categoriaId = (int) \App\Core\Database::lastInsertId();

        $this->successResponse(['categoria_id' => $categoriaId], 'Categoría creada con éxito', 201);
    }

    /**
     * Actualizar una categoría existente
     */
    public function actualizar(int $id): void
    {
        $data = $this->getInputData();
        $empresaId = $this->empresaId();

        $success = \App\Core\Database::query(
            "UPDATE categories_producto SET 
                nombre = ?, 
                descripcion = ?
             WHERE categoria_id = ? AND empresa_id = ?",
            [
                $data['nombre'],
                $data['descripcion'] ?? '',
                $id,
                $empresaId
            ]
        );

        if ($success) {
            $this->successResponse([], 'Categoría actualizada correctamente');
        } else {
            $this->errorResponse('Error al actualizar la categoría');
        }
    }

    /**
     * Eliminar una categoría
     */
    public function eliminar(int $id): void
    {
        $empresaId = $this->empresaId();

        $success = \App\Core\Database::query(
            "UPDATE categories_producto SET is_active = 0 WHERE categoria_id = ? AND empresa_id = ?",
            [$id, $empresaId]
        );

        if ($success) {
            $this->successResponse([], 'Categoría eliminada correctamente');
        } else {
            $this->errorResponse('Error al eliminar la categoría');
        }
    }
}

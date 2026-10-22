<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Models\Producto;

/**
 * ApiProductoController - Endpoints de productos para la App Móvil
 */
class ApiProductoController extends ApiController
{
    /**
     * Listado de productos con paginación básica
     */
    public function index(): void
    {
        $page = (int) ($this->request->get('page', '1'));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $empresaId = (int) $this->empresaId();

        $productos = Database::query(
            "SELECT p.*, c.nombre as categoria_nombre 
             FROM productos p
             LEFT JOIN categorias_productos c ON p.categoria_id = c.categoria_id
             WHERE p.empresa_id = ? AND p.estado = 'activo'
             ORDER BY p.nombre
             LIMIT {$perPage} OFFSET {$offset}",
            [$empresaId]
        )->fetchAll();

        $this->successResponse([
            'productos' => $productos,
            'page' => $page,
            'per_page' => $perPage
        ]);
    }

    /**
     * Búsqueda de productos con precios, stock y presentaciones (para POS móvil)
     */
    public function buscar(): void
    {
        $q = $this->request->get('q', '');
        $empresaId = (int) $this->empresaId();
        $sucursalId = (int) $this->sucursalId();

        if (strlen($q) < 1) {
            $this->successResponse(['productos' => []]);
            return;
        }

        $search = "%{$q}%";
        $productos = Database::query(
            "SELECT p.producto_id, p.codigo, p.nombre, p.codigo_barras, p.itbms,
                    p.maneja_lotes, p.imagen_principal, p.marca,
                    (SELECT d.nombre FROM inventario i JOIN depositos d ON i.deposito_id = d.deposito_id
                     WHERE i.producto_id = p.producto_id AND d.sucursal_id = ? AND i.existencia > 0
                     ORDER BY d.es_principal DESC LIMIT 1) as lugar,
                    COALESCE((SELECT SUM(i.existencia) FROM inventario i JOIN depositos d ON i.deposito_id = d.deposito_id
                              WHERE i.producto_id = p.producto_id AND d.sucursal_id = ?), 0) as stock,
                    COALESCE(pp_a.precio, 0) as precio_a,
                    COALESCE(pp_b.precio, 0) as precio_b
             FROM productos p
             LEFT JOIN precios_productos pp_a ON p.producto_id = pp_a.producto_id AND pp_a.tipo_precio = 'a'
             LEFT JOIN precios_productos pp_b ON p.producto_id = pp_b.producto_id AND pp_b.tipo_precio = 'b'
             WHERE p.estado = 'activo' AND p.empresa_id = ?
             AND (p.nombre LIKE ? OR p.codigo LIKE ? OR p.codigo_barras LIKE ?)
             LIMIT 30",
            [$sucursalId, $sucursalId, $empresaId, $search, $search, $search]
        )->fetchAll();

        foreach ($productos as &$p) {
            $p['unidades'] = Database::query(
                "SELECT * FROM productos_unidades WHERE producto_id = ?",
                [$p['producto_id']]
            )->fetchAll();
        }

        $this->successResponse(['productos' => $productos]);
    }

    /**
     * Detalle de un producto
     */
    public function detalle(int $id): void
    {
        $empresaId = (int) $this->empresaId();
        
        $producto = Database::query(
            "SELECT p.*, c.nombre as categoria_nombre 
             FROM productos p
             LEFT JOIN categorias_productos c ON p.categoria_id = c.categoria_id
             WHERE p.producto_id = ? AND p.empresa_id = ?",
            [$id, $empresaId]
        )->fetch();

        if (!$producto) {
            $this->notFound('Producto no encontrado');
            return;
        }

        // Obtener precios
        $precios = Database::query(
            "SELECT tipo_precio, precio FROM precios_productos WHERE producto_id = ?",
            [$id]
        )->fetchAll(\PDO::FETCH_KEY_PAIR);

        $producto['precios'] = $precios;

        $this->successResponse($producto);
    }

    /**
     * Crear un nuevo producto
     */
    public function guardar(): void
    {
        $data = $this->getInputData();
        $empresaId = (int) $this->empresaId();

        if (empty($data['nombre']) || empty($data['codigo'])) {
            $this->errorResponse('Nombre y código son obligatorios');
            return;
        }

        // Verificar si el código ya existe
        $existe = Database::query(
            "SELECT producto_id FROM productos WHERE codigo = ? AND empresa_id = ?",
            [$data['codigo'], $empresaId]
        )->fetch();

        if ($existe) {
            $this->errorResponse("El código de producto '{$data['codigo']}' ya está en uso");
            return;
        }

        Database::beginTransaction();
        try {
            Database::query(
                "INSERT INTO productos (empresa_id, categoria_id, nombre, codigo, descripcion, costo, stock_minimo, estado, fecha_creacion) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'activo', NOW())",
                [
                    $empresaId,
                    $data['categoria_id'] ?? null,
                    $data['nombre'],
                    $data['codigo'],
                    $data['descripcion'] ?? '',
                    $data['costo'] ?? 0,
                    $data['stock_minimo'] ?? 0
                ]
            );
            
            $productoId = (int) Database::lastInsertId();

            if (!empty($data['precio_a'])) {
                $this->guardarPrecio($productoId, 'a', (float)$data['precio_a']);
            }

            Database::commit();
            $this->successResponse(['producto_id' => $productoId], 'Producto creado correctamente', 201);

        } catch (\Exception $e) {
            Database::rollback();
            $this->errorResponse('Error al crear el producto: ' . $e->getMessage());
        }
    }

    private function guardarPrecio(int $productoId, string $tipo, float $precio): void
    {
        Database::query(
            "INSERT INTO precios_productos (producto_id, tipo_precio, precio, fecha_inicio) 
             VALUES (?, ?, ?, CURDATE())
             ON DUPLICATE KEY UPDATE precio = ?",
            [$productoId, $tipo, $precio, $precio]
        );
    }

    /**
     * Actualizar un producto existente
     */
    public function actualizar(int $id): void
    {
        $data = $this->getInputData();
        $empresaId = (int) $this->empresaId();

        $producto = Database::query(
            "SELECT producto_id FROM productos WHERE producto_id = ? AND empresa_id = ?",
            [$id, $empresaId]
        )->fetch();

        if (!$producto) {
            $this->notFound('Producto no encontrado');
            return;
        }

        // ... lógica similar simplificada para el ejemplo
        $success = Database::query(
            "UPDATE productos SET 
                categoria_id = ?, 
                nombre = ?, 
                codigo = ?, 
                descripcion = ?, 
                costo = ?, 
                stock_minimo = ?
             WHERE producto_id = ? AND empresa_id = ?",
            [
                $data['categoria_id'] ?? null,
                $data['nombre'],
                $data['codigo'],
                $data['descripcion'] ?? '',
                $data['costo'] ?? 0,
                $data['stock_minimo'] ?? 0,
                $id,
                $empresaId
            ]
        );

        if ($success) {
            if (!empty($data['precio_a'])) {
                $this->guardarPrecio($id, 'a', (float)$data['precio_a']);
            }
            $this->successResponse([], 'Producto actualizado correctamente');
        } else {
            $this->errorResponse('Error al actualizar el producto');
        }
    }

    /**
     * Eliminar (desactivar) un producto
     */
    public function eliminar(int $id): void
    {
        $empresaId = (int) $this->empresaId();

        $success = Database::query(
            "UPDATE productos SET estado = 'inactivo' WHERE producto_id = ? AND empresa_id = ?",
            [$id, $empresaId]
        );

        if ($success) {
            $this->successResponse([], 'Producto eliminado correctamente');
        } else {
            $this->errorResponse('Error al eliminar el producto');
        }
    }
}

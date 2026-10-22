<?php
declare(strict_types=1);

namespace App\Application\Inventory;

use App\Core\Database;

/**
 * ProductListQuery - Servicio de lectura (lado Query de CQRS).
 * Contiene SQL de solo-lectura optimizado para la UI; no modifica estado.
 * Los resultados son arrays crudos (read model), no entidades de dominio.
 */
final class ProductListQuery
{
    /**
     * Lista paginada de productos con stock, precios y datos relacionados.
     *
     * @return array{items: array, pagination: array}
     */
    public function paginate(
        int     $companyId,
        int     $depositId,
        int     $page,
        int     $perPage,
        string  $search     = '',
        ?int    $categoryId  = null,
        ?string $status      = null
    ): array {
        [$where, $params] = $this->buildWhere($companyId, $depositId, $search, $categoryId, $status);

        $total  = (int) Database::query(
            "SELECT COUNT(*) as t FROM productos p WHERE {$where}",
            $params
        )->fetch()['t'];

        $offset   = ($page - 1) * $perPage;
        $listSql  = "SELECT p.*,
                            c.nombre  AS categoria_nombre,
                            pr.nombre AS proveedor_nombre,
                            COALESCE(i.existencia, 0)    AS stock_total,
                            COALESCE(i.costo_promedio, 0) AS costo_promedio,
                            COALESCE(pa.precio, 0) AS precio_a,
                            COALESCE(pb.precio, 0) AS precio_b,
                            COALESCE(pc.precio, 0) AS precio_c
                     FROM productos p
                     LEFT JOIN categorias_productos c  ON p.categoria_id  = c.categoria_id
                     LEFT JOIN proveedores pr          ON p.proveedor_id  = pr.proveedor_id
                     LEFT JOIN inventario i            ON i.producto_id   = p.producto_id AND i.deposito_id = ?
                     LEFT JOIN precios_productos pa    ON pa.producto_id  = p.producto_id AND pa.tipo_precio = 'a'
                     LEFT JOIN precios_productos pb    ON pb.producto_id  = p.producto_id AND pb.tipo_precio = 'b'
                     LEFT JOIN precios_productos pc    ON pc.producto_id  = p.producto_id AND pc.tipo_precio = 'c'
                     WHERE {$where}
                     ORDER BY p.fecha_creacion DESC
                     LIMIT {$perPage} OFFSET {$offset}";

        $items = Database::query($listSql, array_merge([$depositId], $params))->fetchAll();

        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'productos'  => $items,
            'pagination' => [
                'total'         => $total,
                'current_page'  => $page,
                'total_pages'   => $totalPages,
                'has_previous'  => $page > 1,
                'has_next'      => $page < $totalPages,
                'previous_page' => max(1, $page - 1),
                'next_page'     => min($totalPages, $page + 1),
            ],
        ];
    }

    /**
     * Estadísticas de stock para el depósito activo.
     *
     * @return array{totalProductos: int, totalCategorias: int, productosStockBajo: int, productosSinStock: int}
     */
    public function stats(int $companyId, int $depositId): array
    {
        $totalProductos = (int) Database::query(
            "SELECT COUNT(DISTINCT p.producto_id) as t
             FROM productos p
             JOIN inventario i ON i.producto_id = p.producto_id AND i.deposito_id = ?
             WHERE p.empresa_id = ?",
            [$depositId, $companyId]
        )->fetch()['t'];

        $totalCategorias = (int) Database::query(
            "SELECT COUNT(*) as t FROM categorias_productos WHERE empresa_id = ?",
            [$companyId]
        )->fetch()['t'];

        $stockBajo = (int) Database::query(
            "SELECT COUNT(*) as t
             FROM productos p
             JOIN inventario i ON i.producto_id = p.producto_id AND i.deposito_id = ?
             WHERE p.empresa_id = ? AND i.existencia > 0 AND i.existencia <= p.stock_minimo",
            [$depositId, $companyId]
        )->fetch()['t'];

        $sinStock = (int) Database::query(
            "SELECT COUNT(*) as t
             FROM productos p
             JOIN inventario i ON i.producto_id = p.producto_id AND i.deposito_id = ?
             WHERE p.empresa_id = ? AND i.existencia <= 0",
            [$depositId, $companyId]
        )->fetch()['t'];

        return [
            'totalProductos'      => $totalProductos,
            'totalCategorias'     => $totalCategorias,
            'productosStockBajo'  => $stockBajo,
            'productosSinStock'   => $sinStock,
        ];
    }

    /**
     * Categorías activas de la empresa (para filtro desplegable).
     */
    public function categories(int $companyId): array
    {
        return Database::query(
            "SELECT categoria_id, nombre FROM categorias_productos
             WHERE empresa_id = ? ORDER BY nombre ASC",
            [$companyId]
        )->fetchAll();
    }

    /**
     * Datos para el formulario de creación/edición.
     *
     * @return array{categorias: array, proveedores: array, depositos: array, marcas: array}
     */
    public function formData(int $companyId, int $branchId): array
    {
        $categorias = Database::query(
            "SELECT categoria_id, nombre FROM categorias_productos
             WHERE empresa_id = ? ORDER BY nombre ASC",
            [$companyId]
        )->fetchAll();

        $proveedores = Database::query(
            "SELECT proveedor_id, nombre FROM proveedores
             WHERE empresa_id = ? AND estado = 'activo' ORDER BY nombre ASC",
            [$companyId]
        )->fetchAll();

        $depositos = Database::query(
            "SELECT deposito_id, nombre FROM depositos
             WHERE sucursal_id = ? AND estado = 'activo' ORDER BY es_principal DESC, nombre ASC",
            [$branchId]
        )->fetchAll();

        $marcas = Database::query(
            "SELECT DISTINCT nombre FROM marcas WHERE empresa_id = ? ORDER BY nombre ASC",
            [$companyId]
        )->fetchAll();

        return compact('categorias', 'proveedores', 'depositos', 'marcas');
    }

    /**
     * Depósito por defecto de una sucursal (el principal activo).
     */
    public function defaultDepositId(int $branchId): int
    {
        $row = Database::query(
            "SELECT deposito_id FROM depositos
             WHERE sucursal_id = ? AND estado = 'activo'
             ORDER BY es_principal DESC LIMIT 1",
            [$branchId]
        )->fetch();

        return $row ? (int) $row['deposito_id'] : 0;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers internos
    // ──────────────────────────────────────────────────────────────────────────

    private function buildWhere(
        int $companyId, int $depositId, string $search, ?int $categoryId, ?string $status
    ): array {
        $where  = 'p.empresa_id = ?';
        $params = [$companyId];

        // Filtro estricto: el producto debe tener registro de inventario en el depósito
        $where   .= " AND EXISTS (SELECT 1 FROM inventario i2 WHERE i2.producto_id = p.producto_id AND i2.deposito_id = ?)";
        $params[] = $depositId;

        if ($search !== '') {
            $where   .= " AND (p.nombre LIKE ? OR p.codigo LIKE ? OR p.codigo_barras LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($categoryId !== null) {
            $where   .= " AND p.categoria_id = ?";
            $params[] = $categoryId;
        }

        if ($status !== null && $status !== '') {
            $where   .= " AND p.estado = ?";
            $params[] = $status;
        }

        return [$where, $params];
    }
}

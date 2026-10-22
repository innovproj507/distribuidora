<?php
declare(strict_types=1);

namespace App\Inventory\Domain\Repository;

use App\Inventory\Domain\Model\Product;
use App\Inventory\Domain\Model\ProductId;

/**
 * ProductRepositoryPort - Puerto de salida para persistencia de productos.
 * save() retorna el ProductId generado (insert) o el existente (update).
 */
interface ProductRepositoryPort
{
    public function save(Product $product): ProductId;

    public function findById(ProductId $id): ?Product;

    public function findBySku(string $sku): ?Product;

    /** @return Product[] */
    public function findAll(): array;

    public function delete(ProductId $id): void;
}

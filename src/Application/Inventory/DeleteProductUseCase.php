<?php
declare(strict_types=1);

namespace App\Application\Inventory;

use App\Inventory\Domain\Model\ProductId;
use App\Inventory\Domain\Repository\ProductRepositoryPort;

/**
 * DeleteProductUseCase - Verifica autorización y elimina el producto.
 */
final class DeleteProductUseCase
{
    public function __construct(
        private readonly ProductRepositoryPort $productRepository
    ) {}

    public function execute(int $productId, int $companyId): void
    {
        $id = new ProductId($productId);

        $product = $this->productRepository->findById($id);
        if ($product === null) {
            throw new \DomainException("Producto no encontrado: {$productId}");
        }
        if ($product->companyId() !== $companyId) {
            throw new \DomainException("No autorizado para eliminar este producto.");
        }

        $this->productRepository->delete($id);
    }
}

<?php
declare(strict_types=1);

namespace App\Application\Inventory;

use App\Inventory\Domain\Model\Product;
use App\Inventory\Domain\Model\ProductId;
use App\Inventory\Domain\Repository\PriceRepositoryPort;
use App\Inventory\Domain\Repository\ProductRepositoryPort;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\Quantity;

/**
 * UpdateProductUseCase - Actualiza datos del producto y sus precios.
 * Usa el patrón de reconstitución: crea un nuevo objeto Product con el mismo
 * ID pero valores actualizados, en lugar de mutar el existente.
 */
final class UpdateProductUseCase
{
    public function __construct(
        private readonly ProductRepositoryPort $productRepository,
        private readonly PriceRepositoryPort   $priceRepository
    ) {}

    public function execute(UpdateProductCommand $command): void
    {
        // 1. Verificar que el producto existe y pertenece a la empresa
        $existing = $this->productRepository->findById(new ProductId($command->productId));
        if ($existing === null) {
            throw new \DomainException("Producto no encontrado: {$command->productId}");
        }
        if ($existing->companyId() !== $command->companyId) {
            throw new \DomainException("No autorizado para modificar este producto.");
        }

        // 2. Reconstruir la entidad con los nuevos valores
        //    imagePath: si no se subió imagen nueva, conservamos la existente
        $updatedProduct = new Product(
            $existing->id(),
            $command->sku,
            $command->name,
            $command->companyId,
            $command->categoryId,
            Money::fromFloat($command->cost),
            $command->taxRate,
            $command->status,
            $command->managesBatches,
            new Quantity($command->minStock),
            $command->description,
            $command->barcode,
            $command->brand,
            $command->supplierId,
            $command->supplierPartNo,
            $command->newImagePath ?? $existing->imagePath()
        );

        // 3. Persistir
        $this->productRepository->save($updatedProduct);

        // 4. Actualizar precios (replace por tipo)
        $today = date('Y-m-d');
        foreach ($command->prices as $priceData) {
            $this->priceRepository->replacePrice(
                $existing->id(),
                $priceData['type'],
                Money::fromFloat((float) ($priceData['amount'] ?? 0)),
                $today
            );
        }
    }
}

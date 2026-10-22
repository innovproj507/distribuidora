<?php
declare(strict_types=1);

namespace App\Application\Inventory;

use App\Inventory\Domain\Model\InventoryRecord;
use App\Inventory\Domain\Model\Product;
use App\Inventory\Domain\Model\ProductId;
use App\Inventory\Domain\Repository\InventoryRepositoryPort;
use App\Inventory\Domain\Repository\PriceRepositoryPort;
use App\Inventory\Domain\Repository\ProductRepositoryPort;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\Quantity;

/**
 * RegisterProductUseCase - Orquesta la creación completa de un producto:
 * persiste el producto, sus precios y su stock inicial por depósito.
 * No conoce PDO, sesiones ni HTTP: solo puertos de dominio.
 */
final class RegisterProductUseCase
{
    public function __construct(
        private readonly ProductRepositoryPort   $productRepository,
        private readonly InventoryRepositoryPort $inventoryRepository,
        private readonly PriceRepositoryPort     $priceRepository
    ) {}

    public function execute(RegisterProductCommand $command): ProductId
    {
        // 1. Validar unicidad del SKU dentro de la misma empresa
        $existing = $this->productRepository->findBySku($command->sku);
        if ($existing !== null && $existing->companyId() === $command->companyId) {
            throw new \DomainException("Ya existe un producto con el código: {$command->sku}");
        }

        // 2. Construir la entidad de dominio (sin ID = nuevo)
        $product = new Product(
            new ProductId(),
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
            $command->imagePath
        );

        // 3. Persistir y obtener el ID generado por la BD
        $productId = $this->productRepository->save($product);

        // 4. Registrar precios por tipo
        $today = date('Y-m-d');
        foreach ($command->prices as $priceData) {
            if (($priceData['amount'] ?? 0) > 0) {
                $this->priceRepository->replacePrice(
                    $productId,
                    $priceData['type'],
                    Money::fromFloat((float) $priceData['amount']),
                    $today
                );
            }
        }

        // 5. Crear registros de stock por depósito
        $minimum = new Quantity($command->minStock);
        $cost    = Money::fromFloat($command->cost);
        foreach ($command->depositStocks as $stockData) {
            $record = InventoryRecord::create(
                $productId,
                (int) $stockData['deposit_id'],
                new Quantity((float) ($stockData['quantity'] ?? 0)),
                $cost,
                $minimum
            );
            $this->inventoryRepository->save($record);
        }

        return $productId;
    }
}

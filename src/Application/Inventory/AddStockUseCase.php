<?php
declare(strict_types=1);

namespace App\Application\Inventory;

use App\Inventory\Domain\Model\InventoryRecord;
use App\Inventory\Domain\Model\ProductId;
use App\Inventory\Domain\Repository\InventoryRepositoryPort;
use App\Inventory\Domain\Repository\ProductRepositoryPort;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\Quantity;

/**
 * AddStockUseCase - Agrega stock a un producto en un depósito.
 * Regla: si ya existe el registro de inventario, recalcula costo promedio
 * ponderado. Si no existe, crea el registro con el costo dado.
 */
final class AddStockUseCase
{
    public function __construct(
        private readonly ProductRepositoryPort   $productRepository,
        private readonly InventoryRepositoryPort $inventoryRepository
    ) {}

    public function execute(int $productId, int $depositId, float $quantity, float $cost): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException("La cantidad debe ser mayor a 0.");
        }

        $productIdVO = new ProductId($productId);
        $incoming    = new Quantity($quantity);
        $incomingCost = Money::fromFloat($cost);

        $record = $this->inventoryRepository->findByProductAndDeposit($productIdVO, $depositId);

        if ($record !== null) {
            $record->addStock($incoming, $incomingCost);
            $this->inventoryRepository->save($record);
            return;
        }

        // Primer ingreso: crear registro con el stock mínimo del producto
        $product = $this->productRepository->findById($productIdVO);
        $minimum = $product !== null ? $product->minimumStock() : new Quantity(0);

        $newRecord = InventoryRecord::create($productIdVO, $depositId, $incoming, $incomingCost, $minimum);
        $this->inventoryRepository->save($newRecord);
    }
}

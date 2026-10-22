<?php
declare(strict_types=1);

namespace App\Inventory\Domain\Repository;

use App\Inventory\Domain\Model\InventoryRecord;
use App\Inventory\Domain\Model\ProductId;

/**
 * InventoryRepositoryPort - Puerto de salida para stock por depósito.
 */
interface InventoryRepositoryPort
{
    public function findByProductAndDeposit(ProductId $productId, int $depositId): ?InventoryRecord;

    public function save(InventoryRecord $record): void;

    /** @return InventoryRecord[] */
    public function findByProduct(ProductId $productId): array;
}

<?php
declare(strict_types=1);

namespace App\Inventory\Domain\Repository;

use App\Inventory\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\Money;

/**
 * PriceRepositoryPort - Puerto de salida para precios por tipo (a, b, c, promocional).
 */
interface PriceRepositoryPort
{
    /**
     * Reemplaza el precio de un tipo específico: elimina el existente e inserta el nuevo
     * si amount > 0. Idempotente: seguro llamar varias veces.
     */
    public function replacePrice(ProductId $productId, string $type, Money $price, string $startDate): void;

    /**
     * @return array<array{type: string, price: float}>
     */
    public function findByProduct(ProductId $productId): array;

    public function deleteByProduct(ProductId $productId): void;
}

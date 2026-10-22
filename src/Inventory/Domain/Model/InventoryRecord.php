<?php
declare(strict_types=1);

namespace App\Inventory\Domain\Model;

use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\Quantity;

/**
 * InventoryRecord - Entidad que representa el stock de un producto en un depósito.
 * Regla de negocio: addStock() recalcula el costo promedio ponderado.
 */
final class InventoryRecord
{
    private ?int $id;
    private ProductId $productId;
    private int $depositId;
    private Quantity $stock;
    private Quantity $minimum;
    private Money $averageCost;
    private Money $lastCost;

    public function __construct(
        ?int $id,
        ProductId $productId,
        int $depositId,
        Quantity $stock,
        Quantity $minimum,
        Money $averageCost,
        Money $lastCost
    ) {
        $this->id = $id;
        $this->productId = $productId;
        $this->depositId = $depositId;
        $this->stock = $stock;
        $this->minimum = $minimum;
        $this->averageCost = $averageCost;
        $this->lastCost = $lastCost;
    }

    public static function create(
        ProductId $productId,
        int $depositId,
        Quantity $initialStock,
        Money $cost,
        Quantity $minimum
    ): self {
        return new self(null, $productId, $depositId, $initialStock, $minimum, $cost, $cost);
    }

    public function addStock(Quantity $quantity, Money $newCost): void
    {
        $currentValue = $this->averageCost->multiply($this->stock->value());
        $incomingValue = $newCost->multiply($quantity->value());
        $newTotal = $this->stock->add($quantity);

        $newAverage = $newTotal->isZero()
            ? $newCost
            : Money::fromFloat(
                ($currentValue->amount() + $incomingValue->amount()) / $newTotal->value()
            );

        $this->stock = $newTotal;
        $this->averageCost = $newAverage;
        $this->lastCost = $newCost;
    }

    public function isBelowMinimum(): bool
    {
        return $this->stock->value() > 0 && $this->stock->value() <= $this->minimum->value();
    }

    public function isOutOfStock(): bool
    {
        return $this->stock->value() <= 0;
    }

    public function id(): ?int { return $this->id; }
    public function productId(): ProductId { return $this->productId; }
    public function depositId(): int { return $this->depositId; }
    public function stock(): Quantity { return $this->stock; }
    public function minimum(): Quantity { return $this->minimum; }
    public function averageCost(): Money { return $this->averageCost; }
    public function lastCost(): Money { return $this->lastCost; }
}

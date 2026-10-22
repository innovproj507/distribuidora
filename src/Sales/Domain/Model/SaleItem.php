<?php
declare(strict_types=1);

namespace App\Sales\Domain\Model;

use App\Inventory\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\Quantity;

/**
 * SaleItem - Entidad que representa una línea de la venta
 */
final class SaleItem
{
    private ProductId $productId;
    private string $productName;
    private Quantity $quantity;
    private Money $unitPrice;
    private float $taxRate;
    private Money $subtotal;
    private Money $taxAmount;
    private Money $total;

    public function __construct(
        ProductId $productId,
        string $productName,
        Quantity $quantity,
        Money $unitPrice,
        float $taxRate
    ) {
        $this->productId = $productId;
        $this->productName = $productName;
        $this->quantity = $quantity;
        $this->unitPrice = $unitPrice;
        $this->taxRate = $taxRate;
        $this->calculateTotals();
    }

    private function calculateTotals(): void
    {
        $this->subtotal = $this->unitPrice->multiply($this->quantity->value());
        $this->taxAmount = $this->subtotal->multiply($this->taxRate);
        $this->total = $this->subtotal->add($this->taxAmount);
    }

    public function productId(): ProductId { return $this->productId; }
    public function productName(): string { return $this->productName; }
    public function quantity(): Quantity { return $this->quantity; }
    public function unitPrice(): Money { return $this->unitPrice; }
    public function taxRate(): float { return $this->taxRate; }
    public function subtotal(): Money { return $this->subtotal; }
    public function taxAmount(): Money { return $this->taxAmount; }
    public function total(): Money { return $this->total; }
}

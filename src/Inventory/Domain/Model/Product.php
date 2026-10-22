<?php
declare(strict_types=1);

namespace App\Inventory\Domain\Model;

use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\Quantity;

/**
 * Product - Aggregate Root del contexto de Inventario
 */
final class Product
{
    private ProductId $id;
    private string $sku;
    private string $name;
    private int $companyId;
    private ?int $categoryId;
    private Money $cost;
    private float $taxRate;
    private string $status;
    private bool $managesBatches;
    private Quantity $minimumStock;
    private ?string $description;
    private ?string $barcode;
    private ?string $brand;
    private ?int $supplierId;
    private ?string $supplierPartNo;
    private ?string $imagePath;

    public function __construct(
        ProductId $id,
        string $sku,
        string $name,
        int $companyId,
        ?int $categoryId,
        Money $cost,
        float $taxRate = 0.07,
        string $status = 'activo',
        bool $managesBatches = false,
        ?Quantity $minimumStock = null,
        ?string $description = null,
        ?string $barcode = null,
        ?string $brand = null,
        ?int $supplierId = null,
        ?string $supplierPartNo = null,
        ?string $imagePath = null
    ) {
        $this->id = $id;
        $this->sku = $sku;
        $this->name = $name;
        $this->companyId = $companyId;
        $this->categoryId = $categoryId;
        $this->cost = $cost;
        $this->taxRate = $taxRate;
        $this->status = $status;
        $this->managesBatches = $managesBatches;
        $this->minimumStock = $minimumStock ?? new Quantity(0);
        $this->description = $description;
        $this->barcode = $barcode;
        $this->brand = $brand;
        $this->supplierId = $supplierId;
        $this->supplierPartNo = $supplierPartNo;
        $this->imagePath = $imagePath;
    }

    public function calculatePriceWithTax(Money $basePrice): Money
    {
        return $basePrice->multiply(1 + $this->taxRate);
    }

    public function changeName(string $newName): void
    {
        if (empty(trim($newName))) {
            throw new \InvalidArgumentException("Product name cannot be empty");
        }
        $this->name = $newName;
    }

    public function updateCost(Money $newCost): void
    {
        $this->cost = $newCost;
    }

    public function isActivo(): bool
    {
        return $this->status === 'activo';
    }

    public function id(): ProductId { return $this->id; }
    public function sku(): string { return $this->sku; }
    public function name(): string { return $this->name; }
    public function companyId(): int { return $this->companyId; }
    public function categoryId(): ?int { return $this->categoryId; }
    public function cost(): Money { return $this->cost; }
    public function taxRate(): float { return $this->taxRate; }
    public function status(): string { return $this->status; }
    public function managesBatches(): bool { return $this->managesBatches; }
    public function minimumStock(): Quantity { return $this->minimumStock; }
    public function description(): ?string { return $this->description; }
    public function barcode(): ?string { return $this->barcode; }
    public function brand(): ?string { return $this->brand; }
    public function supplierId(): ?int { return $this->supplierId; }
    public function supplierPartNo(): ?string { return $this->supplierPartNo; }
    public function imagePath(): ?string { return $this->imagePath; }
}

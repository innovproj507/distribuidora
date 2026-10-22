<?php
declare(strict_types=1);

namespace App\Inventory\Domain\Model;

/**
 * ProductId - Value Object para la identidad del producto
 */
final class ProductId
{
    private ?int $id;

    public function __construct(?int $id = null)
    {
        $this->id = $id;
    }

    public function value(): ?int
    {
        return $this->id;
    }

    public function equals(ProductId $other): bool
    {
        return $this->id === $other->value();
    }

    public function __toString(): string
    {
        return (string) ($this->id ?? '');
    }
}

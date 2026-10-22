<?php
declare(strict_types=1);

namespace App\Sales\Domain\Model;

/**
 * SaleId - Value Object para la identidad de la venta
 */
final class SaleId
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

    public function equals(SaleId $other): bool
    {
        return $this->id === $other->value();
    }

    public function __toString(): string
    {
        return (string) ($this->id ?? '');
    }
}

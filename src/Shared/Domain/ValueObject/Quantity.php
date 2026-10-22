<?php
declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Quantity - Value Object para cantidades de stock
 */
final class Quantity
{
    private float $value;

    public function __construct(float $value)
    {
        if ($value < 0) {
            throw new InvalidArgumentException("Quantity cannot be negative: {$value}");
        }
        $this->value = $value;
    }

    public function value(): float
    {
        return $this->value;
    }

    public function add(Quantity $other): self
    {
        return new self($this->value + $other->value());
    }

    public function subtract(Quantity $other): self
    {
        return new self($this->value - $other->value());
    }

    public function isGreaterThan(Quantity $other): bool
    {
        return $this->value > $other->value();
    }

    public function isZero(): bool
    {
        return $this->value === 0.0;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}

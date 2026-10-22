<?php
declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Money - Value Object inmutable para manejo de dinero
 */
final class Money
{
    private float $amount;
    private string $currency;

    public function __construct(float $amount, string $currency = 'B/.')
    {
        $this->amount = $amount;
        $this->currency = $currency;
    }

    public static function fromFloat(float $amount, string $currency = 'B/.'): self
    {
        return new self($amount, $currency);
    }

    public function amount(): float
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount + $other->amount(), $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount - $other->amount(), $this->currency);
    }

    public function multiply(float $multiplier): self
    {
        return new self($this->amount * $multiplier, $this->currency);
    }

    public function equals(Money $other): bool
    {
        return $this->amount === $other->amount() && $this->currency === $other->currency();
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency()) {
            throw new InvalidArgumentException("Currencies must match: {$this->currency} vs {$other->currency()}");
        }
    }

    public function format(): string
    {
        return $this->currency . ' ' . number_format($this->amount, 2);
    }

    public function __toString(): string
    {
        return (string) $this->amount;
    }
}

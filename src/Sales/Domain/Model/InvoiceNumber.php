<?php
declare(strict_types=1);

namespace App\Sales\Domain\Model;

/**
 * InvoiceNumber - Value Object para el número de factura
 */
final class InvoiceNumber
{
    private string $number;

    public function __construct(string $number)
    {
        if (empty($number)) {
            throw new \InvalidArgumentException("Invoice number cannot be empty");
        }
        $this->number = $number;
    }

    public function value(): string
    {
        return $this->number;
    }

    public function __toString(): string
    {
        return $this->number;
    }
}

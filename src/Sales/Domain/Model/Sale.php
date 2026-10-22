<?php
declare(strict_types=1);

namespace App\Sales\Domain\Model;

use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;

/**
 * Sale - Aggregate Root del contexto de Ventas
 */
final class Sale
{
    private SaleId $id;
    private InvoiceNumber $invoiceNumber;
    private int $customerId;
    private int $branchId; // sucursal_id
    private int $sellerId; // vendedor_id
    private DateTimeImmutable $date;
    private string $status; // 'completado', 'anulado'
    
    /** @var SaleItem[] */
    private array $items = [];
    
    private Money $subtotal;
    private Money $taxAmount; // ITBMS
    private Money $discount;
    private Money $total;

    public function __construct(
        SaleId $id,
        InvoiceNumber $invoiceNumber,
        int $customerId,
        int $branchId,
        int $sellerId,
        Money $discount = null,
        string $status = 'completado',
        ?DateTimeImmutable $date = null
    ) {
        $this->id = $id;
        $this->invoiceNumber = $invoiceNumber;
        $this->customerId = $customerId;
        $this->branchId = $branchId;
        $this->sellerId = $sellerId;
        $this->discount = $discount ?? Money::fromFloat(0);
        $this->status = $status;
        $this->date = $date ?? new DateTimeImmutable();
        $this->subtotal = Money::fromFloat(0);
        $this->taxAmount = Money::fromFloat(0);
        $this->total = Money::fromFloat(0);
    }

    public function addItem(SaleItem $item): void
    {
        if ($this->status !== 'completado') {
            throw new \DomainException("Cannot add items to a sale with status: {$this->status}");
        }
        $this->items[] = $item;
        $this->recalculateTotals();
    }

    private function recalculateTotals(): void
    {
        $subtotal = Money::fromFloat(0);
        $taxAmount = Money::fromFloat(0);

        foreach ($this->items as $item) {
            $subtotal = $subtotal->add($item->subtotal());
            $taxAmount = $taxAmount->add($item->taxAmount());
        }

        $this->subtotal = $subtotal;
        $this->taxAmount = $taxAmount;
        $this->total = $subtotal->add($taxAmount)->subtract($this->discount);
    }

    public function id(): SaleId { return $this->id; }
    public function invoiceNumber(): InvoiceNumber { return $this->invoiceNumber; }
    public function customerId(): int { return $this->customerId; }
    public function branchId(): int { return $this->branchId; }
    public function sellerId(): int { return $this->sellerId; }
    public function date(): DateTimeImmutable { return $this->date; }
    public function status(): string { return $this->status; }
    public function items(): array { return $this->items; }
    public function subtotal(): Money { return $this->subtotal; }
    public function taxAmount(): Money { return $this->taxAmount; }
    public function discount(): Money { return $this->discount; }
    public function total(): Money { return $this->total; }

    public function void(): void
    {
        if ($this->status === 'anulado') {
            throw new \DomainException("Sale is already voided");
        }
        $this->status = 'anulado';
    }
}

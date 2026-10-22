<?php
declare(strict_types=1);

namespace App\Sales\Domain\Repository;

use App\Sales\Domain\Model\Sale;
use App\Sales\Domain\Model\SaleId;
use App\Sales\Domain\Model\InvoiceNumber;
use DateTimeImmutable;

/**
 * SaleRepositoryPort - Interfaz para persistencia de ventas
 */
interface SaleRepositoryPort
{
    public function save(Sale $sale): void;
    
    public function findById(SaleId $id): ?Sale;
    
    public function findByInvoiceNumber(InvoiceNumber $number): ?Sale;
    
    /**
     * @return Sale[]
     */
    public function findByDateRange(DateTimeImmutable $from, DateTimeImmutable $to): array;
    
    public function nextInvoiceNumber(): InvoiceNumber;
}

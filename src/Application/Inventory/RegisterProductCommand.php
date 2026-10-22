<?php
declare(strict_types=1);

namespace App\Application\Inventory;

/**
 * RegisterProductCommand - DTO inmutable que transporta los datos de entrada
 * del caso de uso de registro de producto. Sin lógica, solo datos.
 */
final class RegisterProductCommand
{
    /**
     * @param array<array{type: string, amount: float}> $prices        Ej: [['type'=>'a','amount'=>25.00]]
     * @param array<array{deposit_id: int, quantity: float}> $depositStocks  Ej: [['deposit_id'=>1,'quantity'=>50]]
     */
    public function __construct(
        public readonly int     $companyId,
        public readonly string  $sku,
        public readonly string  $name,
        public readonly ?int    $categoryId,
        public readonly float   $cost,
        public readonly float   $taxRate       = 0.07,
        public readonly float   $minStock      = 0,
        public readonly bool    $managesBatches = false,
        public readonly string  $status        = 'activo',
        public readonly ?string $description   = null,
        public readonly ?string $barcode       = null,
        public readonly ?string $brand         = null,
        public readonly ?int    $supplierId    = null,
        public readonly ?string $supplierPartNo = null,
        public readonly ?string $imagePath     = null,
        public readonly array   $prices        = [],
        public readonly array   $depositStocks = [],
    ) {}
}

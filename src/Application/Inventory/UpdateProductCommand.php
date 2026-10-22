<?php
declare(strict_types=1);

namespace App\Application\Inventory;

/**
 * UpdateProductCommand - DTO inmutable para actualizar un producto existente.
 * imagePath = null significa "no se subió imagen nueva, conservar la actual".
 *
 * @param array<array{type: string, amount: float}> $prices
 */
final class UpdateProductCommand
{
    public function __construct(
        public readonly int     $productId,
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
        public readonly ?string $newImagePath  = null,
        public readonly array   $prices        = [],
    ) {}
}

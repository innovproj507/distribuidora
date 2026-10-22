<?php
declare(strict_types=1);

namespace App\Application\Sales;

use App\Sales\Domain\Model\Sale;
use App\Sales\Domain\Model\SaleId;
use App\Sales\Domain\Model\SaleItem;
use App\Sales\Domain\Repository\SaleRepositoryPort;
use App\Inventory\Domain\Repository\ProductRepositoryPort;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\Quantity;
use Exception;

/**
 * ProcessSaleUseCase - Orquesta la creación de una venta y actualización de stock
 */
final class ProcessSaleUseCase
{
    private SaleRepositoryPort $saleRepository;
    private ProductRepositoryPort $productRepository;

    public function __construct(
        SaleRepositoryPort $saleRepository,
        ProductRepositoryPort $productRepository
    ) {
        $this->saleRepository = $saleRepository;
        $this->productRepository = $productRepository;
    }

    /**
     * @param array $itemsData Array de ['product_sku' => string, 'quantity' => float, 'price' => float]
     */
    public function execute(
        int $customerId,
        int $branchId,
        int $sellerId,
        array $itemsData,
        float $discountAmount = 0
    ): SaleId {
        // 1. Obtener próximo número de factura
        $invoiceNumber = $this->saleRepository->nextInvoiceNumber();

        // 2. Crear el Agregado Sale
        $sale = new Sale(
            new SaleId(),
            $invoiceNumber,
            $customerId,
            $branchId,
            $sellerId,
            Money::fromFloat($discountAmount)
        );

        // 3. Procesar items y validar stock
        foreach ($itemsData as $itemData) {
            $product = $this->productRepository->findBySku($itemData['product_sku']);
            if (!$product) {
                throw new Exception("Product with SKU {$itemData['product_sku']} not found.");
            }

            // Aquí podríamos validar stock disponible antes de proceder
            // Para este ejemplo, simplemente añadimos el item
            $item = new SaleItem(
                $product->id(),
                $product->name(),
                new Quantity($itemData['quantity']),
                Money::fromFloat($itemData['price']),
                $product->taxRate()
            );

            $sale->addItem($item);
            
            // 4. Actualizar Stock (Aquí llamaríamos a otro caso de uso de Inventario o usaríamos un Domain Event)
            // Por ahora, solo registramos la intención en este flujo lineal
        }

        // 5. Persistir venta
        $this->saleRepository->save($sale);

        return $this->saleRepository->findByInvoiceNumber($invoiceNumber)->id();
    }
}

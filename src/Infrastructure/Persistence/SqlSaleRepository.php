<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Database;
use App\Sales\Domain\Model\Sale;
use App\Sales\Domain\Model\SaleId;
use App\Sales\Domain\Model\SaleItem;
use App\Sales\Domain\Model\InvoiceNumber;
use App\Sales\Domain\Repository\SaleRepositoryPort;
use App\Shared\Domain\ValueObject\Money;
use App\Inventory\Domain\Model\ProductId;
use App\Services\NumeracionService;
use DateTimeImmutable;
use Exception;

/**
 * SqlSaleRepository - Implementación SQL para ventas
 */
final class SqlSaleRepository implements SaleRepositoryPort
{
    public function save(Sale $sale): void
    {
        Database::beginTransaction();

        try {
            $data = [
                'numero_factura' => $sale->invoiceNumber()->value(),
                'cliente_id' => $sale->customerId(),
                'sucursal_id' => $sale->branchId(),
                'vendedor_id' => $sale->sellerId(),
                'subtotal' => $sale->subtotal()->amount(),
                'itbms' => $sale->taxAmount()->amount(),
                'descuento' => $sale->discount()->amount(),
                'total' => $sale->total()->amount(),
                'estado' => $sale->status(),
                'fecha' => $sale->date()->format('Y-m-d H:i:s')
            ];

            if ($sale->id()->value() === null) {
                // Insert Sale
                $sql = "INSERT INTO ventas (numero_factura, cliente_id, sucursal_id, vendedor_id, subtotal, itbms, descuento, total, estado, fecha) 
                        VALUES (:numero_factura, :cliente_id, :sucursal_id, :vendedor_id, :subtotal, :itbms, :descuento, :total, :estado, :fecha)";
                Database::query($sql, $data);
                $saleId = (int) Database::lastInsertId();
                
                // Insert Items
                foreach ($sale->items() as $item) {
                    $itemData = [
                        'venta_id' => $saleId,
                        'producto_id' => $item->productId()->value(),
                        'cantidad' => $item->quantity()->value(),
                        'precio_unitario' => $item->unitPrice()->amount(),
                        'subtotal' => $item->subtotal()->amount(),
                        'itbms' => $item->taxAmount()->amount(),
                        'total_linea' => $item->total()->amount()
                    ];
                    $itemSql = "INSERT INTO ventas_detalle (venta_id, producto_id, cantidad, precio_unitario, subtotal, itbms, total_linea) 
                                VALUES (:venta_id, :producto_id, :cantidad, :precio_unitario, :subtotal, :itbms, :total_linea)";
                    Database::query($itemSql, $itemData);
                }
            } else {
                // Update Sale
                $data['id'] = $sale->id()->value();
                $sql = "UPDATE ventas SET 
                        estado = :estado, subtotal = :subtotal, itbms = :itbms, total = :total 
                        WHERE venta_id = :id";
                Database::query($sql, $data);
                
                // For simplicity, we don't handle item updates here in this example
            }

            Database::commit();
        } catch (Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    public function findById(SaleId $id): ?Sale
    {
        $row = Database::query("SELECT * FROM ventas WHERE venta_id = ?", [$id->value()])->fetch();
        if (!$row) return null;

        $sale = $this->mapToDomain($row);
        $this->loadItems($sale);
        return $sale;
    }

    public function findByInvoiceNumber(InvoiceNumber $number): ?Sale
    {
        $row = Database::query("SELECT * FROM ventas WHERE numero_factura = ?", [$number->value()])->fetch();
        if (!$row) return null;

        $sale = $this->mapToDomain($row);
        $this->loadItems($sale);
        return $sale;
    }

    public function findByDateRange(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $rows = Database::query(
            "SELECT * FROM ventas WHERE fecha BETWEEN ? AND ?", 
            [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')]
        )->fetchAll();
        
        return array_map(function($row) {
            $sale = $this->mapToDomain($row);
            $this->loadItems($sale);
            return $sale;
        }, $rows);
    }

    public function nextInvoiceNumber(): InvoiceNumber
    {
        return new InvoiceNumber(NumeracionService::siguiente('factura'));
    }

    private function mapToDomain(array $row): Sale
    {
        return new Sale(
            new SaleId((int)$row['venta_id']),
            new InvoiceNumber($row['numero_factura']),
            (int)$row['cliente_id'],
            (int)$row['sucursal_id'],
            (int)$row['vendedor_id'],
            Money::fromFloat((float)$row['descuento']),
            $row['estado'],
            new DateTimeImmutable($row['fecha'])
        );
    }

    private function loadItems(Sale $sale): void
    {
        $rows = Database::query(
            "SELECT vd.*, p.nombre as producto_nombre 
             FROM ventas_detalle vd 
             JOIN productos p ON vd.producto_id = p.producto_id 
             WHERE vd.venta_id = ?", 
            [$sale->id()->value()]
        )->fetchAll();

        foreach ($rows as $row) {
            $item = new SaleItem(
                new ProductId((int)$row['producto_id']),
                $row['producto_nombre'],
                new \App\Shared\Domain\ValueObject\Quantity((float)$row['cantidad']),
                Money::fromFloat((float)$row['precio_unitario']),
                (float)($row['subtotal'] > 0 ? $row['itbms'] / $row['subtotal'] : 0.07) // Rough estimate
            );
            $sale->addItem($item);
        }
    }
}

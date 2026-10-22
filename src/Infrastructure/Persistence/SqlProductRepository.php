<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Database;
use App\Inventory\Domain\Model\Product;
use App\Inventory\Domain\Model\ProductId;
use App\Inventory\Domain\Repository\ProductRepositoryPort;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\Quantity;

final class SqlProductRepository implements ProductRepositoryPort
{
    public function save(Product $product): ProductId
    {
        $data = [
            'codigo'           => $product->sku(),
            'nombre'           => $product->name(),
            'descripcion'      => $product->description(),
            'empresa_id'       => $product->companyId(),
            'categoria_id'     => $product->categoryId(),
            'costo'            => $product->cost()->amount(),
            'itbms'            => $product->taxRate(),
            'estado'           => $product->status(),
            'maneja_lotes'     => $product->managesBatches() ? 1 : 0,
            'stock_minimo'     => $product->minimumStock()->value(),
            'codigo_barras'    => $product->barcode(),
            'marca'            => $product->brand(),
            'proveedor_id'     => $product->supplierId(),
            'supplier_part_no' => $product->supplierPartNo(),
            'imagen_principal' => $product->imagePath(),
        ];

        if ($product->id()->value() === null) {
            $data['fecha_creacion']     = date('Y-m-d H:i:s');
            $data['fecha_modificacion'] = date('Y-m-d H:i:s');

            $cols  = implode(', ', array_keys($data));
            $marks = implode(', ', array_fill(0, count($data), '?'));
            Database::query(
                "INSERT INTO productos ({$cols}) VALUES ({$marks})",
                array_values($data)
            );
            return new ProductId((int) Database::lastInsertId());
        }

        $data['fecha_modificacion'] = date('Y-m-d H:i:s');
        $sets = implode(', ', array_map(fn($col) => "{$col} = ?", array_keys($data)));
        Database::query(
            "UPDATE productos SET {$sets} WHERE producto_id = ?",
            [...array_values($data), $product->id()->value()]
        );
        return $product->id();
    }

    public function findById(ProductId $id): ?Product
    {
        $row = Database::query(
            "SELECT * FROM productos WHERE producto_id = ?",
            [$id->value()]
        )->fetch();

        return $row ? $this->mapToDomain($row) : null;
    }

    public function findBySku(string $sku): ?Product
    {
        $row = Database::query(
            "SELECT * FROM productos WHERE codigo = ?",
            [$sku]
        )->fetch();

        return $row ? $this->mapToDomain($row) : null;
    }

    public function findAll(): array
    {
        $rows = Database::query("SELECT * FROM productos")->fetchAll();
        return array_map([$this, 'mapToDomain'], $rows);
    }

    public function delete(ProductId $id): void
    {
        Database::query("DELETE FROM productos WHERE producto_id = ?", [$id->value()]);
    }

    private function mapToDomain(array $row): Product
    {
        return new Product(
            new ProductId((int) $row['producto_id']),
            $row['codigo'],
            $row['nombre'],
            (int) $row['empresa_id'],
            isset($row['categoria_id']) && $row['categoria_id'] !== null ? (int) $row['categoria_id'] : null,
            Money::fromFloat((float) $row['costo']),
            (float) $row['itbms'],
            $row['estado'],
            (bool) $row['maneja_lotes'],
            new Quantity((float) ($row['stock_minimo'] ?? 0)),
            $row['descripcion'] ?? null,
            $row['codigo_barras'] ?? null,
            $row['marca'] ?? null,
            isset($row['proveedor_id']) ? (int) $row['proveedor_id'] : null,
            $row['supplier_part_no'] ?? null,
            $row['imagen_principal'] ?? null
        );
    }
}

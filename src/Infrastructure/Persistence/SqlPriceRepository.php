<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Database;
use App\Inventory\Domain\Model\ProductId;
use App\Inventory\Domain\Repository\PriceRepositoryPort;
use App\Shared\Domain\ValueObject\Money;

final class SqlPriceRepository implements PriceRepositoryPort
{
    public function replacePrice(ProductId $productId, string $type, Money $price, string $startDate): void
    {
        Database::query(
            "DELETE FROM precios_productos WHERE producto_id = ? AND tipo_precio = ?",
            [$productId->value(), $type]
        );

        if ($price->amount() > 0) {
            Database::query(
                "INSERT INTO precios_productos (producto_id, tipo_precio, precio, fecha_inicio) VALUES (?, ?, ?, ?)",
                [$productId->value(), $type, $price->amount(), $startDate]
            );
        }
    }

    public function findByProduct(ProductId $productId): array
    {
        return Database::query(
            "SELECT tipo_precio as type, precio as price FROM precios_productos WHERE producto_id = ?",
            [$productId->value()]
        )->fetchAll();
    }

    public function deleteByProduct(ProductId $productId): void
    {
        Database::query(
            "DELETE FROM precios_productos WHERE producto_id = ?",
            [$productId->value()]
        );
    }
}

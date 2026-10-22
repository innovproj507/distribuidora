<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Database;
use App\Inventory\Domain\Model\InventoryRecord;
use App\Inventory\Domain\Model\ProductId;
use App\Inventory\Domain\Repository\InventoryRepositoryPort;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\Quantity;

final class SqlInventoryRepository implements InventoryRepositoryPort
{
    public function findByProductAndDeposit(ProductId $productId, int $depositId): ?InventoryRecord
    {
        $row = Database::query(
            "SELECT * FROM inventario WHERE producto_id = ? AND deposito_id = ?",
            [$productId->value(), $depositId]
        )->fetch();

        return $row ? $this->mapToDomain($row) : null;
    }

    public function save(InventoryRecord $record): void
    {
        if ($record->id() === null) {
            Database::query(
                "INSERT INTO inventario
                    (producto_id, deposito_id, existencia, minimo, maximo, costo_promedio, ultimo_costo,
                     fecha_actualizacion, fecha_actualizacion_costo)
                 VALUES (?, ?, ?, ?, 0, ?, ?, NOW(), NOW())",
                [
                    $record->productId()->value(),
                    $record->depositId(),
                    $record->stock()->value(),
                    $record->minimum()->value(),
                    $record->averageCost()->amount(),
                    $record->lastCost()->amount(),
                ]
            );
        } else {
            Database::query(
                "UPDATE inventario
                 SET existencia = ?, minimo = ?, costo_promedio = ?, ultimo_costo = ?,
                     fecha_actualizacion = NOW(), fecha_actualizacion_costo = NOW()
                 WHERE inventario_id = ?",
                [
                    $record->stock()->value(),
                    $record->minimum()->value(),
                    $record->averageCost()->amount(),
                    $record->lastCost()->amount(),
                    $record->id(),
                ]
            );
        }
    }

    public function findByProduct(ProductId $productId): array
    {
        $rows = Database::query(
            "SELECT * FROM inventario WHERE producto_id = ?",
            [$productId->value()]
        )->fetchAll();

        return array_map([$this, 'mapToDomain'], $rows);
    }

    private function mapToDomain(array $row): InventoryRecord
    {
        return new InventoryRecord(
            (int) $row['inventario_id'],
            new ProductId((int) $row['producto_id']),
            (int) $row['deposito_id'],
            new Quantity((float) ($row['existencia'] ?? 0)),
            new Quantity((float) ($row['minimo'] ?? 0)),
            Money::fromFloat((float) ($row['costo_promedio'] ?? 0)),
            Money::fromFloat((float) ($row['ultimo_costo'] ?? 0))
        );
    }
}

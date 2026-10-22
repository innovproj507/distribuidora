<?php
declare(strict_types=1);

namespace App\Shared\Infrastructure;

use App\Application\Inventory\AddStockUseCase;
use App\Application\Inventory\DeleteProductUseCase;
use App\Application\Inventory\ProductListQuery;
use App\Application\Inventory\RegisterProductUseCase;
use App\Application\Inventory\UpdateProductUseCase;
use App\Application\Sales\ProcessSaleUseCase;
use App\Infrastructure\Persistence\SqlInventoryRepository;
use App\Infrastructure\Persistence\SqlPriceRepository;
use App\Infrastructure\Persistence\SqlProductRepository;
use App\Infrastructure\Persistence\SqlSaleRepository;

/**
 * ServiceFactory - Contenedor de DI manual (singleton por request).
 * Único lugar donde se ensamblan puertos con sus adaptadores.
 */
final class ServiceFactory
{
    // ── Repositorios (singletons por request) ─────────────────────────────────
    private static ?SqlProductRepository   $productRepo   = null;
    private static ?SqlInventoryRepository $inventoryRepo = null;
    private static ?SqlPriceRepository     $priceRepo     = null;
    private static ?SqlSaleRepository      $saleRepo      = null;

    public static function getProductRepository(): SqlProductRepository
    {
        return self::$productRepo ??= new SqlProductRepository();
    }

    public static function getInventoryRepository(): SqlInventoryRepository
    {
        return self::$inventoryRepo ??= new SqlInventoryRepository();
    }

    public static function getPriceRepository(): SqlPriceRepository
    {
        return self::$priceRepo ??= new SqlPriceRepository();
    }

    public static function getSaleRepository(): SqlSaleRepository
    {
        return self::$saleRepo ??= new SqlSaleRepository();
    }

    // ── Casos de uso de Inventario ────────────────────────────────────────────

    public static function getRegisterProductUseCase(): RegisterProductUseCase
    {
        return new RegisterProductUseCase(
            self::getProductRepository(),
            self::getInventoryRepository(),
            self::getPriceRepository()
        );
    }

    public static function getUpdateProductUseCase(): UpdateProductUseCase
    {
        return new UpdateProductUseCase(
            self::getProductRepository(),
            self::getPriceRepository()
        );
    }

    public static function getDeleteProductUseCase(): DeleteProductUseCase
    {
        return new DeleteProductUseCase(self::getProductRepository());
    }

    public static function getAddStockUseCase(): AddStockUseCase
    {
        return new AddStockUseCase(
            self::getProductRepository(),
            self::getInventoryRepository()
        );
    }

    public static function getProductListQuery(): ProductListQuery
    {
        return new ProductListQuery();
    }

    // ── Casos de uso de Ventas ────────────────────────────────────────────────

    public static function getProcessSaleUseCase(): ProcessSaleUseCase
    {
        return new ProcessSaleUseCase(
            self::getSaleRepository(),
            self::getProductRepository()
        );
    }
}

<?php
// modules/reportes/routes.php — $router inyectado por ModuleManager::loadRoutes()

use App\Controllers\ReporteController;
use App\Controllers\Api\ApiReporteController;
use App\Middleware\AuthMiddleware;
use App\Middleware\SucursalMiddleware;

// ── Rutas web ───────────────────────────────────────────────────────────────
$router->group([
    'middleware' => [AuthMiddleware::class, SucursalMiddleware::class],
], function ($r) {
    $r->get('/reportes',                    [ReporteController::class, 'index']);
    $r->get('/reportes/ventas',             [ReporteController::class, 'ventasPorPeriodo']);
    $r->get('/reportes/productos-vendidos', [ReporteController::class, 'productosMasVendidos']);
    $r->get('/reportes/inventario',         [ReporteController::class, 'inventarioActual']);
    $r->get('/reportes/clientes-top',       [ReporteController::class, 'clientesTop']);
});

// ── API v1 — reportes ───────────────────────────────────────────────────────
$router->group(['prefix' => '/api/v1'], function ($r) {
    $r->get('/reportes/ventas',        [ApiReporteController::class, 'ventas']);
    $r->get('/reportes/productos-top', [ApiReporteController::class, 'productosTop']);
    $r->get('/reportes/stock-critico', [ApiReporteController::class, 'inventarioCritico']);
    $r->get('/reportes/compras',       [ApiReporteController::class, 'compras']);
});

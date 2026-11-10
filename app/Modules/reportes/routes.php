<?php

/**
 * Módulo reportes.
 *
 * @var CodeIgniter\Router\RouteCollection $routes
 */

$routes->group('reportes', ['filter' => ['auth', 'sucursal', 'permiso:reportes.ver']], static function ($routes) {
    $routes->get('/', 'ReporteController::index');
    $routes->get('ventas', 'ReporteController::ventasPorPeriodo');
    $routes->get('productos-vendidos', 'ReporteController::productosMasVendidos');
    $routes->get('inventario', 'ReporteController::inventarioActual');
    $routes->get('conteo', 'ReporteController::conteo');
    $routes->get('clientes-top', 'ReporteController::clientesTop');
});

// ── API v1 (token Bearer) ───────────────────────────────────────────────────
$routes->group('api/v1/reportes', ['namespace' => 'App\Controllers\Api', 'filter' => ['apitoken', 'permiso:reportes.ver']], static function ($routes) {
    $routes->get('ventas', 'ApiGeneralController::reporteVentas');
    $routes->get('productos-top', 'ApiGeneralController::productosTop');
    $routes->get('stock-critico', 'ApiGeneralController::stockCritico');
    $routes->get('compras', 'ApiGeneralController::reporteCompras');
});

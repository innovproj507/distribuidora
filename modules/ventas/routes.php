<?php
// modules/ventas/routes.php — $router inyectado por ModuleManager::loadRoutes()

use App\Controllers\VentaController;
use App\Controllers\CotizacionController;
use App\Controllers\Api\ApiVentaController;
use App\Controllers\Api\ApiCotizacionController;
use App\Middleware\AuthMiddleware;
use App\Middleware\SucursalMiddleware;

// ── Rutas web ───────────────────────────────────────────────────────────────
$router->group([
    'middleware' => [AuthMiddleware::class, SucursalMiddleware::class],
], function ($r) {
    $r->get('/ventas',                          [VentaController::class, 'index']);
    $r->get('/ventas/nueva',                    [VentaController::class, 'crear']);
    $r->get('/ventas/pos',                      [VentaController::class, 'puntoVenta']);
    $r->post('/ventas/procesar',                [VentaController::class, 'procesar']);
    $r->get('/ventas/diarias',                  [VentaController::class, 'reporteVentasDiarias']);
    $r->get('/ventas/venta/{venta_id}',         [VentaController::class, 'detalle']);
    $r->post('/ventas/venta/{venta_id}/anular', [VentaController::class, 'anular']);
    $r->post('/ventas/venta/{venta_id}/pagar',  [VentaController::class, 'registrarPago']);

    // Mini-API usada por el POS (rutas web con respuesta JSON)
    $r->get('/api/productos/buscar',              [VentaController::class, 'apiBuscarProductos']);
    $r->get('/api/productos/{producto_id}/stock', [VentaController::class, 'apiVerificarStock']);
    $r->get('/api/depositos',                     [VentaController::class, 'apiDepositos']);
    $r->post('/ventas/clientes/rapido',           [VentaController::class, 'crearClienteRapido']);
    $r->get('/api/clientes/{cliente_id}/credito', [VentaController::class, 'apiInfoCliente']);

    // Cotizaciones
    $r->get('/ventas/cotizaciones',                            [CotizacionController::class, 'index']);
    $r->get('/ventas/cotizaciones/nueva',                      [CotizacionController::class, 'crear']);
    $r->post('/ventas/cotizaciones/guardar',                   [CotizacionController::class, 'guardar']);
    $r->get('/ventas/cotizaciones/pos/pendientes',             [CotizacionController::class, 'pendientesPos']);
    $r->get('/ventas/cotizaciones/{cotizacion_id}',            [CotizacionController::class, 'detalle']);
    $r->get('/ventas/cotizaciones/{cotizacion_id}/imprimir',   [CotizacionController::class, 'imprimir']);
    $r->get('/ventas/cotizaciones/{cotizacion_id}/pdf',        [CotizacionController::class, 'descargarPdf']);
    $r->get('/ventas/cotizaciones/{cotizacion_id}/items-pos',  [CotizacionController::class, 'itemsPos']);
    $r->post('/ventas/cotizaciones/{cotizacion_id}/convertir', [CotizacionController::class, 'convertir']);
    $r->post('/ventas/cotizaciones/{cotizacion_id}/estado',    [CotizacionController::class, 'cambiarEstado']);
});

// Proforma pública de cotización — sin autenticación, protegida por token único (?t=...).
// Pensada para compartir por WhatsApp o correo; nunca requiere sesión iniciada.
$router->get('/c', [CotizacionController::class, 'verPublico']);
$router->get('/c/pdf', [CotizacionController::class, 'descargarPdfPublico']);

// ── API v1 — ventas ─────────────────────────────────────────────────────────
$router->group(['prefix' => '/api/v1'], function ($r) {
    $r->get('/ventas/historial',    [ApiVentaController::class, 'historial']);
    $r->get('/ventas/{id}',         [ApiVentaController::class, 'detalle']);
    $r->post('/ventas/procesar',    [ApiVentaController::class, 'procesar']);
    $r->post('/ventas/{id}/anular', [ApiVentaController::class, 'anular']);

    $r->get('/cotizaciones',                   [ApiCotizacionController::class, 'index']);
    $r->get('/cotizaciones/{id}',              [ApiCotizacionController::class, 'detalle']);
    $r->post('/cotizaciones',                  [ApiCotizacionController::class, 'guardar']);
    $r->post('/cotizaciones/{id}/convertir',   [ApiCotizacionController::class, 'convertir']);
});

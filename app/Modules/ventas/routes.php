<?php

/**
 * Módulo ventas — ventas, POS, cotizaciones y su mini-API JSON (sesión).
 *
 * @var CodeIgniter\Router\RouteCollection $routes
 */

$routes->group('', ['filter' => ['auth', 'sucursal']], static function ($routes) {
    $routes->get('ventas', 'VentaController::index', ['filter' => 'permiso:ventas.ver']);
    $routes->get('ventas/nueva', 'VentaController::crear', ['filter' => 'permiso:ventas.crear']);
    $routes->get('ventas/pos', 'VentaController::puntoVenta', ['filter' => 'permiso:pos.usar']);
    $routes->post('ventas/procesar', 'VentaController::procesar', ['filter' => 'permiso:ventas.crear,pos.usar']);
    $routes->get('ventas/diarias', 'VentaController::reporteVentasDiarias', ['filter' => 'permiso:ventas.ver']);
    $routes->get('ventas/venta/(:num)', 'VentaController::detalle/$1', ['filter' => 'permiso:ventas.ver,pos.usar']);
    $routes->post('ventas/venta/(:num)/anular', 'VentaController::anular/$1', ['filter' => 'permiso:ventas.anular']);
    $routes->post('ventas/venta/(:num)/pagar', 'VentaController::registrarPago/$1', ['filter' => 'permiso:ventas.ver']);

    // Caja con arqueo: la propia (caja.usar) y el historial de todas (cajas.ver)
    $routes->get('caja', 'CajaController::miCaja', ['filter' => 'permiso:caja.usar']);
    $routes->post('caja/abrir', 'CajaController::abrir', ['filter' => 'permiso:caja.usar']);
    $routes->post('caja/movimiento', 'CajaController::movimiento', ['filter' => 'permiso:caja.usar']);
    $routes->post('caja/cerrar', 'CajaController::cerrar', ['filter' => 'permiso:caja.usar,cajas.gestionar']);
    $routes->get('cajas', 'CajaController::index', ['filter' => 'permiso:cajas.ver']);
    $routes->get('cajas/(:num)', 'CajaController::detalle/$1', ['filter' => 'permiso:caja.usar,cajas.ver']);

    // Mini-API del POS (JSON)
    $routes->get('api/productos/buscar', 'VentaController::apiBuscarProductos');
    $routes->get('api/productos/(:num)/stock', 'VentaController::apiVerificarStock/$1');
    $routes->get('api/depositos', 'VentaController::apiDepositos');
    $routes->post('ventas/clientes/rapido', 'VentaController::crearClienteRapido', ['filter' => 'permiso:clientes.crear,pos.usar']);
    $routes->get('api/clientes/(:num)/credito', 'VentaController::apiInfoCliente/$1');

    // Cotizaciones
    $routes->get('ventas/cotizaciones', 'CotizacionController::index', ['filter' => 'permiso:cotizaciones.ver']);
    $routes->get('ventas/cotizaciones/nueva', 'CotizacionController::crear', ['filter' => 'permiso:cotizaciones.crear']);
    $routes->post('ventas/cotizaciones/guardar', 'CotizacionController::guardar', ['filter' => 'permiso:cotizaciones.crear']);
    $routes->get('ventas/cotizaciones/pos/pendientes', 'CotizacionController::pendientesPos');
    $routes->get('ventas/cotizaciones/(:num)', 'CotizacionController::detalle/$1', ['filter' => 'permiso:cotizaciones.ver']);
    $routes->get('ventas/cotizaciones/(:num)/imprimir', 'CotizacionController::imprimir/$1', ['filter' => 'permiso:cotizaciones.ver']);
    $routes->get('ventas/cotizaciones/(:num)/pdf', 'CotizacionController::descargarPdf/$1', ['filter' => 'permiso:cotizaciones.ver']);
    $routes->get('ventas/cotizaciones/(:num)/items-pos', 'CotizacionController::itemsPos/$1');
    $routes->post('ventas/cotizaciones/(:num)/convertir', 'CotizacionController::convertir/$1', ['filter' => 'permiso:ventas.crear']);
    $routes->post('ventas/cotizaciones/(:num)/estado', 'CotizacionController::cambiarEstado/$1', ['filter' => 'permiso:cotizaciones.crear']);
});

// Proforma pública de cotización: sin sesión, protegida por token único (?t=...)
$routes->get('c', 'CotizacionController::verPublico');
$routes->get('c/pdf', 'CotizacionController::descargarPdfPublico');

// ── API v1 (token Bearer) ───────────────────────────────────────────────────
$routes->group('api/v1', ['namespace' => 'App\Controllers\Api', 'filter' => 'apitoken'], static function ($routes) {
    $routes->get('ventas/historial', 'ApiVentaController::historial', ['filter' => 'permiso:ventas.ver']);
    $routes->get('ventas/(:num)', 'ApiVentaController::detalle/$1', ['filter' => 'permiso:ventas.ver']);
    $routes->post('ventas/procesar', 'ApiVentaController::procesar', ['filter' => 'permiso:ventas.crear,pos.usar']);
    $routes->post('ventas/(:num)/anular', 'ApiVentaController::anular/$1', ['filter' => 'permiso:ventas.anular']);

    $routes->get('cotizaciones', 'ApiVentaController::cotizaciones', ['filter' => 'permiso:cotizaciones.ver']);
    $routes->get('cotizaciones/(:num)', 'ApiVentaController::cotizacion/$1', ['filter' => 'permiso:cotizaciones.ver']);
    $routes->post('cotizaciones', 'ApiVentaController::crearCotizacion', ['filter' => 'permiso:cotizaciones.crear']);
    $routes->post('cotizaciones/(:num)/convertir', 'ApiVentaController::convertirCotizacion/$1', ['filter' => 'permiso:ventas.crear']);
});

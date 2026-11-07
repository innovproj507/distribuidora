<?php

/**
 * PWA móvil bajo /m. Los assets viven en public/movil/ (no en public/m/: el servidor
 * serviría esa carpeta real en vez de enrutar /m). El service worker y el manifest se
 * sirven por PHP desde /m para que el alcance (scope) sea /m/.
 *
 * @var CodeIgniter\Router\RouteCollection $routes
 */

$routes->get('m/login', 'MovilController::login');
$routes->get('m/sw.js', 'MovilController::serviceWorker');
$routes->get('m/manifest.webmanifest', 'MovilController::manifest');

$routes->group('m', ['filter' => ['movilauth', 'sucursal']], static function ($routes) {
    $routes->get('/', 'MovilController::inicio');
    $routes->get('vender', 'MovilController::vender');
    $routes->get('inventario', 'MovilController::inventario');
    $routes->get('productos/(:num)', 'MovilController::producto/$1');
    $routes->get('productos/(:num)/editar', 'MovilController::editarProducto/$1');
    $routes->post('productos/(:num)/editar', 'MovilController::guardarProducto/$1');
    $routes->post('productos/(:num)/foto', 'MovilController::subirFoto/$1');
    $routes->get('clientes', 'MovilController::clientes');
    $routes->get('compras', 'MovilController::compras');
    $routes->get('ventas', 'MovilController::ventas');
    $routes->get('cotizaciones', 'MovilController::cotizaciones');
    $routes->get('cobrar', 'MovilController::cobrar');
    $routes->get('alertas', 'MovilController::alertas');
    $routes->get('mas', 'MovilController::mas');
    $routes->get('compras/(:num)', 'MovilController::compra/$1');
    $routes->post('api/compra/(:num)/recibir', 'MovilApiController::recibirCompra/$1');
    $routes->post('api/compra/(:num)/reparto', 'MovilApiController::repartoCompra/$1');
    $routes->get('api/cliente/(:num)/pendientes', 'MovilApiController::clientePendientes/$1');
    $routes->post('api/cobro', 'MovilApiController::crearCobro');
    $routes->post('api/producto/(:num)/presentacion', 'MovilApiController::agregarPresentacion/$1');
    $routes->post('api/presentacion/(:num)/eliminar', 'MovilApiController::quitarPresentacion/$1');
    $routes->get('conteos', 'MovilController::conteos');
    $routes->get('conteos/(:num)', 'MovilController::conteo/$1');
    $routes->post('api/conteo', 'MovilApiController::crearConteo');
    $routes->post('api/conteo/(:num)/linea', 'MovilApiController::anotarConteo/$1');
    $routes->post('api/conteo/(:num)/aplicar', 'MovilApiController::aplicarConteo/$1');
    $routes->post('api/conteo/(:num)/cancelar', 'MovilApiController::cancelarConteo/$1');
    $routes->get('pedido', 'MovilController::pedido');
    $routes->post('api/pedido', 'MovilApiController::crearPedido');
    $routes->get('traslados', 'MovilController::traslados');
    $routes->get('api/traslado/(:num)', 'MovilApiController::traslado/$1');
    $routes->post('api/traslado', 'MovilApiController::enviarTraslado');
    $routes->post('api/traslado/(:num)/recibir', 'MovilApiController::recibirTraslado/$1');
    $routes->get('api/venta/(:num)', 'MovilApiController::venta/$1');
    $routes->post('api/alerta/(:num)/resolver', 'MovilApiController::resolverAlerta/$1');
    $routes->post('logout', 'MovilController::logout');
    $routes->get('logout', static fn () => redirect()->to('/m/mas'));

    $routes->get('api/productos', 'MovilApiController::productos');
    $routes->post('api/movimiento', 'MovilApiController::movimiento');
});

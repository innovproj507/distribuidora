<?php

/**
 * Módulo clientes — clientes, direcciones, cuentas por cobrar y mini-API JSON.
 *
 * @var CodeIgniter\Router\RouteCollection $routes
 */

$routes->group('', ['filter' => ['auth', 'sucursal']], static function ($routes) {
    $routes->get('clientes', 'ClienteController::index', ['filter' => 'permiso:clientes.ver']);
    $routes->get('clientes/nuevo', 'ClienteController::crear', ['filter' => 'permiso:clientes.crear']);
    $routes->post('clientes/nuevo', 'ClienteController::guardar', ['filter' => 'permiso:clientes.crear']);
    $routes->get('clientes/cuentas-por-cobrar', 'ClienteController::cuentasPorCobrar', ['filter' => 'permiso:clientes.ver']);
    $routes->get('clientes/(:num)', 'ClienteController::detalle/$1', ['filter' => 'permiso:clientes.ver']);
    $routes->get('clientes/(:num)/editar', 'ClienteController::editar/$1', ['filter' => 'permiso:clientes.editar']);
    $routes->post('clientes/(:num)/editar', 'ClienteController::actualizar/$1', ['filter' => 'permiso:clientes.editar']);
    $routes->post('clientes/(:num)/estado', 'ClienteController::cambiarEstado/$1', ['filter' => 'permiso:clientes.editar']);
    $routes->post('clientes/(:num)/eliminar', 'ClienteController::eliminar/$1', ['filter' => 'permiso:clientes.eliminar']);
    $routes->post('clientes/(:num)/direccion', 'ClienteController::agregarDireccion/$1', ['filter' => 'permiso:clientes.editar']);
    $routes->post('clientes/direccion/(:num)/eliminar', 'ClienteController::eliminarDireccion/$1', ['filter' => 'permiso:clientes.eliminar']);

    // Mini-API (JSON)
    $routes->post('api/clientes/crear-rapido', 'ClienteController::crearRapido', ['filter' => 'permiso:clientes.crear']);
    $routes->get('api/clientes/buscar', 'ClienteController::buscarAjax', ['filter' => 'permiso:clientes.ver,pos.usar']);
});

// ── API v1 (token Bearer) ───────────────────────────────────────────────────
$routes->group('api/v1', ['namespace' => 'App\Controllers\Api', 'filter' => 'apitoken'], static function ($routes) {
    $routes->get('clientes', 'ApiCatalogoController::clientes', ['filter' => 'permiso:clientes.ver']);
    $routes->get('clientes/buscar', 'ApiCatalogoController::buscarClientes', ['filter' => 'permiso:clientes.ver,pos.usar']);
    $routes->get('clientes/(:num)', 'ApiCatalogoController::cliente/$1', ['filter' => 'permiso:clientes.ver,pos.usar']);
    $routes->post('clientes', 'ApiCatalogoController::crearCliente', ['filter' => 'permiso:clientes.crear']);
    $routes->put('clientes/(:num)', 'ApiCatalogoController::actualizarCliente/$1', ['filter' => 'permiso:clientes.editar']);
    $routes->delete('clientes/(:num)', 'ApiCatalogoController::eliminarCliente/$1', ['filter' => 'permiso:clientes.eliminar']);
});

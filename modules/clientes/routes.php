<?php
// modules/clientes/routes.php — $router inyectado por ModuleManager::loadRoutes()

use App\Controllers\ClienteController;
use App\Controllers\Api\ApiClientController;
use App\Middleware\AuthMiddleware;
use App\Middleware\SucursalMiddleware;

// ── Rutas web ───────────────────────────────────────────────────────────────
$router->group([
    'middleware' => [AuthMiddleware::class, SucursalMiddleware::class],
], function ($r) {
    $r->get('/clientes',                       [ClienteController::class, 'index']);
    $r->get('/clientes/nuevo',                 [ClienteController::class, 'crear']);
    $r->post('/clientes/nuevo',                [ClienteController::class, 'guardar']);
    $r->get('/clientes/cuentas-por-cobrar',    [ClienteController::class, 'cuentasPorCobrar']);
    $r->get('/clientes/{cliente_id}',          [ClienteController::class, 'detalle']);
    $r->get('/clientes/{cliente_id}/editar',   [ClienteController::class, 'editar']);
    $r->post('/clientes/{cliente_id}/editar',  [ClienteController::class, 'actualizar']);
    $r->post('/clientes/{cliente_id}/estado',  [ClienteController::class, 'cambiarEstado']);
    $r->post('/clientes/{cliente_id}/eliminar',[ClienteController::class, 'eliminar']);

    // Mini-API usada por ventas/POS
    $r->post('/api/clientes/crear-rapido', [ClienteController::class, 'crearRapido']);
    $r->get('/api/clientes/buscar',        [ClienteController::class, 'buscarAjax']);
});

// ── API v1 — clientes ───────────────────────────────────────────────────────
$router->group(['prefix' => '/api/v1'], function ($r) {
    $r->get('/clientes',           [ApiClientController::class, 'index']);
    $r->get('/clientes/buscar',    [ApiClientController::class, 'buscar']);
    $r->get('/clientes/{id}',      [ApiClientController::class, 'detalle']);
    $r->post('/clientes',          [ApiClientController::class, 'guardar']);
    $r->put('/clientes/{id}',      [ApiClientController::class, 'actualizar']);
    $r->delete('/clientes/{id}',   [ApiClientController::class, 'eliminar']);
});

<?php
// modules/mantenimiento/routes.php — $router inyectado por ModuleManager::loadRoutes()

use App\Controllers\MantenimientoController;
use App\Middleware\AuthMiddleware;
use App\Middleware\SucursalMiddleware;

$router->group([
    'middleware' => [AuthMiddleware::class, SucursalMiddleware::class],
], function ($r) {
    // Dashboard
    $r->get('/mantenimiento', [MantenimientoController::class, 'index']);

    // Software
    $r->get('/mantenimiento/software',                           [MantenimientoController::class, 'software']);
    $r->get('/mantenimiento/software/nuevo',                     [MantenimientoController::class, 'crearSoftware']);
    $r->post('/mantenimiento/software/nuevo',                    [MantenimientoController::class, 'guardarSoftware']);
    $r->get('/mantenimiento/software/{software_id}/editar',      [MantenimientoController::class, 'editarSoftware']);
    $r->post('/mantenimiento/software/{software_id}/editar',     [MantenimientoController::class, 'actualizarSoftware']);
    $r->post('/mantenimiento/software/{software_id}/eliminar',   [MantenimientoController::class, 'eliminarSoftware']);

    // Tareas (Plan preventivo)
    $r->get('/mantenimiento/tareas',                             [MantenimientoController::class, 'tareas']);
    $r->get('/mantenimiento/tareas/nueva',                       [MantenimientoController::class, 'crearTarea']);
    $r->post('/mantenimiento/tareas/nueva',                      [MantenimientoController::class, 'guardarTarea']);
    $r->get('/mantenimiento/tareas/{tarea_id}/editar',           [MantenimientoController::class, 'editarTarea']);
    $r->post('/mantenimiento/tareas/{tarea_id}/editar',          [MantenimientoController::class, 'actualizarTarea']);
    $r->post('/mantenimiento/tareas/{tarea_id}/ejecutar',        [MantenimientoController::class, 'ejecutarTarea']);
    $r->post('/mantenimiento/tareas/{tarea_id}/eliminar',        [MantenimientoController::class, 'eliminarTarea']);
});

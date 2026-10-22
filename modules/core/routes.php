<?php
// modules/core/routes.php — $router inyectado por ModuleManager::loadRoutes()

use App\Controllers\DashboardController;
use App\Controllers\ConfiguracionController;
use App\Controllers\UsuarioController;
use App\Controllers\AuthController;
use App\Controllers\Auth2faController;
use App\Controllers\BitacoraController;
use App\Controllers\AppController;
use App\Controllers\LimpiezaController;
use App\Controllers\Api\ApiDashboardController;
use App\Controllers\Api\ApiDepositoController;
use App\Controllers\Api\ApiConfiguracionController;
use App\Controllers\Api\ApiUsuarioController;
use App\Middleware\AuthMiddleware;
use App\Middleware\SucursalMiddleware;

// ── Rutas web (sesión) ──────────────────────────────────────────────────────
$router->group([
    'middleware' => [AuthMiddleware::class, SucursalMiddleware::class],
], function ($r) {
    $r->get('/',          [DashboardController::class, 'index']);
    $r->get('/dashboard', [DashboardController::class, 'index']);

    // Configuración
    $r->get('/configuracion',           [ConfiguracionController::class, 'sistema']);
    $r->post('/configuracion/guardar',  [ConfiguracionController::class, 'guardar']);
    $r->get('/configuracion/roles',     [ConfiguracionController::class, 'roles']);
    $r->get('/configuracion/impresoras',[ConfiguracionController::class, 'impresoras']);
    $r->get('/configuracion/numeracion',  [ConfiguracionController::class, 'numeracion']);
    $r->post('/configuracion/numeracion', [ConfiguracionController::class, 'guardarNumeracion']);

    // Usuarios
    $r->get('/usuarios',                     [UsuarioController::class, 'index']);
    $r->get('/usuarios/nuevo',               [UsuarioController::class, 'crear']);
    $r->post('/usuarios/nuevo',              [UsuarioController::class, 'guardar']);
    $r->get('/usuarios/perfil',              [UsuarioController::class, 'perfil']);
    $r->post('/usuarios/perfil',             [UsuarioController::class, 'perfil']);
    $r->post('/usuarios/cambiar-rol',        [UsuarioController::class, 'cambiarRol']);
    $r->post('/usuarios/toggle-activo',      [UsuarioController::class, 'toggleActivo']);
    $r->post('/usuarios/guardar-permisos',   [UsuarioController::class, 'guardarPermisos']);
    $r->get('/usuarios/{user_id}',           [UsuarioController::class, 'detalle']);
    $r->get('/usuarios/{user_id}/editar',    [UsuarioController::class, 'editar']);
    $r->post('/usuarios/{user_id}/editar',   [UsuarioController::class, 'actualizar']);
    $r->post('/usuarios/{user_id}/estado',   [UsuarioController::class, 'cambiarEstado']);
    $r->post('/usuarios/{user_id}/eliminar', [UsuarioController::class, 'eliminar']);
    $r->get('/usuarios/{user_id}/permisos',  [UsuarioController::class, 'permisos']);

    // 2FA setup
    $r->get('/auth/2fa/setup',          [Auth2faController::class, 'setup']);
    $r->post('/auth/2fa/guardar-setup', [Auth2faController::class, 'guardarSetup']);
    $r->post('/auth/2fa/desactivar',    [Auth2faController::class, 'desactivar']);

    // Bitácora
    $r->get('/bitacora', [BitacoraController::class, 'index']);

    // Limpieza de BD (solo superusuarios)
    $r->get('/configuracion/limpieza',           [LimpiezaController::class, 'index']);
    $r->post('/configuracion/limpieza/ejecutar', [LimpiezaController::class, 'ejecutar']);

    // App Store (solo superusuarios)
    $r->get('/apps',                       [AppController::class, 'index']);
    $r->post('/apps/instalar',             [AppController::class, 'instalar']);
    $r->post('/apps/desinstalar',          [AppController::class, 'desinstalar']);
});

// ── API v1 — core ───────────────────────────────────────────────────────────
$router->group(['prefix' => '/api/v1'], function ($r) {
    $r->get('/dashboard', [ApiDashboardController::class, 'index']);

    $r->get('/depositos',          [ApiDepositoController::class, 'index']);
    $r->get('/depositos/{id}',     [ApiDepositoController::class, 'detalle']);
    $r->post('/depositos',         [ApiDepositoController::class, 'guardar']);
    $r->put('/depositos/{id}',     [ApiDepositoController::class, 'actualizar']);
    $r->delete('/depositos/{id}',  [ApiDepositoController::class, 'eliminar']);

    $r->get('/configuracion/empresa',       [ApiConfiguracionController::class, 'empresa']);
    $r->put('/configuracion/empresa',       [ApiConfiguracionController::class, 'actualizarEmpresa']);
    $r->get('/configuracion/sucursal/{id}', [ApiConfiguracionController::class, 'sucursal']);

    $r->get('/usuarios',           [ApiUsuarioController::class, 'index']);
    $r->get('/usuarios/perfil',    [ApiUsuarioController::class, 'perfil']);
    $r->get('/usuarios/{id}',      [ApiUsuarioController::class, 'detalle']);
    $r->post('/usuarios',          [ApiUsuarioController::class, 'guardar']);
    $r->put('/usuarios/{id}',      [ApiUsuarioController::class, 'actualizar']);
});

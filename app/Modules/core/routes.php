<?php

/**
 * Módulo core — dashboard, 2FA, configuración, usuarios, bitácora, limpieza y aplicaciones.
 * La configuración exige usuarios.gestionar (la matriz no tiene un permiso propio para ella);
 * limpieza y aplicaciones exigen superusuario dentro del controlador.
 *
 * @var CodeIgniter\Router\RouteCollection $routes
 */

$routes->group('', ['filter' => ['auth', 'sucursal']], static function ($routes) {
    $routes->get('/', 'DashboardController::index');
    $routes->get('dashboard', 'DashboardController::index');

    // 2FA
    $routes->get('auth/2fa/setup', 'Auth2faController::setup');
    $routes->post('auth/2fa/guardar-setup', 'Auth2faController::guardarSetup');
    $routes->post('auth/2fa/desactivar', 'Auth2faController::desactivar');

    // Configuración
    $routes->get('configuracion', 'ConfiguracionController::sistema', ['filter' => 'permiso:usuarios.gestionar']);
    $routes->post('configuracion/guardar', 'ConfiguracionController::guardar', ['filter' => 'permiso:usuarios.gestionar']);
    $routes->get('configuracion/roles', 'ConfiguracionController::roles', ['filter' => 'permiso:usuarios.ver']);
    $routes->get('configuracion/impresoras', 'ConfiguracionController::impresoras', ['filter' => 'permiso:usuarios.gestionar']);
    $routes->get('configuracion/numeracion', 'ConfiguracionController::numeracion', ['filter' => 'permiso:usuarios.gestionar']);
    $routes->post('configuracion/numeracion', 'ConfiguracionController::guardarNumeracion', ['filter' => 'permiso:usuarios.gestionar']);

    // Usuarios (el perfil propio lo usa cualquier usuario)
    $routes->get('usuarios/perfil', 'UsuarioController::perfil');
    $routes->post('usuarios/perfil', 'UsuarioController::perfil');
    $routes->get('usuarios', 'UsuarioController::index', ['filter' => 'permiso:usuarios.ver']);
    $routes->get('usuarios/nuevo', 'UsuarioController::crear', ['filter' => 'permiso:usuarios.gestionar']);
    $routes->post('usuarios/nuevo', 'UsuarioController::guardar', ['filter' => 'permiso:usuarios.gestionar']);
    $routes->post('usuarios/cambiar-rol', 'UsuarioController::cambiarRol', ['filter' => 'permiso:usuarios.gestionar']);
    $routes->post('usuarios/toggle-activo', 'UsuarioController::toggleActivo', ['filter' => 'permiso:usuarios.gestionar']);
    $routes->post('usuarios/guardar-permisos', 'UsuarioController::guardarPermisos', ['filter' => 'permiso:usuarios.gestionar']);
    $routes->get('usuarios/(:num)', 'UsuarioController::detalle/$1', ['filter' => 'permiso:usuarios.ver']);
    $routes->get('usuarios/(:num)/editar', 'UsuarioController::editar/$1', ['filter' => 'permiso:usuarios.gestionar']);
    $routes->post('usuarios/(:num)/editar', 'UsuarioController::actualizar/$1', ['filter' => 'permiso:usuarios.gestionar']);
    $routes->post('usuarios/(:num)/estado', 'UsuarioController::toggleActivo/$1', ['filter' => 'permiso:usuarios.gestionar']);
    $routes->post('usuarios/(:num)/eliminar', 'UsuarioController::eliminar/$1', ['filter' => 'permiso:usuarios.gestionar']);
    $routes->get('usuarios/(:num)/permisos', 'UsuarioController::permisos/$1', ['filter' => 'permiso:usuarios.gestionar']);

    // Sucursales (el cambio de sucursal/depósito lo valida SucursalService según el usuario)
    $routes->post('sucursales/cambiar', 'SucursalController::cambiar');
    $routes->post('sucursales/cambiar-deposito', 'SucursalController::cambiarDeposito');
    $routes->get('sucursales', 'SucursalController::index', ['filter' => 'permiso:sucursales.ver']);
    $routes->get('sucursales/nueva', 'SucursalController::crear', ['filter' => 'permiso:sucursales.gestionar']);
    $routes->post('sucursales/nueva', 'SucursalController::guardar', ['filter' => 'permiso:sucursales.gestionar']);
    $routes->get('sucursales/(:num)/editar', 'SucursalController::editar/$1', ['filter' => 'permiso:sucursales.gestionar']);
    $routes->post('sucursales/(:num)/editar', 'SucursalController::actualizar/$1', ['filter' => 'permiso:sucursales.gestionar']);
    $routes->post('sucursales/(:num)/estado', 'SucursalController::toggleActiva/$1', ['filter' => 'permiso:sucursales.gestionar']);

    // Bitácora
    $routes->get('bitacora', 'BitacoraController::index', ['filter' => 'permiso:bitacora.ver']);

    // Limpieza de BD y aplicaciones (superusuario, validado en el controlador)
    $routes->get('configuracion/limpieza', 'LimpiezaController::index');
    $routes->post('configuracion/limpieza/ejecutar', 'LimpiezaController::ejecutar');
    $routes->get('apps', 'AppController::index');
    $routes->post('apps/instalar', 'AppController::instalar');
    $routes->post('apps/desinstalar', 'AppController::desinstalar');
});

// ── API v1 (token Bearer) ───────────────────────────────────────────────────
$routes->group('api/v1', ['namespace' => 'App\Controllers\Api', 'filter' => 'apitoken'], static function ($routes) {
    $routes->get('dashboard', 'ApiGeneralController::dashboard', ['filter' => 'permiso:dashboard.ver']);

    $routes->get('depositos', 'ApiInventarioController::depositos', ['filter' => 'permiso:depositos.ver,pos.usar']);
    $routes->get('depositos/(:num)', 'ApiInventarioController::deposito/$1', ['filter' => 'permiso:depositos.ver']);
    $routes->post('depositos', 'ApiInventarioController::crearDeposito', ['filter' => 'permiso:depositos.gestionar']);
    $routes->put('depositos/(:num)', 'ApiInventarioController::actualizarDeposito/$1', ['filter' => 'permiso:depositos.gestionar']);
    $routes->delete('depositos/(:num)', 'ApiInventarioController::eliminarDeposito/$1', ['filter' => 'permiso:depositos.gestionar']);

    $routes->get('configuracion/empresa', 'ApiGeneralController::empresa');
    $routes->put('configuracion/empresa', 'ApiGeneralController::actualizarEmpresa', ['filter' => 'permiso:usuarios.gestionar']);
    $routes->get('configuracion/sucursal/(:num)', 'ApiGeneralController::sucursal/$1');

    $routes->get('usuarios/perfil', 'ApiGeneralController::perfil');
    $routes->get('usuarios', 'ApiGeneralController::usuarios', ['filter' => 'permiso:usuarios.ver']);
    $routes->get('usuarios/(:num)', 'ApiGeneralController::usuario/$1', ['filter' => 'permiso:usuarios.ver']);
    $routes->post('usuarios', 'ApiGeneralController::crearUsuario', ['filter' => 'permiso:usuarios.gestionar']);
    $routes->put('usuarios/(:num)', 'ApiGeneralController::actualizarUsuario/$1'); // propio o usuarios.gestionar (en el controlador)
});

<?php

use App\Libraries\ModuleManager;
use CodeIgniter\Router\RouteCollection;

/**
 * Rutas públicas (sin sesión). El resto lo registra cada módulo instalado en
 * app/Modules/{modulo}/routes.php (ver ModuleManager::loadRoutes()).
 *
 * @var RouteCollection $routes
 */

$routes->get('login', 'AuthController::showLogin');
$routes->post('login', 'AuthController::login');
// Cerrar sesión solo por POST con CSRF (un <img src="/logout"> en otra página ya no la cierra).
// El GET queda para enlaces viejos: no cierra nada, solo vuelve al inicio.
$routes->post('logout', 'AuthController::logout');
$routes->get('logout', static fn () => redirect()->to('/'));

$routes->get('auth/2fa', 'Auth2faController::show');
$routes->post('auth/2fa/verificar', 'Auth2faController::verificar');

// API REST: estado y login públicos; el resto (con token Bearer) lo registra cada módulo
$routes->get('api/v1', 'Api\ApiAuthController::estado');
$routes->post('api/v1/login', 'Api\ApiAuthController::login');
$routes->post('api/v1/logout', 'Api\ApiAuthController::logout');

ModuleManager::loadRoutes($routes);

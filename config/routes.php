<?php
/**
 * Rutas del Sistema POS
 * Solo contiene rutas públicas (sin autenticación) y delega el resto a los módulos.
 */

use App\Controllers\AuthController;
use App\Controllers\Auth2faController;
use App\Controllers\Api\ApiAuthController;

$router = $app->getRouter();

// ═══════════════════════════════════════════════════════════
// RUTAS PÚBLICAS (sin autenticación)
// ═══════════════════════════════════════════════════════════

$router->get('/login',  [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);

$router->get('/auth/2fa',             [Auth2faController::class, 'show']);
$router->post('/auth/2fa/verificar',  [Auth2faController::class, 'verificar']);

// Estado de la API (sin autenticación)
$router->get('/api/v1', function () {
    header('Content-Type: application/json');
    echo json_encode([
        'status'  => 'success',
        'message' => 'API POS v1',
        'version' => '1.0.0',
    ]);
});

// Login / Logout API
$router->post('/api/v1/login',  [ApiAuthController::class, 'login']);
$router->post('/api/v1/logout', [ApiAuthController::class, 'logout']);

// ═══════════════════════════════════════════════════════════
// RUTAS DE MÓDULOS (cargadas dinámicamente)
// ═══════════════════════════════════════════════════════════

\App\Core\ModuleManager::loadRoutes($router);

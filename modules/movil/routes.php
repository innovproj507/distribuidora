<?php
// modules/movil/routes.php — $router inyectado por ModuleManager::loadRoutes()
//
// PWA móvil bajo /m. Los assets estáticos viven en public/movil/ (no en public/m/:
// Nginx serviría la carpeta real en vez de enrutar /m). El service worker y el
// manifest se sirven por PHP desde /m para que su alcance (scope) sea /m/.

use App\Controllers\MovilController;
use App\Controllers\MovilApiController;
use App\Middleware\MovilAuthMiddleware;
use App\Middleware\SucursalMiddleware;

// ── Públicas (sin sesión) ───────────────────────────────────────────────────
$router->get('/m/login',               [MovilController::class, 'login']);
$router->get('/m/sw.js',               [MovilController::class, 'serviceWorker']);
$router->get('/m/manifest.webmanifest', [MovilController::class, 'manifest']);

// ── Páginas y API JSON (sesión web) ─────────────────────────────────────────
$router->group([
    'middleware' => [MovilAuthMiddleware::class, SucursalMiddleware::class],
], function ($r) {
    $r->get('/m',            [MovilController::class, 'inicio']);
    $r->get('/m/vender',     [MovilController::class, 'vender']);
    $r->get('/m/inventario', [MovilController::class, 'inventario']);
    $r->get('/m/clientes',   [MovilController::class, 'clientes']);
    $r->get('/m/compras',    [MovilController::class, 'compras']);
    $r->get('/m/mas',        [MovilController::class, 'mas']);
    $r->get('/m/logout',     [MovilController::class, 'logout']);

    $r->get('/m/api/productos',   [MovilApiController::class, 'productos']);
    $r->post('/m/api/movimiento', [MovilApiController::class, 'movimiento']);
});

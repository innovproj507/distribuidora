<?php
// modules/inventario/routes.php — $router inyectado por ModuleManager::loadRoutes()

use App\Controllers\ProductoController;
use App\Controllers\CategoriaController;
use App\Controllers\MarcaController;
use App\Controllers\ProveedorController;
use App\Controllers\DepositoController;
use App\Controllers\CompraController;
use App\Controllers\OrdenCompraAutoController;
use App\Controllers\LoteController;
use App\Controllers\AlertaController;
use App\Controllers\ConteoController;
use App\Controllers\TrasladoController;
use App\Controllers\Api\ApiProductoController;
use App\Controllers\Api\ApiCategoriaController;
use App\Controllers\Api\ApiProveedorController;
use App\Controllers\Api\ApiInventoryController;
use App\Controllers\Api\ApiCompraController;
use App\Controllers\Api\ApiTrasladoController;
use App\Middleware\AuthMiddleware;
use App\Middleware\SucursalMiddleware;

// ── Rutas web ───────────────────────────────────────────────────────────────
$router->group([
    'middleware' => [AuthMiddleware::class, SucursalMiddleware::class],
], function ($r) {

    // Productos
    $r->get('/inventario',                           [ProductoController::class, 'index']);
    $r->get('/inventario/exportar-excel',            [ProductoController::class, 'exportarExcel']);
    $r->get('/inventario/nuevo',                     [ProductoController::class, 'crear']);
    $r->post('/inventario/nuevo',                    [ProductoController::class, 'guardar']);
    $r->get('/inventario/kardex',                    [ProductoController::class, 'kardex']);
    $r->get('/inventario/{producto_id}',             [ProductoController::class, 'detalle']);
    $r->get('/inventario/{producto_id}/editar',      [ProductoController::class, 'editar']);
    $r->post('/inventario/{producto_id}/editar',     [ProductoController::class, 'actualizar']);
    $r->post('/inventario/{producto_id}/eliminar',   [ProductoController::class, 'eliminar']);
    $r->get('/inventario/{producto_id}/precios',     [ProductoController::class, 'precios']);
    $r->post('/inventario/{producto_id}/precios',    [ProductoController::class, 'precios']);
    $r->post('/inventario/{producto_id}/stock',      [ProductoController::class, 'agregarStock']);

    // Categorías
    $r->get('/inventario/categorias',                          [CategoriaController::class, 'index']);
    $r->get('/inventario/categorias/nueva',                    [CategoriaController::class, 'crear']);
    $r->post('/inventario/categorias/nueva',                   [CategoriaController::class, 'guardar']);
    $r->get('/inventario/categorias/{categoria_id}/editar',    [CategoriaController::class, 'editar']);
    $r->post('/inventario/categorias/{categoria_id}/editar',   [CategoriaController::class, 'actualizar']);
    $r->post('/inventario/categorias/{categoria_id}/eliminar', [CategoriaController::class, 'eliminar']);

    // Marcas
    $r->get('/inventario/marcas',                        [MarcaController::class, 'index']);
    $r->post('/inventario/marcas/guardar',               [MarcaController::class, 'guardar']);
    $r->post('/inventario/marcas/{marca_id}/actualizar', [MarcaController::class, 'actualizar']);
    $r->post('/inventario/marcas/{marca_id}/eliminar',   [MarcaController::class, 'eliminar']);

    // Proveedores
    $r->get('/inventario/proveedores',                             [ProveedorController::class, 'index']);
    $r->get('/inventario/proveedores/nuevo',                       [ProveedorController::class, 'crear']);
    $r->post('/inventario/proveedores/nuevo',                      [ProveedorController::class, 'guardar']);
    $r->get('/inventario/proveedores/{proveedor_id}/editar',       [ProveedorController::class, 'editar']);
    $r->post('/inventario/proveedores/{proveedor_id}/editar',      [ProveedorController::class, 'actualizar']);
    $r->post('/inventario/proveedores/{proveedor_id}/eliminar',    [ProveedorController::class, 'eliminar']);
    $r->get('/inventario/proveedores/{proveedor_id}/evaluaciones', [ProveedorController::class, 'evaluaciones']);
    $r->post('/inventario/proveedores/evaluacion/guardar',         [ProveedorController::class, 'guardarEvaluacion']);

    // Depósitos
    $r->get('/inventario/depositos',                          [DepositoController::class, 'index']);
    $r->get('/inventario/depositos/nuevo',                    [DepositoController::class, 'crear']);
    $r->post('/inventario/depositos/nuevo',                   [DepositoController::class, 'guardar']);
    $r->get('/inventario/depositos/{deposito_id}',            [DepositoController::class, 'detalle']);
    $r->get('/inventario/depositos/{deposito_id}/editar',     [DepositoController::class, 'editar']);
    $r->post('/inventario/depositos/{deposito_id}/editar',    [DepositoController::class, 'actualizar']);
    $r->post('/inventario/depositos/{deposito_id}/eliminar',  [DepositoController::class, 'eliminar']);

    // Mini-API compras (sesión, respuesta JSON)
    $r->get('/api/compras/siguiente-codigo',   [CompraController::class, 'siguienteCodigoProducto']);
    $r->post('/api/compras/producto-rapido',   [CompraController::class, 'crearProductoRapido']);
    $r->post('/api/compras/proveedor-rapido',  [CompraController::class, 'crearProveedorRapido']);

    // Mini-API inventario (creación rápida desde formulario de producto)
    $r->post('/api/inventario/categoria-rapida', [ProductoController::class, 'crearCategoriaRapida']);
    $r->post('/api/inventario/marca-rapida',     [ProductoController::class, 'crearMarcaRapida']);

    // Compras
    $r->get('/compras',                                          [CompraController::class, 'index']);
    $r->get('/compras/nueva',                                    [CompraController::class, 'crear']);
    $r->post('/compras/guardar',                                 [CompraController::class, 'guardar']);
    $r->get('/compras/sugerencias',                              [OrdenCompraAutoController::class, 'index']);
    $r->post('/compras/sugerencias/generar',                     [OrdenCompraAutoController::class, 'generar']);
    $r->post('/compras/sugerencias/{sugerencia_id}/convertir',   [OrdenCompraAutoController::class, 'convertir']);
    $r->post('/compras/sugerencias/{sugerencia_id}/descartar',   [OrdenCompraAutoController::class, 'descartar']);
    $r->get('/compras/{id}',                                     [CompraController::class, 'detalle']);
    $r->get('/compras/{id}/recibir',                             [CompraController::class, 'recibir']);
    $r->post('/compras/{id}/procesar-recepcion',                 [CompraController::class, 'procesarRecepcion']);

    // Lotes
    $r->get('/inventario/lotes',                 [LoteController::class, 'index']);
    $r->post('/inventario/lotes/marcar-vencidos',[LoteController::class, 'marcarVencidos']);

    // Alertas
    $r->get('/inventario/alertas',                       [AlertaController::class, 'index']);
    $r->post('/inventario/alertas/resolver-todas',       [AlertaController::class, 'resolverTodas']);
    $r->post('/inventario/alertas/{alerta_id}/resolver', [AlertaController::class, 'resolver']);

    // Conteos
    $r->get('/inventario/conteos',                         [ConteoController::class, 'index']);
    $r->get('/inventario/conteos/nuevo',                   [ConteoController::class, 'crear']);
    $r->post('/inventario/conteos',                        [ConteoController::class, 'guardar']);
    $r->get('/inventario/conteos/{conteo_id}/contar',      [ConteoController::class, 'contar']);
    $r->post('/inventario/conteos/{conteo_id}/contar',     [ConteoController::class, 'guardarConteo']);
    $r->get('/inventario/conteos/{conteo_id}/reconciliar', [ConteoController::class, 'reconciliar']);
    $r->post('/inventario/conteos/{conteo_id}/ajustar',    [ConteoController::class, 'aplicarAjustes']);
    $r->post('/inventario/conteos/{conteo_id}/cancelar',   [ConteoController::class, 'cancelar']);

    // Traslados
    $r->get('/inventario/traslados',           [TrasladoController::class, 'index']);
    $r->get('/inventario/traslados/nuevo',     [TrasladoController::class, 'crear']);
    $r->post('/inventario/traslados/guardar',  [TrasladoController::class, 'guardar']);
    $r->post('/inventario/traslados/{id}/recibir', [TrasladoController::class, 'recibir']);
});

// ── API v1 — inventario ─────────────────────────────────────────────────────
$router->group(['prefix' => '/api/v1'], function ($r) {
    $r->get('/productos',           [ApiProductoController::class, 'index']);
    $r->get('/productos/buscar',    [ApiProductoController::class, 'buscar']);
    $r->get('/productos/{id}',      [ApiProductoController::class, 'detalle']);
    $r->post('/productos',          [ApiProductoController::class, 'guardar']);
    $r->put('/productos/{id}',      [ApiProductoController::class, 'actualizar']);
    $r->delete('/productos/{id}',   [ApiProductoController::class, 'eliminar']);

    $r->get('/categorias',          [ApiCategoriaController::class, 'index']);
    $r->get('/categorias/{id}',     [ApiCategoriaController::class, 'detalle']);
    $r->post('/categorias',         [ApiCategoriaController::class, 'guardar']);
    $r->put('/categorias/{id}',     [ApiCategoriaController::class, 'actualizar']);
    $r->delete('/categorias/{id}',  [ApiCategoriaController::class, 'eliminar']);

    $r->get('/proveedores',         [ApiProveedorController::class, 'index']);
    $r->get('/proveedores/buscar',  [ApiProveedorController::class, 'buscar']);
    $r->get('/proveedores/{id}',    [ApiProveedorController::class, 'detalle']);
    $r->post('/proveedores',        [ApiProveedorController::class, 'guardar']);
    $r->put('/proveedores/{id}',    [ApiProveedorController::class, 'actualizar']);
    $r->delete('/proveedores/{id}', [ApiProveedorController::class, 'eliminar']);

    $r->get('/inventario/producto/{id}',  [ApiInventoryController::class, 'stockByProduct']);
    $r->get('/inventario/deposito/{id}',  [ApiInventoryController::class, 'inventoryByDeposit']);
    $r->post('/inventario/ajuste',        [ApiInventoryController::class, 'adjustStock']);

    $r->get('/compras',        [ApiCompraController::class, 'index']);
    $r->get('/compras/{id}',   [ApiCompraController::class, 'detalle']);
    $r->post('/compras',       [ApiCompraController::class, 'guardar']);

    $r->get('/traslados',                   [ApiTrasladoController::class, 'index']);
    $r->get('/traslados/{id}',              [ApiTrasladoController::class, 'detalle']);
    $r->post('/traslados',                  [ApiTrasladoController::class, 'guardar']);
    $r->post('/traslados/{id}/recibir',     [ApiTrasladoController::class, 'recibir']);
});

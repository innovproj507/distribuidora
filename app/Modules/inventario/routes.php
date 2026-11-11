<?php

/**
 * Módulo inventario — productos, categorías, marcas, proveedores, depósitos, compras,
 * sugerencias, lotes, alertas, conteos físicos y traslados.
 * Cada ruta exige el permiso de app/Config/Permisos.php que le corresponde.
 *
 * @var CodeIgniter\Router\RouteCollection $routes
 */

$routes->group('', ['filter' => ['auth', 'sucursal']], static function ($routes) {
    // Productos
    $routes->get('inventario', 'ProductoController::index', ['filter' => 'permiso:productos.ver']);
    $routes->get('inventario/exportar-excel', 'ProductoController::exportarExcel', ['filter' => 'permiso:productos.ver']);
    $routes->get('inventario/presentaciones/importar', 'PresentacionController::importar', ['filter' => 'permiso:productos.editar']);
    $routes->get('inventario/presentaciones/plantilla', 'PresentacionController::plantilla', ['filter' => 'permiso:productos.editar']);
    $routes->post('inventario/presentaciones/importar', 'PresentacionController::revisar', ['filter' => 'permiso:productos.editar']);
    $routes->post('inventario/presentaciones/aplicar', 'PresentacionController::aplicar', ['filter' => 'permiso:productos.editar']);
    $routes->get('inventario/nuevo', 'ProductoController::crear', ['filter' => 'permiso:productos.crear']);
    $routes->post('inventario/nuevo', 'ProductoController::guardar', ['filter' => 'permiso:productos.crear']);
    $routes->get('inventario/kardex', 'ProductoController::kardex', ['filter' => 'permiso:inventario.ver,productos.ver']);
    $routes->get('inventario/(:num)', 'ProductoController::detalle/$1', ['filter' => 'permiso:productos.ver']);
    $routes->get('inventario/(:num)/editar', 'ProductoController::editar/$1', ['filter' => 'permiso:productos.editar']);
    $routes->post('inventario/(:num)/editar', 'ProductoController::actualizar/$1', ['filter' => 'permiso:productos.editar']);
    $routes->post('inventario/(:num)/eliminar', 'ProductoController::eliminar/$1', ['filter' => 'permiso:productos.eliminar']);
    $routes->match(['GET', 'POST'], 'inventario/(:num)/precios', 'ProductoController::precios/$1', ['filter' => 'permiso:productos.editar']);
    $routes->post('inventario/(:num)/stock', 'ProductoController::agregarStock/$1', ['filter' => 'permiso:inventario.ajustar']);
    $routes->post('inventario/(:num)/salida', 'ProductoController::sacarStock/$1', ['filter' => 'permiso:inventario.ajustar']);

    // Categorías
    $routes->get('inventario/categorias', 'CategoriaController::index', ['filter' => 'permiso:categorias.ver']);
    $routes->get('inventario/categorias/nueva', 'CategoriaController::crear', ['filter' => 'permiso:categorias.crear']);
    $routes->post('inventario/categorias/nueva', 'CategoriaController::guardar', ['filter' => 'permiso:categorias.crear']);
    $routes->get('inventario/categorias/(:num)/editar', 'CategoriaController::editar/$1', ['filter' => 'permiso:categorias.editar']);
    $routes->post('inventario/categorias/(:num)/editar', 'CategoriaController::actualizar/$1', ['filter' => 'permiso:categorias.editar']);
    $routes->post('inventario/categorias/(:num)/eliminar', 'CategoriaController::eliminar/$1', ['filter' => 'permiso:categorias.eliminar']);

    // Marcas
    $routes->get('inventario/marcas', 'MarcaController::index', ['filter' => 'permiso:productos.ver']);
    $routes->post('inventario/marcas/guardar', 'MarcaController::guardar', ['filter' => 'permiso:productos.crear,productos.editar']);
    $routes->post('inventario/marcas/(:num)/actualizar', 'MarcaController::actualizar/$1', ['filter' => 'permiso:productos.editar']);
    $routes->post('inventario/marcas/(:num)/eliminar', 'MarcaController::eliminar/$1', ['filter' => 'permiso:productos.eliminar']);

    // Proveedores
    $routes->get('inventario/proveedores', 'ProveedorController::index', ['filter' => 'permiso:proveedores.ver']);
    $routes->get('inventario/proveedores/nuevo', 'ProveedorController::crear', ['filter' => 'permiso:proveedores.crear']);
    $routes->post('inventario/proveedores/nuevo', 'ProveedorController::guardar', ['filter' => 'permiso:proveedores.crear']);
    $routes->get('inventario/proveedores/(:num)/editar', 'ProveedorController::editar/$1', ['filter' => 'permiso:proveedores.editar']);
    $routes->post('inventario/proveedores/(:num)/editar', 'ProveedorController::actualizar/$1', ['filter' => 'permiso:proveedores.editar']);
    $routes->post('inventario/proveedores/(:num)/eliminar', 'ProveedorController::eliminar/$1', ['filter' => 'permiso:proveedores.editar']);
    $routes->get('inventario/proveedores/(:num)/evaluaciones', 'ProveedorController::evaluaciones/$1', ['filter' => 'permiso:proveedores.ver']);
    $routes->post('inventario/proveedores/evaluacion/guardar', 'ProveedorController::guardarEvaluacion', ['filter' => 'permiso:proveedores.editar']);

    // Depósitos
    $routes->get('inventario/depositos', 'DepositoController::index', ['filter' => 'permiso:depositos.ver']);
    $routes->get('inventario/depositos/nuevo', 'DepositoController::crear', ['filter' => 'permiso:depositos.gestionar']);
    $routes->post('inventario/depositos/nuevo', 'DepositoController::guardar', ['filter' => 'permiso:depositos.gestionar']);
    $routes->get('inventario/depositos/(:num)', 'DepositoController::detalle/$1', ['filter' => 'permiso:depositos.ver']);
    $routes->get('inventario/depositos/(:num)/editar', 'DepositoController::editar/$1', ['filter' => 'permiso:depositos.gestionar']);
    $routes->post('inventario/depositos/(:num)/editar', 'DepositoController::actualizar/$1', ['filter' => 'permiso:depositos.gestionar']);
    $routes->post('inventario/depositos/(:num)/eliminar', 'DepositoController::eliminar/$1', ['filter' => 'permiso:depositos.gestionar']);

    // Mini-API (JSON) de los formularios de producto y compra
    $routes->get('api/compras/siguiente-codigo', 'CompraController::siguienteCodigoProducto', ['filter' => 'permiso:productos.crear,compras.crear']);
    $routes->post('api/compras/producto-rapido', 'CompraController::crearProductoRapido', ['filter' => 'permiso:productos.crear,compras.crear']);
    $routes->post('api/compras/proveedor-rapido', 'CompraController::crearProveedorRapido', ['filter' => 'permiso:compras.crear,proveedores.crear']);
    $routes->get('api/inventario/siguiente-codigo', 'ProductoController::siguienteCodigo', ['filter' => 'permiso:productos.crear']);
    $routes->post('api/inventario/categoria-rapida', 'ProductoController::crearCategoriaRapida', ['filter' => 'permiso:categorias.crear,productos.crear']);
    $routes->post('api/inventario/marca-rapida', 'ProductoController::crearMarcaRapida', ['filter' => 'permiso:productos.crear,productos.editar']);

    // Compras
    $routes->get('compras', 'CompraController::index', ['filter' => 'permiso:compras.ver']);
    $routes->get('compras/nueva', 'CompraController::crear', ['filter' => 'permiso:compras.crear']);
    $routes->post('compras/guardar', 'CompraController::guardar', ['filter' => 'permiso:compras.crear']);
    $routes->get('compras/sugerencias', 'OrdenCompraAutoController::index', ['filter' => 'permiso:sugerencias.ver']);
    $routes->post('compras/sugerencias/generar', 'OrdenCompraAutoController::generar', ['filter' => 'permiso:sugerencias.gestionar']);
    $routes->post('compras/sugerencias/(:num)/convertir', 'OrdenCompraAutoController::convertir/$1', ['filter' => 'permiso:sugerencias.gestionar']);
    $routes->post('compras/sugerencias/(:num)/descartar', 'OrdenCompraAutoController::descartar/$1', ['filter' => 'permiso:sugerencias.gestionar']);
    $routes->get('compras/(:num)', 'CompraController::detalle/$1', ['filter' => 'permiso:compras.ver']);
    $routes->get('compras/(:num)/editar', 'CompraController::editar/$1', ['filter' => 'permiso:compras.crear']);
    $routes->post('compras/(:num)/actualizar', 'CompraController::actualizar/$1', ['filter' => 'permiso:compras.crear']);
    $routes->get('compras/(:num)/recibir', 'CompraController::recibir/$1', ['filter' => 'permiso:compras.recibir']);
    $routes->post('compras/(:num)/procesar-recepcion', 'CompraController::procesarRecepcion/$1', ['filter' => 'permiso:compras.recibir']);

    // Lotes
    $routes->get('inventario/lotes', 'LoteController::index', ['filter' => 'permiso:lotes.ver']);
    $routes->post('inventario/lotes/marcar-vencidos', 'LoteController::marcarVencidos', ['filter' => 'permiso:lotes.gestionar']);

    // Alertas
    $routes->get('inventario/alertas', 'AlertaController::index', ['filter' => 'permiso:alertas.ver']);
    $routes->post('inventario/alertas/resolver-todas', 'AlertaController::resolverTodas', ['filter' => 'permiso:alertas.resolver']);
    $routes->post('inventario/alertas/(:num)/resolver', 'AlertaController::resolver/$1', ['filter' => 'permiso:alertas.resolver']);

    // Conteos físicos
    $routes->get('inventario/conteos', 'ConteoController::index', ['filter' => 'permiso:conteos.ver']);
    $routes->get('inventario/conteos/nuevo', 'ConteoController::crear', ['filter' => 'permiso:conteos.crear']);
    $routes->post('inventario/conteos', 'ConteoController::guardar', ['filter' => 'permiso:conteos.crear']);
    $routes->get('inventario/conteos/(:num)/contar', 'ConteoController::contar/$1', ['filter' => 'permiso:conteos.crear']);
    $routes->post('inventario/conteos/(:num)/contar', 'ConteoController::guardarConteo/$1', ['filter' => 'permiso:conteos.crear']);
    $routes->get('inventario/conteos/(:num)/reconciliar', 'ConteoController::reconciliar/$1', ['filter' => 'permiso:conteos.ver']);
    $routes->post('inventario/conteos/(:num)/ajustar', 'ConteoController::aplicarAjustes/$1', ['filter' => 'permiso:conteos.reconciliar']);
    $routes->post('inventario/conteos/(:num)/cancelar', 'ConteoController::cancelar/$1', ['filter' => 'permiso:conteos.crear']);

    // Traslados
    $routes->get('inventario/traslados', 'TrasladoController::index', ['filter' => 'permiso:traslados.ver']);
    $routes->get('inventario/traslados/nuevo', 'TrasladoController::crear', ['filter' => 'permiso:traslados.crear']);
    $routes->post('inventario/traslados/guardar', 'TrasladoController::guardar', ['filter' => 'permiso:traslados.crear']);
    $routes->post('inventario/traslados/(:num)/recibir', 'TrasladoController::recibir/$1', ['filter' => 'permiso:traslados.crear,traslados.aprobar']);
});

// ── API v1 (token Bearer) ───────────────────────────────────────────────────
$routes->group('api/v1', ['namespace' => 'App\Controllers\Api', 'filter' => 'apitoken'], static function ($routes) {
    $routes->get('productos', 'ApiProductoController::index', ['filter' => 'permiso:productos.ver,pos.usar']);
    $routes->get('productos/buscar', 'ApiProductoController::buscar', ['filter' => 'permiso:productos.ver,pos.usar']);
    $routes->get('productos/(:num)', 'ApiProductoController::detalle/$1', ['filter' => 'permiso:productos.ver,pos.usar']);
    $routes->post('productos', 'ApiProductoController::crear', ['filter' => 'permiso:productos.crear']);
    $routes->put('productos/(:num)', 'ApiProductoController::actualizar/$1', ['filter' => 'permiso:productos.editar']);
    $routes->delete('productos/(:num)', 'ApiProductoController::eliminar/$1', ['filter' => 'permiso:productos.eliminar']);

    $routes->get('categorias', 'ApiCatalogoController::categorias', ['filter' => 'permiso:categorias.ver,productos.ver']);
    $routes->get('categorias/(:num)', 'ApiCatalogoController::categoria/$1', ['filter' => 'permiso:categorias.ver,productos.ver']);
    $routes->post('categorias', 'ApiCatalogoController::crearCategoria', ['filter' => 'permiso:categorias.crear']);
    $routes->put('categorias/(:num)', 'ApiCatalogoController::actualizarCategoria/$1', ['filter' => 'permiso:categorias.editar']);
    $routes->delete('categorias/(:num)', 'ApiCatalogoController::eliminarCategoria/$1', ['filter' => 'permiso:categorias.eliminar']);

    $routes->get('proveedores', 'ApiCatalogoController::proveedores', ['filter' => 'permiso:proveedores.ver']);
    $routes->get('proveedores/buscar', 'ApiCatalogoController::buscarProveedores', ['filter' => 'permiso:proveedores.ver,compras.crear']);
    $routes->get('proveedores/(:num)', 'ApiCatalogoController::proveedor/$1', ['filter' => 'permiso:proveedores.ver']);
    $routes->post('proveedores', 'ApiCatalogoController::crearProveedor', ['filter' => 'permiso:proveedores.crear']);
    $routes->put('proveedores/(:num)', 'ApiCatalogoController::actualizarProveedor/$1', ['filter' => 'permiso:proveedores.editar']);
    $routes->delete('proveedores/(:num)', 'ApiCatalogoController::eliminarProveedor/$1', ['filter' => 'permiso:proveedores.editar']);

    $routes->get('inventario/producto/(:num)', 'ApiInventarioController::stockProducto/$1', ['filter' => 'permiso:inventario.ver,productos.ver,pos.usar']);
    $routes->get('inventario/deposito/(:num)', 'ApiInventarioController::stockDeposito/$1', ['filter' => 'permiso:inventario.ver,productos.ver']);
    $routes->post('inventario/ajuste', 'ApiInventarioController::ajustar', ['filter' => 'permiso:inventario.ajustar']);

    $routes->get('compras', 'ApiInventarioController::compras', ['filter' => 'permiso:compras.ver']);
    $routes->get('compras/(:num)', 'ApiInventarioController::compra/$1', ['filter' => 'permiso:compras.ver']);
    $routes->post('compras', 'ApiInventarioController::crearCompra', ['filter' => 'permiso:compras.crear']);

    $routes->get('traslados', 'ApiInventarioController::traslados', ['filter' => 'permiso:traslados.ver']);
    $routes->get('traslados/(:num)', 'ApiInventarioController::traslado/$1', ['filter' => 'permiso:traslados.ver']);
    $routes->post('traslados', 'ApiInventarioController::crearTraslado', ['filter' => 'permiso:traslados.crear']);
    $routes->post('traslados/(:num)/recibir', 'ApiInventarioController::recibirTraslado/$1', ['filter' => 'permiso:traslados.crear,traslados.aprobar']);
});

<?php use App\Core\View;
$currentUri = $_SERVER['REQUEST_URI'] ?? '/';
$isProductos = str_contains($currentUri, '/inventario') && !str_contains($currentUri, '/categorias') && !str_contains($currentUri, '/proveedores') && !str_contains($currentUri, '/depositos');
$isCategorias = str_contains($currentUri, '/categorias');
$isProveedores = str_contains($currentUri, '/proveedores');
$isDepositos = str_contains($currentUri, '/depositos');
?>
<!-- Submenú de Inventario -->
<div class="submenu open py-4">
    <h3 class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Inventario</h3>
    <div class="space-y-1">
        <a href="/inventario"
            class="block px-4 py-2 text-sm font-medium <?= $isProductos ? 'text-sky-500 bg-blue-50 border-r-2 border-sky-500' : 'text-gray-700 hover:bg-gray-100 hover:text-sky-500' ?>">
            <i class="fas fa-box mr-2"></i> Todos los Productos
        </a>
        <a href="/inventario/categorias"
            class="block px-4 py-2 text-sm font-medium <?= $isCategorias ? 'text-sky-500 bg-blue-50 border-r-2 border-sky-500' : 'text-gray-700 hover:bg-gray-100 hover:text-sky-500' ?>">
            <i class="fas fa-tags mr-2"></i> Categorías
        </a>
        <a href="/inventario/proveedores"
            class="block px-4 py-2 text-sm font-medium <?= $isProveedores ? 'text-sky-500 bg-blue-50 border-r-2 border-sky-500' : 'text-gray-700 hover:bg-gray-100 hover:text-sky-500' ?>">
            <i class="fas fa-truck mr-2"></i> Proveedores
        </a>
        <a href="/inventario/depositos"
            class="block px-4 py-2 text-sm font-medium <?= $isDepositos ? 'text-sky-500 bg-blue-50 border-r-2 border-sky-500' : 'text-gray-700 hover:bg-gray-100 hover:text-sky-500' ?>">
            <i class="fas fa-warehouse mr-2"></i> Depósitos
        </a>
        <a href="/compras"
            class="block px-4 py-2 text-sm font-medium <?= str_contains($currentUri, '/compras') ? 'text-sky-500 bg-blue-50 border-r-2 border-sky-500' : 'text-gray-700 hover:bg-gray-100 hover:text-sky-500' ?>">
            <i class="fas fa-file-invoice-dollar mr-2 text-emerald-500"></i> Movimientos de Compra
        </a>
        <a href="/inventario/traslados"
            class="block px-4 py-2 text-sm font-medium <?= str_contains($currentUri, '/inventario/traslados') ? 'text-sky-500 bg-blue-50 border-r-2 border-sky-500' : 'text-gray-700 hover:bg-gray-100 hover:text-sky-500' ?>">
            <i class="fas fa-exchange-alt mr-2 text-amber-500"></i> Traslados entre Almacenes
        </a>
    </div>

    <h3 class="px-4 mt-6 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Acciones Rápidas</h3>
    <div class="space-y-1">
        <a href="/inventario/nuevo" class="block px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 hover:text-sky-500">
            <i class="fas fa-plus-circle mr-2 text-emerald-500"></i> Nuevo Producto
        </a>
        <a href="/inventario/categorias/nueva" class="block px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 hover:text-sky-500">
            <i class="fas fa-tag mr-2 text-sky-500"></i> Nueva Categoría
        </a>
        <a href="/inventario/depositos/nuevo" class="block px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 hover:text-sky-500">
            <i class="fas fa-warehouse mr-2 text-purple-500"></i> Nuevo Depósito
        </a>
    </div>
</div>

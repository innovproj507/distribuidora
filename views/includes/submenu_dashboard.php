<?php use App\Core\View;
$currentUri = $_SERVER['REQUEST_URI'] ?? '/';
?>
<!-- Submenú de Dashboard -->
<div class="submenu open py-4">
    <h3 class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Dashboard</h3>
    <div class="space-y-1">
        <a href="/" class="block px-4 py-2 text-sm font-medium text-sky-500 bg-blue-50 border-r-2 border-sky-500">
            <i class="fas fa-chart-line mr-2"></i> Resumen General
        </a>
        <a href="#" class="block px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 hover:text-sky-500">
            <i class="fas fa-chart-bar mr-2 text-blue-500"></i> Estadísticas
        </a>
        <a href="#" class="block px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 hover:text-sky-500">
            <i class="fas fa-clock mr-2 text-gray-400"></i> Actividad Reciente
        </a>
    </div>

    <h3 class="px-4 mt-6 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Acciones Rápidas</h3>
    <div class="space-y-1">
        <a href="/ventas/pos" class="block px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 hover:text-sky-500">
            <i class="fas fa-cash-register mr-2 text-emerald-500"></i> Nueva Venta
        </a>
        <a href="/inventario/nuevo" class="block px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 hover:text-sky-500">
            <i class="fas fa-plus-circle mr-2 text-cyan-500"></i> Nuevo Producto
        </a>
        <a href="/clientes/nuevo" class="block px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 hover:text-sky-500">
            <i class="fas fa-user-plus mr-2 text-amber-500"></i> Nuevo Cliente
        </a>
    </div>
</div>

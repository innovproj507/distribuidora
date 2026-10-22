<?php use App\Core\View;
$currentUri = $_SERVER['REQUEST_URI'] ?? '/';
$isVentas = str_contains($currentUri, '/ventas') && !str_contains($currentUri, '/cotizaciones');
$isCotizaciones = str_contains($currentUri, '/cotizaciones');
?>
<!-- Submenú de Ventas -->
<div class="submenu open py-4">
    <h3 class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Ventas</h3>
    <div class="space-y-1">
        <a href="/ventas"
            class="block px-4 py-2 text-sm font-medium <?= $isVentas ? 'text-sky-500 bg-blue-50 border-r-2 border-sky-500' : 'text-gray-700 hover:bg-gray-100 hover:text-sky-500' ?>">
            <i class="fas fa-shopping-cart mr-2"></i> Ventas
        </a>
        <a href="/ventas/cotizaciones"
            class="block px-4 py-2 text-sm font-medium <?= $isCotizaciones ? 'text-sky-500 bg-blue-50 border-r-2 border-sky-500' : 'text-gray-700 hover:bg-gray-100 hover:text-sky-500' ?>">
            <i class="fas fa-file-invoice mr-2"></i> Cotizaciones
        </a>
    </div>

    <h3 class="px-4 mt-6 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Acciones Rápidas</h3>
    <div class="space-y-1">
        <a href="/ventas/pos" class="block px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 hover:text-sky-500">
            <i class="fas fa-cash-register mr-2 text-emerald-500"></i> Punto de Venta
        </a>
        <a href="/ventas/nueva" class="block px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 hover:text-sky-500">
            <i class="fas fa-plus-circle mr-2 text-blue-500"></i> Nueva Venta
        </a>
        <a href="/ventas/cotizaciones/nueva" class="block px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 hover:text-sky-500">
            <i class="fas fa-file-invoice-dollar mr-2 text-green-500"></i> Nueva Cotización
        </a>
    </div>
</div>

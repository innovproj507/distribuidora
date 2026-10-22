<?php use App\Core\View;
$currentUri = $_SERVER['REQUEST_URI'] ?? '/';
?>
<!-- Submenú de Clientes -->
<div class="submenu open py-4">
    <h3 class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Clientes</h3>
    <div class="space-y-1">
        <a href="/clientes"
            class="block px-4 py-2 text-sm font-medium <?= !str_contains($currentUri, 'estado=') ? 'text-sky-500 bg-blue-50 border-r-2 border-sky-500' : 'text-gray-700 hover:bg-gray-100 hover:text-sky-500' ?>">
            <i class="fas fa-users mr-2"></i> Todos los Clientes
        </a>
        <a href="/clientes?estado=activo"
            class="block px-4 py-2 text-sm font-medium <?= str_contains($currentUri, 'estado=activo') ? 'text-sky-500 bg-blue-50 border-r-2 border-sky-500' : 'text-gray-700 hover:bg-gray-100 hover:text-sky-500' ?>">
            <i class="fas fa-user-check mr-2"></i> Clientes Activos
        </a>
        <a href="/clientes/cuentas-por-cobrar"
            class="block px-4 py-2 text-sm font-medium <?= str_contains($currentUri, '/cuentas-por-cobrar') ? 'text-sky-500 bg-blue-50 border-r-2 border-sky-500' : 'text-gray-700 hover:bg-gray-100 hover:text-sky-500' ?>">
            <i class="fas fa-hand-holding-usd mr-2 text-rose-500"></i> Cuentas por Cobrar
        </a>
    </div>

    <h3 class="px-4 mt-6 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Acciones Rápidas</h3>
    <div class="space-y-1">
        <a href="/clientes/nuevo" class="block px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 hover:text-sky-500">
            <i class="fas fa-user-plus mr-2 text-emerald-500"></i> Nuevo Cliente
        </a>
    </div>

    <!-- Estadísticas -->
    <h3 class="px-4 mt-6 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Estadísticas</h3>
    <div class="space-y-2 px-4">
        <div class="flex justify-between text-sm">
            <span class="text-gray-600">Total:</span>
            <span class="font-medium"><?= number_format((int)($stats['total'] ?? 0)) ?></span>
        </div>
        <div class="flex justify-between text-sm">
            <span class="text-green-600">Activos:</span>
            <span class="font-medium text-green-600"><?= number_format((int)($stats['activos'] ?? 0)) ?></span>
        </div>
        <div class="flex justify-between text-sm">
            <span class="text-red-600">Inactivos:</span>
            <span class="font-medium text-red-600"><?= number_format((int)($stats['inactivos'] ?? 0)) ?></span>
        </div>
    </div>
</div>

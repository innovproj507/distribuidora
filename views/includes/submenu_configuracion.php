<?php use App\Core\View;
$currentUri = $_SERVER['REQUEST_URI'] ?? '/';
?>
<!-- Submenú de Configuración -->
<div class="submenu open py-4">
    <h3 class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Configuración</h3>
    <div class="space-y-1">
        <a href="/configuracion"
            class="block px-4 py-2 text-sm font-medium <?= str_contains($currentUri, '/configuracion') && !str_contains($currentUri, '/roles') && !str_contains($currentUri, '/impresoras') && !str_contains($currentUri, '/numeracion') ? 'text-sky-500 bg-blue-50 border-r-2 border-sky-500' : 'text-gray-700 hover:bg-gray-100 hover:text-sky-500' ?>">
            <i class="fas fa-cog mr-2"></i> Sistema
        </a>
        <a href="/configuracion/roles"
            class="block px-4 py-2 text-sm font-medium <?= str_contains($currentUri, '/roles') ? 'text-sky-500 bg-blue-50 border-r-2 border-sky-500' : 'text-gray-700 hover:bg-gray-100 hover:text-sky-500' ?>">
            <i class="fas fa-user-shield mr-2"></i> Roles y Permisos
        </a>
        <a href="/configuracion/impresoras"
            class="block px-4 py-2 text-sm font-medium <?= str_contains($currentUri, '/impresoras') ? 'text-sky-500 bg-blue-50 border-r-2 border-sky-500' : 'text-gray-700 hover:bg-gray-100 hover:text-sky-500' ?>">
            <i class="fas fa-print mr-2"></i> Impresoras
        </a>
        <a href="/configuracion/numeracion"
            class="block px-4 py-2 text-sm font-medium <?= str_contains($currentUri, '/numeracion') ? 'text-sky-500 bg-blue-50 border-r-2 border-sky-500' : 'text-gray-700 hover:bg-gray-100 hover:text-sky-500' ?>">
            <i class="fas fa-hashtag mr-2"></i> Nomenclatura
        </a>
        <a href="/usuarios"
            class="block px-4 py-2 text-sm font-medium <?= str_contains($currentUri, '/usuarios') ? 'text-sky-500 bg-blue-50 border-r-2 border-sky-500' : 'text-gray-700 hover:bg-gray-100 hover:text-sky-500' ?>">
            <i class="fas fa-users-cog mr-2"></i> Gestión de Usuarios
        </a>
        <a href="/configuracion/suscripcion"
            class="block px-4 py-2 text-sm font-medium <?= str_contains($currentUri, '/suscripcion') ? 'text-sky-500 bg-blue-50 border-r-2 border-sky-500' : 'text-gray-700 hover:bg-gray-100 hover:text-sky-500' ?>">
            <i class="fas fa-crown mr-2 text-amber-500"></i> Mi Plan (SaaS)
        </a>
    </div>
</div>

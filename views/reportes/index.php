<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <a href="/reportes/ventas" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 hover:shadow-md transition group">
        <div class="flex items-center gap-4">
            <div class="h-12 w-12 rounded-xl bg-emerald-500 text-white flex items-center justify-center shrink-0 shadow-sm shadow-emerald-500/30">
                <i class="fas fa-chart-line text-lg"></i>
            </div>
            <div>
                <h3 class="font-semibold text-gray-800">Ventas por Período</h3>
                <p class="text-sm text-gray-400">Análisis de ventas por rango de fechas</p>
            </div>
        </div>
    </a>

    <a href="/reportes/productos-vendidos" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 hover:shadow-md transition group">
        <div class="flex items-center gap-4">
            <div class="h-12 w-12 rounded-xl bg-sky-500 text-white flex items-center justify-center shrink-0 shadow-sm shadow-sky-500/30">
                <i class="fas fa-trophy text-lg"></i>
            </div>
            <div>
                <h3 class="font-semibold text-gray-800">Productos Más Vendidos</h3>
                <p class="text-sm text-gray-400">Top productos por volumen de ventas</p>
            </div>
        </div>
    </a>

    <a href="/reportes/inventario" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 hover:shadow-md transition group">
        <div class="flex items-center gap-4">
            <div class="h-12 w-12 rounded-xl bg-orange-500 text-white flex items-center justify-center shrink-0 shadow-sm shadow-orange-500/30">
                <i class="fas fa-boxes-stacked text-lg"></i>
            </div>
            <div>
                <h3 class="font-semibold text-gray-800">Inventario Actual</h3>
                <p class="text-sm text-gray-400">Existencias y valorización</p>
            </div>
        </div>
    </a>

    <a href="/reportes/clientes-top" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 hover:shadow-md transition group">
        <div class="flex items-center gap-4">
            <div class="h-12 w-12 rounded-xl bg-cyan-500 text-white flex items-center justify-center shrink-0 shadow-sm shadow-cyan-500/30">
                <i class="fas fa-user-group text-lg"></i>
            </div>
            <div>
                <h3 class="font-semibold text-gray-800">Mejores Clientes</h3>
                <p class="text-sm text-gray-400">Top clientes por compras</p>
            </div>
        </div>
    </a>

    <a href="/ventas/diarias" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 hover:shadow-md transition group">
        <div class="flex items-center gap-4">
            <div class="h-12 w-12 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-sm shadow-amber-500/30">
                <i class="fas fa-calendar-day text-lg"></i>
            </div>
            <div>
                <h3 class="font-semibold text-gray-800">Ventas del Día</h3>
                <p class="text-sm text-gray-400">Resumen de ventas diarias</p>
            </div>
        </div>
    </a>
</div>

<?php View::endSection('content'); ?>

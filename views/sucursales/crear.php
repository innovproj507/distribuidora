<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<!-- Breadcrumb -->
<div class="flex items-center gap-2 text-sm text-gray-400 mb-3">
    <a href="/configuracion/sucursales" class="hover:text-gray-600 transition-colors">Sucursales</a>
    <i class="fas fa-chevron-right text-xs"></i>
    <span class="text-gray-700 font-medium">Nueva Sucursal</span>
</div>

<form action="/configuracion/sucursales/guardar" method="POST">
    <?= View::csrf() ?>

    <!-- Action bar -->
    <div class="flex items-center justify-between bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-2.5 mb-4">
        <div class="flex gap-2">
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
                <i class="fas fa-save"></i> Guardar
            </button>
            <a href="/configuracion/sucursales" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                Cancelar
            </a>
        </div>
    </div>

    <!-- Document card -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <h2 class="text-3xl font-bold text-gray-900 mb-6">Nueva Sucursal</h2>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-16">
            <div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Código *</label>
                    <input type="text" name="codigo" required placeholder="Ej: SUC-001"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Nombre *</label>
                    <input type="text" name="nombre" required placeholder="Ej: Sucursal Centro"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Teléfono</label>
                    <input type="text" name="telefono" placeholder="Teléfono de contacto"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
            </div>
            <div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Email</label>
                    <input type="email" name="email" placeholder="correo@sucursal.com"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Dirección</label>
                    <input type="text" name="direccion" placeholder="Dirección física"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <div class="flex gap-6 py-4">
                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-600 cursor-pointer">
                        <input type="checkbox" name="es_principal" value="1" class="rounded border-gray-300 text-sky-600 focus:ring-sky-500">
                        Sucursal Principal
                    </label>
                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-600 cursor-pointer">
                        <input type="checkbox" name="activa" value="1" checked class="rounded border-gray-300 text-sky-600 focus:ring-sky-500">
                        Activa
                    </label>
                </div>
            </div>
        </div>
    </div>
</form>

<?php View::endSection('content'); ?>

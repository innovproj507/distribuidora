<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<!-- Breadcrumb -->
<div class="flex items-center gap-2 text-sm text-gray-400 mb-3">
    <a href="/configuracion/sucursales" class="hover:text-gray-600 transition-colors">Sucursales</a>
    <i class="fas fa-chevron-right text-xs"></i>
    <span class="text-gray-700 font-medium"><?= View::e($sucursal->nombre) ?></span>
</div>

<form action="/configuracion/sucursales/<?= $sucursal->sucursal_id ?>/editar" method="POST">
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
        <span class="px-3 py-1.5 rounded-full text-xs font-semibold <?= $sucursal->activa ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' ?>">
            <?= $sucursal->activa ? 'Activa' : 'Inactiva' ?>
        </span>
    </div>

    <!-- Document card -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <h2 class="text-3xl font-bold text-gray-900 mb-6"><?= View::e($sucursal->nombre) ?></h2>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-16">
            <div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Código *</label>
                    <input type="text" name="codigo" value="<?= View::e($sucursal->codigo) ?>" required
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Nombre *</label>
                    <input type="text" name="nombre" value="<?= View::e($sucursal->nombre) ?>" required
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Teléfono</label>
                    <input type="text" name="telefono" value="<?= View::e($sucursal->telefono) ?>"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
            </div>
            <div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Email</label>
                    <input type="email" name="email" value="<?= View::e($sucursal->email) ?>"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Dirección</label>
                    <input type="text" name="direccion" value="<?= View::e($sucursal->direccion) ?>"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <div class="flex gap-6 py-4">
                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-600 cursor-pointer">
                        <input type="checkbox" name="es_principal" value="1" <?= $sucursal->es_principal ? 'checked' : '' ?> class="rounded border-gray-300 text-sky-600 focus:ring-sky-500">
                        Sucursal Principal
                    </label>
                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-600 cursor-pointer">
                        <input type="checkbox" name="activa" value="1" <?= $sucursal->activa ? 'checked' : '' ?> class="rounded border-gray-300 text-sky-600 focus:ring-sky-500">
                        Activa
                    </label>
                </div>
            </div>
        </div>
    </div>
</form>

<?php View::endSection('content'); ?>

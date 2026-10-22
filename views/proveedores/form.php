<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<?php
$isEdit = isset($proveedor->proveedor_id);
$backUrl = '/inventario/proveedores';
$formAction = $action ?? '/inventario/proveedores/nuevo';
$title = $isEdit ? ($proveedor->nombre ?? 'Editar Proveedor') : 'Nuevo Proveedor';
?>

<!-- Breadcrumb -->
<div class="flex items-center gap-2 text-sm text-gray-400 mb-3">
    <a href="/inventario/proveedores" class="hover:text-gray-600 transition-colors">Proveedores</a>
    <i class="fas fa-chevron-right text-xs"></i>
    <span class="text-gray-700 font-medium"><?= $isEdit ? View::e($proveedor->nombre ?? 'Editar') : 'Nuevo Proveedor' ?></span>
</div>

<form action="<?= $formAction ?>" method="POST">
    <?= View::csrf() ?>

    <!-- Action bar -->
    <div class="flex items-center justify-between bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-2.5 mb-4">
        <div class="flex gap-2">
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm shadow-sky-500/30">
                <i class="fas fa-save"></i> Guardar
            </button>
            <a href="<?= $backUrl ?>" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                Cancelar
            </a>
        </div>
    </div>

    <!-- Document card -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h2 class="text-3xl font-bold text-gray-900 mb-6"><?= $title ?></h2>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-16">
            <div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Código</label>
                    <input type="text" name="codigo" value="<?= View::e($proveedor->codigo ?? '') ?>"
                           placeholder="Dejar vacío para generar automático (<?= View::e($nuevo_codigo ?? '') ?>)"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Nombre *</label>
                    <input type="text" name="nombre" value="<?= View::e($proveedor->nombre ?? '') ?>" required
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">RUC</label>
                    <input type="text" name="ruc" value="<?= View::e($proveedor->ruc ?? '') ?>"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Contacto</label>
                    <input type="text" name="contacto" value="<?= View::e($proveedor->contacto ?? '') ?>"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
            </div>
            <div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Teléfono</label>
                    <input type="text" name="telefono" value="<?= View::e($proveedor->telefono ?? '') ?>"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Email</label>
                    <input type="email" name="email" value="<?= View::e($proveedor->email ?? '') ?>"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Dirección</label>
                    <textarea name="direccion" rows="2"
                              class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none resize-none"><?= View::e($proveedor->direccion ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>
</form>

<?php View::endSection('content'); ?>

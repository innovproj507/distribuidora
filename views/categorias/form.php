<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<?php
$isEdit = isset($categoria->categoria_id);
$formAction = $action ?? '/inventario/categorias/nueva';
$title = $isEdit ? View::e($categoria->nombre ?? 'Editar Categoría') : 'Nueva Categoría';
?>

<!-- Breadcrumb -->
<div class="flex items-center gap-2 text-sm text-gray-400 mb-3">
    <a href="/inventario/categorias" class="hover:text-gray-600 transition-colors">Categorías</a>
    <i class="fas fa-chevron-right text-xs"></i>
    <span class="text-gray-700 font-medium"><?= $title ?></span>
</div>

<form action="<?= $formAction ?>" method="POST">
    <?= View::csrf() ?>

    <!-- Action bar -->
    <div class="flex items-center justify-between bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-2.5 mb-4">
        <div class="flex gap-2">
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm shadow-sky-500/30">
                <i class="fas fa-save"></i> Guardar
            </button>
            <a href="/inventario/categorias" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                Cancelar
            </a>
        </div>
    </div>

    <!-- Document card -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h2 class="text-3xl font-bold text-gray-900 mb-6"><?= $title ?></h2>

        <div class="max-w-lg">
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Nombre *</label>
                <input type="text" name="nombre" value="<?= View::e($categoria->nombre ?? '') ?>" required
                       class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Categoría Padre</label>
                <select name="padre_id" class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                    <option value="">Ninguna (raíz)</option>
                    <?php foreach (($padres ?? []) as $p): ?>
                        <option value="<?= $p->categoria_id ?>" <?= ($categoria->padre_id ?? '') == $p->categoria_id ? 'selected' : '' ?>>
                            <?= View::e($p->nombre) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Descripción</label>
                <textarea name="descripcion" rows="3"
                          class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none resize-none"><?= View::e($categoria->descripcion ?? '') ?></textarea>
            </div>
        </div>
    </div>
</form>

<?php View::endSection('content'); ?>

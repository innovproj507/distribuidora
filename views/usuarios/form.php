<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<?php
$isEdit = isset($usuario->id);
$formAction = $action ?? '/usuarios/nuevo';
$title = $isEdit ? View::e(trim(($usuario->first_name ?? '') . ' ' . ($usuario->last_name ?? '')) ?: $usuario->username) : 'Nuevo Usuario';
?>

<!-- Breadcrumb -->
<div class="flex items-center gap-2 text-sm text-gray-400 mb-3">
    <a href="/usuarios" class="hover:text-gray-600 transition-colors">Usuarios</a>
    <i class="fas fa-chevron-right text-xs"></i>
    <span class="text-gray-700 font-medium"><?= $title ?></span>
</div>

<form action="<?= $formAction ?>" method="POST">
    <?= View::csrf() ?>

    <!-- Action bar -->
    <div class="flex items-center justify-between bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-3 mb-4">
        <div class="flex gap-2">
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
                <i class="fas fa-save"></i> Guardar
            </button>
            <a href="/usuarios" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                Cancelar
            </a>
        </div>
        <?php if ($isEdit): ?>
        <span class="px-3 py-1.5 rounded-full text-xs font-bold text-white <?= ($usuario->is_active ?? true) ? 'bg-emerald-500' : 'bg-slate-400' ?>">
            <?= ($usuario->is_active ?? true) ? 'Activo' : 'Inactivo' ?>
        </span>
        <?php endif; ?>
    </div>

    <!-- Document card -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h2 class="text-3xl font-bold text-gray-900 mb-6"><?= $title ?></h2>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-16">
            <div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Usuario *</label>
                    <?php if ($isEdit): ?>
                    <input type="text" value="<?= View::e($usuario->username ?? '') ?>" readonly
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-100 text-gray-400 cursor-not-allowed outline-none">
                    <?php else: ?>
                    <input type="text" name="username" value="<?= View::e($usuario->username ?? '') ?>" required
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                    <?php endif; ?>
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Nombre</label>
                    <input type="text" name="first_name" value="<?= View::e($usuario->first_name ?? '') ?>"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Apellido</label>
                    <input type="text" name="last_name" value="<?= View::e($usuario->last_name ?? '') ?>"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Email</label>
                    <input type="email" name="email" value="<?= View::e($usuario->email ?? '') ?>"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
            </div>
            <div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">
                        <?= $isEdit ? 'Nueva Contraseña' : 'Contraseña *' ?>
                    </label>
                    <input type="password" name="password" <?= $isEdit ? '' : 'required' ?>
                           placeholder="<?= $isEdit ? 'Dejar vacío para no cambiar' : '' ?>"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Rol *</label>
                    <select name="rol" required class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                        <?php foreach ($roles ?? ['cajero','supervisor','gerente','auditor','superadmin'] as $r): ?>
                            <option value="<?= $r ?>" <?= ($usuario->rol ?? '') === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex gap-6 py-4">
                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-600 cursor-pointer">
                        <input type="checkbox" name="is_staff" value="1" <?= ($usuario->is_staff ?? false) ? 'checked' : '' ?> class="rounded border-gray-300 text-sky-600 focus:ring-sky-500">
                        Staff
                    </label>
                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-600 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" <?= ($usuario->is_active ?? true) ? 'checked' : '' ?> class="rounded border-gray-300 text-sky-600 focus:ring-sky-500">
                        Activo
                    </label>
                </div>
            </div>
        </div>
    </div>
</form>

<?php View::endSection('content'); ?>

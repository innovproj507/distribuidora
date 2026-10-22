<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>
<div class="max-w-lg mx-auto">
    <form action="/usuarios/perfil" method="POST" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
        <?= View::csrf() ?>
        <div class="text-center mb-4">
            <div class="mx-auto h-16 w-16 bg-blue-500 text-white rounded-full flex items-center justify-center text-2xl font-bold shadow-sm shadow-blue-500/30"><?= strtoupper(substr($auth['name'] ?? 'U', 0, 1)) ?></div>
            <h3 class="mt-2 font-semibold text-gray-800"><?= View::e($auth['name'] ?? '') ?></h3>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label><input type="text" name="first_name" value="<?= View::e($usuario->first_name ?? '') ?>" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Apellido</label><input type="text" name="last_name" value="<?= View::e($usuario->last_name ?? '') ?>" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
        </div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Email</label><input type="email" name="email" value="<?= View::e($usuario->email ?? '') ?>" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
        <hr>
        <p class="text-sm text-gray-500">Cambiar contraseña (opcional)</p>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Contraseña Actual</label><input type="password" name="current_password" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Nueva Contraseña</label><input type="password" name="new_password" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
        <div class="flex justify-end gap-3 border-t pt-4">
            <a href="/" class="px-4 py-2 border rounded-lg text-sm text-gray-600 hover:bg-gray-50">Cancelar</a>
            <button type="submit" class="px-6 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 shadow-sm"><i class="fas fa-save mr-1"></i> Actualizar Perfil</button>
        </div>
    </form>
</div>
<?php View::endSection('content'); ?>

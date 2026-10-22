<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<!-- Filtros + acciones -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-4">
    <form method="GET" class="flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-56">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Buscar</label>
            <div class="relative">
                <i class="fas fa-search absolute left-0 top-2 text-gray-400 text-xs"></i>
                <input type="text" name="buscar" value="<?= View::e($buscar ?? '') ?>" placeholder="Buscar usuario..."
                       class="w-full pl-5 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
            </div>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
                <i class="fas fa-filter"></i> Filtrar
            </button>
            <a href="/usuarios/nuevo" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50 transition">
                <i class="fas fa-user-plus"></i> Nuevo Usuario
            </a>
        </div>
    </form>
</div>

<!-- Tabla -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                <th class="px-5 py-3.5 bg-gray-50/80">Usuario</th>
                <th class="px-5 py-3.5 bg-gray-50/80">Nombre</th>
                <th class="px-5 py-3.5 bg-gray-50/80">Email</th>
                <th class="px-5 py-3.5 bg-gray-50/80">Staff</th>
                <th class="px-5 py-3.5 bg-gray-50/80">Estado</th>
                <th class="px-5 py-3.5 bg-gray-50/80">Último Acceso</th>
                <th class="px-5 py-3.5 bg-gray-50/80 text-center">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            <?php foreach (($usuarios ?? []) as $u): ?>
            <tr class="hover:bg-sky-50/40 transition-colors">
                <td class="px-5 py-3.5">
                    <div class="flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-sky-500 text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-sm shadow-sky-500/30">
                            <?= strtoupper(substr($u['username'], 0, 1)) ?>
                        </span>
                        <span class="font-bold text-gray-800"><?= View::e($u['username']) ?></span>
                    </div>
                </td>
                <td class="px-5 py-3.5 text-gray-600"><?= View::e(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))) ?: '—' ?></td>
                <td class="px-5 py-3.5 text-gray-500 text-xs"><?= View::e($u['email'] ?? '—') ?></td>
                <td class="px-5 py-3.5">
                    <?php if ($u['is_staff']): ?>
                        <i class="fas fa-shield-alt text-sky-500" title="Staff"></i>
                    <?php else: ?>
                        <i class="fas fa-user text-gray-300"></i>
                    <?php endif; ?>
                </td>
                <td class="px-5 py-3.5">
                    <span class="px-2.5 py-1 rounded-full text-xs font-bold text-white <?= $u['is_active'] ? 'bg-emerald-500' : 'bg-red-500' ?>">
                        <?= $u['is_active'] ? 'Activo' : 'Inactivo' ?>
                    </span>
                </td>
                <td class="px-5 py-3.5 text-gray-400 text-xs">
                    <?= !empty($u['last_login']) ? date('d/m/Y H:i', strtotime($u['last_login'])) : 'Nunca' ?>
                </td>
                <td class="px-5 py-3.5 text-center">
                    <a href="/usuarios/<?= $u['id'] ?>/editar" title="Editar"
                       class="inline-flex w-8 h-8 rounded-lg bg-amber-500 text-white hover:bg-amber-600 transition-colors items-center justify-center shadow-sm shadow-amber-500/30">
                        <i class="fas fa-pen text-xs"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($usuarios)): ?>
            <tr><td colspan="7" class="px-5 py-10 text-center text-sm text-gray-400">No hay usuarios registrados.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php View::include('partials.pagination', ['pagination' => $pagination ?? []]); ?>
</div>

<?php View::endSection('content'); ?>

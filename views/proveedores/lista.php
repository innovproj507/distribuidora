<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<?php $s = $stats ?? []; $proveedores = $proveedores ?? []; $pagination = $pagination ?? []; ?>

<!-- Tarjetas de métricas -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-sky-500 to-sky-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Total</p>
                <h3 class="text-xl font-extrabold mt-1"><?= (int)($s['total'] ?? 0) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-truck"></i></span>
        </div>
    </div>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-emerald-500 to-emerald-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Activos</p>
                <h3 class="text-xl font-extrabold mt-1"><?= (int)($s['activos'] ?? 0) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-circle-check"></i></span>
        </div>
    </div>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-gray-400 to-gray-500 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Inactivos</p>
                <h3 class="text-xl font-extrabold mt-1"><?= (int)($s['inactivos'] ?? 0) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-circle-xmark"></i></span>
        </div>
    </div>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-orange-500 to-orange-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Con compras</p>
                <h3 class="text-xl font-extrabold mt-1"><?= (int)($s['con_compras'] ?? 0) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-file-invoice-dollar"></i></span>
        </div>
    </div>
</div>

<!-- Barra de filtros -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-3 mb-4">
    <form method="GET" id="search-form" class="flex flex-wrap items-end gap-3">

        <div class="flex-1 min-w-48">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Buscar</label>
            <div class="relative">
                <i class="fas fa-search absolute left-0 top-2 text-gray-400 text-xs"></i>
                <input type="text" name="buscar" id="search-input"
                    value="<?= View::e($buscar ?? '') ?>"
                    placeholder="Nombre, código, RUC..."
                    class="w-full pl-5 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                <div id="search-spinner" class="absolute right-0 top-2 hidden">
                    <i class="fas fa-spinner fa-spin text-sky-500 text-xs"></i>
                </div>
            </div>
        </div>

        <div class="min-w-28">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Por página</label>
            <select name="por_pagina" onchange="this.form.submit()"
                class="w-full py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                <?php foreach ([10, 25, 50, 100] as $n): ?>
                <option value="<?= $n ?>" <?= (($pagination['per_page'] ?? 25) == $n) ? 'selected' : '' ?>><?= $n ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex gap-2">
            <?php if (!empty($buscar)): ?>
            <a href="/inventario/proveedores"
                class="inline-flex items-center gap-1 px-3 py-2 border border-gray-200 text-gray-400 rounded-lg text-sm hover:bg-gray-50 transition"
                title="Limpiar búsqueda">
                <i class="fas fa-times"></i>
            </a>
            <?php endif; ?>
            <a href="/inventario/proveedores/nuevo"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
                <i class="fas fa-plus"></i> Nuevo Proveedor
            </a>
        </div>
    </form>
</div>

<!-- Tabla -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">

    <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
        <span class="text-xs font-medium text-gray-500">
            <?= number_format($pagination['total'] ?? 0) ?> proveedor(es)
            <?php if (!empty($buscar)): ?>
            <span class="ml-1 text-sky-500">— filtrado</span>
            <?php endif; ?>
        </span>
        <span class="text-xs text-gray-400">
            Pág. <?= $pagination['current_page'] ?? 1 ?> / <?= $pagination['total_pages'] ?? 1 ?>
        </span>
    </div>

    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-100 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">
                <th class="px-4 py-3 bg-white">Código</th>
                <th class="px-4 py-3 bg-white">Nombre</th>
                <th class="px-4 py-3 bg-white">Contacto</th>
                <th class="px-4 py-3 bg-white">Teléfono</th>
                <th class="px-4 py-3 bg-white">Email</th>
                <th class="px-4 py-3 bg-white text-center">Estado</th>
                <th class="px-4 py-3 bg-white text-center">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            <?php foreach ($proveedores as $prov): ?>
            <tr class="hover:bg-sky-50/40 transition-colors group">
                <td class="px-4 py-3 font-mono text-xs text-gray-400"><?= View::e($prov['codigo'] ?? '—') ?></td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-sky-500 text-white flex items-center justify-center shrink-0 shadow-sm shadow-sky-500/30">
                            <i class="fas fa-truck text-xs"></i>
                        </div>
                        <span class="font-semibold text-gray-800 group-hover:text-sky-700 transition-colors">
                            <?= View::e($prov['nombre']) ?>
                        </span>
                    </div>
                </td>
                <td class="px-4 py-3 text-gray-500 text-xs"><?= View::e($prov['contacto'] ?? '—') ?></td>
                <td class="px-4 py-3 text-gray-500 text-xs"><?= View::e($prov['telefono'] ?? '—') ?></td>
                <td class="px-4 py-3 text-gray-500 text-xs"><?= View::e($prov['email'] ?? '—') ?></td>
                <td class="px-4 py-3 text-center">
                    <?php $activo = ($prov['estado'] ?? 'activo') === 'activo'; ?>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold
                        <?= $activo ? 'bg-emerald-500 text-white' : 'bg-gray-300 text-white' ?>">
                        <?= $activo ? 'Activo' : 'Inactivo' ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-center">
                    <div class="flex items-center justify-center gap-1.5 opacity-70 group-hover:opacity-100 transition-opacity">
                        <a href="/inventario/proveedores/<?= $prov['proveedor_id'] ?>/evaluaciones"
                            class="w-8 h-8 rounded-lg bg-amber-500 text-white hover:bg-amber-600 transition-colors flex items-center justify-center shadow-sm shadow-amber-500/30" title="Evaluaciones">
                            <i class="fas fa-star text-xs"></i>
                        </a>
                        <a href="/inventario/proveedores/<?= $prov['proveedor_id'] ?>/editar"
                            class="w-8 h-8 rounded-lg bg-blue-500 text-white hover:bg-blue-600 transition-colors flex items-center justify-center shadow-sm shadow-blue-500/30" title="Editar">
                            <i class="fas fa-pen text-xs"></i>
                        </a>
                        <form action="/inventario/proveedores/<?= $prov['proveedor_id'] ?>/eliminar" method="POST"
                            class="inline" onsubmit="return confirm('¿Eliminar el proveedor «<?= View::e(addslashes($prov['nombre'])) ?>»?')">
                            <?= View::csrf() ?>
                            <button type="submit" class="w-8 h-8 rounded-lg bg-red-500 text-white hover:bg-red-600 transition-colors flex items-center justify-center shadow-sm shadow-red-500/30" title="Eliminar">
                                <i class="fas fa-trash text-xs"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>

            <?php if (empty($proveedores)): ?>
            <tr>
                <td colspan="7" class="px-4 py-16 text-center">
                    <div class="inline-flex flex-col items-center gap-2">
                        <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center">
                            <i class="fas fa-truck text-gray-300 text-2xl"></i>
                        </div>
                        <p class="text-sm font-medium text-gray-400">No se encontraron proveedores</p>
                        <?php if (empty($buscar)): ?>
                        <a href="/inventario/proveedores/nuevo"
                            class="mt-1 text-sky-500 hover:underline text-sm font-medium">
                            Registrar el primer proveedor
                        </a>
                        <?php else: ?>
                        <a href="/inventario/proveedores" class="mt-1 text-gray-400 hover:text-sky-500 text-xs">
                            Limpiar búsqueda
                        </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if (($pagination['total_pages'] ?? 1) > 1): ?>
<div class="mt-4">
    <?php View::include('partials.pagination', ['pagination' => $pagination]); ?>
</div>
<?php endif; ?>

<?php View::endSection('content'); ?>

<?php View::section('extra_js'); ?>
<script>
    var searchTimeout;
    var searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            var spinner = document.getElementById('search-spinner');
            if (e.target.value.length >= 2 || e.target.value.length === 0) {
                spinner.classList.remove('hidden');
                searchTimeout = setTimeout(function() {
                    document.getElementById('search-form').submit();
                }, 500);
            }
        });
    }
</script>
<?php View::endSection('extra_js'); ?>

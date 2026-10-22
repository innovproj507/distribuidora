<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<?php $s = $stats ?? []; ?>

<!-- Tarjetas de métricas -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-sky-500 to-sky-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Total</p>
                <h3 class="text-xl font-extrabold mt-1"><?= (int)($s['total'] ?? 0) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-tags"></i></span>
        </div>
    </div>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-red-500 to-red-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Raíz</p>
                <h3 class="text-xl font-extrabold mt-1"><?= (int)($s['raiz'] ?? 0) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-sitemap"></i></span>
        </div>
    </div>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-amber-500 to-amber-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Subcategorías</p>
                <h3 class="text-xl font-extrabold mt-1"><?= (int)($s['subcategorias'] ?? 0) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-code-branch"></i></span>
        </div>
    </div>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-emerald-500 to-emerald-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Con productos</p>
                <h3 class="text-xl font-extrabold mt-1"><?= (int)($s['con_productos'] ?? 0) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-box"></i></span>
        </div>
    </div>
</div>

<!-- Barra de filtros -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-3 mb-4">
    <form method="get" id="search-form" class="flex flex-wrap items-end gap-3">

        <div class="flex-1 min-w-48">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Buscar</label>
            <div class="relative">
                <i class="fas fa-search absolute left-0 top-2 text-gray-400 text-xs"></i>
                <input type="text" name="buscar" id="search-input"
                    value="<?= View::e($_GET['buscar'] ?? '') ?>"
                    placeholder="Nombre o descripción..."
                    class="w-full pl-5 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                <div id="search-spinner" class="absolute right-0 top-2 hidden">
                    <i class="fas fa-spinner fa-spin text-sky-500 text-xs"></i>
                </div>
            </div>
        </div>

        <div class="min-w-44">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Categoría padre</label>
            <select name="padre_id" onchange="this.form.submit()"
                class="w-full py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                <option value="">Todas</option>
                <option value="0" <?= (($_GET['padre_id'] ?? '') === '0') ? 'selected' : '' ?>>Sin padre (raíz)</option>
                <?php foreach (($todasCategorias ?? []) as $cat): ?>
                <option value="<?= $cat['categoria_id'] ?>"
                    <?= (($_GET['padre_id'] ?? '') == $cat['categoria_id']) ? 'selected' : '' ?>>
                    <?= View::e($cat['nombre']) ?>
                </option>
                <?php endforeach; ?>
            </select>
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
            <?php if (!empty($_GET['buscar']) || (isset($_GET['padre_id']) && $_GET['padre_id'] !== '')): ?>
            <a href="/inventario/categorias"
                class="inline-flex items-center gap-1 px-3 py-2 border border-gray-200 text-gray-400 rounded-lg text-sm hover:bg-gray-50 transition"
                title="Limpiar filtros">
                <i class="fas fa-times"></i>
            </a>
            <?php endif; ?>
            <a href="/inventario/categorias/nueva"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
                <i class="fas fa-plus"></i> Nueva Categoría
            </a>
        </div>
    </form>
</div>

<!-- Tabla -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">

    <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
        <span class="text-xs font-medium text-gray-500">
            <?= number_format($pagination['total'] ?? 0) ?> resultado(s)
            <?php if (!empty($_GET['buscar']) || (isset($_GET['padre_id']) && $_GET['padre_id'] !== '')): ?>
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
                <th class="px-4 py-3 bg-white">Categoría</th>
                <th class="px-4 py-3 bg-white">Descripción</th>
                <th class="px-4 py-3 bg-white">Padre</th>
                <th class="px-4 py-3 bg-white text-center">Productos</th>
                <th class="px-4 py-3 bg-white text-center">Sub.</th>
                <th class="px-4 py-3 bg-white text-center">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            <?php foreach (($categorias ?? []) as $cat): ?>
            <?php
                $totalProd  = (int)($cat['total_productos'] ?? 0);
                $totalSubs  = (int)($cat['total_subcategorias'] ?? 0);
                $esPadre    = empty($cat['padre_id']);
            ?>
            <tr class="hover:bg-sky-50/40 transition-colors group">

                <!-- Nombre con ícono -->
                <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 text-white shadow-sm
                            <?= $esPadre ? 'bg-red-500 shadow-red-500/30' : 'bg-sky-500 shadow-sky-500/30' ?>">
                            <i class="fas <?= $esPadre ? 'fa-sitemap' : 'fa-tag' ?> text-xs"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800 group-hover:text-sky-700 transition-colors">
                                <?= View::e($cat['nombre']) ?>
                            </p>
                            <?php if ($esPadre): ?>
                            <span class="text-xs text-red-500 font-medium">Categoría raíz</span>
                            <?php else: ?>
                            <span class="text-xs text-gray-400">Nivel <?= (int)($cat['nivel'] ?? 2) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>

                <!-- Descripción -->
                <td class="px-4 py-3 text-gray-500 text-xs max-w-xs">
                    <?php if (!empty($cat['descripcion'])): ?>
                        <span class="line-clamp-2"><?= View::e($cat['descripcion']) ?></span>
                    <?php else: ?>
                        <span class="text-gray-300">—</span>
                    <?php endif; ?>
                </td>

                <!-- Padre -->
                <td class="px-4 py-3">
                    <?php if (!empty($cat['padre_nombre'])): ?>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-500 text-white text-xs font-medium">
                        <i class="fas fa-level-up-alt" style="font-size:9px"></i>
                        <?= View::e($cat['padre_nombre']) ?>
                    </span>
                    <?php else: ?>
                    <span class="text-gray-300 text-xs">—</span>
                    <?php endif; ?>
                </td>

                <!-- Productos -->
                <td class="px-4 py-3 text-center">
                    <?php if ($totalProd > 0): ?>
                    <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-500 text-white min-w-[2rem]">
                        <?= $totalProd ?>
                    </span>
                    <?php else: ?>
                    <span class="text-gray-300 text-xs">0</span>
                    <?php endif; ?>
                </td>

                <!-- Subcategorías -->
                <td class="px-4 py-3 text-center">
                    <?php if ($totalSubs > 0): ?>
                    <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-semibold bg-sky-500 text-white min-w-[2rem]">
                        <?= $totalSubs ?>
                    </span>
                    <?php else: ?>
                    <span class="text-gray-300 text-xs">—</span>
                    <?php endif; ?>
                </td>

                <!-- Acciones -->
                <td class="px-4 py-3 text-center">
                    <div class="flex items-center justify-center gap-1.5 opacity-70 group-hover:opacity-100 transition-opacity">
                        <a href="/inventario/categorias/<?= $cat['categoria_id'] ?>/editar"
                            class="w-8 h-8 rounded-lg bg-amber-500 text-white hover:bg-amber-600 transition-colors flex items-center justify-center shadow-sm shadow-amber-500/30" title="Editar">
                            <i class="fas fa-pen text-xs"></i>
                        </a>
                        <form action="/inventario/categorias/<?= $cat['categoria_id'] ?>/eliminar"
                            method="POST" class="inline"
                            onsubmit="return confirm('¿Eliminar «<?= View::e(addslashes($cat['nombre'])) ?>»?<?= ($totalProd > 0 || $totalSubs > 0) ? '\n\nTiene productos o subcategorías asociadas.' : '' ?>')">
                            <?= View::csrf() ?>
                            <button type="submit"
                                class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors <?= ($totalProd > 0 || $totalSubs > 0) ? 'bg-gray-100 text-gray-300 cursor-not-allowed' : 'bg-red-500 text-white hover:bg-red-600 shadow-sm shadow-red-500/30' ?>"
                                title="<?= ($totalProd > 0 || $totalSubs > 0) ? 'Tiene elementos asociados' : 'Eliminar' ?>">
                                <i class="fas fa-trash text-xs"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>

            <?php if (empty($categorias)): ?>
            <tr>
                <td colspan="6" class="px-4 py-16 text-center">
                    <div class="inline-flex flex-col items-center gap-2">
                        <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center">
                            <i class="fas fa-tags text-gray-300 text-2xl"></i>
                        </div>
                        <p class="text-sm font-medium text-gray-400">No se encontraron categorías</p>
                        <?php if (empty($_GET['buscar']) && empty($_GET['padre_id'])): ?>
                        <a href="/inventario/categorias/nueva"
                            class="mt-1 text-sky-500 hover:underline text-sm font-medium">
                            Crear la primera categoría
                        </a>
                        <?php else: ?>
                        <a href="/inventario/categorias" class="mt-1 text-gray-400 hover:text-sky-500 text-xs">
                            Limpiar filtros
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
    <?php View::include('partials.pagination', ['pagination' => $pagination ?? []]); ?>
</div>
<?php endif; ?>

<?php View::endSection('content'); ?>

<?php View::section('extra_js'); ?>
<script>
    let searchTimeout;
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function (e) {
            clearTimeout(searchTimeout);
            const spinner = document.getElementById('search-spinner');
            if (e.target.value.length >= 2 || e.target.value.length === 0) {
                spinner.classList.remove('hidden');
                searchTimeout = setTimeout(() => {
                    document.getElementById('search-form').submit();
                }, 500);
            }
        });
    }
</script>
<?php View::endSection('extra_js'); ?>

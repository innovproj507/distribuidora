<?php $this->extend('layouts/app'); ?>
<?php $this->section('content'); ?>

<div x-data="{ filtro: '', categoria: 'todas' }">

    <!-- Barra de filtros -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-6 flex flex-col sm:flex-row gap-3 items-start sm:items-center">
        <div class="relative flex-1 min-w-0">
            <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-sm"></i>
            <input type="text" x-model="filtro" placeholder="Buscar aplicación..."
                class="w-full pl-9 pr-4 py-2 text-sm rounded-lg border border-gray-200 focus:outline-none focus:ring-1 focus:ring-sky-500">
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <button @click="categoria = 'todas'"
                :class="categoria === 'todas' ? 'bg-sky-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors">
                Todas
            </button>
            <?php foreach (array_keys($categorias) as $cat): ?>
            <button @click="categoria = '<?= esc($cat) ?>'"
                :class="categoria === '<?= esc($cat) ?>' ? 'bg-sky-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors">
                <?= esc($cat) ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Grid de módulos -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <?php foreach ($modules as $mod): ?>
        <?php
            $name        = $mod['name'] ?? '';
            $label       = $mod['label'] ?? $name;
            $description = $mod['description'] ?? '';
            $icon        = $mod['icon'] ?? 'fas fa-cube';
            $hex         = $mod['hex'] ?? '#64748b';
            $category    = $mod['category'] ?? 'General';
            $version     = $mod['version'] ?? '1.0.0';
            $instalado   = $mod['instalado'] ?? false;
            $isCore      = $name === 'core';
            $depends     = array_filter($mod['depends'] ?? [], fn($d) => $d !== 'core');
        ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden flex flex-col transition hover:shadow-md"
             x-show="
                (filtro === '' || '<?= strtolower(esc($label)) ?> <?= strtolower(esc($description)) ?>'.includes(filtro.toLowerCase()))
                && (categoria === 'todas' || categoria === '<?= esc($category) ?>')
             ">

            <!-- Header coloreado -->
            <div class="h-24 flex items-center justify-center relative"
                 style="background: linear-gradient(135deg, <?= esc($hex) ?>22 0%, <?= esc($hex) ?>44 100%);">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center shadow-lg"
                     style="background-color: <?= esc($hex) ?>;">
                    <i class="<?= esc($icon) ?> text-white text-2xl"></i>
                </div>
                <?php if ($instalado): ?>
                <span class="absolute top-2 right-2 bg-emerald-500 text-white text-[0.6rem] font-bold px-2 py-0.5 rounded-full uppercase tracking-wide">
                    Instalado
                </span>
                <?php endif; ?>
                <?php if ($isCore): ?>
                <span class="absolute top-2 left-2 bg-slate-600 text-white text-[0.6rem] font-bold px-2 py-0.5 rounded-full uppercase tracking-wide">
                    Sistema
                </span>
                <?php endif; ?>
            </div>

            <!-- Contenido -->
            <div class="p-4 flex-1 flex flex-col">
                <div class="flex items-start justify-between mb-1">
                    <h3 class="text-sm font-bold text-gray-800"><?= esc($label) ?></h3>
                    <span class="text-[0.6rem] text-gray-400 font-mono ml-2 shrink-0">v<?= esc($version) ?></span>
                </div>
                <span class="inline-block text-[0.6rem] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded mb-2"
                      style="color: <?= esc($hex) ?>; background-color: <?= esc($hex) ?>18;">
                    <?= esc($category) ?>
                </span>
                <p class="text-xs text-gray-500 flex-1 leading-relaxed"><?= esc($description) ?></p>

                <?php if (!empty($depends)): ?>
                <div class="mt-2 text-[0.65rem] text-gray-400">
                    <i class="fas fa-link mr-1"></i>
                    Requiere: <?= esc(implode(', ', $depends)) ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Acción -->
            <div class="px-4 pb-4">
                <?php if ($isCore): ?>
                <div class="w-full py-2 text-center text-xs font-semibold text-slate-400 bg-slate-50 rounded-lg">
                    <i class="fas fa-lock mr-1"></i> Módulo base del sistema
                </div>
                <?php elseif ($instalado): ?>
                <form method="post" action="/apps/desinstalar"
                      onsubmit="return confirm('¿Desinstalar el módulo <?= esc($label) ?>? Los datos NO se eliminarán, pero las funciones dejarán de estar disponibles.')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="module" value="<?= esc($name) ?>">
                    <button type="submit"
                        class="w-full py-2 text-xs font-semibold rounded-lg border border-rose-200 text-rose-500 hover:bg-rose-50 transition-colors">
                        <i class="fas fa-minus-circle mr-1"></i> Desinstalar
                    </button>
                </form>
                <?php else: ?>
                <form method="post" action="/apps/instalar">
                    <?= csrf_field() ?>
                    <input type="hidden" name="module" value="<?= esc($name) ?>">
                    <button type="submit"
                        class="w-full py-2 text-xs font-semibold rounded-lg text-white transition-colors"
                        style="background-color: <?= esc($hex) ?>; hover:opacity-90;">
                        <i class="fas fa-plus-circle mr-1"></i> Instalar
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</div>

<?php $this->endSection(); ?>

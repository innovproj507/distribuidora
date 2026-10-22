<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<!-- Filter bar -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-4">
    <form method="GET" action="/bitacora" class="flex flex-wrap items-end gap-3">
        <div class="min-w-40">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Módulo</label>
            <select name="modulo" class="w-full py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                <option value="">Todos</option>
                <?php foreach ($modulos as $mod): ?>
                    <option value="<?= View::e($mod) ?>" <?= $filtros['modulo'] === $mod ? 'selected' : '' ?>>
                        <?= View::e(ucfirst($mod)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex-1 min-w-40">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Usuario</label>
            <input type="text" name="usuario" value="<?= View::e($filtros['usuario']) ?>" placeholder="Nombre de usuario"
                   class="w-full py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
        </div>
        <div class="min-w-36">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Desde</label>
            <input type="date" name="desde" value="<?= View::e($filtros['desde']) ?>"
                   class="w-full py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
        </div>
        <div class="min-w-36">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Hasta</label>
            <input type="date" name="hasta" value="<?= View::e($filtros['hasta']) ?>"
                   class="w-full py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
                <i class="fas fa-filter"></i> Filtrar
            </button>
            <a href="/bitacora" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                Limpiar
            </a>
        </div>
    </form>
</div>

<!-- Table -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-50">
        <span class="text-xs text-gray-400"><?= number_format($total) ?> eventos</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                    <th class="px-5 py-3.5 bg-gray-50/80">Fecha</th>
                    <th class="px-5 py-3.5 bg-gray-50/80">Usuario</th>
                    <th class="px-5 py-3.5 bg-gray-50/80">Módulo</th>
                    <th class="px-5 py-3.5 bg-gray-50/80">Acción</th>
                    <th class="px-5 py-3.5 bg-gray-50/80">Descripción</th>
                    <th class="px-5 py-3.5 bg-gray-50/80">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-gray-400">
                        <i class="fas fa-history text-3xl mb-2 block text-gray-200"></i>
                        No hay eventos registrados
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <?php
                            $moduloColors = [
                                'auth'        => 'bg-blue-500',
                                'ventas'      => 'bg-emerald-500',
                                'compras'     => 'bg-orange-500',
                                'productos'   => 'bg-sky-500',
                                'inventario'  => 'bg-teal-500',
                                'usuarios'    => 'bg-rose-500',
                                'conteos'     => 'bg-amber-500',
                            ];
                            $color = $moduloColors[$log['modulo']] ?? 'bg-gray-400';
                        ?>
                        <tr class="hover:bg-sky-50/40 transition-colors">
                            <td class="px-5 py-3.5 text-gray-400 text-xs whitespace-nowrap">
                                <?= date('d/m/Y H:i', strtotime($log['fecha_registro'])) ?>
                            </td>
                            <td class="px-5 py-3.5 font-bold text-gray-800">
                                <?= View::e($log['username'] ?? '—') ?>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold text-white <?= $color ?>">
                                    <?= View::e(ucfirst($log['modulo'])) ?>
                                </span>
                            </td>
                            <td class="px-5 py-3.5 font-mono text-xs text-gray-600">
                                <?= View::e($log['accion']) ?>
                            </td>
                            <td class="px-5 py-3.5 text-gray-600 max-w-xs truncate" title="<?= View::e($log['descripcion']) ?>">
                                <?= View::e($log['descripcion']) ?>
                            </td>
                            <td class="px-5 py-3.5 font-mono text-xs text-gray-400">
                                <?= View::e($log['ip'] ?? '') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_paginas > 1): ?>
    <div class="px-4 py-3 border-t border-gray-50 flex items-center justify-between">
        <span class="text-xs text-gray-400">Página <?= $pagina ?> de <?= $total_paginas ?></span>
        <div class="flex gap-1">
            <?php if ($pagina > 1): ?>
                <a href="?<?= http_build_query(array_merge($filtros, ['page' => $pagina - 1])) ?>"
                   class="px-3 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-xs">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php endif; ?>
            <?php for ($p = max(1, $pagina - 2); $p <= min($total_paginas, $pagina + 2); $p++): ?>
                <a href="?<?= http_build_query(array_merge($filtros, ['page' => $p])) ?>"
                   class="px-3 py-1 rounded-lg text-xs <?= $p === $pagina ? 'bg-sky-500 text-white' : 'bg-gray-100 hover:bg-gray-200' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
            <?php if ($pagina < $total_paginas): ?>
                <a href="?<?= http_build_query(array_merge($filtros, ['page' => $pagina + 1])) ?>"
                   class="px-3 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-xs">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php View::endSection('content'); ?>

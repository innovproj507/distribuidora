<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<?php $s = $stats ?? []; $compras = $compras ?? []; $pagination = $pagination ?? []; ?>

<!-- Tarjetas de métricas -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-sky-500 to-sky-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Total OC</p>
                <h3 class="text-xl font-extrabold mt-1"><?= (int)($s['total'] ?? 0) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-clipboard-list"></i></span>
        </div>
    </div>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-amber-500 to-amber-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Pendientes</p>
                <h3 class="text-xl font-extrabold mt-1"><?= (int)($s['pendientes'] ?? 0) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-hourglass-half"></i></span>
        </div>
    </div>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-emerald-500 to-emerald-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Recibidas</p>
                <h3 class="text-xl font-extrabold mt-1"><?= (int)($s['recibidas'] ?? 0) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-check-circle"></i></span>
        </div>
    </div>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-orange-500 to-orange-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Monto recibido</p>
                <h3 class="text-xl font-extrabold mt-1 truncate">$<?= number_format((float)($s['monto_recibido'] ?? 0), 2) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-sack-dollar"></i></span>
        </div>
    </div>
</div>

<!-- Barra de búsqueda -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-4">
    <form method="GET" id="search-form" class="flex flex-wrap items-end gap-3">

        <div class="flex-1 min-w-48">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Buscar</label>
            <div class="relative">
                <i class="fas fa-search absolute left-0 top-2 text-gray-400 text-xs"></i>
                <input type="text" name="buscar" id="search-input"
                    value="<?= View::e($buscar ?? '') ?>"
                    placeholder="N° factura o proveedor..."
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

        <!-- Contador de resultados + acciones -->
        <div class="flex items-center gap-2 ml-auto">
            <span class="text-xs font-medium text-gray-400 whitespace-nowrap">
                <?= number_format($pagination['total'] ?? 0) ?> orden(es)
                <?php if (!empty($buscar)): ?>
                <span class="text-sky-500">— filtrado</span>
                <?php endif; ?>
            </span>
            <?php if (!empty($buscar)): ?>
            <a href="/compras"
                class="inline-flex items-center gap-1 px-3 py-2 border border-gray-200 text-gray-400 rounded-lg text-sm hover:bg-gray-50 transition"
                title="Limpiar búsqueda">
                <i class="fas fa-times"></i>
            </a>
            <?php endif; ?>
            <a href="/compras/nueva"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
                <i class="fas fa-plus"></i> Nueva Compra
            </a>
        </div>
    </form>
</div>

<!-- Tabla -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">

    <div class="px-5 py-3.5 bg-gray-50/80 border-b border-gray-100 flex items-center justify-between">
        <span class="text-xs font-medium text-gray-500">
            Ordenado por fecha de OC — más reciente primero
        </span>
        <span class="text-xs text-gray-400">
            Pág. <?= $pagination['current_page'] ?? 1 ?> / <?= $pagination['total_pages'] ?? 1 ?>
        </span>
    </div>

    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-100 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                <th class="px-5 py-3.5 bg-gray-50/80">Factura #</th>
                <th class="px-5 py-3.5 bg-gray-50/80">Proveedor</th>
                <th class="px-5 py-3.5 bg-gray-50/80 text-right">Monto total</th>
                <th class="px-5 py-3.5 bg-gray-50/80 text-center">Estado</th>
                <th class="px-5 py-3.5 bg-gray-50/80">Fecha OC</th>
                <th class="px-5 py-3.5 bg-gray-50/80 text-center">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            <?php foreach ($compras as $compra): ?>
            <?php
                $badgeMap = [
                    'recibida'              => ['bg-emerald-500', 'Recibida'],
                    'parcialmente_recibida' => ['bg-blue-500',    'Parcial'],
                    'pendiente'             => ['bg-amber-500',   'Pendiente'],
                    'cancelada'             => ['bg-red-500',     'Cancelada'],
                ];
                [$badgeClass, $badgeLabel] = $badgeMap[$compra['estado']] ?? ['bg-gray-400', ucfirst($compra['estado'])];
            ?>
            <tr class="hover:bg-sky-50/40 transition-colors group">

                <!-- Factura -->
                <td class="px-5 py-3.5">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-sky-500 text-white flex items-center justify-center shrink-0 shadow-sm shadow-sky-500/30">
                            <i class="fas fa-file-invoice-dollar text-xs"></i>
                        </div>
                        <span class="font-semibold text-gray-800 group-hover:text-sky-700 transition-colors font-mono text-xs">
                            <?= View::e($compra['numero_factura']) ?>
                        </span>
                    </div>
                </td>

                <!-- Proveedor -->
                <td class="px-5 py-3.5 text-gray-600 text-sm"><?= View::e($compra['proveedor_nombre']) ?></td>

                <!-- Monto -->
                <td class="px-5 py-3.5 text-right font-bold text-gray-900">
                    $<?= number_format((float)$compra['monto_total'], 2) ?>
                </td>

                <!-- Estado -->
                <td class="px-5 py-3.5 text-center">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold text-white <?= $badgeClass ?>">
                        <?= $badgeLabel ?>
                    </span>
                </td>

                <!-- Fecha OC -->
                <td class="px-5 py-3.5 text-gray-500 text-xs whitespace-nowrap">
                    <?= date('d/m/Y', strtotime($compra['fecha_compra'] ?? $compra['fecha_registro'])) ?>
                </td>

                <!-- Acciones -->
                <td class="px-5 py-3.5 text-center">
                    <div class="flex items-center justify-center gap-1.5">
                        <?php if (\in_array($compra['estado'], ['pendiente', 'parcialmente_recibida'])): ?>
                        <a href="/compras/<?= $compra['compra_id'] ?>/recibir" title="Registrar recepción"
                           class="w-8 h-8 rounded-lg bg-blue-500 text-white hover:bg-blue-600 transition-colors flex items-center justify-center shadow-sm shadow-blue-500/30">
                            <i class="fas fa-truck-loading text-xs"></i>
                        </a>
                        <?php endif; ?>
                        <a href="/compras/<?= $compra['compra_id'] ?>" title="Ver detalles"
                           class="w-8 h-8 rounded-lg bg-sky-500 text-white hover:bg-sky-600 transition-colors flex items-center justify-center shadow-sm shadow-sky-500/30">
                            <i class="fas fa-eye text-xs"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>

            <?php if (empty($compras)): ?>
            <tr>
                <td colspan="6" class="px-4 py-16 text-center">
                    <div class="inline-flex flex-col items-center gap-2">
                        <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center">
                            <i class="fas fa-file-invoice-dollar text-gray-300 text-2xl"></i>
                        </div>
                        <p class="text-sm font-medium text-gray-400">No se encontraron órdenes de compra</p>
                        <?php if (empty($buscar)): ?>
                        <a href="/compras/nueva"
                            class="mt-1 text-sky-500 hover:underline text-sm font-medium">
                            Registrar primera compra
                        </a>
                        <?php else: ?>
                        <a href="/compras" class="mt-1 text-gray-400 hover:text-sky-500 text-xs">
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

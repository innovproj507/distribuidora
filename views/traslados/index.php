<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<?php $s = $stats ?? []; $traslados = $traslados ?? []; $pagination = $pagination ?? []; ?>

<!-- Tarjetas de métricas -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-sky-500 to-sky-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Total</p>
                <h3 class="text-xl font-extrabold mt-1"><?= (int)($s['total'] ?? 0) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-exchange-alt"></i></span>
        </div>
    </div>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-amber-500 to-amber-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">En tránsito</p>
                <h3 class="text-xl font-extrabold mt-1"><?= (int)($s['en_transito'] ?? 0) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-truck-moving"></i></span>
        </div>
    </div>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-emerald-500 to-emerald-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Recibidos</p>
                <h3 class="text-xl font-extrabold mt-1"><?= (int)($s['recibidos'] ?? 0) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-check-circle"></i></span>
        </div>
    </div>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-red-500 to-red-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Cancelados</p>
                <h3 class="text-xl font-extrabold mt-1"><?= (int)($s['cancelados'] ?? 0) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-ban"></i></span>
        </div>
    </div>
</div>

<!-- Barra de búsqueda -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-4">
    <form method="GET" id="search-form" class="flex flex-wrap items-end gap-3">

        <div class="flex-1 min-w-48">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Buscar por usuario</label>
            <div class="relative">
                <i class="fas fa-user absolute left-0 top-2 text-gray-400 text-xs"></i>
                <input type="text" name="buscar" id="search-input"
                    value="<?= View::e($buscar ?? '') ?>"
                    placeholder="Nombre de quien realiza el traslado..."
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

        <div class="flex items-center gap-2 ml-auto">
            <span class="text-xs font-medium text-gray-400 whitespace-nowrap">
                <?= number_format($pagination['total'] ?? 0) ?> traslado(s)
                <?php if (!empty($buscar)): ?>
                <span class="text-sky-500">— filtrado</span>
                <?php endif; ?>
            </span>
            <?php if (!empty($buscar)): ?>
            <a href="/inventario/traslados"
                class="inline-flex items-center gap-1 px-3 py-2 border border-gray-200 text-gray-400 rounded-lg text-sm hover:bg-gray-50 transition"
                title="Limpiar búsqueda">
                <i class="fas fa-times"></i>
            </a>
            <?php endif; ?>
            <a href="/inventario/traslados/nuevo"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
                <i class="fas fa-exchange-alt"></i> Nuevo Traslado
            </a>
        </div>
    </form>
</div>

<!-- Tabla -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden" x-data="trasladosList()">

    <div class="px-5 py-3.5 bg-gray-50/80 border-b border-gray-100 flex items-center justify-between">
        <span class="text-xs font-medium text-gray-500">Ordenado por más reciente</span>
        <span class="text-xs text-gray-400">
            Pág. <?= $pagination['current_page'] ?? 1 ?> / <?= $pagination['total_pages'] ?? 1 ?>
        </span>
    </div>

    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-100 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                <th class="px-5 py-3.5 bg-gray-50/80">#</th>
                <th class="px-5 py-3.5 bg-gray-50/80">Origen</th>
                <th class="px-5 py-3.5 bg-gray-50/80">Destino</th>
                <th class="px-5 py-3.5 bg-gray-50/80 text-center">Estado</th>
                <th class="px-5 py-3.5 bg-gray-50/80">Enviado por</th>
                <th class="px-5 py-3.5 bg-gray-50/80 text-center">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            <?php foreach ($traslados as $t): ?>
            <?php
                $badgeMap = [
                    'recibido'    => 'bg-emerald-500',
                    'en_transito' => 'bg-amber-500',
                    'borrador'    => 'bg-gray-400',
                    'cancelado'   => 'bg-red-500',
                ];
                $badgeClass = $badgeMap[$t['estado']] ?? 'bg-gray-400';
            ?>
            <tr class="hover:bg-sky-50/40 transition-colors group">

                <!-- # -->
                <td class="px-5 py-3.5">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-sky-500 text-white flex items-center justify-center shrink-0 shadow-sm shadow-sky-500/30">
                            <i class="fas fa-exchange-alt text-xs"></i>
                        </div>
                        <span class="font-mono text-xs text-gray-400">#<?= $t['traslado_id'] ?></span>
                    </div>
                </td>

                <!-- Origen -->
                <td class="px-5 py-3.5 font-semibold text-gray-700 text-sm">
                    <?= View::e($t['origen_nombre']) ?>
                </td>

                <!-- Destino -->
                <td class="px-5 py-3.5 text-gray-600 text-sm">
                    <div class="flex items-center gap-1.5">
                        <i class="fas fa-arrow-right text-gray-300 text-xs"></i>
                        <?= View::e($t['destino_nombre']) ?>
                    </div>
                </td>

                <!-- Estado -->
                <td class="px-5 py-3.5 text-center">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold text-white <?= $badgeClass ?>">
                        <?= ucfirst(str_replace('_', ' ', $t['estado'])) ?>
                    </span>
                </td>

                <!-- Enviado por -->
                <td class="px-5 py-3.5">
                    <p class="text-sm font-semibold text-gray-700"><?= View::e($t['usuario_envia_nombre']) ?></p>
                    <p class="text-xs text-gray-400 mt-0.5"><?= date('d/m/Y H:i', strtotime($t['fecha_envio'])) ?></p>
                </td>

                <!-- Acciones -->
                <td class="px-5 py-3.5 text-center">
                    <div class="flex items-center justify-center">
                        <?php if ($t['estado'] === 'en_transito'): ?>
                        <button @click="recibirTraslado(<?= $t['traslado_id'] ?>)"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-500 text-white rounded-lg text-xs font-bold hover:bg-emerald-600 transition shadow-sm shadow-emerald-500/30">
                            <i class="fas fa-check"></i> Confirmar
                        </button>
                        <?php else: ?>
                        <span class="text-xs text-gray-300">—</span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>

            <?php if (empty($traslados)): ?>
            <tr>
                <td colspan="6" class="px-4 py-16 text-center">
                    <div class="inline-flex flex-col items-center gap-2">
                        <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center">
                            <i class="fas fa-exchange-alt text-gray-300 text-2xl"></i>
                        </div>
                        <p class="text-sm font-medium text-gray-400">No se encontraron traslados</p>
                        <?php if (empty($buscar)): ?>
                        <a href="/inventario/traslados/nuevo"
                            class="mt-1 text-sky-500 hover:underline text-sm font-medium">
                            Crear el primer traslado
                        </a>
                        <?php else: ?>
                        <a href="/inventario/traslados" class="mt-1 text-gray-400 hover:text-sky-500 text-xs">
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
    /* ── Debounce de búsqueda ── */
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

    /* ── Alpine: confirmar recepción ── */
    function trasladosList() {
        return {
            async recibirTraslado(id) {
                if (!confirm('¿Confirmar recepción? El stock se actualizará en el depósito de destino.')) return;
                try {
                    const response = await fetch(`/inventario/traslados/${id}/recibir`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' }
                    });
                    const res = await response.json();
                    if (res.success) { window.location.reload(); }
                    else { alert('Error: ' + res.error); }
                } catch (error) {
                    alert('Ocurrió un error al procesar la recepción');
                }
            }
        };
    }
</script>
<?php View::endSection('extra_js'); ?>

<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<!-- Filter bar -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-4">
    <form method="GET" class="flex flex-wrap items-end gap-3">
        <div class="min-w-36">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Desde</label>
            <input type="date" name="desde" value="<?= View::e($desde) ?>"
                   class="w-full py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
        </div>
        <div class="min-w-36">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Hasta</label>
            <input type="date" name="hasta" value="<?= View::e($hasta) ?>"
                   class="w-full py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
                <i class="fas fa-filter"></i> Filtrar
            </button>
        </div>
    </form>
</div>

<!-- Métricas -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-blue-500 to-blue-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Ventas</p>
                <h3 class="text-xl font-extrabold mt-1"><?= number_format((int)($totales['cantidad'] ?? 0)) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-receipt"></i></span>
        </div>
    </div>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-emerald-500 to-emerald-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Total</p>
                <h3 class="text-xl font-extrabold mt-1 truncate">$<?= number_format((float)($totales['total'] ?? 0), 2) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-sack-dollar"></i></span>
        </div>
    </div>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-amber-500 to-amber-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">ITBMS</p>
                <h3 class="text-xl font-extrabold mt-1 truncate">$<?= number_format((float)($totales['itbms'] ?? 0), 2) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-percent"></i></span>
        </div>
    </div>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-sky-500 to-sky-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Ganancia</p>
                <h3 class="text-xl font-extrabold mt-1 truncate">$<?= number_format((float)($totales['total'] ?? 0) - (float)($totales['costo'] ?? 0), 2) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-chart-line"></i></span>
        </div>
    </div>
</div>

<!-- Tabla -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                <th class="px-5 py-3.5 bg-gray-50/80">Fecha</th>
                <th class="px-5 py-3.5 bg-gray-50/80 text-right">Ventas</th>
                <th class="px-5 py-3.5 bg-gray-50/80 text-right">Subtotal</th>
                <th class="px-5 py-3.5 bg-gray-50/80 text-right">ITBMS</th>
                <th class="px-5 py-3.5 bg-gray-50/80 text-right">Total</th>
                <th class="px-5 py-3.5 bg-gray-50/80 text-right">Costo</th>
                <th class="px-5 py-3.5 bg-gray-50/80 text-right">Ganancia</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            <?php foreach (($ventas ?? []) as $v): ?>
            <tr class="hover:bg-sky-50/40 transition-colors">
                <td class="px-5 py-3.5 font-bold text-gray-800"><?= !empty($v['fecha_emision']) ? date('d/m/Y', strtotime($v['fecha_emision'])) : 'S/F' ?></td>
                <td class="px-5 py-3.5 text-right text-gray-700"><?= $v['total_transacciones'] ?? 0 ?></td>
                <td class="px-5 py-3.5 text-right text-gray-600">$<?= number_format((float)$v['subtotal'], 2) ?></td>
                <td class="px-5 py-3.5 text-right text-gray-500">$<?= number_format((float)$v['itbms'], 2) ?></td>
                <td class="px-5 py-3.5 text-right font-bold text-emerald-600">$<?= number_format((float)$v['total'], 2) ?></td>
                <td class="px-5 py-3.5 text-right text-gray-500">$<?= number_format((float)$v['costo'], 2) ?></td>
                <td class="px-5 py-3.5 text-right font-bold text-sky-600">$<?= number_format((float)$v['total'] - (float)$v['costo'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($ventas)): ?>
            <tr><td colspan="7" class="px-5 py-10 text-center text-sm text-gray-400">No hay datos para el período seleccionado</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php View::endSection('content'); ?>

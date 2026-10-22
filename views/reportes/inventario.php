<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<!-- Métricas -->
<div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-4">
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-sky-500 to-sky-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Total Productos</p>
                <h3 class="text-xl font-extrabold mt-1"><?= number_format($totalProductos) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-boxes-stacked"></i></span>
        </div>
    </div>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-emerald-500 to-emerald-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Valor de Inventario</p>
                <h3 class="text-xl font-extrabold mt-1 truncate">$<?= number_format($valorTotal, 2) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-sack-dollar"></i></span>
        </div>
    </div>
</div>

<!-- Tabla -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                <th class="px-5 py-3.5 bg-gray-50/80">Código</th>
                <th class="px-5 py-3.5 bg-gray-50/80">Producto</th>
                <th class="px-5 py-3.5 bg-gray-50/80">Categoría</th>
                <th class="px-5 py-3.5 bg-gray-50/80 text-right">Stock</th>
                <th class="px-5 py-3.5 bg-gray-50/80 text-right">Mín.</th>
                <th class="px-5 py-3.5 bg-gray-50/80 text-right">Costo Prom.</th>
                <th class="px-5 py-3.5 bg-gray-50/80 text-right">Valor Total</th>
                <th class="px-5 py-3.5 bg-gray-50/80">Estado</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            <?php foreach (($productos ?? []) as $p): ?>
            <?php $stock = (float)$p['stock_total']; $min = (float)$p['stock_minimo']; ?>
            <tr class="hover:bg-sky-50/40 transition-colors <?= $stock <= 0 ? 'bg-red-50/50' : ($stock <= $min ? 'bg-orange-50/50' : '') ?>">
                <td class="px-5 py-3.5 font-mono text-xs text-gray-400"><?= View::e($p['codigo']) ?></td>
                <td class="px-5 py-3.5 font-bold text-gray-800"><?= View::e($p['nombre']) ?></td>
                <td class="px-5 py-3.5 text-gray-500 text-xs"><?= View::e($p['categoria'] ?? '—') ?></td>
                <td class="px-5 py-3.5 text-right font-semibold text-gray-700"><?= number_format($stock) ?></td>
                <td class="px-5 py-3.5 text-right text-gray-500"><?= number_format($min) ?></td>
                <td class="px-5 py-3.5 text-right text-gray-500">$<?= number_format((float)$p['costo_promedio'], 2) ?></td>
                <td class="px-5 py-3.5 text-right font-bold text-gray-800">$<?= number_format((float)$p['valor_total'], 2) ?></td>
                <td class="px-5 py-3.5">
                    <?php if ($stock <= 0): ?>
                    <span class="px-2.5 py-1 inline-flex text-xs font-bold rounded-full bg-red-500 text-white">Agotado</span>
                    <?php elseif ($stock <= $min): ?>
                    <span class="px-2.5 py-1 inline-flex text-xs font-bold rounded-full bg-orange-500 text-white">Bajo</span>
                    <?php else: ?>
                    <span class="px-2.5 py-1 inline-flex text-xs font-bold rounded-full bg-emerald-500 text-white">OK</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php View::endSection('content'); ?>

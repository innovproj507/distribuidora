<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-xs font-bold text-gray-500 uppercase tracking-wider"><th class="px-5 py-3.5 bg-gray-50/80">#</th><th class="px-5 py-3.5 bg-gray-50/80">Código</th><th class="px-5 py-3.5 bg-gray-50/80">Cliente</th><th class="px-5 py-3.5 bg-gray-50/80">Tipo</th><th class="px-5 py-3.5 bg-gray-50/80 text-right">N° Compras</th><th class="px-5 py-3.5 bg-gray-50/80 text-right">Total Compras</th><th class="px-5 py-3.5 bg-gray-50/80">Última Compra</th></tr></thead>
        <tbody class="divide-y divide-gray-50">
            <?php foreach (($clientes ?? []) as $i => $c): ?>
            <tr class="hover:bg-sky-50/40 transition-colors">
                <td class="px-5 py-3.5">
                    <span class="w-7 h-7 rounded-lg bg-cyan-500 text-white flex items-center justify-center text-xs font-bold shadow-sm shadow-cyan-500/30"><?= $i + 1 ?></span>
                </td>
                <td class="px-5 py-3.5 font-mono text-xs text-gray-500"><?= View::e($c['codigo']) ?></td>
                <td class="px-5 py-3.5 font-bold text-gray-800"><?= View::e($c['nombre']) ?></td>
                <td class="px-5 py-3.5 text-gray-500 capitalize"><?= View::e($c['tipo']) ?></td>
                <td class="px-5 py-3.5 text-right font-semibold text-gray-700"><?= $c['num_compras'] ?></td>
                <td class="px-5 py-3.5 text-right font-bold text-emerald-600">$<?= number_format((float)$c['total_compras'], 2) ?></td>
                <td class="px-5 py-3.5 text-gray-400 text-xs"><?= $c['ultima_compra'] ? date('d/m/Y', strtotime($c['ultima_compra'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($clientes)): ?><tr><td colspan="7" class="px-5 py-10 text-center text-gray-400">Sin datos</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php View::endSection('content'); ?>

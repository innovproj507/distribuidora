<?php $this->extend('layouts/app'); ?>
<?php $this->section('content'); ?>

<?php $m = fn ($v) => '$' . number_format((float) $v, 2); ?>

<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <div class="rounded-2xl p-4 bg-gradient-to-br from-sky-500 to-sky-600 text-white"><p class="text-xs text-white/80">Ventas en cajas</p><p class="text-xl font-extrabold"><?= $m($totales['ventas']) ?></p></div>
    <div class="rounded-2xl p-4 bg-gradient-to-br from-red-500 to-red-600 text-white"><p class="text-xs text-white/80">Faltantes</p><p class="text-xl font-extrabold"><?= $m(abs($totales['faltante'])) ?></p></div>
    <div class="rounded-2xl p-4 bg-gradient-to-br from-emerald-500 to-emerald-600 text-white"><p class="text-xs text-white/80">Sobrantes</p><p class="text-xl font-extrabold"><?= $m($totales['sobrante']) ?></p></div>
    <div class="rounded-2xl p-4 bg-gradient-to-br from-amber-500 to-amber-600 text-white"><p class="text-xs text-white/80">Abiertas ahora</p><p class="text-xl font-extrabold"><?= (int) $totales['abiertas'] ?></p></div>
</div>

<form method="GET" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-3 mb-4 flex flex-wrap items-end gap-3">
    <div><label class="block text-xs font-semibold text-gray-500 mb-1">Desde</label><input type="date" name="desde" value="<?= esc($desde, 'attr') ?>" class="py-1.5 text-sm border-0 border-b border-gray-200 focus:ring-0 focus:border-sky-400"></div>
    <div><label class="block text-xs font-semibold text-gray-500 mb-1">Hasta</label><input type="date" name="hasta" value="<?= esc($hasta, 'attr') ?>" class="py-1.5 text-sm border-0 border-b border-gray-200 focus:ring-0 focus:border-sky-400"></div>
    <div><label class="block text-xs font-semibold text-gray-500 mb-1">Cajero</label>
        <select name="usuario" class="py-1.5 text-sm border-0 border-b border-gray-200 focus:ring-0 focus:border-sky-400">
            <option value="">Todos</option>
            <?php foreach ($usuarios as $u): ?><option value="<?= (int) $u['id'] ?>" <?= (int) $usuario === (int) $u['id'] ? 'selected' : '' ?>><?= esc($u['username']) ?></option><?php endforeach; ?>
        </select></div>
    <button class="px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600"><i class="fas fa-filter mr-1"></i> Filtrar</button>
</form>

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto">
    <table class="min-w-full text-sm">
        <thead><tr class="text-xs font-semibold text-gray-500 uppercase tracking-wider text-left bg-gray-50">
            <th class="px-4 py-3">Caja</th><th class="px-4 py-3">Tienda</th><th class="px-4 py-3">Cajero</th><th class="px-4 py-3">Apertura</th><th class="px-4 py-3">Cierre</th>
            <th class="px-4 py-3 text-right">Ventas</th><th class="px-4 py-3 text-right">Efectivo esperado</th><th class="px-4 py-3 text-right">Contado</th><th class="px-4 py-3 text-right">Diferencia</th>
        </tr></thead>
        <tbody class="divide-y divide-gray-50">
        <?php foreach ($cajas as $c): $d = (float) $c['diferencia_efectivo']; ?>
            <tr class="hover:bg-gray-50 cursor-pointer" onclick="location.href='/cajas/<?= (int) $c['caja_id'] ?>'">
                <td class="px-4 py-2.5 font-semibold text-sky-600">#<?= (int) $c['caja_id'] ?></td>
                <td class="px-4 py-2.5"><?= esc($c['sucursal']) ?></td>
                <td class="px-4 py-2.5"><?= esc($c['cajero']) ?></td>
                <td class="px-4 py-2.5 text-xs"><?= date('d/m/Y H:i', strtotime($c['abierta_en'])) ?></td>
                <td class="px-4 py-2.5 text-xs"><?= $c['estado'] === 'abierta' ? '<span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-600 font-bold">Abierta</span>' : date('d/m/Y H:i', strtotime($c['cerrada_en'])) ?></td>
                <td class="px-4 py-2.5 text-right"><?= $m($c['ventas_total']) ?> <span class="text-xs text-gray-400">(<?= (int) $c['ventas_cantidad'] ?>)</span></td>
                <td class="px-4 py-2.5 text-right"><?= $c['estado'] === 'cerrada' ? $m($c['efectivo_esperado']) : '—' ?></td>
                <td class="px-4 py-2.5 text-right"><?= $c['estado'] === 'cerrada' ? $m($c['efectivo_contado']) : '—' ?></td>
                <td class="px-4 py-2.5 text-right font-bold <?= $c['estado'] !== 'cerrada' ? 'text-gray-300' : (abs($d) < 0.005 ? 'text-emerald-600' : ($d > 0 ? 'text-sky-600' : 'text-red-600')) ?>">
                    <?= $c['estado'] !== 'cerrada' ? '—' : (abs($d) < 0.005 ? 'Cuadra' : ($d > 0 ? '+' : '−') . $m(abs($d))) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$cajas): ?><tr><td colspan="9" class="px-4 py-10 text-center text-gray-400">No hay cajas en este periodo.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php $this->endSection(); ?>

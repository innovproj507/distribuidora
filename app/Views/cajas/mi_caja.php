<?php $this->extend('layouts/app'); ?>
<?php $this->section('content'); ?>

<?php $m = fn ($v) => '$' . number_format((float) $v, 2); ?>

<?php if (!$resumen): ?>
<!-- ── Sin caja abierta: abrir ─────────────────────────────────────────── -->
<div class="max-w-lg mx-auto">
    <form method="POST" action="/caja/abrir" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <?= csrf_field() ?>
        <input type="hidden" name="volver" value="<?= esc($volver, 'attr') ?>">
        <div class="flex items-center gap-3 mb-4">
            <span class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-500 flex items-center justify-center"><i class="fas fa-cash-register"></i></span>
            <div>
                <h2 class="font-bold text-gray-800">Abrir caja</h2>
                <p class="text-xs text-gray-400"><?= esc($sucursal_actual['nombre'] ?? '') ?> · <?= esc($auth['name'] ?? '') ?></p>
            </div>
        </div>
        <label class="block text-sm font-semibold text-gray-600 mb-1">Efectivo inicial (fondo de cambio)</label>
        <div class="relative mb-4">
            <span class="absolute left-3 top-2.5 text-gray-400">$</span>
            <input type="number" name="monto_inicial" step="0.01" min="0" required autofocus value="0.00"
                   class="w-full pl-7 pr-3 py-2.5 text-lg font-bold border border-gray-200 rounded-xl focus:outline-none focus:border-sky-400">
        </div>
        <label class="block text-sm font-semibold text-gray-600 mb-1">Notas (opcional)</label>
        <input type="text" name="notas" maxlength="300" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl mb-5 focus:outline-none focus:border-sky-400" placeholder="Ej: 2 rollos de monedas de 25¢">
        <button class="w-full py-2.5 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700"><i class="fas fa-lock-open mr-1"></i> Abrir caja</button>
    </form>
    <?php if ($ultima): ?>
    <p class="text-center text-xs text-gray-400 mt-3">
        Último cierre: <?= date('d/m/Y H:i', strtotime($ultima['cerrada_en'])) ?> ·
        <a href="/cajas/<?= (int) $ultima['caja_id'] ?>" class="text-sky-600 hover:underline">ver arqueo</a>
    </p>
    <?php endif; ?>
</div>

<?php else: $r = $resumen; $c = $r['caja']; ?>
<!-- ── Caja abierta ────────────────────────────────────────────────────── -->
<div class="flex flex-wrap items-center justify-between gap-3 bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-3 mb-4">
    <p class="text-sm text-gray-600">
        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-600 text-xs font-bold mr-2"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Abierta</span>
        Caja #<?= (int) $c['caja_id'] ?> · desde <?= date('d/m/Y H:i', strtotime($c['abierta_en'])) ?> · fondo inicial <?= $m($c['monto_inicial']) ?>
    </p>
    <a href="/ventas/pos" class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-800 text-white rounded-lg text-sm font-bold hover:bg-slate-900"><i class="fas fa-cash-register"></i> Ir al POS</a>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <div class="rounded-2xl p-4 bg-gradient-to-br from-emerald-500 to-emerald-600 text-white">
        <p class="text-xs text-white/80">Efectivo en caja (esperado)</p>
        <p class="text-2xl font-extrabold"><?= $m($r['esperado']['EFECTIVO']) ?></p>
    </div>
    <div class="rounded-2xl p-4 bg-gradient-to-br from-sky-500 to-sky-600 text-white">
        <p class="text-xs text-white/80">Ventas (<?= (int) $r['ventas']['cantidad'] ?>)</p>
        <p class="text-2xl font-extrabold"><?= $m($r['ventas']['total']) ?></p>
    </div>
    <div class="rounded-2xl p-4 bg-white border border-gray-100">
        <p class="text-xs text-gray-400">A crédito (no es dinero)</p>
        <p class="text-xl font-bold text-gray-700"><?= $m($r['credito']) ?></p>
    </div>
    <div class="rounded-2xl p-4 bg-white border border-gray-100">
        <p class="text-xs text-gray-400">Entradas / salidas</p>
        <p class="text-xl font-bold text-gray-700"><span class="text-emerald-600">+<?= $m($r['entradas']) ?></span> <span class="text-red-500">−<?= $m($r['salidas']) ?></span></p>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-4 mb-4">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 lg:col-span-2">
        <h3 class="font-bold text-gray-800 mb-3">Por forma de pago</h3>
        <table class="w-full text-sm">
            <thead><tr class="text-xs text-gray-400 text-left"><th class="py-1">Forma</th><th class="py-1 text-right">Ventas</th><th class="py-1 text-right">Abonos</th><th class="py-1 text-right">Esperado</th></tr></thead>
            <tbody class="divide-y divide-gray-50">
            <?php foreach ($formas as $f): ?>
                <tr>
                    <td class="py-1.5 font-semibold text-gray-700"><?= esc(ucfirst(strtolower($f))) ?></td>
                    <td class="py-1.5 text-right"><?= $m($r['por_forma_ventas'][$f] ?? 0) ?></td>
                    <td class="py-1.5 text-right"><?= $m($r['cobros'][$f]['monto'] ?? 0) ?></td>
                    <td class="py-1.5 text-right font-bold"><?= $m($r['esperado'][$f]) ?><?= $f === 'EFECTIVO' ? ' <span class="text-[10px] font-normal text-gray-400">(+ fondo y movimientos)</span>' : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($r['anuladas']['cantidad']): ?>
            <p class="text-xs text-gray-400 mt-2"><?= (int) $r['anuladas']['cantidad'] ?> venta(s) anulada(s) por <?= $m($r['anuladas']['total']) ?> no cuentan.</p>
        <?php endif; ?>
    </div>

    <form method="POST" action="/caja/movimiento" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5" x-data="{ tipo: 'salida' }">
        <?= csrf_field() ?>
        <h3 class="font-bold text-gray-800 mb-1">Entrada / salida de efectivo</h3>
        <p class="text-xs text-gray-400 mb-3">Retiro para depósito, pago menor, cambio que te trajeron…</p>
        <input type="hidden" name="tipo" :value="tipo">
        <div class="flex gap-2 mb-3">
            <button type="button" @click="tipo = 'entrada'" :class="tipo === 'entrada' ? 'bg-emerald-500 text-white' : 'bg-gray-100 text-gray-500'" class="flex-1 py-1.5 rounded-lg text-sm font-semibold">+ Entrada</button>
            <button type="button" @click="tipo = 'salida'" :class="tipo === 'salida' ? 'bg-red-500 text-white' : 'bg-gray-100 text-gray-500'" class="flex-1 py-1.5 rounded-lg text-sm font-semibold">− Salida</button>
        </div>
        <input type="number" name="monto" step="0.01" min="0.01" required placeholder="Monto" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg mb-2 focus:outline-none focus:border-sky-400">
        <input type="text" name="motivo" maxlength="200" required placeholder="Motivo" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg mb-3 focus:outline-none focus:border-sky-400">
        <button class="w-full py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600">Registrar</button>

        <?php if ($r['movimientos']): ?>
        <div class="mt-4 space-y-1.5 max-h-40 overflow-y-auto">
            <?php foreach ($r['movimientos'] as $mv): ?>
            <p class="text-xs flex justify-between gap-2"><span class="text-gray-500 truncate"><?= date('H:i', strtotime($mv['fecha'])) ?> · <?= esc($mv['motivo']) ?></span>
                <span class="font-semibold <?= $mv['tipo'] === 'entrada' ? 'text-emerald-600' : 'text-red-500' ?>"><?= $mv['tipo'] === 'entrada' ? '+' : '−' ?><?= $m($mv['monto']) ?></span></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </form>
</div>

<?= view('cajas/_cierre', ['r' => $r, 'formas' => $formas, 'denominaciones' => $denominaciones]) ?>
<?php endif; ?>

<?php $this->endSection(); ?>

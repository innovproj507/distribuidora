<?php $this->extend('layouts/app'); ?>
<?php $this->section('content'); ?>

<?php
$m = fn ($v) => '$' . number_format((float) $v, 2);
$c = $r['caja'];
$cerrada = $c['estado'] === 'cerrada';
$etiquetaDif = fn (float $d) => abs($d) < 0.005 ? '<span class="text-emerald-600 font-bold">Cuadra</span>'
    : ($d > 0 ? '<span class="text-sky-600 font-bold">Sobra ' . '$' . number_format($d, 2) . '</span>' : '<span class="text-red-600 font-bold">Falta ' . '$' . number_format(abs($d), 2) . '</span>');
?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-4 print:hidden">
    <a href="<?= \App\Libraries\Auth::can('cajas.ver') ? '/cajas' : '/caja' ?>" class="text-sm text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left mr-1"></i> Volver</a>
    <button onclick="window.print()" class="px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-semibold text-gray-600 hover:bg-gray-50"><i class="fas fa-print mr-1"></i> Imprimir</button>
</div>

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-4">
    <div class="flex flex-wrap justify-between gap-4 mb-5">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Caja #<?= (int) $c['caja_id'] ?></h2>
            <p class="text-sm text-gray-500"><?= esc($c['sucursal'] ?? '') ?> · cajero <b><?= esc($c['cajero'] ?? '') ?></b></p>
        </div>
        <div class="text-sm text-right">
            <p>Apertura: <b><?= date('d/m/Y H:i', strtotime($c['abierta_en'])) ?></b></p>
            <p>Cierre: <b><?= $cerrada ? date('d/m/Y H:i', strtotime($c['cerrada_en'])) : '— (abierta)' ?></b><?= $cerrada && $c['cerrada_por_nombre'] && $c['cerrada_por'] != $c['usuario_id'] ? ' por ' . esc($c['cerrada_por_nombre']) : '' ?></p>
            <p>Fondo inicial: <b><?= $m($c['monto_inicial']) ?></b></p>
        </div>
    </div>

    <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2"><?= $cerrada ? 'Arqueo' : 'Esperado hasta ahora' ?></h3>
    <table class="w-full text-sm mb-4">
        <thead><tr class="text-xs text-gray-400 text-left border-b border-gray-100"><th class="py-1.5">Forma de pago</th><th class="py-1.5 text-right">Esperado</th><?php if ($cerrada): ?><th class="py-1.5 text-right">Declarado</th><th class="py-1.5 text-right">Diferencia</th><?php endif; ?></tr></thead>
        <tbody class="divide-y divide-gray-50">
        <?php foreach ($formas as $f):
            $fila = $cerrada && $arqueo ? ($arqueo['formas'][$f] ?? null) : null; ?>
            <tr>
                <td class="py-1.5 font-semibold text-gray-700"><?= esc(ucfirst(strtolower($f))) ?></td>
                <td class="py-1.5 text-right"><?= $m($fila['esperado'] ?? $r['esperado'][$f]) ?></td>
                <?php if ($cerrada): ?>
                <td class="py-1.5 text-right"><?= $m($fila['declarado'] ?? 0) ?></td>
                <td class="py-1.5 text-right"><?= $etiquetaDif((float) ($fila['diferencia'] ?? 0)) ?></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="grid sm:grid-cols-4 gap-3 text-sm">
        <p class="bg-gray-50 rounded-lg p-2">Ventas: <b><?= (int) $r['ventas']['cantidad'] ?></b> · <?= $m($r['ventas']['total']) ?></p>
        <p class="bg-gray-50 rounded-lg p-2">A crédito: <b><?= $m($r['credito']) ?></b></p>
        <p class="bg-gray-50 rounded-lg p-2">Entradas: <b class="text-emerald-600"><?= $m($r['entradas']) ?></b> · Salidas: <b class="text-red-500"><?= $m($r['salidas']) ?></b></p>
        <p class="bg-gray-50 rounded-lg p-2">Anuladas: <b><?= (int) $r['anuladas']['cantidad'] ?></b> · <?= $m($r['anuladas']['total']) ?></p>
    </div>

    <?php if ($cerrada && !empty($arqueo['billetes'])): ?>
    <p class="text-xs text-gray-500 mt-4"><b>Conteo de efectivo:</b>
        <?php foreach ($arqueo['billetes'] as $den => $n): ?><span class="inline-block mr-3"><?= (float) $den >= 1 ? '$' . (int) $den : (int) round((float) $den * 100) . '¢' ?> × <?= (int) $n ?></span><?php endforeach; ?>
    </p>
    <?php endif; ?>
    <?php if ($c['notas_apertura']): ?><p class="text-xs text-gray-500 mt-2"><b>Notas de apertura:</b> <?= esc($c['notas_apertura']) ?></p><?php endif; ?>
    <?php if ($c['notas_cierre']): ?><p class="text-xs text-gray-500 mt-1"><b>Notas de cierre:</b> <?= esc($c['notas_cierre']) ?></p><?php endif; ?>
</div>

<?php if ($puedeCerrar): ?>
<div class="mb-4 print:hidden">
    <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-xl px-4 py-2 mb-3"><i class="fas fa-triangle-exclamation mr-1"></i> Esta caja sigue abierta. Como gerente puedes cerrarla haciendo el arqueo con el efectivo entregado.</p>
    <?= view('cajas/_cierre', ['r' => $r, 'formas' => $formas, 'denominaciones' => $denominaciones]) ?>
</div>
<?php endif; ?>

<div class="grid lg:grid-cols-2 gap-4">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="font-bold text-gray-800 mb-3">Ventas (<?= count($ventas) ?>)</h3>
        <div class="max-h-96 overflow-y-auto">
        <table class="w-full text-sm">
            <?php foreach ($ventas as $v): ?>
            <tr class="border-b border-gray-50 <?= $v['estado'] === 'anulada' ? 'line-through text-gray-300' : '' ?>">
                <td class="py-1.5"><a href="/ventas/venta/<?= (int) $v['venta_id'] ?>" class="text-sky-600 hover:underline print:no-underline"><?= esc($v['numero_factura']) ?></a></td>
                <td class="py-1.5 text-xs text-gray-400"><?= date('H:i', strtotime($v['fecha'])) ?></td>
                <td class="py-1.5 text-xs truncate max-w-[10rem]"><?= esc($v['cliente'] ?? 'Consumidor final') ?></td>
                <td class="py-1.5 text-xs"><?= esc($v['forma_pago']) ?></td>
                <td class="py-1.5 text-right font-semibold"><?= $m($v['total']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$ventas): ?><tr><td class="py-4 text-center text-gray-400 text-sm">Sin ventas.</td></tr><?php endif; ?>
        </table>
        </div>
    </div>
    <div class="space-y-4">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <h3 class="font-bold text-gray-800 mb-3">Abonos de clientes (<?= count($cobros) ?>)</h3>
            <?php foreach ($cobros as $co): ?>
            <p class="text-sm flex justify-between border-b border-gray-50 py-1.5"><span><?= esc($co['numero']) ?> · <span class="text-gray-500"><?= esc($co['cliente']) ?></span> · <span class="text-xs"><?= esc($co['forma_pago']) ?></span></span><b><?= $m($co['monto']) ?></b></p>
            <?php endforeach; ?>
            <?php if (!$cobros): ?><p class="text-sm text-gray-400">Sin abonos.</p><?php endif; ?>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <h3 class="font-bold text-gray-800 mb-3">Entradas y salidas</h3>
            <?php foreach ($r['movimientos'] as $mv): ?>
            <p class="text-sm flex justify-between border-b border-gray-50 py-1.5"><span><?= date('H:i', strtotime($mv['fecha'])) ?> · <?= esc($mv['motivo']) ?> <span class="text-xs text-gray-400"><?= esc($mv['username'] ?? '') ?></span></span>
                <b class="<?= $mv['tipo'] === 'entrada' ? 'text-emerald-600' : 'text-red-500' ?>"><?= $mv['tipo'] === 'entrada' ? '+' : '−' ?><?= $m($mv['monto']) ?></b></p>
            <?php endforeach; ?>
            <?php if (!$r['movimientos']): ?><p class="text-sm text-gray-400">Sin movimientos.</p><?php endif; ?>
        </div>
    </div>
</div>

<?php $this->endSection(); ?>

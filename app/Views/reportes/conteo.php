<?php
$this->extend('layouts/app');

$costos = ver_costos();
$num    = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
$dinero = fn ($v) => '$' . number_format((float) $v, 2);
$id     = (int) ($conteo['conteo_id'] ?? 0);
$url    = fn (array $extra) => '/reportes/conteo?' . http_build_query(array_filter(array_merge(['id' => $id, 'ver' => $ver, 'sucursal' => $sucursal_filtro ?: null], $extra), fn ($v) => $v !== null && $v !== ''));
$campo  = 'w-full py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none';
$estadoTxt = ['completado' => 'Cerrado', 'en_proceso' => 'En proceso'];
?>
<?php $this->section('extra_css'); ?><?= view('reportes/_imprimir') ?><?php $this->endSection(); ?>
<?php $this->section('content'); ?>

<form method="GET" class="no-print bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-4 flex flex-wrap items-end gap-4">
    <?= view('reportes/_sucursal', ['sucursales_reporte' => $sucursales_reporte ?? [], 'sucursal_filtro' => $sucursal_filtro ?? 0]) ?>
    <div class="flex-1 min-w-64">
        <label class="block text-xs font-semibold text-gray-500 mb-1">Conteo</label>
        <select name="id" onchange="this.form.submit()" class="<?= $campo ?>">
            <?php foreach ($conteos as $c): ?>
            <option value="<?= (int) $c['conteo_id'] ?>" <?= (int) $c['conteo_id'] === $id ? 'selected' : '' ?>>
                <?= esc(date('d/m/Y', strtotime($c['fecha_cierre'] ?: $c['fecha_inicio'])) . ' · ' . $c['sucursal'] . ' — ' . $c['deposito'] . ' · ' . ($c['descripcion'] ?: 'Conteo #' . $c['conteo_id']) . ' (' . ($estadoTxt[$c['estado']] ?? $c['estado']) . ')') ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if ($conteo): ?>
    <div class="flex gap-2">
        <a href="<?= esc($url(['formato' => 'xlsx']), 'attr') ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700 shadow-sm"><i class="fas fa-file-excel"></i> Excel</a>
        <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50"><i class="fas fa-print"></i> Imprimir</button>
    </div>
    <?php endif; ?>
</form>

<?php if (!$conteo): ?>
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-10 text-center text-sm text-gray-400">Todavía no hay conteos físicos en estas tiendas.</div>
<?php else: ?>

<!-- Encabezado -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-4">
    <h2 class="text-xl font-bold text-gray-800"><?= esc($conteo['descripcion'] ?: 'Conteo #' . $id) ?></h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-3 text-sm">
        <div><p class="text-[11px] text-gray-400 uppercase font-semibold">Tienda / depósito</p><p class="font-medium"><?= esc($conteo['sucursal'] . ' — ' . $conteo['deposito']) ?></p></div>
        <div><p class="text-[11px] text-gray-400 uppercase font-semibold">Inició</p><p class="font-medium"><?= date('d/m/Y H:i', strtotime($conteo['fecha_inicio'])) ?></p></div>
        <div><p class="text-[11px] text-gray-400 uppercase font-semibold">Cerró</p><p class="font-medium"><?= $conteo['fecha_cierre'] ? date('d/m/Y H:i', strtotime($conteo['fecha_cierre'])) : 'En proceso' ?></p></div>
        <div><p class="text-[11px] text-gray-400 uppercase font-semibold">Creado por</p><p class="font-medium"><?= esc($conteo['usuario'] ?? '—') ?></p></div>
    </div>
    <?php if ($conteo['estado'] === 'en_proceso'): ?>
    <p class="mt-3 text-xs bg-amber-50 text-amber-700 rounded-lg px-3 py-2"><i class="fas fa-clock mr-1"></i> Este conteo sigue abierto: los números pueden cambiar y los ajustes aún no se aplicaron al stock.</p>
    <?php endif; ?>
</div>

<!-- Resumen -->
<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
    <?php foreach ([
        ['Productos', $res['total'], 'text-gray-800', ''],
        ['Contados', $res['contados'], 'text-gray-800', ''],
        ['Exactos', $res['exactos'], 'text-emerald-600', ''],
        ['Faltantes', $res['faltantes'], 'text-red-600', $num($res['u_faltan']) . ' uds · ' . $dinero($res['pv_faltan']) . ' a precio venta' . ($costos ? ' · ' . $dinero($res['v_faltan']) . ' a costo' : '')],
        ['Sobrantes', $res['sobrantes'], 'text-sky-600', $num($res['u_sobran']) . ' uds · ' . $dinero($res['pv_sobran']) . ' a precio venta' . ($costos ? ' · ' . $dinero($res['v_sobran']) . ' a costo' : '')],
    ] as [$t, $v, $c, $sub]): ?>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider"><?= $t ?></p>
        <p class="text-xl font-extrabold mt-1 <?= $c ?>"><?= number_format($v) ?></p>
        <?php if ($sub): ?><p class="text-[11px] text-gray-500 mt-1"><?= $sub ?></p><?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<p class="text-sm text-gray-600 mb-4">
    Resultado neto: <b class="<?= $res['pv_sobran'] - $res['pv_faltan'] < 0 ? 'text-red-600' : 'text-emerald-700' ?>"><?= $dinero($res['pv_sobran'] - $res['pv_faltan']) ?></b> a precio de venta<?php if ($costos): ?>
    · <b class="<?= $res['v_sobran'] - $res['v_faltan'] < 0 ? 'text-red-600' : 'text-emerald-700' ?>"><?= $dinero($res['v_sobran'] - $res['v_faltan']) ?></b> a costo<?php endif; ?>
    <?php if ($res['sin_contar']): ?> · <span class="text-amber-700"><?= $res['sin_contar'] ?> sin contar</span><?php endif; ?>
</p>

<!-- Filtro de líneas -->
<div class="no-print flex flex-wrap gap-2 mb-3">
    <?php foreach (['' => 'Todos', 'diferencias' => 'Con diferencia', 'faltantes' => 'Faltantes', 'sobrantes' => 'Sobrantes', 'sin_contar' => 'Sin contar'] as $k => $t): ?>
    <a href="<?= esc($url(['ver' => $k]), 'attr') ?>" class="px-3 py-1.5 rounded-full text-xs font-semibold <?= $ver === $k ? 'bg-sky-500 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>"><?= $t ?></a>
    <?php endforeach; ?>
</div>

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider">
                <th class="px-4 py-3 bg-gray-50/80">Producto</th>
                <th class="px-4 py-3 bg-gray-50/80">Ubicación</th>
                <th class="px-4 py-3 bg-gray-50/80 text-right">Sistema</th>
                <th class="px-4 py-3 bg-gray-50/80 text-right">Contado</th>
                <th class="px-4 py-3 bg-gray-50/80 text-right">Diferencia</th>
                <th class="px-4 py-3 bg-gray-50/80 text-right">A precio venta</th>
                <?php if ($costos): ?><th class="px-4 py-3 bg-gray-50/80 text-right">A costo</th><?php endif; ?>
                <th class="px-4 py-3 bg-gray-50/80">Resultado</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            <?php foreach ($lineas as $l):
                [$txt, $cls] = !$l['contado'] ? ['Sin contar', 'bg-gray-100 text-gray-500']
                    : (abs($l['dif']) < 0.005 ? ['Exacto', 'bg-emerald-100 text-emerald-700'] : ($l['dif'] < 0 ? ['Faltante', 'bg-red-100 text-red-700'] : ['Sobrante', 'bg-sky-100 text-sky-700'])); ?>
            <tr>
                <td class="px-4 py-2.5">
                    <span class="font-semibold text-gray-800"><?= esc($l['nombre']) ?></span>
                    <div class="text-[11px] text-gray-400"><span class="font-mono"><?= esc($l['codigo']) ?></span> · <?= esc($l['categoria']) ?></div>
                </td>
                <td class="px-4 py-2.5 text-xs text-gray-500"><?= esc($l['ubicacion'] ?: '—') ?></td>
                <td class="px-4 py-2.5 text-right text-gray-600"><?= $num($l['cantidad_sistema']) ?> <span class="text-[11px] text-gray-400"><?= esc($l['um']) ?></span></td>
                <td class="px-4 py-2.5 text-right font-semibold"><?= $l['contado'] ? $num($l['cantidad_contada']) : '—' ?></td>
                <td class="px-4 py-2.5 text-right font-bold <?= $l['dif'] < 0 ? 'text-red-600' : ($l['dif'] > 0 ? 'text-sky-600' : 'text-gray-400') ?>"><?= $l['contado'] ? ($l['dif'] > 0 ? '+' : '') . $num($l['dif']) : '' ?></td>
                <td class="px-4 py-2.5 text-right <?= $l['valor_venta'] < 0 ? 'text-red-600' : '' ?>"><?= $l['contado'] && abs($l['dif']) >= 0.005 ? $dinero($l['valor_venta']) : '' ?></td>
                <?php if ($costos): ?><td class="px-4 py-2.5 text-right <?= $l['valor_costo'] < 0 ? 'text-red-600' : '' ?>"><?= $l['contado'] && abs($l['dif']) >= 0.005 ? $dinero($l['valor_costo']) : '' ?></td><?php endif; ?>
                <td class="px-4 py-2.5"><span class="px-2 py-0.5 rounded-full text-[11px] font-bold <?= $cls ?>"><?= $txt ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$lineas): ?>
            <tr><td colspan="8" class="px-5 py-10 text-center text-sm text-gray-400">No hay productos en esta vista.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php $this->endSection(); ?>

<?php
$this->extend('layouts/app');

$costos   = ver_costos();
$num      = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
$dinero   = fn ($v) => '$' . number_format((float) $v, 2);
$varias   = count($enUso) > 1;
$query    = $_GET ?? [];
$xlsx     = '/reportes/inventario?' . http_build_query(array_merge($query, ['formato' => 'xlsx']));
$badge    = ['ok' => ['OK', 'bg-emerald-500'], 'bajo' => ['Bajo', 'bg-orange-500'], 'agotado' => ['Agotado', 'bg-red-500']];
$campo    = 'w-full py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none';
?>
<?php $this->section('extra_css'); ?><?= view('reportes/_imprimir') ?><?php $this->endSection(); ?>
<?php $this->section('content'); ?>

<div class="print-only mb-3">
    <h1 class="text-xl font-bold">Inventario general — <?= esc($empresa_actual['nombre'] ?? 'Distribuidora') ?></h1>
    <p class="text-xs text-gray-500"><?= date('d/m/Y H:i') ?> · <?= esc($varias ? 'Todas las tiendas del filtro' : ($enUso[0]['sucursal'] . ' — ' . $enUso[0]['nombre'])) ?></p>
</div>

<!-- Filtros -->
<form method="GET" class="no-print bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-4 flex flex-wrap items-end gap-4">
    <?= view('reportes/_sucursal', ['sucursales_reporte' => $sucursales_reporte ?? [], 'sucursal_filtro' => $sucursal_filtro ?? 0]) ?>
    <?php if (count($depositos) > 1): ?>
    <div class="min-w-44">
        <label class="block text-xs font-semibold text-gray-500 mb-1">Depósito</label>
        <select name="deposito" onchange="this.form.submit()" class="<?= $campo ?>">
            <option value="">Todos</option>
            <?php foreach ($depositos as $d): ?>
            <option value="<?= (int) $d['deposito_id'] ?>" <?= (int) $d['deposito_id'] === $depositoId ? 'selected' : '' ?>><?= esc($d['sucursal'] . ' — ' . $d['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="min-w-44">
        <label class="block text-xs font-semibold text-gray-500 mb-1">Categoría</label>
        <select name="categoria" onchange="this.form.submit()" class="<?= $campo ?>">
            <option value="">Todas</option>
            <?php foreach ($categorias as $c): ?>
            <option value="<?= (int) $c['categoria_id'] ?>" <?= (int) $c['categoria_id'] === $categoria ? 'selected' : '' ?>><?= esc($c['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="min-w-36">
        <label class="block text-xs font-semibold text-gray-500 mb-1">Stock</label>
        <select name="stock" onchange="this.form.submit()" class="<?= $campo ?>">
            <?php foreach (['' => 'Todos', 'con' => 'Con existencia', 'bajo' => 'Bajo mínimo', 'agotado' => 'Agotados'] as $k => $t): ?>
            <option value="<?= $k ?>" <?= $filtroStock === $k ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="flex-1 min-w-48">
        <label class="block text-xs font-semibold text-gray-500 mb-1">Buscar</label>
        <input type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="Nombre, código o código de barras" class="<?= $campo ?>">
    </div>
    <div class="flex gap-2">
        <a href="<?= esc($xlsx, 'attr') ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700 shadow-sm"><i class="fas fa-file-excel"></i> Excel</a>
        <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50"><i class="fas fa-print"></i> Imprimir</button>
    </div>
</form>

<!-- Totales -->
<div class="grid grid-cols-2 md:grid-cols-3 <?= $costos ? 'lg:grid-cols-6' : 'lg:grid-cols-5' ?> gap-3 mb-4">
    <?php
    $tarjetas = [
        ['Productos', number_format($tot['productos']), 'text-gray-800'],
        ['Con existencia', number_format($tot['con_stock']), 'text-emerald-600'],
        ['Bajo mínimo', number_format($tot['bajos']), 'text-orange-600'],
        ['Agotados', number_format($tot['agotados']), 'text-red-600'],
        ['Valor a precio de venta', $dinero($tot['valor_venta']), 'text-sky-700'],
    ];
    if ($costos) $tarjetas[] = ['Valor a costo', $dinero($tot['valor_costo']), 'text-gray-800'];
    foreach ($tarjetas as [$t, $v, $c]): ?>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider"><?= $t ?></p>
        <p class="text-xl font-extrabold mt-1 <?= $c ?>"><?= $v ?></p>
    </div>
    <?php endforeach; ?>
</div>
<?php if ($costos && $tot['valor_venta'] > 0): ?>
<p class="text-xs text-gray-500 mb-4 -mt-2">Ganancia potencial si se vende todo a precio de lista: <b class="text-emerald-700"><?= $dinero($tot['valor_venta'] - $tot['valor_costo']) ?></b></p>
<?php endif; ?>

<!-- Resumen por categoría -->
<?php if (count($porCategoria) > 1): ?>
<details class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-4" open>
    <summary class="cursor-pointer select-none px-5 py-3 text-sm font-bold text-gray-700">Resumen por categoría</summary>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-[11px] font-bold text-gray-500 uppercase">
            <th class="px-5 py-2 bg-gray-50/80">Categoría</th><th class="px-5 py-2 bg-gray-50/80 text-right">Productos</th>
            <th class="px-5 py-2 bg-gray-50/80 text-right">Unidades</th><th class="px-5 py-2 bg-gray-50/80 text-right">Valor venta</th>
            <?php if ($costos): ?><th class="px-5 py-2 bg-gray-50/80 text-right">Valor costo</th><?php endif; ?>
        </tr></thead>
        <tbody class="divide-y divide-gray-50">
        <?php foreach ($porCategoria as $c): ?>
            <tr><td class="px-5 py-2 font-medium"><?= esc($c['categoria']) ?></td><td class="px-5 py-2 text-right"><?= $c['productos'] ?></td>
                <td class="px-5 py-2 text-right"><?= $num($c['unidades']) ?></td><td class="px-5 py-2 text-right font-semibold"><?= $dinero($c['valor_venta']) ?></td>
                <?php if ($costos): ?><td class="px-5 py-2 text-right"><?= $dinero($c['valor_costo']) ?></td><?php endif; ?></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</details>
<?php endif; ?>

<!-- Detalle -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider">
                <th class="px-4 py-3 bg-gray-50/80">Producto</th>
                <th class="px-4 py-3 bg-gray-50/80">Categoría</th>
                <?php if ($varias): foreach ($enUso as $d): ?><th class="px-4 py-3 bg-gray-50/80 text-right"><?= esc($d['nombre']) ?></th><?php endforeach; endif; ?>
                <th class="px-4 py-3 bg-gray-50/80 text-right">Existencia</th>
                <?php if (!$varias): ?><th class="px-4 py-3 bg-gray-50/80">Ubicación</th><?php endif; ?>
                <th class="px-4 py-3 bg-gray-50/80 text-right">Mín.</th>
                <th class="px-4 py-3 bg-gray-50/80 text-right">Precio</th>
                <th class="px-4 py-3 bg-gray-50/80 text-right">Valor venta</th>
                <?php if ($costos): ?><th class="px-4 py-3 bg-gray-50/80 text-right">Costo</th><th class="px-4 py-3 bg-gray-50/80 text-right">Valor costo</th><?php endif; ?>
                <th class="px-4 py-3 bg-gray-50/80">Estado</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            <?php foreach ($filas as $f): [$txt, $cls] = $badge[$f['estado_stock']]; ?>
            <tr class="<?= $f['estado_stock'] === 'agotado' ? 'bg-red-50/40' : ($f['estado_stock'] === 'bajo' ? 'bg-orange-50/40' : '') ?>">
                <td class="px-4 py-2.5">
                    <a href="/inventario/<?= (int) $f['producto_id'] ?>" class="font-semibold text-gray-800 hover:text-sky-600"><?= esc($f['nombre']) ?></a>
                    <div class="text-[11px] font-mono text-gray-400"><?= esc($f['codigo']) ?></div>
                </td>
                <td class="px-4 py-2.5 text-xs text-gray-500"><?= esc($f['categoria']) ?></td>
                <?php if ($varias): foreach ($f['por_deposito'] as $e): ?><td class="px-4 py-2.5 text-right text-gray-600"><?= $num($e) ?></td><?php endforeach; endif; ?>
                <td class="px-4 py-2.5 text-right">
                    <span class="font-bold text-gray-800"><?= $num($f['total']) ?></span> <span class="text-[11px] text-gray-400"><?= esc($f['um']) ?></span>
                    <?php if ($f['desglose']): ?><div class="text-[11px] text-sky-700"><?= esc($f['desglose']) ?></div><?php endif; ?>
                </td>
                <?php if (!$varias): ?><td class="px-4 py-2.5 text-xs text-gray-500"><?= esc($f['ubicacion'] ?: '—') ?></td><?php endif; ?>
                <td class="px-4 py-2.5 text-right text-gray-500"><?= $num($f['minimo']) ?></td>
                <td class="px-4 py-2.5 text-right"><?= $dinero($f['precio']) ?></td>
                <td class="px-4 py-2.5 text-right font-semibold"><?= $dinero($f['valor_venta']) ?></td>
                <?php if ($costos): ?><td class="px-4 py-2.5 text-right text-gray-500"><?= $dinero($f['costo']) ?></td><td class="px-4 py-2.5 text-right"><?= $dinero($f['valor_costo']) ?></td><?php endif; ?>
                <td class="px-4 py-2.5"><span class="px-2 py-0.5 text-[11px] font-bold rounded-full text-white <?= $cls ?>"><?= $txt ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$filas): ?>
            <tr><td colspan="12" class="px-5 py-10 text-center text-sm text-gray-400">No hay productos con estos filtros.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php $this->endSection(); ?>

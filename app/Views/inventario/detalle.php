<?php
$this->extend('layouts/app');

$precios_map = [];
foreach (($precios ?? []) as $p) {
    $tipo = is_array($p) ? ($p['tipo_precio'] ?? '') : ($p->tipo_precio ?? '');
    $val  = is_array($p) ? ($p['precio'] ?? 0) : ($p->precio ?? 0);
    $precios_map[$tipo] = $val;
}
$precio   = (float) ($precios_map['a'] ?? 0);
$costo    = (float) $producto->costo;
$stockTot = (float) array_sum(array_column($inventarios ?? [], 'existencia'));
$ganancia = $precio - $costo;
$campo    = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-sky-300 focus:border-sky-400 focus:outline-none';
?>
<?php $this->section('content'); ?>

<div class="max-w-4xl mx-auto">
<!-- Breadcrumb -->
<div class="flex items-center gap-2 text-sm text-gray-400 mb-3">
    <a href="/inventario" class="hover:text-gray-600 transition-colors">Inventario</a>
    <i class="fas fa-chevron-right text-xs"></i>
    <span class="text-gray-700 font-medium"><?= esc($producto->nombre) ?></span>
</div>

<!-- Barra de acciones -->
<div class="flex items-center justify-between bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-2.5 mb-4">
    <div class="flex gap-2">
        <a href="/inventario/<?= $producto->producto_id ?>/editar"
           class="inline-flex items-center gap-1.5 px-5 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
            <i class="fas fa-pen"></i> Editar precio y datos
        </a>
        <a href="/inventario" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">Volver</a>
    </div>
    <span class="px-3 py-1.5 rounded-full text-xs font-bold <?= $producto->estado === 'activo' ? 'bg-emerald-500 text-white' : 'bg-gray-400 text-white' ?>"><?= ucfirst($producto->estado) ?></span>
</div>

<!-- Producto -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-4">
    <div class="flex items-start gap-5">
        <?php if (!empty($producto->imagen_principal)): ?>
            <img src="/assets/uploads/<?= esc($producto->imagen_principal) ?>" alt="" class="h-20 w-20 rounded-xl object-cover shrink-0">
        <?php else: ?>
            <div class="h-20 w-20 bg-gray-100 rounded-xl flex items-center justify-center shrink-0"><i class="fas fa-box text-gray-300 text-2xl"></i></div>
        <?php endif; ?>
        <div class="min-w-0">
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-900"><?= esc($producto->nombre) ?></h2>
            <p class="text-sm text-gray-500 mt-1">
                <span class="font-mono"><?= esc($producto->codigo) ?></span>
                <?= !empty($categoria->nombre) ? ' · ' . esc($categoria->nombre) : '' ?>
                <?= !empty($producto->marca) ? ' · ' . esc($producto->marca) : '' ?>
                <?= !empty($proveedor->nombre) ? ' · Proveedor: ' . esc($proveedor->nombre) : '' ?>
            </p>
            <?php if ($producto->descripcion): ?><p class="text-sm text-gray-400 mt-1"><?= esc($producto->descripcion) ?></p><?php endif; ?>
        </div>
    </div>

    <!-- Lo importante, una sola vez -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-5">
        <div class="rounded-2xl border-2 border-sky-100 px-5 py-4">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Precio de venta</p>
            <p class="text-3xl font-black <?= $precio > 0 ? 'text-sky-700' : 'text-amber-600' ?>"><?= $precio > 0 ? '$' . number_format($precio, 2) : 'Sin precio' ?></p>
            <?php if ($precio <= 0): ?><p class="text-xs text-amber-600 mt-1">No se puede vender hasta ponerle precio.</p><?php endif; ?>
        </div>
        <div class="rounded-2xl border border-gray-100 px-5 py-4">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Costo por unidad</p>
            <p class="text-3xl font-black <?= $costo > 0 ? 'text-gray-800' : 'text-amber-600' ?>"><?= $costo > 0 ? '$' . number_format($costo, 2) : 'Falta' ?></p>
            <?php if ($costo > 0 && $precio > 0): ?>
                <p class="text-xs mt-1 <?= $ganancia < 0 ? 'text-red-600 font-semibold' : 'text-emerald-600' ?>">
                    <?= $ganancia < 0 ? 'Pierdes $' . number_format(abs($ganancia), 2) . ' por unidad' : 'Ganas $' . number_format($ganancia, 2) . ' por unidad' ?>
                </p>
            <?php elseif ($costo <= 0): ?><p class="text-xs text-amber-600 mt-1">Sin costo no hay ganancia.</p><?php endif; ?>
        </div>
        <div class="rounded-2xl border border-gray-100 px-5 py-4">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Stock total</p>
            <p class="text-3xl font-black text-gray-800"><?= number_format($stockTot, 0) ?> <span class="text-sm font-normal text-gray-400">unidades</span></p>
        </div>
    </div>
</div>

<!-- Pestañas -->
<div x-data="{ tab: 'stock' }">
    <div class="flex flex-wrap gap-1 mb-4 bg-white rounded-2xl border border-gray-100 shadow-sm p-1 w-fit">
        <button @click="tab='stock'" :class="tab==='stock' ? 'bg-sky-500 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'" class="px-4 py-1.5 rounded-lg text-sm font-semibold transition">Stock por tienda</button>
        <button @click="tab='agregar'" :class="tab==='agregar' ? 'bg-sky-500 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'" class="px-4 py-1.5 rounded-lg text-sm font-semibold transition">Agregar stock</button>
        <?php if (!empty($unidades)): ?>
        <button @click="tab='pres'" :class="tab==='pres' ? 'bg-sky-500 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'" class="px-4 py-1.5 rounded-lg text-sm font-semibold transition">Presentaciones</button>
        <?php endif; ?>
    </div>

    <!-- Stock por tienda -->
    <div x-show="tab==='stock'">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        <th class="px-4 py-3">Tienda / depósito</th>
                        <th class="px-4 py-3 text-right">Hay</th>
                        <th class="px-4 py-3 text-right">Avisar si baja de</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach (($inventarios ?? []) as $inv): ?>
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-4 py-3 font-medium text-gray-800"><?= esc($inv['deposito_nombre'] ?? 'N/A') ?></td>
                        <td class="px-4 py-3 text-right font-bold text-gray-900"><?= number_format((float) $inv['existencia'], 0) ?></td>
                        <td class="px-4 py-3 text-right text-gray-500"><?= number_format((float) ($inv['minimo'] ?? 0), 0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($inventarios)): ?>
                    <tr><td colspan="3" class="px-4 py-10 text-center text-sm text-gray-400">Sin inventario registrado</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Agregar stock -->
    <div x-show="tab==='agregar'" x-cloak>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 max-w-lg">
            <p class="text-sm text-gray-500 mb-4">Suma unidades a una tienda. Queda registrado en el historial del producto.</p>
            <form action="/inventario/<?= $producto->producto_id ?>/stock" method="POST">
                <?= csrf_field() ?>
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-600 mb-1">¿En qué tienda? *</label>
                    <select name="deposito_id" required class="<?= $campo ?>">
                        <?php foreach (($inventarios ?? []) as $inv): ?>
                            <option value="<?= $inv['deposito_id'] ?>" <?= ((int) ($deposito_actual['deposito_id'] ?? 0)) === (int) $inv['deposito_id'] ? 'selected' : '' ?>><?= esc($inv['deposito_nombre'] ?? 'Depósito ' . $inv['deposito_id']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-600 mb-1">¿Cuántas unidades? *</label>
                    <input type="number" name="cantidad" min="1" step="any" required placeholder="0" class="<?= $campo ?> text-lg font-bold">
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Costo de estas unidades <span class="font-normal text-gray-400">(opcional)</span></label>
                    <input type="number" name="costo" step="0.01" min="0" placeholder="<?= $costo > 0 ? number_format($costo, 2) : '0.00' ?>" class="<?= $campo ?>">
                    <p class="text-xs text-gray-400 mt-1">
                        <?= $costo > 0 ? 'Déjalo vacío para usar el costo del producto ($' . number_format($costo, 2) . ').' : 'Este producto no tiene costo: ponlo aquí o en Editar.' ?>
                    </p>
                </div>
                <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
                    <i class="fas fa-plus"></i> Agregar stock
                </button>
            </form>
        </div>
    </div>

    <!-- Presentaciones -->
    <?php if (!empty($unidades)): ?>
    <div x-show="tab==='pres'" x-cloak>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        <th class="px-4 py-3">Presentación</th>
                        <th class="px-4 py-3 text-center">Trae (unidades)</th>
                        <th class="px-4 py-3 text-right">Precio</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($unidades as $u): $pu = (float) $u['precio_a'] > 0 ? (float) $u['precio_a'] : $precio * (float) $u['factor_conversion']; ?>
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-4 py-3 font-medium text-gray-700"><?= esc($u['nombre']) ?></td>
                        <td class="px-4 py-3 text-center text-gray-600"><?= number_format((float) $u['factor_conversion'], 0) ?></td>
                        <td class="px-4 py-3 text-right font-bold text-sky-700">$<?= number_format($pu, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
</div>

<?php $this->endSection(); ?>

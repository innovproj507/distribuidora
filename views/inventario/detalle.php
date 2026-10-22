<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<!-- Breadcrumb -->
<div class="flex items-center gap-2 text-sm text-gray-400 mb-3">
    <a href="/inventario" class="hover:text-gray-600 transition-colors">Inventario</a>
    <i class="fas fa-chevron-right text-xs"></i>
    <span class="text-gray-700 font-medium"><?= View::e($producto->nombre) ?></span>
</div>

<!-- Action bar -->
<div class="flex items-center justify-between bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-2.5 mb-4">
    <div class="flex gap-2">
        <a href="/inventario/<?= $producto->producto_id ?>/editar"
           class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
            <i class="fas fa-pen"></i> Editar
        </a>
        <a href="/inventario/<?= $producto->producto_id ?>/precios"
           class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
            <i class="fas fa-tags"></i> Precios
        </a>
        <a href="/inventario" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
            Volver
        </a>
    </div>
    <span class="px-3 py-1.5 rounded-full text-xs font-bold <?= $producto->estado === 'activo' ? 'bg-emerald-500 text-white' : 'bg-gray-400 text-white' ?>">
        <?= ucfirst($producto->estado) ?>
    </span>
</div>

<!-- Document card -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-4">
    <div class="flex items-start gap-5 mb-6">
        <?php if (!empty($producto->imagen_principal)): ?>
            <img src="/assets/uploads/<?= View::e($producto->imagen_principal) ?>" alt="" class="h-20 w-20 rounded-xl object-cover shrink-0">
        <?php else: ?>
            <div class="h-20 w-20 bg-gray-100 rounded-xl flex items-center justify-center shrink-0">
                <i class="fas fa-box text-gray-300 text-2xl"></i>
            </div>
        <?php endif; ?>
        <div>
            <h2 class="text-3xl font-bold text-gray-900"><?= View::e($producto->nombre) ?></h2>
            <?php if ($producto->descripcion): ?>
            <p class="text-sm text-gray-500 mt-1"><?= View::e($producto->descripcion) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-16">
        <div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Código</label>
                <span class="text-sm text-gray-800 font-mono"><?= View::e($producto->codigo) ?></span>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Código Barras</label>
                <span class="text-sm text-gray-600 font-mono"><?= View::e($producto->codigo_barras ?: '—') ?></span>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Marca</label>
                <span class="text-sm text-gray-600"><?= View::e($producto->marca ?: '—') ?></span>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Categoría</label>
                <span class="text-sm text-gray-600"><?= View::e($categoria->nombre ?? '—') ?></span>
            </div>
        </div>
        <div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Proveedor</label>
                <span class="text-sm text-gray-600"><?= View::e($proveedor->nombre ?? '—') ?></span>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Costo</label>
                <span class="text-sm font-semibold text-gray-800">$<?= number_format((float)$producto->costo, 2) ?></span>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">ITBMS</label>
                <span class="text-sm text-gray-600">$<?= number_format((float)$producto->itbms, 2) ?></span>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Stock Total</label>
                <span class="text-sm font-bold text-gray-900"><?= number_format($producto->stockTotal(), 0) ?> unidades</span>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<div x-data="{ tab: 'precios' }">
    <div class="flex gap-1 mb-4 bg-white rounded-2xl border border-gray-100 shadow-sm p-1 w-fit">
        <button @click="tab='precios'" :class="tab==='precios' ? 'bg-sky-500 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-1.5 rounded-lg text-sm font-semibold transition">
            Precios por Presentación
        </button>
        <button @click="tab='inventario'" :class="tab==='inventario' ? 'bg-sky-500 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-1.5 rounded-lg text-sm font-semibold transition">
            Inventario por Depósito
        </button>
        <button @click="tab='stock'" :class="tab==='stock' ? 'bg-sky-500 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-1.5 rounded-lg text-sm font-semibold transition">
            Agregar Stock
        </button>
    </div>

    <!-- Precios -->
    <div x-show="tab==='precios'">
        <?php
        $precios_map = [];
        foreach (($precios ?? []) as $p) {
            $tipo = is_array($p) ? ($p['tipo_precio'] ?? '') : ($p->tipo_precio ?? '');
            $val  = is_array($p) ? ($p['precio'] ?? 0) : ($p->precio ?? 0);
            $precios_map[$tipo] = $val;
        }
        ?>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        <th class="px-4 py-3">Presentación</th>
                        <th class="px-4 py-3 text-center">Unidades</th>
                        <th class="px-4 py-3 text-right text-sky-600">Precio A</th>
                        <th class="px-4 py-3 text-right text-emerald-600">Precio B</th>
                        <th class="px-4 py-3 text-right text-pink-600">Precio C</th>
                        <th class="px-4 py-3 text-right">Cód. Barras</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr class="bg-gray-50/50">
                        <td class="px-4 py-3 font-semibold text-gray-700">Unidad base</td>
                        <td class="px-4 py-3 text-center text-gray-400">1</td>
                        <td class="px-4 py-3 text-right font-bold text-sky-700">$<?= number_format((float)($precios_map['a'] ?? 0), 2) ?></td>
                        <td class="px-4 py-3 text-right font-bold text-emerald-700">$<?= number_format((float)($precios_map['b'] ?? 0), 2) ?></td>
                        <td class="px-4 py-3 text-right font-bold text-pink-700">$<?= number_format((float)($precios_map['c'] ?? 0), 2) ?></td>
                        <td class="px-4 py-3 text-right text-gray-300">—</td>
                    </tr>
                    <?php foreach (($unidades ?? []) as $u): ?>
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-4 py-3 font-medium text-gray-700"><?= View::e($u['nombre']) ?></td>
                        <td class="px-4 py-3 text-center text-gray-600"><?= number_format((float)$u['factor_conversion'], 2) ?></td>
                        <td class="px-4 py-3 text-right font-bold text-sky-700">$<?= number_format((float)$u['precio_a'], 2) ?></td>
                        <td class="px-4 py-3 text-right font-bold text-emerald-700">$<?= number_format((float)$u['precio_b'], 2) ?></td>
                        <td class="px-4 py-3 text-right font-bold text-pink-700">$<?= number_format((float)($u['precio_c'] ?? 0), 2) ?></td>
                        <td class="px-4 py-3 text-right text-gray-400 font-mono text-xs"><?= View::e($u['codigo_barras'] ?: '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Inventario -->
    <div x-show="tab==='inventario'" x-cloak>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        <th class="px-4 py-3">Depósito</th>
                        <th class="px-4 py-3 text-right">Existencia</th>
                        <th class="px-4 py-3 text-right">Mínimo</th>
                        <th class="px-4 py-3 text-right">Últ. Costo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach (($inventarios ?? []) as $inv): ?>
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-4 py-3 font-medium text-gray-800"><?= View::e($inv['deposito_nombre'] ?? 'N/A') ?></td>
                        <td class="px-4 py-3 text-right font-semibold text-gray-900"><?= number_format((float)$inv['existencia'], 0) ?></td>
                        <td class="px-4 py-3 text-right text-gray-500"><?= number_format((float)($inv['minimo'] ?? 0), 0) ?></td>
                        <td class="px-4 py-3 text-right text-gray-500">$<?= number_format((float)($inv['ultimo_costo'] ?? 0), 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($inventarios)): ?>
                    <tr><td colspan="4" class="px-4 py-10 text-center text-sm text-gray-400">Sin inventario registrado</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Agregar Stock -->
    <div x-show="tab==='stock'" x-cloak>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 max-w-lg">
            <form action="/inventario/<?= $producto->producto_id ?>/stock" method="POST">
                <?= View::csrf() ?>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Depósito *</label>
                    <select name="deposito_id" required
                            class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                        <option value="">Seleccionar depósito</option>
                        <?php foreach (($inventarios ?? []) as $inv): ?>
                            <option value="<?= $inv['deposito_id'] ?>"><?= View::e($inv['deposito_nombre'] ?? 'Depósito ' . $inv['deposito_id']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Cantidad *</label>
                    <input type="number" name="cantidad" min="1" required placeholder="0"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <div class="flex items-baseline gap-4 py-2 mb-6">
                    <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Costo Unitario</label>
                    <input type="number" name="costo" step="0.01" placeholder="0.00"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
                    <i class="fas fa-plus"></i> Agregar Stock
                </button>
            </form>
        </div>
    </div>
</div>

<?php View::endSection('content'); ?>

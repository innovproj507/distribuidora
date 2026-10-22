<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<!-- Breadcrumb -->
<div class="flex items-center gap-2 text-sm text-gray-400 mb-3">
    <a href="/clientes" class="hover:text-gray-600 transition-colors">Clientes</a>
    <i class="fas fa-chevron-right text-xs"></i>
    <span class="text-gray-700 font-medium"><?= View::e($cliente->nombre) ?></span>
</div>

<!-- Action bar -->
<div class="flex items-center justify-between bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-3 mb-4">
    <div class="flex gap-2">
        <a href="/clientes/<?= $cliente->cliente_id ?>/editar"
           class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
            <i class="fas fa-pen"></i> Editar
        </a>
        <a href="/clientes" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
            Volver
        </a>
    </div>
    <span class="px-3 py-1.5 rounded-full text-xs font-bold text-white <?= $cliente->estado === 'activo' ? 'bg-emerald-500' : 'bg-slate-400' ?>">
        <?= ucfirst($cliente->estado) ?>
    </span>
</div>

<!-- Document card -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-4">
    <h2 class="text-3xl font-bold text-gray-900 mb-1"><?= View::e($cliente->nombre) ?></h2>
    <p class="text-sm text-gray-400 mb-6">
        <span class="capitalize"><?= View::e($cliente->tipo) ?></span>
        <?php if ($cliente->codigo): ?> · <span class="font-mono"><?= View::e($cliente->codigo) ?></span><?php endif; ?>
    </p>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-16">
        <div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">RUC</label>
                <span class="text-sm text-gray-800"><?= View::e($cliente->ruc ?: '—') ?></span>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">DV</label>
                <span class="text-sm text-gray-600"><?= View::e($cliente->dv ?: '—') ?></span>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Teléfono</label>
                <span class="text-sm text-gray-600"><?= View::e($cliente->telefono ?: '—') ?></span>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Email</label>
                <span class="text-sm text-gray-600"><?= View::e($cliente->email ?: '—') ?></span>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Dirección</label>
                <span class="text-sm text-gray-600"><?= View::e($cliente->direccion ?: '—') ?></span>
            </div>
        </div>
        <div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Límite Crédito</label>
                <span class="text-sm text-gray-800 font-medium">$<?= number_format((float)$cliente->limite_credito, 2) ?></span>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Saldo</label>
                <span class="text-sm font-semibold <?= (float)$cliente->saldo > 0 ? 'text-red-600' : 'text-emerald-600' ?>">
                    $<?= number_format((float)$cliente->saldo, 2) ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<div x-data="{ tab: 'compras' }">
    <div class="flex gap-1 mb-4 bg-white rounded-2xl border border-gray-100 shadow-sm p-1 w-fit">
        <button @click="tab='compras'" :class="tab==='compras' ? 'bg-sky-500 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-1.5 rounded-lg text-sm font-semibold transition">
            Historial de Compras
        </button>
        <button @click="tab='direcciones'" :class="tab==='direcciones' ? 'bg-sky-500 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-1.5 rounded-lg text-sm font-semibold transition">
            Direcciones
        </button>
    </div>

    <!-- Historial de compras -->
    <div x-show="tab==='compras'">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                        <th class="px-5 py-3.5 bg-gray-50/80">Factura</th>
                        <th class="px-5 py-3.5 bg-gray-50/80 text-right">Total</th>
                        <th class="px-5 py-3.5 bg-gray-50/80">Estado</th>
                        <th class="px-5 py-3.5 bg-gray-50/80">Fecha</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach (($ventas ?? []) as $v): ?>
                    <tr class="hover:bg-sky-50/40 transition-colors">
                        <td class="px-5 py-3.5">
                            <a href="/ventas/venta/<?= $v['venta_id'] ?>" class="text-sky-600 hover:text-sky-700 font-bold">
                                <?= View::e($v['numero_factura']) ?>
                            </a>
                        </td>
                        <td class="px-5 py-3.5 text-right font-bold text-gray-800">$<?= number_format((float)$v['total'], 2) ?></td>
                        <td class="px-5 py-3.5">
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold text-white <?= $v['estado'] === 'pagada' ? 'bg-emerald-500' : 'bg-slate-400' ?>">
                                <?= ucfirst($v['estado']) ?>
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-gray-400 text-xs"><?= date('d/m/Y', strtotime($v['fecha'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($ventas)): ?>
                    <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-gray-400">Sin compras registradas</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Direcciones -->
    <div x-show="tab==='direcciones'" x-cloak>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <?php foreach (($direcciones ?? []) as $dir): ?>
            <div class="py-3 border-b border-gray-50 last:border-0">
                <p class="text-sm text-gray-800"><?= View::e($dir->direccion) ?></p>
                <?php if ($dir->telefono): ?>
                <p class="text-xs text-gray-400 mt-0.5"><i class="fas fa-phone text-xs mr-1"></i><?= View::e($dir->telefono) ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (empty($direcciones)): ?>
            <p class="text-sm text-gray-400 mb-4">No hay direcciones registradas.</p>
            <?php endif; ?>
            <form action="/clientes/<?= $cliente->cliente_id ?>/direccion" method="POST" class="mt-4 pt-4 border-t border-gray-50">
                <?= View::csrf() ?>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-28 shrink-0">Dirección</label>
                    <input type="text" name="direccion" required placeholder="Nueva dirección"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <div class="flex items-baseline gap-4 py-2 mb-4">
                    <label class="text-sm font-semibold text-gray-600 w-28 shrink-0">Teléfono</label>
                    <input type="text" name="telefono" placeholder="Teléfono"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
                    <i class="fas fa-plus"></i> Agregar Dirección
                </button>
            </form>
        </div>
    </div>
</div>

<?php View::endSection('content'); ?>

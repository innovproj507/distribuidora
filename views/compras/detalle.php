<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<?php
$est = $compra['estado'] ?? '';
$hayParcial = in_array($est, ['pendiente', 'parcialmente_recibida']);

$pipelineStates = [
    ['label' => 'Pendiente',        'key' => 'pendiente'],
    ['label' => 'Parcial',          'key' => 'parcialmente_recibida'],
    ['label' => 'Recibida',         'key' => 'recibida'],
];
$cancelada = $est === 'cancelada';
?>

<!-- Breadcrumb -->
<div class="flex items-center gap-2 text-sm text-gray-400 mb-3">
    <a href="/compras" class="hover:text-gray-600 transition-colors">Compras</a>
    <i class="fas fa-chevron-right text-xs"></i>
    <span class="text-gray-700 font-medium"><?= View::e($compra['numero_factura'] ?? '') ?></span>
</div>

<!-- Action bar -->
<div class="flex items-center justify-between bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-2.5 mb-4">
    <div class="flex gap-2">
        <?php if ($hayParcial): ?>
        <a href="/compras/<?= $compra['compra_id'] ?>/recibir"
           class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
            <i class="fas fa-truck-loading"></i> Registrar Recepción
        </a>
        <?php endif; ?>
        <a href="/compras" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
            Volver
        </a>
    </div>

    <?php if ($cancelada): ?>
    <span class="px-3 py-1.5 rounded-full text-xs font-bold bg-red-500 text-white">Cancelada</span>
    <?php else: ?>
    <!-- Pipeline -->
    <div class="flex items-stretch text-xs font-semibold select-none">
        <?php foreach ($pipelineStates as $i => $stage):
            $isActive = $est === $stage['key'];
            $isPast = ($est === 'recibida' && $stage['key'] !== 'recibida') || ($est === 'parcialmente_recibida' && $stage['key'] === 'pendiente');
            $bgActive = $isActive || $isPast ? 'bg-sky-500 text-white' : 'bg-gray-100 text-gray-400';
            $bgArrow  = $isActive || $isPast ? 'bg-sky-500' : 'bg-gray-100';
            $isFirst = $i === 0;
            $isLast  = $i === count($pipelineStates) - 1;
        ?>
        <div class="flex items-center <?= $bgActive ?> <?= $isFirst ? 'pl-4 rounded-l-lg' : 'pl-7' ?> <?= $isLast ? 'pr-5 rounded-r-lg' : 'pr-6' ?> py-2 relative">
            <?= $stage['label'] ?>
            <?php if (!$isLast): ?>
            <span class="absolute right-0 top-0 h-full w-3 overflow-hidden translate-x-2.5 z-10">
                <span class="block w-6 h-6 <?= $bgArrow ?> rotate-45 origin-top-left mt-0.5 ml-0.5"></span>
            </span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Document card -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-4">
    <h2 class="text-3xl font-bold text-gray-900 mb-6">Compra #<?= View::e($compra['numero_factura'] ?? '') ?></h2>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-16">
        <div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Proveedor</label>
                <span class="text-sm text-gray-800"><?= View::e($compra['proveedor_nombre'] ?? 'N/A') ?></span>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Depósito</label>
                <span class="text-sm text-gray-600"><?= View::e($compra['deposito_nombre'] ?? 'N/A') ?></span>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Factura Proveedor</label>
                <span class="text-sm text-gray-600 font-mono"><?= View::e($compra['numero_factura_proveedor'] ?? '—') ?></span>
            </div>
        </div>
        <div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Fecha</label>
                <span class="text-sm text-gray-600"><?= $compra['fecha_compra'] ?? '' ?></span>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Registrado por</label>
                <span class="text-sm text-gray-600"><?= View::e($compra['usuario_nombre'] ?? 'N/A') ?></span>
            </div>
            <?php if (!empty($compra['notas'])): ?>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Notas</label>
                <span class="text-sm text-gray-600"><?= View::e($compra['notas']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Items table -->
<?php if (!empty($detalles)): ?>
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50/80 border-b border-gray-100">
                <tr class="text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3">Código</th>
                    <th class="px-4 py-3">Producto</th>
                    <th class="px-4 py-3 text-right">Pedido</th>
                    <th class="px-4 py-3 text-right">Recibido</th>
                    <?php if ($hayParcial): ?>
                    <th class="px-4 py-3 text-right text-amber-600">Pendiente</th>
                    <?php endif; ?>
                    <th class="px-4 py-3 text-right">Costo Unit.</th>
                    <th class="px-4 py-3 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($detalles as $d):
                    $pendiente = (float)($d['cantidad_pendiente'] ?? 0);
                ?>
                <tr class="hover:bg-gray-50/50 transition-colors <?= $pendiente > 0 ? 'bg-amber-50/40' : '' ?>">
                    <td class="px-4 py-3 font-mono text-xs text-gray-400"><?= View::e($d['producto_codigo'] ?? '—') ?></td>
                    <td class="px-4 py-3 font-medium text-gray-800"><?= View::e($d['producto_nombre'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-right"><?= number_format((float)($d['cantidad'] ?? 0), 2) ?></td>
                    <td class="px-4 py-3 text-right font-medium <?= (float)$d['cantidad_recibida'] > 0 ? 'text-emerald-700' : 'text-gray-400' ?>">
                        <?= number_format((float)($d['cantidad_recibida'] ?? 0), 2) ?>
                    </td>
                    <?php if ($hayParcial): ?>
                    <td class="px-4 py-3 text-right font-medium <?= $pendiente > 0 ? 'text-amber-700' : 'text-gray-400' ?>">
                        <?= $pendiente > 0 ? number_format($pendiente, 2) : '—' ?>
                    </td>
                    <?php endif; ?>
                    <td class="px-4 py-3 text-right text-gray-600">$<?= number_format((float)($d['costo'] ?? 0), 2) ?></td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-800">$<?= number_format((float)($d['total_linea'] ?? 0), 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="bg-gray-50 border-t-2 border-gray-100">
                <?php $cols = $hayParcial ? 6 : 5; ?>
                <tr><td colspan="<?= $cols ?>" class="px-4 py-2 text-right text-xs text-gray-500">Subtotal</td><td class="px-4 py-2 text-right font-medium text-sm">$<?= number_format((float)($compra['monto_subtotal'] ?? 0), 2) ?></td></tr>
                <tr><td colspan="<?= $cols ?>" class="px-4 py-2 text-right text-xs text-gray-500">ITBMS</td><td class="px-4 py-2 text-right font-medium text-sm">$<?= number_format((float)($compra['monto_itbms'] ?? 0), 2) ?></td></tr>
                <tr><td colspan="<?= $cols ?>" class="px-4 py-3 text-right font-bold text-gray-800">Total</td><td class="px-4 py-3 text-right font-bold text-xl text-gray-900">$<?= number_format((float)($compra['monto_total'] ?? 0), 2) ?></td></tr>
            </tfoot>
        </table>
    </div>
</div>
<?php endif; ?>

<?php View::endSection('content'); ?>

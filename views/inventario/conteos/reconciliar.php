<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<!-- Resumen -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-sky-400">
        <p class="text-xs text-gray-500">Productos contados</p>
        <p class="text-3xl font-black text-sky-600"><?= $totales['productos'] ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-amber-400">
        <p class="text-xs text-gray-500">Con diferencia</p>
        <p class="text-3xl font-black text-amber-600"><?= $totales['diferencias'] ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-emerald-400">
        <p class="text-xs text-gray-500">Sobrantes</p>
        <p class="text-3xl font-black text-emerald-600"><?= $totales['sobrantes'] ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-red-400">
        <p class="text-xs text-gray-500">Faltantes</p>
        <p class="text-3xl font-black text-red-600"><?= $totales['faltantes'] ?></p>
    </div>
</div>

<?php if ($conteo['estado'] === 'en_proceso' && $totales['diferencias'] > 0): ?>
<div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-5 flex items-start gap-3">
    <i class="fas fa-exclamation-triangle text-amber-500 mt-0.5"></i>
    <div class="flex-1">
        <p class="font-semibold text-amber-800 text-sm">Hay <?= $totales['diferencias'] ?> producto(s) con diferencia.</p>
        <p class="text-xs text-amber-700 mt-0.5">Al aplicar ajustes se actualizará el inventario y se registrará un movimiento de ajuste en el kardex.</p>
    </div>
    <form method="POST" action="/inventario/conteos/<?= $conteo['conteo_id'] ?>/ajustar">
        <?= View::csrf() ?>
        <button class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-sm font-bold shadow-sm transition">
            <i class="fas fa-sync-alt mr-1"></i> Aplicar ajustes
        </button>
    </form>
</div>
<?php elseif ($conteo['estado'] === 'completado'): ?>
<div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 mb-5 flex items-center gap-3">
    <i class="fas fa-check-circle text-emerald-500 text-xl"></i>
    <p class="text-emerald-800 font-semibold text-sm">Conteo completado el <?= date('d/m/Y H:i', strtotime($conteo['fecha_cierre'])) ?>. Ajustes aplicados al inventario.</p>
</div>
<?php endif; ?>

<!-- Tabla de diferencias -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="px-5 py-3 bg-gray-50 border-b flex items-center justify-between">
        <span class="font-semibold text-gray-700 text-sm">Detalle por producto</span>
        <div class="flex gap-2 text-xs text-gray-400">
            <span class="w-3 h-3 inline-block bg-red-100 rounded"></span> Faltante
            <span class="w-3 h-3 inline-block bg-green-100 rounded ml-2"></span> Sobrante
        </div>
    </div>
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
            <tr>
                <th class="px-4 py-3 text-left">Producto</th>
                <th class="px-4 py-3 text-right">Sistema</th>
                <th class="px-4 py-3 text-right">Contado</th>
                <th class="px-4 py-3 text-right">Diferencia</th>
                <th class="px-4 py-3 text-right">Costo unit.</th>
                <th class="px-4 py-3 text-right">Impacto $</th>
                <th class="px-4 py-3 text-center">Estado</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
        <?php foreach ($items as $item):
            $dif    = (float) $item['diferencia'];
            $rowBg  = $dif < 0 ? 'bg-red-50' : ($dif > 0 ? 'bg-green-50' : '');
            $difColor = $dif < 0 ? 'text-red-600' : ($dif > 0 ? 'text-emerald-600' : 'text-gray-400');
        ?>
        <tr class="<?= $rowBg ?> hover:opacity-90 transition-opacity">
            <td class="px-4 py-2 font-medium text-gray-800"><?= View::e($item['nombre']) ?></td>
            <td class="px-4 py-2 text-right"><?= number_format((float)$item['cantidad_sistema'], 2) ?></td>
            <td class="px-4 py-2 text-right font-semibold"><?= number_format((float)$item['cantidad_contada'], 2) ?></td>
            <td class="px-4 py-2 text-right font-bold <?= $difColor ?>">
                <?= ($dif > 0 ? '+' : '') . number_format($dif, 2) ?>
            </td>
            <td class="px-4 py-2 text-right text-gray-500">$<?= number_format((float)$item['costo_unitario'], 4) ?></td>
            <td class="px-4 py-2 text-right font-semibold <?= (float)$item['impacto_costo'] < 0 ? 'text-red-600' : 'text-emerald-600' ?>">
                <?= ($item['impacto_costo'] > 0 ? '+' : '') ?>$<?= number_format((float)$item['impacto_costo'], 2) ?>
            </td>
            <td class="px-4 py-2 text-center">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium
                    <?= $item['estado'] === 'ajustado' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' ?>">
                    <?= ucfirst($item['estado']) ?>
                </span>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($items)): ?>
        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Aún no hay productos contados. <a href="/inventario/conteos/<?= $conteo['conteo_id'] ?>/contar" class="text-sky-600 hover:underline">Ir a contar</a></td></tr>
        <?php endif; ?>
        </tbody>
        <?php if (!empty($items)): ?>
        <tfoot class="bg-gray-50 font-semibold text-sm">
            <tr>
                <td class="px-4 py-3 text-gray-600" colspan="5">Impacto total en valor de inventario</td>
                <td class="px-4 py-3 text-right <?= $totales['impacto'] < 0 ? 'text-red-600' : 'text-emerald-600' ?>">
                    <?= ($totales['impacto'] > 0 ? '+' : '') ?>$<?= number_format($totales['impacto'], 2) ?>
                </td>
                <td></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
</div>

<div class="mt-4 flex justify-between">
    <a href="/inventario/conteos" class="text-sm text-gray-500 hover:text-gray-700 transition">
        <i class="fas fa-arrow-left mr-1"></i> Volver a conteos
    </a>
    <?php if ($conteo['estado'] === 'en_proceso'): ?>
    <a href="/inventario/conteos/<?= $conteo['conteo_id'] ?>/contar"
       class="px-4 py-2 bg-sky-50 text-sky-700 rounded-lg text-sm font-medium hover:bg-sky-100 transition">
        <i class="fas fa-edit mr-1"></i> Editar conteo
    </a>
    <?php endif; ?>
</div>

<?php View::endSection('content'); ?>

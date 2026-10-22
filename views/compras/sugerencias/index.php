<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Órdenes de Compra Sugeridas</h2>
            <p class="text-slate-500 text-sm">Generadas por punto de reorden y demanda histórica</p>
        </div>
        <form method="POST" action="/compras/sugerencias/generar" onsubmit="return confirm('¿Regenerar sugerencias? Se eliminarán las pendientes actuales.')">
            <?= View::csrf() ?>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-5 rounded-xl flex items-center gap-2">
                <i class="fas fa-sync-alt"></i> Regenerar
            </button>
        </form>
    </div>

    <!-- Resumen -->
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
            <p class="text-sm text-gray-500">Por stock mínimo</p>
            <p class="text-3xl font-bold text-red-600"><?= $totales['stock_minimo'] ?></p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
            <p class="text-sm text-gray-500">Por demanda histórica</p>
            <p class="text-3xl font-bold text-amber-600"><?= $totales['demanda_historica'] ?></p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
            <p class="text-sm text-gray-500">Monto estimado total</p>
            <p class="text-3xl font-bold text-blue-600">$<?= number_format($totales['monto'], 2) ?></p>
        </div>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-5 py-3 text-left font-bold text-slate-500 uppercase text-xs">Producto</th>
                        <th class="px-5 py-3 text-left font-bold text-slate-500 uppercase text-xs">Depósito</th>
                        <th class="px-5 py-3 text-left font-bold text-slate-500 uppercase text-xs">Proveedor</th>
                        <th class="px-5 py-3 text-center font-bold text-slate-500 uppercase text-xs">Motivo</th>
                        <th class="px-5 py-3 text-right font-bold text-slate-500 uppercase text-xs">Cant. sugerida</th>
                        <th class="px-5 py-3 text-right font-bold text-slate-500 uppercase text-xs">Costo est.</th>
                        <th class="px-5 py-3 text-right font-bold text-slate-500 uppercase text-xs">Total est.</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($sugerencias as $s): ?>
                    <tr class="hover:bg-slate-50/50">
                        <td class="px-5 py-3">
                            <div class="font-medium text-slate-800"><?= View::e($s['producto_nombre']) ?></div>
                            <div class="text-xs text-slate-400 font-mono"><?= View::e($s['producto_codigo']) ?></div>
                        </td>
                        <td class="px-5 py-3 text-slate-600"><?= View::e($s['deposito_nombre']) ?></td>
                        <td class="px-5 py-3 text-slate-600"><?= $s['proveedor_nombre'] ? View::e($s['proveedor_nombre']) : '<span class="text-slate-300 italic">Sin asignar</span>' ?></td>
                        <td class="px-5 py-3 text-center">
                            <?php if ($s['motivo'] === 'stock_minimo'): ?>
                                <span class="px-2 py-0.5 bg-red-100 text-red-700 rounded-full text-xs font-bold">Stock mínimo</span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full text-xs font-bold">Demanda</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3 text-right font-bold"><?= number_format((float)$s['cantidad_sugerida'], 2) ?></td>
                        <td class="px-5 py-3 text-right">$<?= number_format((float)$s['costo_estimado'], 4) ?></td>
                        <td class="px-5 py-3 text-right font-bold text-blue-600">$<?= number_format((float)$s['total_estimado'], 2) ?></td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <form method="POST" action="/compras/sugerencias/<?= $s['sugerencia_id'] ?>/convertir">
                                    <?= View::csrf() ?>
                                    <button type="submit" title="Crear orden pendiente"
                                            class="text-xs px-3 py-1 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 font-medium">
                                        <i class="fas fa-file-invoice mr-1"></i>Crear OC
                                    </button>
                                </form>
                                <form method="POST" action="/compras/sugerencias/<?= $s['sugerencia_id'] ?>/descartar">
                                    <?= View::csrf() ?>
                                    <button type="submit" title="Descartar sugerencia"
                                            class="text-xs px-2 py-1 text-slate-400 hover:text-red-500 transition-colors">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($sugerencias)): ?>
                    <tr>
                        <td colspan="8" class="px-5 py-16 text-center text-slate-400 italic">
                            No hay sugerencias pendientes. Haz clic en <strong>Regenerar</strong> para analizar el inventario.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php View::endSection('content'); ?>

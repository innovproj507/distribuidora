<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<!-- Stat cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-blue-500 to-blue-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Total Lotes</p>
                <h3 class="text-xl font-extrabold mt-1"><?= $stats['total'] ?? 0 ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-boxes"></i></span>
        </div>
    </div>

    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-orange-500 to-orange-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Por Vencer</p>
                <h3 class="text-xl font-extrabold mt-1"><?= $stats['por_vencer'] ?? 0 ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-clock"></i></span>
        </div>
    </div>

    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-red-500 to-red-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Vencidos</p>
                <h3 class="text-xl font-extrabold mt-1"><?= $stats['vencidos'] ?? 0 ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-exclamation-circle"></i></span>
        </div>
    </div>

    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-gray-400 to-gray-500 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Sin Fecha</p>
                <h3 class="text-xl font-extrabold mt-1"><?= $stats['sin_fecha'] ?? 0 ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-infinity"></i></span>
        </div>
    </div>
</div>

<!-- Table card -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100">
    <div class="flex justify-between items-center p-6 border-b border-gray-100">
        <h2 class="text-lg font-semibold text-gray-800">Lotes de Inventario</h2>
        <form method="POST" action="/inventario/lotes/marcar-vencidos" onsubmit="return confirm('¿Marcar todos los lotes vencidos como vencido?');">
            <?= View::csrf() ?>
            <button type="submit" class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                <i class="fas fa-skull-crossbones"></i> Marcar Vencidos
            </button>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Producto</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Código</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">N° Lote</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Depósito</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Stock Lote</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Fabricación</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Vencimiento</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Días</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach (($lotes ?? []) as $lote): ?>
                <?php
                    $estadoVenc = $lote['estado_vencimiento'] ?? 'sin_vencimiento';
                    $dias = $lote['dias_para_vencer'];

                    // Badge color
                    switch ($estadoVenc) {
                        case 'vencido':
                            $badgeCls = 'bg-red-500 text-white';
                            $badgeLabel = 'Vencido';
                            break;
                        case 'por_vencer':
                            $badgeCls = 'bg-amber-500 text-white';
                            $badgeLabel = 'Por Vencer';
                            break;
                        case 'vigente':
                            $badgeCls = 'bg-emerald-500 text-white';
                            $badgeLabel = 'Vigente';
                            break;
                        default:
                            $badgeCls = 'bg-gray-400 text-white';
                            $badgeLabel = 'Sin Vencimiento';
                    }

                    // Días color
                    if ($dias === null) {
                        $diasHtml = '<span class="text-gray-400">N/A</span>';
                    } elseif ((int)$dias < 0) {
                        $diasHtml = '<span class="font-bold text-red-600">' . (int)$dias . '</span>';
                    } elseif ((int)$dias <= 30) {
                        $diasHtml = '<span class="font-bold text-amber-600">' . (int)$dias . '</span>';
                    } else {
                        $diasHtml = '<span class="font-bold text-green-600">' . (int)$dias . '</span>';
                    }
                ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 text-sm font-medium text-gray-800"><?= View::e($lote['producto_nombre']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-500"><?= View::e($lote['codigo']) ?></td>
                    <td class="px-4 py-3 text-sm font-mono text-gray-700"><?= View::e($lote['numero_lote']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= View::e($lote['deposito_nombre']) ?></td>
                    <td class="px-4 py-3 text-right text-sm font-bold text-gray-800"><?= number_format((float)$lote['cantidad_actual'], 2) ?></td>
                    <td class="px-4 py-3 text-center text-sm text-gray-500">
                        <?= $lote['fecha_fabricacion'] ? date('d/m/Y', strtotime($lote['fecha_fabricacion'])) : '<span class="text-gray-300">—</span>' ?>
                    </td>
                    <td class="px-4 py-3 text-center text-sm text-gray-600">
                        <?= $lote['fecha_vencimiento'] ? date('d/m/Y', strtotime($lote['fecha_vencimiento'])) : '<span class="text-gray-300">—</span>' ?>
                    </td>
                    <td class="px-4 py-3 text-center text-sm"><?= $diasHtml ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-1 text-xs font-bold rounded-full <?= $badgeCls ?>"><?= $badgeLabel ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($lotes)): ?>
                <tr>
                    <td colspan="9" class="px-4 py-12 text-center text-sm text-gray-400 italic">
                        No hay lotes registrados. Los lotes se crean al registrar compras con número de lote.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php View::endSection('content'); ?>

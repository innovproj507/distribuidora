<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="rounded-2xl p-6 shadow-sm bg-gradient-to-br from-rose-500 to-rose-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div>
                <p class="text-xs font-bold text-white/80 uppercase tracking-widest">Deuda Total</p>
                <h3 class="text-2xl font-black mt-1">$<?= number_format((float)($stats['total_deuda'] ?? 0), 2) ?></h3>
            </div>
            <span class="w-11 h-11 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-hand-holding-usd text-xl"></i></span>
        </div>
    </div>
    <div class="rounded-2xl p-6 shadow-sm bg-gradient-to-br from-amber-500 to-amber-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div>
                <p class="text-xs font-bold text-white/80 uppercase tracking-widest">Clientes Deudores</p>
                <h3 class="text-2xl font-black mt-1"><?= $stats['total_deudores'] ?? 0 ?></h3>
            </div>
            <span class="w-11 h-11 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-users-slash text-xl"></i></span>
        </div>
    </div>
    <div class="rounded-2xl p-6 shadow-sm bg-gradient-to-br from-emerald-500 to-emerald-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div>
                <p class="text-xs font-bold text-white/80 uppercase tracking-widest">Crédito Utilizado</p>
                <h3 class="text-2xl font-black mt-1">
                    <?php
                    $totalCupo = (float)($stats['total_cupo'] ?? 0);
                    $totalDeuda = (float)($stats['total_deuda'] ?? 0);
                    $porcentaje = $totalCupo > 0 ? ($totalDeuda / $totalCupo) * 100 : 0;
                    echo number_format($porcentaje, 1) . '%';
                    ?>
                </h3>
            </div>
            <span class="w-11 h-11 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-chart-line text-xl"></i></span>
        </div>
    </div>
</div>

<div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/30">
        <h3 class="text-lg font-bold text-slate-800">Cuentas Pendientes</h3>
        <button class="text-blue-500 hover:text-blue-500/80 font-bold text-sm transition-all flex items-center gap-2">
            <i class="fas fa-file-export"></i> Exportar Reporte
        </button>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100">
            <thead class="bg-slate-50/50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase">Cliente</th>
                    <th class="px-6 py-4 text-right text-xs font-bold text-slate-400 uppercase">Cupo Disponible</th>
                    <th class="px-6 py-4 text-right text-xs font-bold text-slate-400 uppercase">Saldo Pendiente</th>
                    <th class="px-6 py-4 text-center text-xs font-bold text-slate-400 uppercase">Último Crédito</th>
                    <th class="px-6 py-4 text-right text-xs font-bold text-slate-400 uppercase">Estado</th>
                    <th class="px-6 py-4"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                <?php foreach ($cuentas as $c): ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4">
                        <div class="flex flex-col">
                            <span class="text-sm font-bold text-slate-700"><?= htmlspecialchars($c['nombre']) ?></span>
                            <span class="text-[10px] text-slate-400 font-medium"><?= htmlspecialchars($c['ruc']) ?> • <?= htmlspecialchars($c['telefono']) ?></span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <span class="text-sm font-medium text-slate-500">$<?= number_format($c['cupo_credito'] - $c['saldo_pendiente'], 2) ?></span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <span class="text-sm font-black text-rose-600">$<?= number_format($c['saldo_pendiente'], 2) ?></span>
                    </td>
                    <td class="px-6 py-4 text-center whitespace-nowrap">
                        <span class="text-xs text-slate-500"><?= $c['ultima_venta_credito'] ? date('d/m/Y', strtotime($c['ultima_venta_credito'])) : 'N/A' ?></span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <?php 
                        $porcOcupado = ((float)$c['saldo_pendiente'] / ((float)$c['cupo_credito'] ?: 1)) * 100;
                        $color = $porcOcupado > 80 ? 'rose' : ($porcOcupado > 50 ? 'amber' : 'emerald');
                        ?>
                        <div class="flex items-center justify-end gap-2">
                            <div class="w-16 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full bg-<?= $color ?>-500" style="width: <?= min($porcOcupado, 100) ?>%"></div>
                            </div>
                            <span class="text-[10px] font-bold text-<?= $color ?>-600"><?= round($porcOcupado) ?>%</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <a href="/clientes/<?= $c['cliente_id'] ?>" title="Ver"
                           class="inline-flex w-8 h-8 rounded-lg bg-blue-500 text-white hover:bg-blue-600 transition-colors items-center justify-center shadow-sm shadow-blue-500/30">
                            <i class="fas fa-chevron-right text-xs"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($cuentas)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-20 text-center">
                        <div class="flex flex-col items-center gap-4">
                            <div class="w-16 h-16 bg-emerald-50 rounded-full flex items-center justify-center">
                                <i class="fas fa-check-double text-2xl text-emerald-400"></i>
                            </div>
                            <p class="text-slate-400 italic">¡Excelente! No hay cuentas pendientes por cobrar.</p>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php View::endSection('content'); ?>

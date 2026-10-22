<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<div class="flex justify-between items-center mb-8">
    <div>
        <h2 class="text-2xl font-black text-slate-800 tracking-tight"><?= $page_title ?></h2>
        <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-1"><?= $page_subtitle ?></p>
    </div>
    <div class="flex gap-3">
        <button class="px-6 py-2.5 bg-emerald-600 text-white font-bold rounded-xl hover:bg-emerald-700 shadow-lg shadow-emerald-100 transition-all text-sm flex items-center gap-2">
            <i class="fas fa-file-export"></i> Exportar Reporte
        </button>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
    <!-- Métricas de Cobro -->
    <div class="lg:col-span-1 space-y-6">
        <div class="bg-slate-900 rounded-3xl p-6 text-white shadow-xl relative overflow-hidden">
            <p class="text-[10px] font-black opacity-50 uppercase tracking-widest mb-1">Recaudación Mensual</p>
            <h3 class="text-3xl font-black text-indigo-400">$<?= number_format(array_sum(array_column($cobros, 'precio')), 2) ?></h3>
            <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase">Estimado basado en planes activos</p>
            <div class="absolute -right-8 -bottom-8 w-24 h-24 bg-white/5 rounded-full blur-xl"></div>
        </div>

        <div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm">
            <h3 class="text-xs font-black text-slate-800 mb-4 uppercase tracking-widest">Resumen por Plan</h3>
            <div class="space-y-4">
                <?php 
                $planesContador = [];
                foreach ($cobros as $c) {
                    $planesContador[$c['plan_nombre']] = ($planesContador[$c['plan_nombre']] ?? 0) + 1;
                }
                foreach ($planesContador as $nombre => $cant):
                ?>
                <div class="flex justify-between items-center text-sm">
                    <span class="text-slate-500 font-medium"><?= $nombre ?></span>
                    <span class="font-black text-slate-800"><?= $cant ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Tabla de Suscripciones -->
    <div class="lg:col-span-3">
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100">
                    <thead class="bg-slate-50/50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest">Empresa</th>
                            <th class="px-6 py-4 text-center text-xs font-black text-slate-400 uppercase tracking-widest">Plan Actual</th>
                            <th class="px-6 py-4 text-right text-xs font-black text-slate-400 uppercase tracking-widest">Cuota Mensual</th>
                            <th class="px-6 py-4 text-center text-xs font-black text-slate-400 uppercase tracking-widest">Próximo Cobro</th>
                            <th class="px-6 py-4 text-center text-xs font-black text-slate-400 uppercase tracking-widest">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($cobros as $c): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors group">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-slate-700">
                                <?= htmlspecialchars($c['nombre_comercial']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 bg-indigo-50 text-indigo-600 text-[9px] font-black uppercase rounded border border-indigo-100">
                                    <?= htmlspecialchars($c['plan_nombre']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-black text-slate-800">
                                $<?= number_format($c['precio'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-xs text-slate-400 font-bold">
                                <?= date('d M, Y', strtotime($c['fecha_registro'] . ' + 1 month')) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-0.5 bg-emerald-50 text-emerald-600 text-[9px] font-black uppercase rounded border border-emerald-100">Al Día</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php View::endSection('content'); ?>

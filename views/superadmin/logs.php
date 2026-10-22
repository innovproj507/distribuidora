<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="p-8 border-b border-slate-50 flex justify-between items-center">
        <div>
            <h2 class="text-xl font-black text-slate-800 tracking-tight">Registro de Actividad</h2>
            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-1">Audit Log Global</p>
        </div>
        <button class="px-4 py-2 bg-slate-100 text-slate-500 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-slate-200 transition-all">
            <i class="fas fa-filter mr-2"></i> Filtrar Eventos
        </button>
    </div>

    <div class="p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-50">
                <thead class="bg-slate-50/30">
                    <tr>
                        <th class="px-8 py-4 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Fecha y Hora</th>
                        <th class="px-8 py-4 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Tipo de Evento</th>
                        <th class="px-8 py-4 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Detalle / Recurso</th>
                        <th class="px-8 py-4 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($logs as $log): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-8 py-4 whitespace-nowrap">
                            <span class="text-xs font-bold text-slate-500 font-mono"><?= date('d/m/Y H:i', strtotime($log['fecha'])) ?></span>
                        </td>
                        <td class="px-8 py-4 whitespace-nowrap">
                            <?php 
                            $color = str_contains($log['tipo'], 'EMPRESA') ? 'indigo' : 'emerald';
                            ?>
                            <span class="px-2 py-1 bg-<?= $color ?>-50 text-<?= $color ?>-600 text-[9px] font-black uppercase rounded border border-<?= $color ?>-100">
                                <?= $log['tipo'] ?>
                            </span>
                        </td>
                        <td class="px-8 py-4 whitespace-nowrap">
                            <span class="text-sm font-bold text-slate-700"><?= htmlspecialchars($log['detalle']) ?></span>
                        </td>
                        <td class="px-8 py-4 whitespace-nowrap text-center">
                            <i class="fas fa-check-circle text-emerald-400 text-xs"></i>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-8 p-6 bg-indigo-50 rounded-2xl border border-indigo-100 flex items-center gap-4">
    <div class="w-10 h-10 bg-indigo-600 text-white rounded-xl flex items-center justify-center shadow-lg shadow-indigo-100">
        <i class="fas fa-info-circle"></i>
    </div>
    <div class="flex-1">
        <h4 class="text-sm font-bold text-indigo-900">Nota del Sistema</h4>
        <p class="text-xs text-indigo-700">Esta vista combina eventos de empresas y accesos de usuarios de las últimas 24 horas. Los logs de errores de bajo nivel deben consultarse directamente en el servidor.</p>
    </div>
</div>

<?php View::endSection('content'); ?>

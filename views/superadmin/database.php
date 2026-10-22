<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<div class="flex justify-between items-center mb-8">
    <div>
        <h2 class="text-2xl font-black text-slate-800 tracking-tight">Monitor de Base de Datos</h2>
        <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-1">SaaS Infrastucture</p>
    </div>
    <div class="flex gap-3">
        <a href="/master/mantenimiento/optimizar" class="px-6 py-2.5 bg-slate-100 text-slate-600 font-bold rounded-xl hover:bg-slate-200 transition-all text-sm flex items-center gap-2">
            <i class="fas fa-broom"></i> Optimizar Tablas
        </a>
        <a href="/master/mantenimiento/backup" class="px-6 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 shadow-lg shadow-indigo-100 transition-all text-sm flex items-center gap-2">
            <i class="fas fa-download"></i> Descargar Backup
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
    <!-- Tabla de Estado -->
    <div class="lg:col-span-3">
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100">
                    <thead class="bg-slate-50/50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest">Nombre de Tabla</th>
                            <th class="px-6 py-4 text-center text-xs font-black text-slate-400 uppercase tracking-widest">Registros</th>
                            <th class="px-6 py-4 text-right text-xs font-black text-slate-400 uppercase tracking-widest">Data (MB)</th>
                            <th class="px-6 py-4 text-right text-xs font-black text-slate-400 uppercase tracking-widest">Índices (MB)</th>
                            <th class="px-6 py-4 text-center text-xs font-black text-slate-400 uppercase tracking-widest">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($tables as $t): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors group">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-slate-50 text-slate-400 flex items-center justify-center group-hover:bg-indigo-50 group-hover:text-indigo-500 transition-all">
                                        <i class="fas fa-table text-xs"></i>
                                    </div>
                                    <span class="text-sm font-bold text-slate-700"><?= $t['TABLE_NAME'] ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-bold text-slate-500">
                                <?= number_format($t['TABLE_ROWS']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-black text-slate-600">
                                <?= number_format($t['DATA_LENGTH'] / 1024 / 1024, 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-slate-400">
                                <?= number_format($t['INDEX_LENGTH'] / 1024 / 1024, 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-0.5 bg-emerald-50 text-emerald-600 text-[9px] font-black uppercase rounded border border-emerald-100">OK</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Panel Lateral Estadísticas -->
    <div class="space-y-6">
        <div class="bg-slate-900 rounded-3xl p-6 text-white shadow-xl relative overflow-hidden">
            <h3 class="text-sm font-black mb-4 uppercase tracking-widest opacity-50">Resumen de Motor</h3>
            <div class="space-y-4">
                <div class="flex justify-between items-end border-b border-slate-800 pb-2">
                    <span class="text-xs text-slate-400">Motor Storage</span>
                    <span class="text-lg font-black text-indigo-400">InnoDB</span>
                </div>
                <div class="flex justify-between items-end border-b border-slate-800 pb-2">
                    <span class="text-xs text-slate-400">Tablas Totales</span>
                    <span class="text-lg font-black"><?= count($tables) ?></span>
                </div>
                <div class="flex justify-between items-end">
                    <span class="text-xs text-slate-400">Uso de Disco</span>
                    <span class="text-lg font-black text-emerald-400"><?= number_format(array_sum(array_column($tables, 'DATA_LENGTH')) / 1024 / 1024, 2) ?> MB</span>
                </div>
            </div>
            <!-- Decoración -->
            <div class="absolute -right-8 -bottom-8 w-24 h-24 bg-white/5 rounded-full blur-xl"></div>
        </div>

        <div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm">
            <h3 class="text-sm font-black text-slate-800 mb-4 uppercase tracking-widest">Próximo Backup</h3>
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-amber-50 text-amber-500 rounded-2xl flex items-center justify-center animate-bounce">
                    <i class="fas fa-clock text-xl"></i>
                </div>
                <div>
                    <p class="text-xs font-black text-slate-800 leading-none">Hoy, 02:00 AM</p>
                    <p class="text-[10px] text-slate-400 font-bold mt-1 uppercase">Automático (Daily)</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php View::endSection('content'); ?>

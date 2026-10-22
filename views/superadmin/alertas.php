<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<div class="max-w-5xl mx-auto">
    <div class="flex justify-between items-center mb-10">
        <div>
            <h2 class="text-3xl font-black text-slate-900 tracking-tight italic"><?= $page_title ?></h2>
            <p class="text-sm text-slate-500 font-medium"><?= $page_subtitle ?></p>
        </div>
        <div class="flex gap-3">
            <button class="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 font-bold rounded-xl hover:bg-slate-50 transition-all text-xs flex items-center gap-2">
                <i class="fas fa-filter"></i> Filtrar
            </button>
            <button class="px-5 py-2.5 bg-slate-900 text-white font-bold rounded-xl hover:bg-slate-800 transition-all text-xs flex items-center gap-2 shadow-lg shadow-slate-200">
                <i class="fas fa-check-double"></i> Marcar Todo como Leído
            </button>
        </div>
    </div>

    <!-- Estadísticas Rápidas -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
        <?php
        $stats = [
            ['label' => 'Total Alertas', 'value' => count($alertas), 'icon' => 'fa-bell', 'color' => 'bg-slate-100 text-slate-600'],
            ['label' => 'Críticas', 'value' => '1', 'icon' => 'fa-fire', 'color' => 'bg-rose-100 text-rose-600'],
            ['label' => 'Pendientes', 'value' => '3', 'icon' => 'fa-clock', 'color' => 'bg-amber-100 text-amber-600'],
            ['label' => 'Solucionadas', 'value' => '24', 'icon' => 'fa-circle-check', 'color' => 'bg-emerald-100 text-emerald-600'],
        ];
        foreach ($stats as $s):
        ?>
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl <?= $s['color'] ?> flex items-center justify-center text-xl">
                <i class="fas <?= $s['icon'] ?>"></i>
            </div>
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest"><?= $s['label'] ?></p>
                <p class="text-xl font-black text-slate-800 tracking-tighter"><?= $s['value'] ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Listado de Alertas -->
    <div class="space-y-4">
        <?php foreach ($alertas as $a): ?>
        <?php
        $colors = [
            'success' => ['bg' => 'bg-emerald-50', 'border' => 'border-emerald-100', 'icon' => 'bg-emerald-500', 'text' => 'text-emerald-700'],
            'danger' => ['bg' => 'bg-rose-50', 'border' => 'border-rose-100', 'icon' => 'bg-rose-500', 'text' => 'text-rose-700'],
            'warning' => ['bg' => 'bg-amber-50', 'border' => 'border-amber-100', 'icon' => 'bg-amber-500', 'text' => 'text-amber-700'],
            'info' => ['bg' => 'bg-indigo-50', 'border' => 'border-indigo-100', 'icon' => 'bg-indigo-500', 'text' => 'text-indigo-700'],
        ];
        $c = $colors[$a['tipo']] ?? $colors['info'];
        ?>
        <div class="<?= $c['bg'] ?> <?= $c['border'] ?> border rounded-3xl p-6 hover:shadow-md transition-all group relative overflow-hidden">
            <div class="flex items-start gap-5 relative z-10">
                <div class="w-14 h-14 rounded-2xl <?= $c['icon'] ?> text-white flex items-center justify-center text-2xl shadow-lg shadow-<?= explode('-', $c['icon'])[1] ?>-200 group-hover:scale-110 transition-transform">
                    <i class="fas <?= $a['icono'] ?>"></i>
                </div>
                <div class="flex-1">
                    <div class="flex justify-between items-start">
                        <div>
                            <h4 class="text-lg font-black text-slate-800 tracking-tight"><?= $a['titulo'] ?></h4>
                            <p class="text-sm font-bold <?= $c['text'] ?>/80 mt-1"><?= $a['mensaje'] ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest"><?= $a['fecha'] ?></p>
                            <span class="inline-block mt-2 px-3 py-1 bg-white/50 rounded-full text-[9px] font-black text-slate-600 uppercase tracking-tighter border border-slate-200/50">SISTEMA</span>
                        </div>
                    </div>
                    <div class="mt-6 pt-4 border-t border-slate-900/5 flex gap-4">
                        <button class="text-[10px] font-black text-slate-500 uppercase tracking-widest hover:text-slate-800 transition-all flex items-center gap-2">
                            <i class="fas fa-eye"></i> Detalles Técnicos
                        </button>
                        <button class="text-[10px] font-black text-slate-500 uppercase tracking-widest hover:text-slate-800 transition-all flex items-center gap-2">
                            <i class="fas fa-trash"></i> Descartar
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Decoración sutil -->
            <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:opacity-[0.08] transition-opacity">
                <i class="fas <?= $a['icono'] ?> text-9xl"></i>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div class="mt-12 text-center">
        <p class="text-xs text-slate-400 font-bold uppercase tracking-widest">Fin del historial reciente</p>
    </div>
</div>

<?php View::endSection('content'); ?>

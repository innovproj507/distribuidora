<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
    <?php foreach ($planes as $p): ?>
    <div class="bg-white rounded-3xl p-8 border border-slate-100 shadow-sm relative overflow-hidden group hover:shadow-xl hover:shadow-indigo-50 transition-all flex flex-col">
        <div class="flex justify-between items-start mb-6">
            <div class="w-12 h-12 rounded-2xl bg-slate-50 text-slate-400 flex items-center justify-center group-hover:bg-slate-900 group-hover:text-white transition-all">
                <i class="fas <?= (int)$p['precio'] > 100 ? 'fa-crown text-amber-500' : 'fa-leaf' ?> text-xl"></i>
            </div>
            <div class="flex flex-col items-end gap-2">
                <span class="px-3 py-1 bg-slate-100 text-slate-500 text-[10px] font-black uppercase rounded-lg shadow-sm">ID: <?= $p['plan_id'] ?></span>
                <?php if (!$p['activo']): ?>
                <span class="px-2 py-0.5 bg-rose-50 text-rose-500 text-[8px] font-black uppercase rounded border border-rose-100">Inactivo</span>
                <?php endif; ?>
            </div>
        </div>
        
        <h3 class="text-2xl font-black text-slate-800 mb-1"><?= htmlspecialchars($p['nombre']) ?></h3>
        <p class="text-[10px] text-indigo-500 font-black uppercase tracking-widest mb-4">$<?= number_format($p['precio'], 2) ?> / mes</p>
        
        <div class="space-y-4 mb-8 flex-1">
            <div class="flex items-center gap-3">
                <i class="fas fa-check-circle text-emerald-500 text-sm"></i>
                <span class="text-sm font-bold text-slate-600"><?= $p['limite_usuarios'] == 0 ? 'Ilimitados' : $p['limite_usuarios'] ?> Usuarios</span>
            </div>
            <div class="flex items-center gap-3">
                <i class="fas fa-check-circle text-emerald-500 text-sm"></i>
                <span class="text-sm font-bold text-slate-600"><?= $p['limite_sucursales'] == 0 ? 'Ilimitadas' : $p['limite_sucursales'] ?> Sucursales</span>
            </div>
            <div class="flex items-center gap-3">
                <i class="fas fa-check-circle text-emerald-500 text-sm"></i>
                <span class="text-sm font-bold text-slate-600"><?= $p['limite_productos'] == 0 ? 'Ilimitados' : $p['limite_productos'] ?> Productos</span>
            </div>
            <div class="flex items-center gap-3">
                <i class="fas fa-check-circle text-emerald-500 text-sm"></i>
                <span class="text-sm font-bold text-slate-600"><?= $p['limite_depositos'] == 0 ? 'Ilimitados' : $p['limite_depositos'] ?> Depósitos</span>
            </div>
            <?php if ($p['ai_enabled']): ?>
            <div class="flex items-center gap-3 group/ia">
                <i class="fas fa-robot text-indigo-500 text-sm animate-pulse"></i>
                <span class="text-sm font-black text-indigo-600 italic">IA Predictiva Incluida</span>
            </div>
            <?php endif; ?>
        </div>

        <div class="p-4 bg-slate-50 rounded-2xl flex items-center justify-between mb-6">
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Empresas Activadas</span>
            <span class="text-xl font-black text-slate-800 uppercase tracking-tighter"><?= $p['cantidad_empresas'] ?></span>
        </div>

        <div class="flex gap-2">
            <a href="/master/planes/<?= $p['plan_id'] ?>/editar" class="flex-1 px-4 py-2.5 bg-slate-900 text-white font-bold rounded-xl hover:bg-slate-800 transition-all text-xs text-center">
                Editar Plan
            </a>
            <form action="/master/planes/<?= $p['plan_id'] ?>/eliminar" method="post" onsubmit="return confirm('¿Estás seguro de eliminar este plan maestro?');">
                <?= View::csrf() ?>
                <button type="submit" class="w-10 h-10 flex items-center justify-center rounded-xl bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white transition-all border border-rose-100">
                    <i class="fas fa-trash-alt text-sm"></i>
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Botón Nuevo Plan -->
    <a href="/master/planes/nuevo" class="bg-white rounded-3xl p-8 border border-dashed border-slate-200 shadow-sm flex flex-col items-center justify-center text-center group hover:border-blue-500 hover:bg-slate-50 transition-all">
        <div class="w-16 h-16 rounded-full bg-slate-50 text-slate-300 flex items-center justify-center mb-4 group-hover:bg-blue-500 group-hover:text-white transition-all">
            <i class="fas fa-plus text-2xl"></i>
        </div>
        <h3 class="text-lg font-black text-slate-400 group-hover:text-blue-500 transition-all">Nuevo Plan</h3>
        <p class="text-xs text-slate-300 mt-2">Definir nuevos límites y cuotas dinámicas</p>
    </a>
</div>

<?php View::endSection('content'); ?>

<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<div class="space-y-6">
    <!-- Encabezado de Plan Actual -->
    <div class="bg-white/80 backdrop-blur-md border border-white/20 rounded-2xl p-8 shadow-xl relative overflow-hidden group">
        <div class="absolute -right-20 -top-20 w-64 h-64 bg-blue-500/10 rounded-full blur-3xl group-hover:bg-blue-500/20 transition-colors duration-500"></div>
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 relative z-10">
            <div>
                <h2 class="text-3xl font-bold text-slate-800 mb-2">Plan <?= htmlspecialchars($plan['nombre'] ?? 'Básico') ?></h2>
                <p class="text-slate-500 max-w-lg">
                    Estas utilizando la versión premium de nuestro sistema POS. Tu próximo vencimiento es el 
                    <span class="font-semibold text-blue-500"><?= $empresa['fecha_vencimiento'] ?? 'N/A' ?></span>.
                </p>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-4xl font-extrabold text-blue-500">$<?= number_format((float)$plan['precio'], 2) ?></span>
                <span class="text-slate-400 font-medium">/ mes</span>
            </div>
        </div>
    </div>

    <!-- Grid de Uso de Recursos -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Usuarios -->
        <div class="bg-white/70 backdrop-blur-md border border-white/20 rounded-2xl p-6 shadow-lg hover:shadow-xl transition-all duration-300">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 rounded-xl bg-blue-500 text-white flex items-center justify-center shadow-sm shadow-blue-500/30">
                    <i class="fas fa-users text-xl"></i>
                </div>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Usuarios</span>
            </div>
            <div class="space-y-2">
                <div class="flex justify-between items-end">
                    <h3 class="text-2xl font-bold text-slate-700"><?= $uso['usuarios'] ?></h3>
                    <span class="text-sm font-medium text-slate-400">Límite: <?= $plan['limite_usuarios'] ?></span>
                </div>
                <?php $pct = min(100, ($uso['usuarios'] / $plan['limite_usuarios']) * 100); ?>
                <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-blue-500 rounded-full transition-all duration-1000" style="width: <?= $pct ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Sucursales -->
        <div class="bg-white/70 backdrop-blur-md border border-white/20 rounded-2xl p-6 shadow-lg hover:shadow-xl transition-all duration-300">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 rounded-xl bg-orange-500 text-white flex items-center justify-center shadow-sm shadow-orange-500/30">
                    <i class="fas fa-store text-xl"></i>
                </div>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Sucursales</span>
            </div>
            <div class="space-y-2">
                <div class="flex justify-between items-end">
                    <h3 class="text-2xl font-bold text-slate-700"><?= $uso['sucursales'] ?></h3>
                    <span class="text-sm font-medium text-slate-400">Límite: <?= $plan['limite_sucursales'] ?></span>
                </div>
                <?php $pct = min(100, ($uso['sucursales'] / $plan['limite_sucursales']) * 100); ?>
                <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-orange-500 rounded-full transition-all duration-1000" style="width: <?= $pct ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Almacenes -->
        <div class="bg-white/70 backdrop-blur-md border border-white/20 rounded-2xl p-6 shadow-lg hover:shadow-xl transition-all duration-300">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 rounded-xl bg-amber-500 text-white flex items-center justify-center shadow-sm shadow-amber-500/30">
                    <i class="fas fa-warehouse text-xl"></i>
                </div>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Almacenes</span>
            </div>
            <div class="space-y-2">
                <div class="flex justify-between items-end">
                    <h3 class="text-2xl font-bold text-slate-700"><?= $uso['depositos'] ?></h3>
                    <span class="text-sm font-medium text-slate-400">Límite: <?= $plan['limite_depositos'] ?></span>
                </div>
                <?php $pct = min(100, ($uso['depositos'] / $plan['limite_depositos']) * 100); ?>
                <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-amber-500 rounded-full transition-all duration-1000" style="width: <?= $pct ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Productos -->
        <div class="bg-white/70 backdrop-blur-md border border-white/20 rounded-2xl p-6 shadow-lg hover:shadow-xl transition-all duration-300">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-500 text-white flex items-center justify-center shadow-sm shadow-emerald-500/30">
                    <i class="fas fa-box text-xl"></i>
                </div>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Productos</span>
            </div>
            <div class="space-y-2">
                <div class="flex justify-between items-end">
                    <h3 class="text-2xl font-bold text-slate-700"><?= $uso['productos'] ?></h3>
                    <span class="text-sm font-medium text-slate-400">Límite: <?= $plan['limite_productos'] ?></span>
                </div>
                <?php $pct = min(100, ($uso['productos'] / $plan['limite_productos']) * 100); ?>
                <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500 rounded-full transition-all duration-1000" style="width: <?= $pct ?>%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Comparativa de Planes -->
    <div class="pt-8 text-center">
        <h3 class="text-2xl font-bold text-slate-800 mb-8">Mejora tu plan para desbloquear más potencia</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            <?php foreach ($planes as $p): ?>
                <?php $isCurrent = ($p['plan_id'] == $plan['plan_id']); ?>
                <div class="bg-white/80 border <?= $isCurrent ? 'border-blue-500 ring-2 ring-blue-500/20 scale-105 z-10' : 'border-slate-100 shadow-sm' ?> rounded-3xl p-8 flex flex-col hover:shadow-2xl transition-all duration-500">
                    <?php if ($isCurrent): ?>
                        <div class="bg-blue-500 text-white text-[10px] font-bold uppercase tracking-widest py-1 px-3 rounded-full mb-4 w-fit mx-auto">Plan Actual</div>
                    <?php endif; ?>
                    
                    <h4 class="text-center font-bold text-xl text-slate-800 mb-2"><?= htmlspecialchars($p['nombre']) ?></h4>
                    <div class="text-center mb-6">
                        <span class="text-3xl font-extrabold text-slate-900">$<?= number_format((float)$p['precio'], 0) ?></span>
                        <span class="text-slate-400 font-medium">/ mes</span>
                    </div>
                    
                    <ul class="space-y-4 mb-8 flex-grow text-left">
                        <li class="flex items-center gap-3 text-sm text-slate-600">
                            <i class="fas fa-check-circle text-blue-500 opacity-60"></i>
                            <span><?= $p['limite_usuarios'] ?> Usuarios</span>
                        </li>
                        <li class="flex items-center gap-3 text-sm text-slate-600">
                            <i class="fas fa-check-circle text-blue-500 opacity-60"></i>
                            <span><?= $p['limite_sucursales'] ?> Sucursales</span>
                        </li>
                        <li class="flex items-center gap-3 text-sm text-slate-600">
                            <i class="fas fa-check-circle text-blue-500 opacity-60"></i>
                            <span><?= $p['limite_depositos'] ?> Almacenes</span>
                        </li>
                        <li class="flex items-center gap-3 text-sm text-slate-600">
                            <i class="fas fa-check-circle text-blue-500 opacity-60"></i>
                            <span><?= number_format($p['limite_productos']) ?> Productos</span>
                        </li>
                    </ul>
                    
                    <button class="w-full py-4 rounded-2xl font-bold transition-all duration-300 <?= $isCurrent ? 'bg-slate-100 text-slate-400 cursor-not-allowed' : 'bg-slate-900 text-white hover:bg-blue-500 shadow-lg hover:shadow-blue-500/30' ?>">
                        <?= $isCurrent ? 'Plan Seleccionado' : 'Mejorar Ahora' ?>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php View::endSection('content'); ?>

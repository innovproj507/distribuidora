<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<!-- SaaS Health Metrics -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Estado de Empresas -->
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Ecosistema SaaS</p>
                <h3 class="text-3xl font-black text-slate-800 mt-1"><?= $metrics['total_empresas'] ?></h3>
                <div class="flex items-center gap-2 mt-2">
                    <span class="text-[10px] font-bold text-emerald-500 bg-emerald-50 px-1.5 py-0.5 rounded"><?= $metrics['empresas_activas'] ?> Activas</span>
                    <span class="text-[10px] font-bold text-slate-400 bg-slate-50 px-1.5 py-0.5 rounded"><?= $metrics['empresas_inactivas'] ?> Inactivas</span>
                </div>
            </div>
            <div class="w-12 h-12 bg-indigo-50 border border-indigo-100 rounded-xl flex items-center justify-center text-indigo-500 shadow-sm">
                <i class="fas fa-building text-xl"></i>
            </div>
        </div>
    </div>

    <!-- Usuarios Totales -->
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Usuarios Globales</p>
                <h3 class="text-3xl font-black text-slate-800 mt-1"><?= $metrics['total_usuarios'] ?></h3>
                <p class="text-[10px] text-slate-400 font-bold mt-2 uppercase tracking-tighter">Cuentas creadas en el sistema</p>
            </div>
            <div class="w-12 h-12 bg-amber-50 border border-amber-100 rounded-xl flex items-center justify-center text-amber-500 shadow-sm">
                <i class="fas fa-users text-xl"></i>
            </div>
        </div>
    </div>

    <!-- Base de Datos -->
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Almacenamiento DB</p>
                <h3 class="text-3xl font-black text-slate-800 mt-1"><?= $metrics['db_size'] ?> <span class="text-sm font-bold text-slate-400">MB</span></h3>
                <div class="flex items-center gap-1.5 mt-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="text-[10px] font-bold text-emerald-600 uppercase">Optimizado</span>
                </div>
            </div>
            <div class="w-12 h-12 bg-emerald-50 border border-emerald-100 rounded-xl flex items-center justify-center text-emerald-500 shadow-sm">
                <i class="fas fa-database text-xl"></i>
            </div>
        </div>
    </div>

    <!-- Status de Servidor -->
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Hora del Servidor</p>
                <h3 class="text-3xl font-black text-slate-800 mt-1"><?= $metrics['server_time'] ?></h3>
                <p class="text-[10px] text-blue-500 font-black mt-2 uppercase tracking-widest tracking-widest">GTM-5 Online</p>
            </div>
            <div class="w-12 h-12 bg-blue-50 border border-blue-100 rounded-xl flex items-center justify-center text-blue-500 shadow-sm">
                <i class="fas fa-microchip text-xl"></i>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Auditoría de Registro -->
    <div class="lg:col-span-2 bg-white rounded-3xl p-8 shadow-sm border border-slate-100">
        <div class="flex justify-between items-center mb-8">
            <h3 class="text-xl font-black text-slate-800 tracking-tight">Registro Reciente de Empresas</h3>
            <a href="/master/empresas" class="text-[10px] font-black text-blue-500 uppercase tracking-widest hover:underline">Ver todas <i class="fas fa-arrow-right ml-1"></i></a>
        </div>
        
        <div class="space-y-6">
            <?php foreach ($ultimasEmpresas as $e): ?>
            <div class="flex items-center justify-between p-4 bg-slate-50/50 rounded-2xl border border-transparent hover:border-slate-100 hover:bg-white transition-all group">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-white border border-slate-100 flex items-center justify-center text-slate-400 group-hover:scale-110 group-hover:bg-blue-500 group-hover:text-white group-hover:border-blue-500 transition-all shadow-sm">
                        <i class="fas fa-building"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-700"><?= htmlspecialchars($e['nombre_comercial']) ?></h4>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Registrado: <?= date('d M, Y', strtotime($e['fecha_registro'])) ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <?php if ($e['activa']): ?>
                        <span class="px-3 py-1 bg-emerald-50 text-emerald-600 text-[10px] font-black uppercase rounded-lg border border-emerald-100">Activo</span>
                    <?php else: ?>
                        <span class="px-3 py-1 bg-slate-100 text-slate-400 text-[10px] font-black uppercase rounded-lg border border-slate-200">Inactivo</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Módulo de Mantenimiento Rápido -->
    <div class="bg-slate-900 rounded-3xl p-8 text-white shadow-2xl shadow-slate-200 relative overflow-hidden">
        <div class="relative z-10">
            <h3 class="text-xl font-black mb-1">Mantenimiento</h3>
            <p class="text-slate-400 text-xs mb-8">Acciones críticas del administrador</p>
            
            <div class="space-y-4">
                <a href="/master/mantenimiento/backup" class="w-full flex items-center gap-4 p-4 bg-slate-800 rounded-2xl hover:bg-slate-700 transition-all border border-slate-700/50 group">
                    <div class="w-10 h-10 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center group-hover:bg-indigo-500 group-hover:text-white transition-all">
                        <i class="fas fa-download"></i>
                    </div>
                    <div class="text-left">
                        <p class="text-sm font-bold">Respaldo SQL</p>
                        <p class="text-[10px] text-slate-500 uppercase font-black">Generar ahora</p>
                    </div>
                </a>

                <a href="/master/mantenimiento/clear-cache" class="w-full flex items-center gap-4 p-4 bg-slate-800 rounded-2xl hover:bg-slate-700 transition-all border border-slate-700/50 group">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center group-hover:bg-emerald-500 group-hover:text-white transition-all">
                        <i class="fas fa-broom"></i>
                    </div>
                    <div class="text-left">
                        <p class="text-sm font-bold">Limpiar Cache</p>
                        <p class="text-[10px] text-slate-500 uppercase font-black">Optimizar vistas</p>
                    </div>
                </a>

                <div class="pt-6 mt-6 border-t border-slate-800/50">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-4">Información del Sistema</p>
                    <div class="flex justify-between text-xs py-1">
                        <span class="text-slate-500">Versión PHP</span>
                        <span class="font-bold font-mono"><?= PHP_VERSION ?></span>
                    </div>
                    <div class="flex justify-between text-xs py-1">
                        <span class="text-slate-500">Manejador DB</span>
                        <span class="font-bold uppercase tracking-tighter">MySQL 8.0 / InnoDB</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Decoración -->
        <div class="absolute -right-16 -top-16 w-64 h-64 bg-indigo-500/5 rounded-full blur-3xl"></div>
        <div class="absolute -left-16 -bottom-16 w-64 h-64 bg-emerald-500/5 rounded-full blur-3xl"></div>
    </div>
</div>

<?php View::endSection('content'); ?>

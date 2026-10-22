<?php
/**
 * Submenú Maestro (Solo Superadmin) - SaaS Focus
 */
$uri = $_SERVER['REQUEST_URI'] ?? '/';
?>
<div class="px-6 py-8">
    <div class="flex items-center gap-3 mb-8">
        <div class="w-10 h-10 rounded-xl bg-slate-900 text-white flex items-center justify-center shadow-lg shadow-slate-200">
            <i class="fas fa-shield-halved"></i>
        </div>
        <div>
            <h3 class="font-bold text-slate-800 leading-none">SaaS Console</h3>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1 text-blue-500">Master Control</p>
        </div>
    </div>

    <nav class="space-y-1">
        <p class="px-3 text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Administración</p>
        
        <a href="/master" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= ($uri === '/master') ? 'bg-indigo-50 text-indigo-600 font-bold' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700' ?>">
            <i class="fas fa-chart-pie w-5"></i> Dashboard Global
        </a>

        <a href="/master/empresas" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= str_contains($uri, '/master/empresas') ? 'bg-indigo-50 text-indigo-600 font-bold' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700' ?>">
            <i class="fas fa-building w-5"></i> Gestión de Clientes
        </a>

        <a href="/master/usuarios" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= str_contains($uri, '/master/usuarios') ? 'bg-indigo-50 text-indigo-600 font-bold' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700' ?>">
            <i class="fas fa-users-gear w-5"></i> Usuarios Globales
        </a>

        <div class="pt-6">
            <p class="px-3 text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">SaaS Business</p>
            <a href="/master/planes" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= str_contains($uri, '/master/planes') ? 'bg-indigo-50 text-indigo-600 font-bold' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700' ?>">
                <i class="fas fa-certificate w-5 text-amber-500"></i> Planes y Cuotas
            </a>
            <a href="/master/facturacion" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= str_contains($uri, '/master/facturacion') ? 'bg-indigo-50 text-indigo-600 font-bold' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700' ?>">
                <i class="fas fa-file-invoice-dollar w-5"></i> Cobros SaaS
            </a>
        </div>

        <div class="pt-6">
            <p class="px-3 text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Mantenimiento</p>
            <a href="/master/database" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= str_contains($uri, '/master/database') ? 'bg-indigo-50 text-indigo-600 font-bold' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700' ?>">
                <i class="fas fa-database w-5"></i> Base de Datos
            </a>
            <a href="/master/logs" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= str_contains($uri, '/master/logs') ? 'bg-indigo-50 text-indigo-600 font-bold' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700' ?>">
                <i class="fas fa-terminal w-5"></i> Logs del Sistema
            </a>
        </div>
    </nav>
</div>

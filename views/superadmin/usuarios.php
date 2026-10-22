<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Usuarios Globales</h2>
        <p class="text-sm text-slate-500">Buscador universal de cuentas en todo el ecosistema SaaS</p>
    </div>
</div>

<!-- Buscador -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 mb-6">
    <form method="get" class="flex gap-4">
        <div class="relative flex-1">
            <input type="text" name="buscar" value="<?= htmlspecialchars($buscar) ?>" 
                   placeholder="Buscar por usuario, nombre o email..." 
                   class="w-full pl-10 pr-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
            <i class="fas fa-search absolute left-4 top-4 text-slate-400"></i>
        </div>
        <button type="submit" class="px-6 py-3 bg-slate-900 text-white font-bold rounded-xl hover:bg-slate-800 transition-all">
            Buscar Usuario
        </button>
        <?php if ($buscar): ?>
        <a href="/master/usuarios" class="px-6 py-3 bg-slate-100 text-slate-600 font-bold rounded-xl hover:bg-slate-200 transition-all">
            Limpiar
        </a>
        <?php endif; ?>
    </form>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100">
            <thead class="bg-slate-50/50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest">Usuario / Email</th>
                    <th class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest">Empresa Asignada</th>
                    <th class="px-6 py-4 text-center text-xs font-black text-slate-400 uppercase tracking-widest">Estado</th>
                    <th class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest">Registro</th>
                    <th class="px-6 py-4 text-center text-xs font-black text-slate-400 uppercase tracking-widest">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($usuarios as $u): ?>
                <tr class="hover:bg-slate-50/50 transition-colors group">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mr-3 group-hover:bg-indigo-50 group-hover:text-indigo-500 transition-all">
                                <i class="fas fa-user text-sm"></i>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-slate-700"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></p>
                                <p class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($u['email'] ?: $u['username']) ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php if ($u['empresa_nombre']): ?>
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-indigo-400"></span>
                            <span class="text-xs font-bold text-slate-600"><?= htmlspecialchars($u['empresa_nombre']) ?></span>
                        </div>
                        <?php else: ?>
                        <span class="text-[10px] text-slate-300 italic font-bold uppercase">Sin empresa (Superadmin)</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <?php if ($u['is_superuser']): ?>
                        <span class="px-2 py-1 bg-indigo-600 text-white text-[9px] font-black uppercase rounded shadow-sm">Superuser</span>
                        <?php elseif ($u['is_staff']): ?>
                        <span class="px-2 py-1 bg-amber-500 text-white text-[9px] font-black uppercase rounded shadow-sm">Staff</span>
                        <?php else: ?>
                        <span class="px-2 py-1 bg-slate-100 text-slate-500 text-[9px] font-black uppercase rounded">Inquilino</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <?php if ($u['is_active']): ?>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-emerald-50 text-emerald-600 border border-emerald-100">
                            Activo
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-rose-50 text-rose-600 border border-rose-100">
                            Baneado
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-400 font-bold">
                        <?= date('d M, Y', strtotime($u['date_joined'])) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                         <div class="flex justify-center gap-2">
                            <a href="/master/usuarios/<?= $u['id'] ?>/editar" class="w-8 h-8 flex items-center justify-center rounded-lg bg-slate-50 text-slate-400 hover:bg-slate-900 hover:text-white transition-all shadow-sm" title="Editar Permisos">
                                <i class="fas fa-cog text-[10px]"></i>
                            </a>
                            <form method="post" action="/master/usuarios/<?= $u['id'] ?>/estado" onsubmit="return confirm('¿Estás seguro de que deseas cambiar el estado de acceso de este usuario?');">
                                <?= View::csrf() ?>
                                <button type="submit" class="w-8 h-8 flex items-center justify-center rounded-lg <?= $u['is_active'] ? 'bg-rose-50 text-rose-500 hover:bg-rose-500' : 'bg-emerald-50 text-emerald-500 hover:bg-emerald-500' ?> hover:text-white transition-all shadow-sm" title="<?= $u['is_active'] ? 'Desactivar/Banear' : 'Activar' ?>">
                                    <i class="fas <?= $u['is_active'] ? 'fa-ban' : 'fa-check' ?> text-[10px]"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php View::endSection('content'); ?>

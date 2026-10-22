<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Gestión de Inquilinos</h2>
        <p class="text-sm text-slate-500">Administra todas las empresas registradas en la plataforma</p>
    </div>
    <a href="/master/empresas/nueva" class="inline-flex items-center px-4 py-2 bg-blue-500 text-white text-sm font-bold rounded-xl shadow-lg hover:shadow-blue-500/30 transition-all gap-2">
        <i class="fas fa-plus"></i> Nueva Empresa
    </a>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100">
            <thead class="bg-slate-50/50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest">Empresa</th>
                    <th class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest">Identifición (RUC)</th>
                    <th class="px-6 py-4 text-center text-xs font-black text-slate-400 uppercase tracking-widest">Status</th>
                    <th class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest">Plan</th>
                    <th class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest">Registro</th>
                    <th class="px-6 py-4 text-center text-xs font-black text-slate-400 uppercase tracking-widest">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($empresas as $e): ?>
                <tr class="hover:bg-slate-50/50 transition-colors group">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-xl bg-blue-500/5 flex items-center justify-center text-blue-500 mr-4 group-hover:scale-110 transition-transform">
                                <i class="fas fa-building text-lg"></i>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-slate-700"><?= htmlspecialchars($e['nombre_comercial']) ?></p>
                                <p class="text-[10px] text-slate-400 font-medium"><?= htmlspecialchars($e['razon_social']) ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 font-mono">
                        <?= htmlspecialchars($e['ruc']) ?>-<?= htmlspecialchars($e['dv'] ?? '00') ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <?php if ($e['activa']): ?>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-600 border border-emerald-100">
                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span> Activa
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-50 text-rose-600 border border-rose-100">
                            <span class="w-1.5 h-1.5 bg-rose-500 rounded-full"></span> Suspendida
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-xs font-bold text-slate-700 bg-slate-100 px-2 py-1 rounded-md">
                            <?= $e['plan_id'] == 2 ? 'Premium' : 'Básico' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">
                        <?= date('d M, Y', strtotime($e['fecha_registro'])) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <div class="flex justify-center gap-2">
                             <a href="/dashboard?set_tenant_id=<?= $e['empresa_id'] ?>" class="w-8 h-8 flex items-center justify-center rounded-lg bg-indigo-50 text-indigo-500 hover:bg-indigo-500 hover:text-white transition-all shadow-sm" title="Impersonar (Entrar)">
                                <i class="fas fa-sign-in-alt text-xs"></i>
                            </a>
                            <a href="/master/empresas/<?= $e['empresa_id'] ?>/editar" class="w-8 h-8 flex items-center justify-center rounded-lg bg-amber-50 text-amber-500 hover:bg-amber-500 hover:text-white transition-all shadow-sm" title="Editar">
                                <i class="fas fa-edit text-xs"></i>
                            </a>
                            <form method="post" action="/master/empresas/<?= $e['empresa_id'] ?>/eliminar" onsubmit="return confirm('¿Estás seguro de que deseas eliminar esta empresa del ecosistema?');">
                                <?= View::csrf() ?>
                                <button type="submit" class="w-8 h-8 flex items-center justify-center rounded-lg bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white transition-all shadow-sm" title="Eliminar">
                                    <i class="fas fa-trash-alt text-xs"></i>
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

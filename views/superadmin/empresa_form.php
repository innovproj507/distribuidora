<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h2 class="text-2xl font-black text-slate-800 tracking-tight"><?= $page_title ?></h2>
            <p class="text-sm text-slate-500"><?= $page_subtitle ?></p>
        </div>
        <a href="/master/empresas" class="text-sm font-bold text-slate-400 hover:text-slate-600 transition-all flex items-center gap-2">
            <i class="fas fa-arrow-left"></i> Volver al listado
        </a>
    </div>

    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-8">
        <form method="post" action="<?= isset($empresa) ? "/master/empresas/{$empresa['empresa_id']}/editar" : "/master/empresas/nueva" ?>" class="space-y-8">
            <?= View::csrf() ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Información General -->
                <div class="space-y-6">
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest border-b border-slate-50 pb-2">Información de la Entidad</h3>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Nombre Comercial</label>
                        <input type="text" name="nombre_comercial" value="<?= htmlspecialchars($empresa['nombre_comercial'] ?? '') ?>" required
                               class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm font-medium"
                               placeholder="Ej: Distribuidora Arango">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Razón Social</label>
                        <input type="text" name="razon_social" value="<?= htmlspecialchars($empresa['razon_social'] ?? '') ?>" required
                               class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm font-medium"
                               placeholder="Ej: Inversiones Arango S.A.">
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div class="col-span-2">
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-2">RUC (Registro Único)</label>
                            <input type="text" name="ruc" value="<?= htmlspecialchars($empresa['ruc'] ?? '') ?>" required
                                   class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm font-medium"
                                   placeholder="8-XXX-XXXX">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-2">DV</label>
                            <input type="text" name="dv" value="<?= htmlspecialchars($empresa['dv'] ?? '') ?>"
                                   class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm font-medium"
                                   placeholder="00">
                        </div>
                    </div>
                </div>

                <!-- Configuración SaaS -->
                <div class="space-y-6">
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest border-b border-slate-50 pb-2">Suscripción y Estado</h3>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Plan de Servicio</label>
                        <select name="plan_id" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm font-medium bg-slate-50">
                            <?php foreach ($planes as $p): ?>
                            <option value="<?= $p['plan_id'] ?>" <?= ($empresa['plan_id'] ?? '') == $p['plan_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Estado de la cuenta</label>
                        <div class="flex gap-4">
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="activa" value="1" class="hidden peer" <?= ($empresa['activa'] ?? 1) == 1 ? 'checked' : '' ?>>
                                <div class="p-3 text-center rounded-xl border border-slate-200 peer-checked:bg-emerald-50 peer-checked:border-emerald-500 peer-checked:text-emerald-600 text-xs font-bold uppercase transition-all">
                                    Activo
                                </div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="activa" value="0" class="hidden peer" <?= ($empresa['activa'] ?? 1) == 0 ? 'checked' : '' ?>>
                                <div class="p-3 text-center rounded-xl border border-slate-200 peer-checked:bg-rose-50 peer-checked:border-rose-500 peer-checked:text-rose-600 text-xs font-bold uppercase transition-all">
                                    Suspendida
                                </div>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Funciones Avanzadas</label>
                        <label class="flex items-center gap-3 p-4 bg-slate-900 text-white rounded-2xl cursor-pointer hover:bg-slate-800 transition-all shadow-lg shadow-slate-200">
                            <input type="checkbox" name="ai_enabled" value="1" <?= ($empresa['ai_enabled'] ?? 0) ? 'checked' : '' ?> class="w-5 h-5 rounded border-slate-700 bg-slate-800 text-indigo-500 focus:ring-indigo-500/20">
                            <div>
                                <p class="text-sm font-black italic">Activar Cerebro AI</p>
                                <p class="text-[9px] text-slate-400 font-bold uppercase tracking-tighter">Habilita Gemini para este cliente</p>
                            </div>
                            <div class="ml-auto">
                                <i class="fas fa-microchip text-indigo-400"></i>
                            </div>
                        </label>
                    </div>

                    <div class="p-4 bg-amber-50 rounded-2xl border border-amber-100">
                        <div class="flex items-center gap-3 mb-2 text-amber-700">
                            <i class="fas fa-exclamation-triangle text-xs"></i>
                            <span class="text-[10px] font-black uppercase tracking-widest">Atención</span>
                        </div>
                        <p class="text-[10px] text-amber-600 font-bold leading-relaxed">
                            Al suspender una empresa, todos sus usuarios perderán acceso inmediato a sus respectivos dashboards y depósitos.
                        </p>
                    </div>
                </div>
            </div>

            <div class="pt-8 border-t border-slate-50 flex justify-end gap-4">
                <a href="/master/empresas" class="px-8 py-3 bg-slate-100 text-slate-500 font-bold rounded-xl hover:bg-slate-200 transition-all text-sm">
                    Cancelar
                </a>
                <button type="submit" class="px-8 py-3 bg-slate-900 text-white font-bold rounded-xl hover:bg-slate-800 transition-all shadow-lg shadow-slate-200 text-sm">
                    <?= isset($empresa) ? 'Guardar Cambios' : 'Crear Empresa' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php View::endSection('content'); ?>

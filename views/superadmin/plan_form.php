<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h2 class="text-2xl font-black text-slate-800 tracking-tight"><?= $page_title ?></h2>
            <p class="text-sm text-slate-500"><?= $page_subtitle ?></p>
        </div>
        <a href="/master/planes" class="text-sm font-bold text-slate-400 hover:text-slate-600 transition-all flex items-center gap-2">
            <i class="fas fa-arrow-left"></i> Volver a planes
        </a>
    </div>

    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-8">
        <form method="post" action="<?= isset($plan) ? '/master/planes/'.$plan['plan_id'].'/editar' : '/master/planes/nuevo' ?>" class="space-y-8">
            <?= View::csrf() ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Configuración Básica -->
                <div class="space-y-6">
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest border-b border-slate-50 pb-2">Información del Plan</h3>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Nombre del Plan</label>
                        <input type="text" name="nombre" value="<?= htmlspecialchars($plan['nombre'] ?? '') ?>" required placeholder="Ej: Plan Profesional"
                               class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm font-medium">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Precio Mensual ($)</label>
                        <input type="number" step="0.01" name="precio" value="<?= htmlspecialchars($plan['precio'] ?? '') ?>" required placeholder="0.00"
                               class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm font-medium">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Estado</label>
                        <select name="activo" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm font-medium">
                            <option value="1" <?= (isset($plan) && $plan['activo']) ? 'selected' : '' ?>>Activo (Ofertado)</option>
                            <option value="0" <?= (isset($plan) && !$plan['activo']) ? 'selected' : '' ?>>Inactivo (Archivo)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Cerebro AI</label>
                        <label class="flex items-center gap-3 p-4 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-100 transition-all">
                            <input type="checkbox" name="ai_enabled" value="1" <?= (isset($plan) && $plan['ai_enabled']) ? 'checked' : '' ?> class="w-5 h-5 rounded border-slate-300 text-blue-500 focus:ring-blue-500/20">
                            <div>
                                <p class="text-sm font-black text-slate-800">Incluir Funciones de IA</p>
                                <p class="text-[10px] text-slate-400 font-bold uppercase">Habilita el Chat y Predicciones</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Límites Tecnológicos -->
                <div class="space-y-6">
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest border-b border-slate-50 pb-2">Límites y Cuotas</h3>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Máx Usuarios</label>
                            <input type="number" name="limite_usuarios" value="<?= htmlspecialchars($plan['limite_usuarios'] ?? '5') ?>" required
                                   class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm font-medium">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Máx Sucursales</label>
                            <input type="number" name="limite_sucursales" value="<?= htmlspecialchars($plan['limite_sucursales'] ?? '1') ?>" required
                                   class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm font-medium">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Máx Productos</label>
                            <input type="number" name="limite_productos" value="<?= htmlspecialchars($plan['limite_productos'] ?? '100') ?>" required
                                   class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm font-medium">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Máx Depósitos</label>
                            <input type="number" name="limite_depositos" value="<?= htmlspecialchars($plan['limite_depositos'] ?? '2') ?>" required
                                   class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm font-medium">
                        </div>
                    </div>

                    <div class="p-4 bg-indigo-50 rounded-2xl border border-indigo-100 flex gap-3">
                        <i class="fas fa-info-circle text-indigo-500 mt-1"></i>
                        <p class="text-[10px] text-indigo-700 font-bold leading-relaxed">
                            Usa "0" para definir límites ilimitados en cualquier parámetro. Ten cuidado con los recursos del servidor.
                        </p>
                    </div>
                </div>
            </div>

            <div class="pt-8 border-t border-slate-50 flex justify-end gap-4">
                <a href="/master/planes" class="px-8 py-3 bg-slate-100 text-slate-500 font-bold rounded-xl hover:bg-slate-200 transition-all text-sm">
                    Cancelar
                </a>
                <button type="submit" class="px-8 py-3 bg-slate-900 text-white font-bold rounded-xl hover:bg-slate-800 transition-all shadow-lg shadow-slate-200 text-sm">
                    <?= isset($plan) ? 'Guardar Cambios' : 'Crear Plan Maestro' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php View::endSection('content'); ?>

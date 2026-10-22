<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h2 class="text-2xl font-black text-slate-800 tracking-tight"><?= $page_title ?></h2>
            <p class="text-sm text-slate-500"><?= $page_subtitle ?></p>
        </div>
        <a href="/master/usuarios" class="text-sm font-bold text-slate-400 hover:text-slate-600 transition-all flex items-center gap-2">
            <i class="fas fa-arrow-left"></i> Volver al listado
        </a>
    </div>

    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-8">
        <form method="post" action="/master/usuarios/<?= $usuario['id'] ?>/editar" class="space-y-8">
            <?= View::csrf() ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Información de Identidad -->
                <div class="space-y-6">
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest border-b border-slate-50 pb-2">Identidad del Usuario</h3>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Username</label>
                        <input type="text" value="<?= htmlspecialchars($usuario['username']) ?>" disabled
                               class="w-full px-4 py-3 rounded-xl border border-slate-100 bg-slate-50 text-slate-400 text-sm font-mono cursor-not-allowed">
                        <p class="text-[10px] text-slate-400 mt-1 font-medium italic">* El nombre de usuario no puede ser modificado por seguridad SaaS.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Nombre</label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($usuario['first_name'] ?? '') ?>" required
                                   class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm font-medium">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Apellido</label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($usuario['last_name'] ?? '') ?>" required
                                   class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm font-medium">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Email de acceso</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($usuario['email'] ?? '') ?>" required
                               class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm font-medium">
                    </div>
                </div>

                <!-- Roles y Privilegios -->
                <div class="space-y-6">
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest border-b border-slate-50 pb-2">Roles y Seguridad</h3>
                    
                    <div class="space-y-4">
                        <label class="flex items-center p-4 bg-slate-50 rounded-2xl cursor-pointer hover:bg-slate-100 transition-all border border-transparent peer-checked:border-blue-500">
                            <input type="checkbox" name="is_superuser" value="1" <?= $usuario['is_superuser'] ? 'checked' : '' ?> class="w-4 h-4 text-blue-500 rounded border-slate-300 focus:ring-blue-500">
                            <div class="ml-4">
                                <p class="text-xs font-black text-slate-700 uppercase tracking-tight">Superusuario Global</p>
                                <p class="text-[10px] text-slate-500 font-medium">Acceso total a la Master Console y todas las empresas.</p>
                            </div>
                        </label>

                        <label class="flex items-center p-4 bg-slate-50 rounded-2xl cursor-pointer hover:bg-slate-100 transition-all border border-transparent peer-checked:border-blue-500">
                            <input type="checkbox" name="is_staff" value="1" <?= $usuario['is_staff'] ? 'checked' : '' ?> class="w-4 h-4 text-blue-500 rounded border-slate-300 focus:ring-blue-500">
                            <div class="ml-4">
                                <p class="text-xs font-black text-slate-700 uppercase tracking-tight">Acceso Staff (Soporte)</p>
                                <p class="text-[10px] text-slate-500 font-medium">Permisos administrativos de soporte técnico.</p>
                            </div>
                        </label>

                        <div class="pt-4 border-t border-slate-50">
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Estado de la cuenta</label>
                            <select name="is_active" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm font-medium">
                                <option value="1" <?= $usuario['is_active'] ? 'selected' : '' ?>>Cuenta Activa (Habilitada)</option>
                                <option value="0" <?= !$usuario['is_active'] ? 'selected' : '' ?>>Cuenta Suspendida (Baneada)</option>
                            </select>
                        </div>
                    </div>

                    <div class="p-4 bg-indigo-50 rounded-2xl border border-indigo-100 flex gap-3">
                        <i class="fas fa-info-circle text-indigo-500 mt-1"></i>
                        <p class="text-[10px] text-indigo-700 font-bold leading-relaxed">
                            Cambiar privilegios de superusuario afecta la seguridad global. Asegúrate de que el usuario pertenezca a tu equipo de confianza.
                        </p>
                    </div>
                </div>
            </div>

            <div class="pt-8 border-t border-slate-50 flex justify-end gap-4">
                <a href="/master/usuarios" class="px-8 py-3 bg-slate-100 text-slate-500 font-bold rounded-xl hover:bg-slate-200 transition-all text-sm">
                    Cancelar
                </a>
                <button type="submit" class="px-8 py-3 bg-slate-900 text-white font-bold rounded-xl hover:bg-slate-800 transition-all shadow-lg shadow-slate-200 text-sm">
                    Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<?php View::endSection('content'); ?>

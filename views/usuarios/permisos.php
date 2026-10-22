<?php use App\Core\View; use App\Core\Auth; View::layout('app'); ?>
<?php View::section('content'); ?>

<div class="space-y-6 max-w-3xl">
    <div class="flex items-center gap-3">
        <a href="/usuarios" class="text-slate-400 hover:text-slate-600">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Permisos de Depósito</h2>
            <p class="text-slate-500 text-sm">
                Usuario: <span class="font-medium text-slate-700"><?= View::e($usuario['username']) ?></span>
                &mdash; Rol: <span class="font-medium"><?= View::e(ucfirst($usuario['rol'])) ?></span>
            </p>
        </div>
    </div>

    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800">
        <i class="fas fa-info-circle mr-1"></i>
        Si no se selecciona ningún depósito, el usuario tendrá acceso a <strong>todos</strong> los depósitos
        (sin restricciones). Selecciona uno o más para limitarlo.
    </div>

    <form method="POST" action="/usuarios/guardar-permisos">
        <?= View::csrf() ?>
        <input type="hidden" name="user_id" value="<?= $usuario['id'] ?>">

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-semibold text-slate-700">Depósitos disponibles</h3>
                <button type="button" onclick="toggleAll()"
                        class="text-xs font-bold text-teal-600 hover:underline" id="btn-toggle">
                    Seleccionar todos
                </button>
            </div>

            <?php
            // Agrupar por sucursal
            $porSucursal = [];
            foreach ($depositos as $dep) {
                $porSucursal[$dep['sucursal_nombre']][] = $dep;
            }
            ?>

            <div class="divide-y divide-slate-50">
                <?php foreach ($porSucursal as $sucursalNombre => $deps): ?>
                    <div class="px-5 py-3">
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">
                            <i class="fas fa-building mr-1"></i><?= View::e($sucursalNombre) ?>
                        </p>
                        <div class="space-y-2">
                            <?php foreach ($deps as $dep): ?>
                                <label class="flex items-center gap-3 cursor-pointer group">
                                    <input type="checkbox"
                                           name="depositos[]"
                                           value="<?= $dep['deposito_id'] ?>"
                                           class="dep-checkbox w-4 h-4 rounded text-teal-600 border-slate-300 focus:ring-teal-400"
                                           <?= in_array((int)$dep['deposito_id'], $asignados, true) ? 'checked' : '' ?>>
                                    <span class="text-sm text-slate-700 group-hover:text-slate-900">
                                        <?= View::e($dep['deposito_nombre']) ?>
                                        <?php if ($dep['es_principal']): ?>
                                            <span class="ml-1 text-xs text-slate-400">(principal)</span>
                                        <?php endif; ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($depositos)): ?>
                    <div class="px-5 py-8 text-center text-slate-400 text-sm">
                        No hay depósitos disponibles en la empresa.
                    </div>
                <?php endif; ?>
            </div>

            <div class="px-5 py-4 border-t border-slate-100 bg-slate-50 flex gap-3">
                <button type="submit"
                        class="px-5 py-2 bg-teal-500 text-white rounded-lg text-sm font-semibold hover:bg-teal-600 shadow-sm">
                    <i class="fas fa-save mr-1"></i> Guardar permisos
                </button>
                <a href="/usuarios" class="px-5 py-2 bg-white border border-slate-200 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-50">
                    Cancelar
                </a>
            </div>
        </div>
    </form>
</div>

<script>
function toggleAll() {
    const boxes = document.querySelectorAll('.dep-checkbox');
    const allChecked = Array.from(boxes).every(b => b.checked);
    boxes.forEach(b => b.checked = !allChecked);
    document.getElementById('btn-toggle').textContent = allChecked ? 'Seleccionar todos' : 'Deseleccionar todos';
}
</script>

<?php View::endSection('content'); ?>

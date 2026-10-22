<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<!-- Breadcrumb -->
<div class="flex items-center gap-2 text-sm text-gray-400 mb-3">
    <a href="/mantenimiento/tareas" class="hover:text-gray-600 transition-colors">Plan Preventivo</a>
    <i class="fas fa-chevron-right text-xs"></i>
    <span class="text-gray-700 font-medium"><?= View::e($page_title ?? '') ?></span>
</div>

<form method="post" action="<?= View::e($action) ?>">
    <?= View::csrf() ?>

    <!-- Action bar -->
    <div class="flex items-center justify-between bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-2.5 mb-4">
        <div class="flex gap-2">
            <button type="submit"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-amber-500 text-white rounded-lg text-sm font-semibold hover:bg-amber-600 transition shadow-sm">
                <i class="fas fa-save"></i> Guardar
            </button>
            <a href="/mantenimiento/tareas"
               class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                Cancelar
            </a>
        </div>
        <?php if ($tarea && $tarea->tarea_id): ?>
        <div class="flex items-center gap-2 text-sm">
            <span class="text-gray-400">Tarea activa</span>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" name="activa" value="1" class="sr-only peer"
                       <?= (int)($tarea->activa ?? 1) ? 'checked' : '' ?>>
                <div class="w-10 h-5 bg-gray-200 peer-focus:ring-2 peer-focus:ring-sky-300 rounded-full peer
                            peer-checked:after:translate-x-full peer-checked:after:border-white
                            after:content-[''] after:absolute after:top-0.5 after:left-[2px]
                            after:bg-white after:border-gray-300 after:border after:rounded-full
                            after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
            </label>
        </div>
        <?php else: ?>
        <input type="hidden" name="activa" value="1">
        <?php endif; ?>
    </div>

    <!-- Form card -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <h2 class="text-3xl font-bold text-gray-900 mb-6"><?= View::e($page_title ?? '') ?></h2>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-16">

            <!-- Columna izquierda -->
            <div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Nombre *</label>
                    <input type="text" name="nombre" required
                           value="<?= View::e($tarea->nombre ?? '') ?>"
                           placeholder="Ej: Respaldo de base de datos, Actualizar parches"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Software *</label>
                    <select name="software_id" required
                            class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                        <option value="">— Seleccione —</option>
                        <?php foreach (($software_list ?? []) as $sw): ?>
                        <option value="<?= $sw['software_id'] ?>"
                            <?= (int)($tarea->software_id ?? 0) === (int)$sw['software_id'] ? 'selected' : '' ?>>
                            <?= View::e($sw['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Frecuencia *</label>
                    <select name="frecuencia" required
                            class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                        <option value="diaria"      <?= ($tarea->frecuencia ?? '') === 'diaria'      ? 'selected' : '' ?>>Diaria</option>
                        <option value="semanal"     <?= ($tarea->frecuencia ?? '') === 'semanal'     ? 'selected' : '' ?>>Semanal</option>
                        <option value="mensual"     <?= ($tarea->frecuencia ?? 'mensual') === 'mensual'  ? 'selected' : '' ?>>Mensual</option>
                        <option value="trimestral"  <?= ($tarea->frecuencia ?? '') === 'trimestral'  ? 'selected' : '' ?>>Trimestral</option>
                        <option value="semestral"   <?= ($tarea->frecuencia ?? '') === 'semestral'   ? 'selected' : '' ?>>Semestral</option>
                        <option value="anual"       <?= ($tarea->frecuencia ?? '') === 'anual'       ? 'selected' : '' ?>>Anual</option>
                    </select>
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Prioridad *</label>
                    <select name="prioridad" required
                            class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                        <option value="alta"  <?= ($tarea->prioridad ?? '') === 'alta'  ? 'selected' : '' ?>>Alta</option>
                        <option value="media" <?= ($tarea->prioridad ?? 'media') === 'media' ? 'selected' : '' ?>>Media</option>
                        <option value="baja"  <?= ($tarea->prioridad ?? '') === 'baja'  ? 'selected' : '' ?>>Baja</option>
                    </select>
                </div>
            </div>

            <!-- Columna derecha -->
            <div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Responsable</label>
                    <input type="text" name="responsable"
                           value="<?= View::e($tarea->responsable ?? '') ?>"
                           placeholder="Nombre o cargo del responsable"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Duración est. (min)</label>
                    <input type="number" name="duracion_estimada" min="1"
                           value="<?= (int)($tarea->duracion_estimada ?? 30) ?>"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Próxima ejecución</label>
                    <input type="date" name="proxima_ejecucion"
                           value="<?= View::e($tarea->proxima_ejecucion ?? '') ?>"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
            </div>

            <!-- Descripción — full width -->
            <div class="lg:col-span-2 flex items-start gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-44 shrink-0 pt-1">Descripción</label>
                <textarea name="descripcion" rows="3"
                          placeholder="Pasos a seguir, herramientas necesarias, notas..."
                          class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none resize-none"><?= View::e($tarea->descripcion ?? '') ?></textarea>
            </div>
        </div>
    </div>
</form>

<?php View::endSection('content'); ?>

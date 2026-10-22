<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<!-- Breadcrumb -->
<div class="flex items-center gap-2 text-sm text-gray-400 mb-3">
    <a href="/mantenimiento/software" class="hover:text-gray-600 transition-colors">Software</a>
    <i class="fas fa-chevron-right text-xs"></i>
    <span class="text-gray-700 font-medium"><?= View::e($page_title ?? '') ?></span>
</div>

<form method="post" action="<?= View::e($action) ?>">
    <?= View::csrf() ?>

    <!-- Action bar -->
    <div class="flex items-center justify-between bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-2.5 mb-4">
        <div class="flex gap-2">
            <button type="submit"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
                <i class="fas fa-save"></i> Guardar
            </button>
            <a href="/mantenimiento/software"
               class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                Cancelar
            </a>
        </div>
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
                           value="<?= View::e($software->nombre ?? '') ?>"
                           placeholder="Ej: Sistema POS, MySQL, Ubuntu Server"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Tipo *</label>
                    <select name="tipo" required
                            class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                        <option value="aplicacion"        <?= ($software->tipo ?? '') === 'aplicacion'        ? 'selected' : '' ?>>Aplicación</option>
                        <option value="base_datos"        <?= ($software->tipo ?? '') === 'base_datos'        ? 'selected' : '' ?>>Base de datos</option>
                        <option value="sistema_operativo" <?= ($software->tipo ?? '') === 'sistema_operativo' ? 'selected' : '' ?>>Sistema operativo</option>
                        <option value="servicio"          <?= ($software->tipo ?? '') === 'servicio'          ? 'selected' : '' ?>>Servicio / Daemon</option>
                        <option value="otro"              <?= ($software->tipo ?? '') === 'otro'              ? 'selected' : '' ?>>Otro</option>
                    </select>
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Versión</label>
                    <input type="text" name="version"
                           value="<?= View::e($software->version ?? '') ?>"
                           placeholder="Ej: 8.0.32, 22.04 LTS"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Proveedor</label>
                    <input type="text" name="proveedor"
                           value="<?= View::e($software->proveedor ?? '') ?>"
                           placeholder="Ej: Oracle, Microsoft, Canonical"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Estado</label>
                    <select name="estado"
                            class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                        <option value="activo"   <?= ($software->estado ?? 'activo') === 'activo'   ? 'selected' : '' ?>>Activo</option>
                        <option value="inactivo" <?= ($software->estado ?? '') === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </div>
            </div>

            <!-- Columna derecha -->
            <div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Servidor / Equipo</label>
                    <input type="text" name="servidor"
                           value="<?= View::e($software->servidor ?? '') ?>"
                           placeholder="Ej: SRV-POS-01, 192.168.1.10"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Fecha instalación</label>
                    <input type="date" name="fecha_instalacion"
                           value="<?= View::e($software->fecha_instalacion ?? '') ?>"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Vencimiento licencia</label>
                    <input type="date" name="fecha_vencimiento_licencia"
                           value="<?= View::e($software->fecha_vencimiento_licencia ?? '') ?>"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
                <div class="flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-44 shrink-0">Contacto soporte</label>
                    <input type="text" name="contacto_soporte"
                           value="<?= View::e($software->contacto_soporte ?? '') ?>"
                           placeholder="Teléfono, email o ticket URL"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
            </div>

            <!-- Notas — full width -->
            <div class="lg:col-span-2 flex items-start gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-44 shrink-0 pt-1">Notas</label>
                <textarea name="notas" rows="3"
                          placeholder="Notas adicionales, configuraciones importantes..."
                          class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none resize-none"><?= View::e($software->notas ?? '') ?></textarea>
            </div>
        </div>
    </div>
</form>

<?php View::endSection('content'); ?>

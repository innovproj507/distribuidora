<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<?php $s = $stats ?? []; ?>

<!-- Tarjetas de métricas -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <div class="bg-white rounded-xl p-4 shadow-sm border-l-4 border-sky-400">
        <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Total</p>
        <p class="text-2xl font-black text-sky-600"><?= (int)($s['total'] ?? 0) ?></p>
        <p class="text-xs text-gray-400 mt-0.5">sistemas registrados</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border-l-4 border-emerald-400">
        <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Activos</p>
        <p class="text-2xl font-black text-emerald-600"><?= (int)($s['activos'] ?? 0) ?></p>
        <p class="text-xs text-gray-400 mt-0.5">en operación</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border-l-4 border-gray-300">
        <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Inactivos</p>
        <p class="text-2xl font-black text-gray-500"><?= (int)($s['inactivos'] ?? 0) ?></p>
        <p class="text-xs text-gray-400 mt-0.5">fuera de servicio</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border-l-4 border-amber-400">
        <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Lic. por vencer</p>
        <p class="text-2xl font-black text-amber-600"><?= (int)($s['licencias_por_vencer'] ?? 0) ?></p>
        <p class="text-xs text-gray-400 mt-0.5">próximos 30 días</p>
    </div>
</div>

<!-- Barra de búsqueda -->
<div class="bg-white rounded-xl border border-gray-100 shadow-sm p-3 mb-4">
    <form method="GET" id="search-form" class="flex flex-wrap items-end gap-3">

        <div class="flex-1 min-w-48">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Buscar</label>
            <div class="relative">
                <i class="fas fa-search absolute left-0 top-2 text-gray-400 text-xs"></i>
                <input type="text" name="buscar" id="search-input"
                    value="<?= View::e($buscar ?? '') ?>"
                    placeholder="Nombre, proveedor, servidor..."
                    class="w-full pl-5 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
            </div>
        </div>

        <div class="min-w-36">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Tipo</label>
            <select name="tipo" onchange="this.form.submit()"
                class="w-full py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                <option value="">Todos</option>
                <option value="aplicacion"       <?= ($tipo_filtro ?? '') === 'aplicacion'       ? 'selected' : '' ?>>Aplicación</option>
                <option value="base_datos"        <?= ($tipo_filtro ?? '') === 'base_datos'        ? 'selected' : '' ?>>Base de datos</option>
                <option value="sistema_operativo" <?= ($tipo_filtro ?? '') === 'sistema_operativo' ? 'selected' : '' ?>>S. Operativo</option>
                <option value="servicio"          <?= ($tipo_filtro ?? '') === 'servicio'          ? 'selected' : '' ?>>Servicio</option>
                <option value="otro"              <?= ($tipo_filtro ?? '') === 'otro'              ? 'selected' : '' ?>>Otro</option>
            </select>
        </div>

        <div class="min-w-28">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Estado</label>
            <select name="estado" onchange="this.form.submit()"
                class="w-full py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                <option value="">Todos</option>
                <option value="activo"   <?= ($estado_filtro ?? '') === 'activo'   ? 'selected' : '' ?>>Activo</option>
                <option value="inactivo" <?= ($estado_filtro ?? '') === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
            </select>
        </div>

        <div class="flex items-center gap-2 ml-auto">
            <span class="text-xs font-medium text-gray-400 whitespace-nowrap">
                <?= number_format($pagination['total'] ?? 0) ?> sistema(s)
                <?php if (!empty($buscar) || !empty($tipo_filtro) || !empty($estado_filtro)): ?>
                <span class="text-sky-500">— filtrado</span>
                <?php endif; ?>
            </span>
            <?php if (!empty($buscar) || !empty($tipo_filtro) || !empty($estado_filtro)): ?>
            <a href="/mantenimiento/software"
               class="inline-flex items-center gap-1 px-3 py-2 border border-gray-200 text-gray-400 rounded-lg text-sm hover:bg-gray-50 transition" title="Limpiar filtros">
                <i class="fas fa-times"></i>
            </a>
            <?php endif; ?>
            <a href="/mantenimiento/software/nuevo"
               class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
                <i class="fas fa-plus"></i> Nuevo Software
            </a>
        </div>
    </form>
</div>

<!-- Tabla -->
<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">

    <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
        <span class="text-xs font-medium text-gray-500">Ordenado por nombre</span>
        <span class="text-xs text-gray-400">
            Pág. <?= $pagination['current_page'] ?? 1 ?> / <?= $pagination['total_pages'] ?? 1 ?>
        </span>
    </div>

    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-100 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">
                <th class="px-4 py-3 bg-white">Sistema</th>
                <th class="px-4 py-3 bg-white">Versión / Proveedor</th>
                <th class="px-4 py-3 bg-white">Servidor</th>
                <th class="px-4 py-3 bg-white text-center">Licencia vence</th>
                <th class="px-4 py-3 bg-white text-center">Tareas</th>
                <th class="px-4 py-3 bg-white text-center">Estado</th>
                <th class="px-4 py-3 bg-white text-center">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            <?php
            $tipoConfig = [
                'aplicacion'       => ['icon' => 'fa-window-maximize', 'bg' => 'bg-sky-50',    'text' => 'text-sky-500',    'label' => 'Aplicación'],
                'base_datos'       => ['icon' => 'fa-database',        'bg' => 'bg-violet-50', 'text' => 'text-violet-500', 'label' => 'Base de datos'],
                'sistema_operativo'=> ['icon' => 'fa-server',          'bg' => 'bg-emerald-50','text' => 'text-emerald-500','label' => 'S. Operativo'],
                'servicio'         => ['icon' => 'fa-cogs',            'bg' => 'bg-orange-50', 'text' => 'text-orange-500', 'label' => 'Servicio'],
                'otro'             => ['icon' => 'fa-cube',            'bg' => 'bg-gray-50',   'text' => 'text-gray-400',   'label' => 'Otro'],
            ];
            foreach (($software_list ?? []) as $sw):
                $tc  = $tipoConfig[$sw['tipo']] ?? $tipoConfig['otro'];
                $hoy = date('Y-m-d');
                $lic = $sw['fecha_vencimiento_licencia'] ?? null;
                if (!$lic) {
                    $licClass = 'text-gray-400'; $licLabel = '—';
                } elseif ($lic < $hoy) {
                    $licClass = 'text-red-600 font-semibold'; $licLabel = date('d/m/Y', strtotime($lic)) . ' ⚠';
                } elseif ($lic <= date('Y-m-d', strtotime('+30 days'))) {
                    $licClass = 'text-amber-600 font-semibold'; $licLabel = date('d/m/Y', strtotime($lic));
                } else {
                    $licClass = 'text-gray-600'; $licLabel = date('d/m/Y', strtotime($lic));
                }
            ?>
            <tr class="hover:bg-sky-50/40 transition-colors group">

                <!-- Sistema -->
                <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg <?= $tc['bg'] ?> flex items-center justify-center shrink-0">
                            <i class="fas <?= $tc['icon'] ?> <?= $tc['text'] ?> text-xs"></i>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-800"><?= View::e($sw['nombre']) ?></p>
                            <p class="text-xs text-gray-400"><?= $tc['label'] ?></p>
                        </div>
                    </div>
                </td>

                <!-- Versión / Proveedor -->
                <td class="px-4 py-3">
                    <p class="text-xs font-mono text-gray-600"><?= View::e($sw['version'] ?: '—') ?></p>
                    <p class="text-xs text-gray-400"><?= View::e($sw['proveedor'] ?: '—') ?></p>
                </td>

                <!-- Servidor -->
                <td class="px-4 py-3 text-sm text-gray-600">
                    <?= View::e($sw['servidor'] ?: '—') ?>
                </td>

                <!-- Licencia vence -->
                <td class="px-4 py-3 text-center text-xs <?= $licClass ?>">
                    <?= $licLabel ?>
                </td>

                <!-- Tareas activas -->
                <td class="px-4 py-3 text-center">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold
                        <?= (int)($sw['tareas_activas'] ?? 0) > 0 ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-400' ?>">
                        <?= (int)($sw['tareas_activas'] ?? 0) ?>
                    </span>
                </td>

                <!-- Estado -->
                <td class="px-4 py-3 text-center">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border
                        <?= $sw['estado'] === 'activo'
                            ? 'bg-emerald-50 text-emerald-700 border-emerald-100'
                            : 'bg-red-50 text-red-600 border-red-100' ?>">
                        <?= ucfirst(View::e($sw['estado'])) ?>
                    </span>
                </td>

                <!-- Acciones -->
                <td class="px-4 py-3 text-center">
                    <div class="flex items-center justify-center gap-2 opacity-60 group-hover:opacity-100 transition-opacity">
                        <a href="/mantenimiento/software/<?= $sw['software_id'] ?>/editar"
                           class="text-gray-400 hover:text-blue-600" title="Editar">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form method="post" action="/mantenimiento/software/<?= $sw['software_id'] ?>/eliminar" class="inline"
                              onsubmit="return confirm('¿Eliminar <?= View::e(addslashes($sw['nombre'])) ?>? Se eliminarán todas las tareas asociadas.')">
                            <?= View::csrf() ?>
                            <button type="submit" class="text-gray-300 hover:text-red-500" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>

            <?php if (empty($software_list)): ?>
            <tr>
                <td colspan="7" class="px-4 py-16 text-center">
                    <div class="inline-flex flex-col items-center gap-2">
                        <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center">
                            <i class="fas fa-laptop-code text-gray-300 text-2xl"></i>
                        </div>
                        <p class="text-sm font-medium text-gray-400">No se encontró software registrado</p>
                        <?php if (empty($buscar) && empty($tipo_filtro) && empty($estado_filtro)): ?>
                        <a href="/mantenimiento/software/nuevo" class="mt-1 text-sky-500 hover:underline text-sm font-medium">
                            Registrar el primer sistema
                        </a>
                        <?php else: ?>
                        <a href="/mantenimiento/software" class="mt-1 text-gray-400 hover:text-sky-500 text-xs">Limpiar filtros</a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if (($pagination['total_pages'] ?? 1) > 1): ?>
<div class="mt-4">
    <?php View::include('partials.pagination', ['pagination' => $pagination]); ?>
</div>
<?php endif; ?>

<?php View::endSection('content'); ?>

<?php View::section('extra_js'); ?>
<script>
    var searchTimeout;
    var searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function (e) {
            clearTimeout(searchTimeout);
            if (e.target.value.length >= 2 || e.target.value.length === 0) {
                searchTimeout = setTimeout(function () {
                    document.getElementById('search-form').submit();
                }, 500);
            }
        });
    }
</script>
<?php View::endSection('extra_js'); ?>

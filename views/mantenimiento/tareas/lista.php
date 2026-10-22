<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<?php $s = $stats ?? []; ?>

<!-- Tarjetas de métricas -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <div class="bg-white rounded-xl p-4 shadow-sm border-l-4 border-sky-400">
        <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Total tareas</p>
        <p class="text-2xl font-black text-sky-600"><?= (int)($s['total'] ?? 0) ?></p>
        <p class="text-xs text-gray-400 mt-0.5"><?= (int)($s['activas'] ?? 0) ?> activas</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border-l-4 border-emerald-400">
        <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Activas</p>
        <p class="text-2xl font-black text-emerald-600"><?= (int)($s['activas'] ?? 0) ?></p>
        <p class="text-xs text-gray-400 mt-0.5">en el plan</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border-l-4 border-amber-400">
        <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Esta semana</p>
        <p class="text-2xl font-black text-amber-600"><?= (int)($s['proximas_semana'] ?? 0) ?></p>
        <p class="text-xs text-gray-400 mt-0.5">próximas 7 días</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border-l-4 border-red-400">
        <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Vencidas</p>
        <p class="text-2xl font-black text-red-600"><?= (int)($s['vencidas'] ?? 0) ?></p>
        <p class="text-xs text-gray-400 mt-0.5">sin ejecutar</p>
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
                    placeholder="Nombre de la tarea..."
                    class="w-full pl-5 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
            </div>
        </div>

        <div class="min-w-40">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Software</label>
            <select name="software" onchange="this.form.submit()"
                class="w-full py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                <option value="">Todos</option>
                <?php foreach (($software_list ?? []) as $sw): ?>
                <option value="<?= $sw['software_id'] ?>" <?= ($software_filtro ?? '') == $sw['software_id'] ? 'selected' : '' ?>>
                    <?= View::e($sw['nombre']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="min-w-32">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Frecuencia</label>
            <select name="frecuencia" onchange="this.form.submit()"
                class="w-full py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                <option value="">Todas</option>
                <option value="diaria"      <?= ($frecuencia_filtro ?? '') === 'diaria'      ? 'selected' : '' ?>>Diaria</option>
                <option value="semanal"     <?= ($frecuencia_filtro ?? '') === 'semanal'     ? 'selected' : '' ?>>Semanal</option>
                <option value="mensual"     <?= ($frecuencia_filtro ?? '') === 'mensual'     ? 'selected' : '' ?>>Mensual</option>
                <option value="trimestral"  <?= ($frecuencia_filtro ?? '') === 'trimestral'  ? 'selected' : '' ?>>Trimestral</option>
                <option value="semestral"   <?= ($frecuencia_filtro ?? '') === 'semestral'   ? 'selected' : '' ?>>Semestral</option>
                <option value="anual"       <?= ($frecuencia_filtro ?? '') === 'anual'       ? 'selected' : '' ?>>Anual</option>
            </select>
        </div>

        <div class="min-w-28">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Prioridad</label>
            <select name="prioridad" onchange="this.form.submit()"
                class="w-full py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                <option value="">Todas</option>
                <option value="alta"  <?= ($prioridad_filtro ?? '') === 'alta'  ? 'selected' : '' ?>>Alta</option>
                <option value="media" <?= ($prioridad_filtro ?? '') === 'media' ? 'selected' : '' ?>>Media</option>
                <option value="baja"  <?= ($prioridad_filtro ?? '') === 'baja'  ? 'selected' : '' ?>>Baja</option>
            </select>
        </div>

        <div class="flex items-center gap-2 ml-auto">
            <span class="text-xs font-medium text-gray-400 whitespace-nowrap">
                <?= number_format($pagination['total'] ?? 0) ?> tarea(s)
                <?php if (!empty($buscar) || !empty($software_filtro) || !empty($frecuencia_filtro) || !empty($prioridad_filtro)): ?>
                <span class="text-sky-500">— filtrado</span>
                <?php endif; ?>
            </span>
            <?php if (!empty($buscar) || !empty($software_filtro) || !empty($frecuencia_filtro) || !empty($prioridad_filtro)): ?>
            <a href="/mantenimiento/tareas"
               class="inline-flex items-center gap-1 px-3 py-2 border border-gray-200 text-gray-400 rounded-lg text-sm hover:bg-gray-50 transition" title="Limpiar filtros">
                <i class="fas fa-times"></i>
            </a>
            <?php endif; ?>
            <a href="/mantenimiento/tareas/nueva"
               class="inline-flex items-center gap-1.5 px-4 py-2 bg-amber-500 text-white rounded-lg text-sm font-semibold hover:bg-amber-600 transition shadow-sm">
                <i class="fas fa-plus"></i> Nueva Tarea
            </a>
        </div>
    </form>
</div>

<!-- Tabla + Modal ejecutar -->
<div x-data="ejecutarModal()">

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">

        <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
            <span class="text-xs font-medium text-gray-500">Ordenado por próxima ejecución</span>
            <span class="text-xs text-gray-400">
                Pág. <?= $pagination['current_page'] ?? 1 ?> / <?= $pagination['total_pages'] ?? 1 ?>
            </span>
        </div>

        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">
                    <th class="px-4 py-3 bg-white">Tarea</th>
                    <th class="px-4 py-3 bg-white">Software</th>
                    <th class="px-4 py-3 bg-white text-center">Frecuencia</th>
                    <th class="px-4 py-3 bg-white text-center">Prioridad</th>
                    <th class="px-4 py-3 bg-white text-center">Próxima</th>
                    <th class="px-4 py-3 bg-white text-center">Última</th>
                    <th class="px-4 py-3 bg-white text-center">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php
                $frecColors = [
                    'diaria'     => 'bg-sky-50 text-sky-700 border-sky-100',
                    'semanal'    => 'bg-indigo-50 text-indigo-700 border-indigo-100',
                    'mensual'    => 'bg-violet-50 text-violet-700 border-violet-100',
                    'trimestral' => 'bg-pink-50 text-pink-700 border-pink-100',
                    'semestral'  => 'bg-orange-50 text-orange-700 border-orange-100',
                    'anual'      => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                ];
                $priorColors = [
                    'alta'  => 'bg-red-50 text-red-700 border-red-100',
                    'media' => 'bg-amber-50 text-amber-700 border-amber-100',
                    'baja'  => 'bg-gray-50 text-gray-500 border-gray-100',
                ];
                foreach (($tareas ?? []) as $t):
                    $hoy    = date('Y-m-d');
                    $prox   = $t['proxima_ejecucion'] ?? null;
                    if (!$prox) {
                        $proxClass = 'text-gray-400'; $proxLabel = 'Sin programar';
                    } elseif ($prox < $hoy) {
                        $proxClass = 'text-red-600 font-bold'; $proxLabel = date('d/m/Y', strtotime($prox)) . ' ⚠';
                    } elseif ($prox <= date('Y-m-d', strtotime('+3 days'))) {
                        $proxClass = 'text-amber-600 font-semibold'; $proxLabel = date('d/m/Y', strtotime($prox));
                    } else {
                        $proxClass = 'text-gray-600'; $proxLabel = date('d/m/Y', strtotime($prox));
                    }
                ?>
                <tr class="hover:bg-sky-50/40 transition-colors group">

                    <!-- Tarea -->
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center shrink-0">
                                <i class="fas fa-wrench text-amber-500 text-xs"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800"><?= View::e($t['nombre']) ?></p>
                                <?php if ($t['responsable']): ?>
                                <p class="text-xs text-gray-400"><?= View::e($t['responsable']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>

                    <!-- Software -->
                    <td class="px-4 py-3">
                        <p class="text-sm text-gray-700"><?= View::e($t['software_nombre']) ?></p>
                        <p class="text-xs text-gray-400"><?= View::e($t['duracion_estimada'] ?? 0) ?> min estimados</p>
                    </td>

                    <!-- Frecuencia -->
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border
                            <?= $frecColors[$t['frecuencia']] ?? 'bg-gray-50 text-gray-500 border-gray-100' ?>">
                            <?= ucfirst($t['frecuencia']) ?>
                        </span>
                    </td>

                    <!-- Prioridad -->
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border
                            <?= $priorColors[$t['prioridad']] ?? 'bg-gray-50 text-gray-500 border-gray-100' ?>">
                            <?= ucfirst($t['prioridad']) ?>
                        </span>
                    </td>

                    <!-- Próxima ejecución -->
                    <td class="px-4 py-3 text-center text-xs <?= $proxClass ?>">
                        <?= $proxLabel ?>
                    </td>

                    <!-- Última ejecución -->
                    <td class="px-4 py-3 text-center text-xs text-gray-500">
                        <?= $t['ultima_ejecucion'] ? date('d/m/Y', strtotime($t['ultima_ejecucion'])) : '—' ?>
                    </td>

                    <!-- Acciones -->
                    <td class="px-4 py-3 text-center">
                        <div class="flex items-center justify-center gap-2 opacity-60 group-hover:opacity-100 transition-opacity">
                            <?php if ($t['activa']): ?>
                            <button type="button"
                                @click="abrir(<?= $t['tarea_id'] ?>, <?= json_encode($t['nombre']) ?>)"
                                class="text-emerald-500 hover:text-emerald-700" title="Registrar ejecución">
                                <i class="fas fa-play"></i>
                            </button>
                            <?php endif; ?>
                            <a href="/mantenimiento/tareas/<?= $t['tarea_id'] ?>/editar"
                               class="text-gray-400 hover:text-blue-600" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="post" action="/mantenimiento/tareas/<?= $t['tarea_id'] ?>/eliminar" class="inline"
                                  onsubmit="return confirm('¿Eliminar la tarea <?= View::e(addslashes($t['nombre'])) ?>?')">
                                <?= View::csrf() ?>
                                <button type="submit" class="text-gray-300 hover:text-red-500" title="Eliminar">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if (empty($tareas)): ?>
                <tr>
                    <td colspan="7" class="px-4 py-16 text-center">
                        <div class="inline-flex flex-col items-center gap-2">
                            <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center">
                                <i class="fas fa-calendar-check text-gray-300 text-2xl"></i>
                            </div>
                            <p class="text-sm font-medium text-gray-400">No se encontraron tareas</p>
                            <?php if (empty($buscar) && empty($software_filtro) && empty($frecuencia_filtro) && empty($prioridad_filtro)): ?>
                            <a href="/mantenimiento/tareas/nueva" class="mt-1 text-sky-500 hover:underline text-sm font-medium">
                                Crear la primera tarea
                            </a>
                            <?php else: ?>
                            <a href="/mantenimiento/tareas" class="mt-1 text-gray-400 hover:text-sky-500 text-xs">Limpiar filtros</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal: registrar ejecución -->
    <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center" x-cloak>
        <div class="fixed inset-0 bg-gray-900/50" @click="open = false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 z-10">
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-1">Registrar ejecución</h3>
                <p class="text-sm text-gray-500 mb-5" x-text="tareaNombre"></p>

                <form method="POST" :action="'/mantenimiento/tareas/' + tareaId + '/ejecutar'">
                    <?= View::csrf() ?>

                    <div class="mb-4">
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Estado *</label>
                        <select name="estado" class="form-select">
                            <option value="completado">Completado</option>
                            <option value="fallido">Fallido</option>
                            <option value="omitido">Omitido</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Duración real (minutos)</label>
                        <input type="number" name="duracion_real" min="0" placeholder="0"
                               class="form-input">
                    </div>

                    <div class="mb-5">
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Notas / Observaciones</label>
                        <textarea name="notas" rows="3" placeholder="Opcional: qué se hizo, incidencias..."
                                  class="form-textarea"></textarea>
                    </div>

                    <div class="flex gap-3 justify-end">
                        <button type="button" @click="open = false"
                            class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="px-5 py-2 bg-emerald-500 text-white rounded-lg text-sm font-semibold hover:bg-emerald-600 transition shadow-sm">
                            <i class="fas fa-check mr-1"></i> Registrar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<?php if (($pagination['total_pages'] ?? 1) > 1): ?>
<div class="mt-4">
    <?php View::include('partials.pagination', ['pagination' => $pagination]); ?>
</div>
<?php endif; ?>

<?php View::endSection('content'); ?>

<?php View::section('extra_js'); ?>
<script>
    function ejecutarModal() {
        return {
            open: false,
            tareaId: null,
            tareaNombre: '',
            abrir: function (id, nombre) {
                this.tareaId = id;
                this.tareaNombre = nombre;
                this.open = true;
            }
        };
    }

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

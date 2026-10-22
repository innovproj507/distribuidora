<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<?php
$sw = $stats['software'] ?? [];
$tk = $stats['tareas']   ?? [];
?>

<!-- Tarjetas de métricas -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <div class="bg-white rounded-xl p-4 shadow-sm border-l-4 border-sky-400">
        <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Software</p>
        <p class="text-2xl font-black text-sky-600"><?= (int)($sw['total'] ?? 0) ?></p>
        <p class="text-xs text-gray-400 mt-0.5"><?= (int)($sw['activos'] ?? 0) ?> activos</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border-l-4 border-emerald-400">
        <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Tareas activas</p>
        <p class="text-2xl font-black text-emerald-600"><?= (int)($tk['activas'] ?? 0) ?></p>
        <p class="text-xs text-gray-400 mt-0.5">en plan preventivo</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border-l-4 border-amber-400">
        <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Esta semana</p>
        <p class="text-2xl font-black text-amber-600"><?= (int)($tk['proximas_semana'] ?? 0) ?></p>
        <p class="text-xs text-gray-400 mt-0.5">tareas próximas</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border-l-4 border-red-400">
        <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Vencidas</p>
        <p class="text-2xl font-black text-red-600"><?= (int)($tk['vencidas'] ?? 0) ?></p>
        <p class="text-xs text-gray-400 mt-0.5">requieren atención</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

    <!-- Próximas tareas -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-calendar-check text-amber-500"></i>
                Próximas tareas
            </h3>
            <a href="/mantenimiento/tareas" class="text-xs text-sky-500 hover:underline">Ver todas</a>
        </div>

        <?php if (empty($proximas)): ?>
        <div class="px-4 py-10 text-center">
            <i class="fas fa-check-circle text-emerald-300 text-3xl mb-2"></i>
            <p class="text-sm text-gray-400">No hay tareas próximas en los próximos 14 días</p>
        </div>
        <?php else: ?>
        <ul class="divide-y divide-gray-50">
            <?php foreach ($proximas as $t):
                $hoy     = date('Y-m-d');
                $proxima = $t['proxima_ejecucion'] ?? null;
                if (!$proxima) {
                    $colorDot = 'bg-gray-400'; $colorText = 'text-gray-400'; $label = 'Sin programar';
                } elseif ($proxima < $hoy) {
                    $colorDot = 'bg-red-500'; $colorText = 'text-red-600'; $label = 'Vencida';
                } elseif ($proxima <= date('Y-m-d', strtotime('+3 days'))) {
                    $colorDot = 'bg-amber-500'; $colorText = 'text-amber-600'; $label = date('d/m/Y', strtotime($proxima));
                } else {
                    $colorDot = 'bg-emerald-500'; $colorText = 'text-emerald-600'; $label = date('d/m/Y', strtotime($proxima));
                }
                $priorColor = match($t['prioridad']) {
                    'alta'  => 'bg-red-50 text-red-700 border-red-100',
                    'baja'  => 'bg-gray-50 text-gray-500 border-gray-100',
                    default => 'bg-amber-50 text-amber-700 border-amber-100',
                };
            ?>
            <li class="px-4 py-3 flex items-center gap-3 hover:bg-gray-50/50 transition-colors">
                <div class="w-2.5 h-2.5 rounded-full shrink-0 <?= $colorDot ?>"></div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate"><?= View::e($t['nombre']) ?></p>
                    <p class="text-xs text-gray-400 truncate"><?= View::e($t['software_nombre']) ?></p>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-xs font-semibold <?= $colorText ?>"><?= $label ?></p>
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium border <?= $priorColor ?>">
                        <?= ucfirst($t['prioridad']) ?>
                    </span>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>

    <!-- Últimas ejecuciones -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-history text-sky-500"></i>
                Últimas ejecuciones
            </h3>
        </div>

        <?php if (empty($recientes)): ?>
        <div class="px-4 py-10 text-center">
            <i class="fas fa-inbox text-gray-300 text-3xl mb-2"></i>
            <p class="text-sm text-gray-400">Aún no hay ejecuciones registradas</p>
        </div>
        <?php else: ?>
        <ul class="divide-y divide-gray-50">
            <?php foreach ($recientes as $e):
                $estadoStyle = match($e['estado']) {
                    'completado' => ['icon' => 'fa-check-circle', 'color' => 'text-emerald-500', 'badge' => 'bg-emerald-50 text-emerald-700 border-emerald-100'],
                    'fallido'    => ['icon' => 'fa-times-circle', 'color' => 'text-red-500',     'badge' => 'bg-red-50 text-red-700 border-red-100'],
                    default      => ['icon' => 'fa-minus-circle', 'color' => 'text-gray-400',    'badge' => 'bg-gray-50 text-gray-500 border-gray-100'],
                };
            ?>
            <li class="px-4 py-3 flex items-center gap-3">
                <i class="fas <?= $estadoStyle['icon'] ?> <?= $estadoStyle['color'] ?> text-lg shrink-0"></i>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate"><?= View::e($e['tarea_nombre']) ?></p>
                    <p class="text-xs text-gray-400 truncate">
                        <?= View::e($e['software_nombre']) ?>
                        <?php if ($e['usuario']): ?> · <?= View::e($e['usuario']) ?><?php endif; ?>
                    </p>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-xs text-gray-500"><?= date('d/m/Y', strtotime($e['fecha_ejecucion'])) ?></p>
                    <?php if ($e['duracion_real']): ?>
                    <p class="text-xs text-gray-400"><?= $e['duracion_real'] ?> min</p>
                    <?php endif; ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>

</div>

<!-- Accesos rápidos -->
<div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
    <a href="/mantenimiento/software/nuevo"
       class="flex items-center gap-3 bg-white rounded-xl border border-gray-100 shadow-sm px-5 py-4 hover:border-sky-200 hover:shadow-md transition group">
        <div class="w-10 h-10 rounded-lg bg-sky-50 flex items-center justify-center group-hover:bg-sky-100 transition">
            <i class="fas fa-plus text-sky-500"></i>
        </div>
        <div>
            <p class="text-sm font-semibold text-gray-800">Registrar software</p>
            <p class="text-xs text-gray-400">Agregar sistema o aplicación</p>
        </div>
    </a>
    <a href="/mantenimiento/tareas/nueva"
       class="flex items-center gap-3 bg-white rounded-xl border border-gray-100 shadow-sm px-5 py-4 hover:border-amber-200 hover:shadow-md transition group">
        <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center group-hover:bg-amber-100 transition">
            <i class="fas fa-plus text-amber-500"></i>
        </div>
        <div>
            <p class="text-sm font-semibold text-gray-800">Nueva tarea preventiva</p>
            <p class="text-xs text-gray-400">Agregar tarea al plan</p>
        </div>
    </a>
</div>

<?php View::endSection('content'); ?>

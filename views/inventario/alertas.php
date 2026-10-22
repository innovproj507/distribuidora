<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<?php
$tipoLabels = [
    'stock_minimo'        => ['label' => 'Stock Mínimo',         'icon' => 'fa-arrow-down',         'color' => 'amber'],
    'stock_exceso'        => ['label' => 'Stock en Exceso',      'icon' => 'fa-arrow-up',            'color' => 'blue'],
    'vencimiento_proximo' => ['label' => 'Próximo a Vencer',     'icon' => 'fa-clock',               'color' => 'orange'],
    'lote_vencido'        => ['label' => 'Lote Vencido',         'icon' => 'fa-skull-crossbones',    'color' => 'red'],
    'rotacion_lenta'      => ['label' => 'Rotación Lenta',       'icon' => 'fa-snooze',              'color' => 'gray'],
];
$prioridadClasses = [
    'critica' => 'bg-red-500 text-white',
    'alta'    => 'bg-orange-500 text-white',
    'media'   => 'bg-amber-500 text-white',
    'baja'    => 'bg-gray-400 text-white',
];
?>

<!-- Resumen -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <?php foreach (['critica' => ['Críticas','red','fa-fire'], 'alta' => ['Altas','orange','fa-arrow-up'], 'media' => ['Medias','amber','fa-minus'], 'baja' => ['Bajas','gray','fa-arrow-down']] as $p => [$label, $color, $icon]): ?>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-<?= $color ?>-500 to-<?= $color ?>-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80"><?= $label ?></p>
                <h3 class="text-xl font-extrabold mt-1"><?= $resumen[$p] ?? 0 ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas <?= $icon ?>"></i></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Acciones globales -->
<?php if (!empty($alertas)): ?>
<div class="flex items-center justify-between mb-4">
    <p class="text-sm text-gray-500"><?= count($alertas) ?> alerta(s) activa(s) — generadas en este momento</p>
    <form method="POST" action="/inventario/alertas/resolver-todas">
        <?= View::csrf() ?>
        <input type="hidden" name="tipo" value="">
        <button class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium transition">
            <i class="fas fa-check-double mr-1"></i> Resolver todas
        </button>
    </form>
</div>
<?php endif; ?>

<!-- Lista de alertas agrupadas por tipo -->
<?php
$agrupadas = [];
foreach ($alertas as $a) { $agrupadas[$a['tipo']][] = $a; }
?>

<?php if (empty($alertas)): ?>
<div class="bg-white rounded-2xl shadow-sm p-12 text-center">
    <i class="fas fa-check-circle text-5xl text-emerald-400 mb-3"></i>
    <p class="text-lg font-semibold text-gray-700">Sin alertas activas</p>
    <p class="text-sm text-gray-400 mt-1">El inventario está en buen estado.</p>
</div>
<?php else: ?>
<?php foreach ($agrupadas as $tipo => $grupo): ?>
<?php $meta = $tipoLabels[$tipo] ?? ['label' => $tipo, 'icon' => 'fa-bell', 'color' => 'gray']; ?>

<div class="bg-white rounded-2xl shadow-sm overflow-hidden mb-5">
    <!-- Cabecera del grupo -->
    <div class="flex items-center justify-between px-5 py-3 bg-gray-50 border-b">
        <div class="flex items-center gap-2">
            <span class="w-7 h-7 rounded-lg bg-<?= $meta['color'] ?>-500 text-white flex items-center justify-center shrink-0"><i class="fas <?= $meta['icon'] ?> text-xs"></i></span>
            <span class="font-semibold text-gray-700 text-sm"><?= $meta['label'] ?></span>
            <span class="ml-1 px-2 py-0.5 bg-<?= $meta['color'] ?>-500 text-white rounded-full text-xs font-bold"><?= count($grupo) ?></span>
        </div>
        <form method="POST" action="/inventario/alertas/resolver-todas">
            <?= View::csrf() ?>
            <input type="hidden" name="tipo" value="<?= $tipo ?>">
            <button class="text-xs text-gray-400 hover:text-gray-700 transition">Resolver grupo</button>
        </form>
    </div>

    <!-- Filas -->
    <table class="w-full text-sm">
        <thead class="text-[10px] text-gray-400 uppercase bg-gray-50/50">
            <tr>
                <th class="px-4 py-2 text-left">Mensaje</th>
                <th class="px-4 py-2 text-center">Prioridad</th>
                <th class="px-4 py-2 text-center w-24">Acción</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
        <?php foreach ($grupo as $alerta): ?>
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3">
                    <p class="text-gray-800"><?= View::e($alerta['mensaje']) ?></p>
                    <?php if ($alerta['producto_nombre']): ?>
                    <p class="text-xs text-gray-400 mt-0.5">
                        <span class="font-mono"><?= View::e($alerta['producto_codigo']) ?></span>
                        <?php if ($alerta['deposito_nombre']): ?> · <?= View::e($alerta['deposito_nombre']) ?><?php endif; ?>
                    </p>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase <?= $prioridadClasses[$alerta['prioridad']] ?? 'bg-gray-400 text-white' ?>">
                        <?= ucfirst($alerta['prioridad']) ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-center">
                    <form method="POST" action="/inventario/alertas/<?= $alerta['alerta_id'] ?>/resolver">
                        <?= View::csrf() ?>
                        <button class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-500 text-white rounded-lg text-[10px] font-bold hover:bg-emerald-600 transition">
                            <i class="fas fa-check"></i>Resolver
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php View::endSection('content'); ?>

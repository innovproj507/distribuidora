<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<?php
$estadoClases = [
    'borrador'   => 'bg-gray-100 text-gray-600',
    'en_proceso' => 'bg-blue-100 text-blue-700',
    'completado' => 'bg-emerald-100 text-emerald-700',
    'cancelado'  => 'bg-red-100 text-red-600',
];
?>

<div class="flex justify-between items-center mb-6">
    <div></div>
    <a href="/inventario/conteos/nuevo"
       class="px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-medium hover:bg-sky-600 transition shadow-sm">
        <i class="fas fa-plus mr-1"></i> Nuevo Conteo
    </a>
</div>

<?php if (empty($conteos)): ?>
<div class="bg-white rounded-xl shadow-sm p-12 text-center">
    <i class="fas fa-clipboard-list text-5xl text-gray-200 mb-4"></i>
    <p class="text-gray-500 font-medium">Aún no hay conteos registrados.</p>
    <a href="/inventario/conteos/nuevo" class="mt-3 inline-block text-sm text-sky-600 hover:underline">Iniciar primer conteo</a>
</div>
<?php else: ?>
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
            <tr>
                <th class="px-4 py-3 text-left">#</th>
                <th class="px-4 py-3 text-left">Descripción</th>
                <th class="px-4 py-3 text-left">Depósito</th>
                <th class="px-4 py-3 text-center">Tipo</th>
                <th class="px-4 py-3 text-center">Productos</th>
                <th class="px-4 py-3 text-center">Diferencias</th>
                <th class="px-4 py-3 text-center">Estado</th>
                <th class="px-4 py-3 text-left">Fecha</th>
                <th class="px-4 py-3 text-right">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
        <?php foreach ($conteos as $c): ?>
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 font-mono text-gray-400">#<?= $c['conteo_id'] ?></td>
                <td class="px-4 py-3 font-medium text-gray-800"><?= View::e($c['descripcion'] ?: '—') ?></td>
                <td class="px-4 py-3 text-gray-600"><?= View::e($c['deposito_nombre']) ?></td>
                <td class="px-4 py-3 text-center capitalize text-gray-600"><?= $c['tipo'] ?></td>
                <td class="px-4 py-3 text-center"><?= $c['total_productos'] ?></td>
                <td class="px-4 py-3 text-center">
                    <?php if ($c['total_diferencias'] > 0): ?>
                        <span class="text-amber-600 font-semibold"><?= $c['total_diferencias'] ?></span>
                    <?php else: ?>
                        <span class="text-gray-400">—</span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $estadoClases[$c['estado']] ?? '' ?>">
                        <?= ucfirst($c['estado']) ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-gray-500 text-xs"><?= date('d/m/Y', strtotime($c['fecha_registro'])) ?></td>
                <td class="px-4 py-3 text-right space-x-2">
                    <?php if ($c['estado'] === 'en_proceso'): ?>
                        <a href="/inventario/conteos/<?= $c['conteo_id'] ?>/contar"
                           class="text-sky-600 hover:text-sky-800 font-medium text-xs">
                            <i class="fas fa-edit mr-1"></i>Contar
                        </a>
                        <a href="/inventario/conteos/<?= $c['conteo_id'] ?>/reconciliar"
                           class="text-amber-600 hover:text-amber-800 font-medium text-xs">
                            <i class="fas fa-balance-scale mr-1"></i>Reconciliar
                        </a>
                    <?php elseif ($c['estado'] === 'completado'): ?>
                        <a href="/inventario/conteos/<?= $c['conteo_id'] ?>/reconciliar"
                           class="text-emerald-600 hover:text-emerald-800 font-medium text-xs">
                            <i class="fas fa-eye mr-1"></i>Ver
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php View::endSection('content'); ?>

<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<!-- Action bar -->
<div class="bg-white rounded-xl border border-gray-100 shadow-sm p-3 mb-4 flex justify-between items-center">
    <span class="text-sm text-gray-400"><?= count($sucursales ?? []) ?> sucursal(es)</span>
    <a href="/configuracion/sucursales/nueva" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
        <i class="fas fa-plus"></i> Nueva Sucursal
    </a>
</div>

<!-- Table -->
<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
            <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                <th class="px-4 py-3">Código</th>
                <th class="px-4 py-3">Nombre</th>
                <th class="px-4 py-3">Dirección / Teléfono</th>
                <th class="px-4 py-3 text-center">Estado</th>
                <th class="px-4 py-3 text-center">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            <?php if (empty($sucursales)): ?>
            <tr>
                <td colspan="5" class="px-4 py-10 text-center text-gray-400">
                    <i class="fas fa-store text-3xl mb-2 block text-gray-200"></i>
                    <p class="text-sm mb-2">No hay sucursales registradas.</p>
                    <a href="/configuracion/sucursales/nueva" class="text-sky-600 hover:text-sky-700 text-sm font-semibold">
                        Crear tu primera sucursal
                    </a>
                </td>
            </tr>
            <?php else: ?>
                <?php foreach ($sucursales as $sucursal): ?>
                <tr class="hover:bg-gray-50/50 transition-colors">
                    <td class="px-4 py-3">
                        <span class="font-mono text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                            <?= View::e($sucursal['codigo'] ?: 'N/A') ?>
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-800"><?= View::e($sucursal['nombre']) ?></span>
                            <?php if ($sucursal['es_principal']): ?>
                            <span class="w-5 h-5 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center" title="Sucursal Principal">
                                <i class="fas fa-star text-[0.6rem]"></i>
                            </span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="text-xs text-gray-600 truncate max-w-xs">
                            <i class="fas fa-map-marker-alt text-gray-400 mr-1"></i><?= View::e($sucursal['direccion'] ?: 'Sin dirección') ?>
                        </div>
                        <div class="text-xs text-gray-400 mt-0.5">
                            <i class="fas fa-phone-alt text-gray-300 mr-1"></i><?= View::e($sucursal['telefono'] ?: 'Sin teléfono') ?>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $sucursal['activa'] ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-600' ?>">
                            <?= $sucursal['activa'] ? 'Activa' : 'Inactiva' ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <a href="/configuracion/sucursales/<?= $sucursal['sucursal_id'] ?>/editar" class="text-gray-400 hover:text-blue-600" title="Editar">
                                <i class="fas fa-pen text-xs"></i>
                            </a>
                            <form action="/configuracion/sucursales/<?= $sucursal['sucursal_id'] ?>/eliminar" method="POST" class="inline"
                                  onsubmit="return confirm('¿Eliminar esta sucursal? Todos los datos asociados podrían verse afectados.')">
                                <?= View::csrf() ?>
                                <button type="submit" class="text-gray-300 hover:text-red-600" title="Eliminar">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php View::endSection('content'); ?>

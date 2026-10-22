<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<!-- Barra de búsqueda por barcode / nombre -->
<div class="bg-white rounded-xl shadow-sm p-4 mb-4 flex items-center gap-3">
    <div class="relative flex-1">
        <i class="fas fa-barcode absolute left-3 top-2.5 text-gray-400"></i>
        <input type="text" id="filtro-producto" placeholder="Buscar por nombre, código o código de barras…"
               class="w-full pl-9 pr-4 py-2 text-sm rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-sky-500">
    </div>
    <span class="text-xs text-gray-400">
        <?= count($items) ?> productos · Depósito: <?= View::e($conteo['deposito_nombre']) ?>
    </span>
</div>

<form method="POST" action="/inventario/conteos/<?= $conteo['conteo_id'] ?>/contar">
    <?= View::csrf() ?>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-4">
        <table class="min-w-full divide-y divide-gray-200 text-sm" id="tabla-conteo">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Producto</th>
                    <th class="px-4 py-3 text-left">Código</th>
                    <th class="px-4 py-3 text-right">Sistema</th>
                    <th class="px-4 py-3 text-center w-40">Contado</th>
                    <th class="px-4 py-3 text-center w-28">Diferencia</th>
                    <th class="px-4 py-3 text-center">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($items as $item): ?>
            <?php
                $contado = $item['cantidad_contada'];
                $dif     = $contado !== null ? (float)$contado - (float)$item['cantidad_sistema'] : null;
                $rowBg   = '';
                if ($dif !== null && $dif < 0) $rowBg = 'bg-red-50';
                elseif ($dif !== null && $dif > 0) $rowBg = 'bg-green-50';
            ?>
            <tr class="fila-producto <?= $rowBg ?> hover:bg-gray-50 transition-colors"
                data-nombre="<?= strtolower(View::e($item['nombre'])) ?>"
                data-codigo="<?= strtolower(View::e($item['codigo'])) ?>"
                data-barcode="<?= strtolower(View::e($item['codigo_barras'] ?? '')) ?>">
                <td class="px-4 py-2 font-medium text-gray-800"><?= View::e($item['nombre']) ?></td>
                <td class="px-4 py-2 font-mono text-xs text-gray-500"><?= View::e($item['codigo']) ?></td>
                <td class="px-4 py-2 text-right font-semibold text-gray-700"><?= number_format((float)$item['cantidad_sistema'], 2) ?></td>
                <td class="px-4 py-2 text-center">
                    <input type="number"
                           name="cantidad[<?= $item['producto_id'] ?>]"
                           value="<?= $contado !== null ? (float)$contado : '' ?>"
                           step="0.01" min="0" placeholder="—"
                           class="w-36 border border-gray-200 rounded-lg px-2 py-1 text-sm text-center font-bold
                                  focus:ring-2 focus:ring-sky-400 focus:border-sky-400
                                  input-cantidad"
                           data-sistema="<?= (float)$item['cantidad_sistema'] ?>">
                </td>
                <td class="px-4 py-2 text-center font-bold diferencia-celda">
                    <?php if ($dif !== null): ?>
                        <span class="<?= $dif > 0 ? 'text-emerald-600' : ($dif < 0 ? 'text-red-600' : 'text-gray-400') ?>">
                            <?= ($dif > 0 ? '+' : '') . number_format($dif, 2) ?>
                        </span>
                    <?php else: ?>
                        <span class="text-gray-300">—</span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-2 text-center">
                    <?php if ($item['estado'] === 'ajustado'): ?>
                        <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded-full text-xs">Ajustado</span>
                    <?php elseif ($item['estado'] === 'contado'): ?>
                        <span class="px-2 py-0.5 bg-sky-100 text-sky-700 rounded-full text-xs">Contado</span>
                    <?php else: ?>
                        <span class="px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full text-xs">Pendiente</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="flex justify-between items-center">
        <?php if ($conteo['estado'] !== 'completado'): ?>
        <form method="POST" action="/inventario/conteos/<?= $conteo['conteo_id'] ?>/cancelar" class="inline">
            <?= View::csrf() ?>
            <button type="submit" class="px-4 py-2 text-sm text-red-500 hover:text-red-700 transition"
                    onclick="return confirm('¿Cancelar este conteo?')">
                <i class="fas fa-times mr-1"></i> Cancelar conteo
            </button>
        </form>
        <?php endif; ?>

        <div class="flex gap-3">
            <a href="/inventario/conteos/<?= $conteo['conteo_id'] ?>/reconciliar"
               class="px-4 py-2 border rounded-lg text-sm text-gray-600 hover:bg-gray-50">
                <i class="fas fa-balance-scale mr-1"></i> Ver reconciliación
            </a>
            <button type="submit"
                    class="px-6 py-2 bg-sky-500 text-white rounded-lg text-sm font-medium hover:bg-sky-600 shadow-sm">
                <i class="fas fa-save mr-1"></i> Guardar conteo
            </button>
        </div>
    </div>
</form>

<script>
// Filtro de búsqueda en tiempo real
document.getElementById('filtro-producto').addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('.fila-producto').forEach(function (fila) {
        const match = !q
            || fila.dataset.nombre.includes(q)
            || fila.dataset.codigo.includes(q)
            || fila.dataset.barcode.includes(q);
        fila.style.display = match ? '' : 'none';
    });
});

// Actualizar diferencia en tiempo real al ingresar valor
document.querySelectorAll('.input-cantidad').forEach(function (input) {
    input.addEventListener('input', function () {
        const sistema = parseFloat(this.dataset.sistema) || 0;
        const contado = this.value !== '' ? parseFloat(this.value) : null;
        const celda   = this.closest('tr').querySelector('.diferencia-celda');
        if (contado === null) {
            celda.innerHTML = '<span class="text-gray-300">—</span>';
        } else {
            const dif = contado - sistema;
            const color = dif > 0 ? 'text-emerald-600' : dif < 0 ? 'text-red-600' : 'text-gray-400';
            const signo = dif > 0 ? '+' : '';
            celda.innerHTML = `<span class="${color}">${signo}${dif.toFixed(2)}</span>`;
        }
    });
});
</script>

<?php View::endSection('content'); ?>

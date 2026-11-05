<?php
/**
 * Selector de sucursal de los reportes (solo si el usuario ve más de una).
 * Variables: $sucursales_reporte, $sucursal_filtro; $formulario = true lo envuelve en su propio <form GET>.
 */
$sucursales_reporte = $sucursales_reporte ?? [];
if (count($sucursales_reporte) < 2) {
    return;
}
$select = static function () use ($sucursales_reporte, $sucursal_filtro): void { ?>
    <div class="min-w-44">
        <label class="block text-xs font-semibold text-gray-500 mb-1">Sucursal</label>
        <select name="sucursal" onchange="this.form.submit()"
                class="w-full py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
            <option value="">Todas las sucursales</option>
            <?php foreach ($sucursales_reporte as $s): ?>
            <option value="<?= (int) $s['sucursal_id'] ?>" <?= (int) $s['sucursal_id'] === (int) ($sucursal_filtro ?? 0) ? 'selected' : '' ?>><?= esc($s['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
<?php };

if (!empty($formulario)): ?>
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-4">
    <form method="GET" class="flex flex-wrap items-end gap-3"><?php $select(); ?></form>
</div>
<?php else:
    $select();
endif;

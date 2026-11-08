<?php
$this->extend('layouts/app');

$etiquetas = [
    'nueva'      => ['Nueva', 'bg-emerald-100 text-emerald-700'],
    'actualizar' => ['Cambia', 'bg-sky-100 text-sky-700'],
    'igual'      => ['Sin cambio', 'bg-gray-100 text-gray-500'],
    'error'      => ['Error', 'bg-red-100 text-red-700'],
];
$conteo = ['nueva' => 0, 'actualizar' => 0, 'igual' => 0, 'error' => 0];
foreach (($lineas ?? []) as $l) {
    $conteo[$l['accion']]++;
}
$num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
?>
<?php $this->section('content'); ?>

<div class="max-w-5xl mx-auto">
    <div class="flex items-center gap-2 text-sm text-gray-400 mb-3">
        <a href="/inventario" class="hover:text-gray-600">Inventario</a>
        <i class="fas fa-chevron-right text-xs"></i>
        <span class="text-gray-700 font-medium">Importar presentaciones</span>
    </div>

    <?php if ($lineas === null): ?>
    <!-- Paso 1 y 2: plantilla y subir -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-4">
        <h3 class="text-base font-bold text-gray-800 mb-1">Cómo funciona</h3>
        <p class="text-sm text-gray-500 mb-4">Una fila por presentación. Ejemplo: <b>Agua galón Aquaviva</b> se vende a $2.50 la unidad y la <b>Caja</b> de <b>4</b> a <b>$9.00</b>.</p>
        <div class="overflow-x-auto mb-4">
            <table class="text-xs border border-gray-200 rounded-lg">
                <thead class="bg-gray-50"><tr>
                    <?php foreach (\App\Libraries\PresentacionImportService::ENCABEZADOS as $h): ?><th class="px-3 py-2 text-left font-bold text-gray-600 border-b"><?= esc($h) ?></th><?php endforeach; ?>
                </tr></thead>
                <tbody>
                    <tr><td class="px-3 py-1.5 font-mono">ALI011</td><td class="px-3 py-1.5">Agua galón Aquaviva</td><td class="px-3 py-1.5 text-gray-400">2.50</td><td class="px-3 py-1.5 font-semibold">Caja</td><td class="px-3 py-1.5 font-semibold">4</td><td class="px-3 py-1.5 font-semibold">9.00</td></tr>
                    <tr><td class="px-3 py-1.5 font-mono">ALI011</td><td class="px-3 py-1.5">Agua galón Aquaviva</td><td class="px-3 py-1.5 text-gray-400">2.50</td><td class="px-3 py-1.5 font-semibold">Paca</td><td class="px-3 py-1.5 font-semibold">48</td><td class="px-3 py-1.5 font-semibold">100.00</td></tr>
                </tbody>
            </table>
        </div>
        <ul class="text-sm text-gray-600 space-y-1 mb-5 list-disc pl-5">
            <li><b>Descarga la plantilla</b>: trae todos los productos activos con sus presentaciones actuales.</li>
            <li>Llena <b>Presentación</b>, <b>Trae (unidades)</b> y <b>Precio presentación</b>. Para dos presentaciones del mismo producto, copia la fila.</li>
            <li>Las filas sin presentación se ignoran. <b>Precio unidad</b> es solo referencia: no se importa.</li>
            <li>Si la presentación ya existe (mismo producto y nombre) se actualiza; si no, se crea. <b>No borra nada</b>: para quitar una, ve a Editar producto.</li>
            <li>Precio vacío = se cobra precio unidad × unidades.</li>
        </ul>
        <a href="/inventario/presentaciones/plantilla" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700 shadow-sm">
            <i class="fas fa-file-excel"></i> Descargar plantilla
        </a>
    </div>

    <form action="/inventario/presentaciones/importar" method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-4">
        <?= csrf_field() ?>
        <h3 class="text-base font-bold text-gray-800 mb-3">Subir el archivo lleno</h3>
        <div class="flex flex-wrap items-center gap-3">
            <input type="file" name="archivo" accept=".xlsx,.csv" required class="text-sm flex-1 min-w-[220px]">
            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 shadow-sm">
                <i class="fas fa-search"></i> Revisar
            </button>
        </div>
        <p class="text-xs text-gray-400 mt-2">Primero verás qué se va a crear o cambiar. Nada se guarda hasta que confirmes.</p>
    </form>

    <?php else: ?>
    <!-- Paso 3: revisión -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-4">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <h3 class="text-base font-bold text-gray-800">Revisión de <?= esc($archivo ?? 'archivo') ?></h3>
                <p class="text-sm text-gray-500">
                    <b class="text-emerald-700"><?= $conteo['nueva'] ?> nuevas</b> ·
                    <b class="text-sky-700"><?= $conteo['actualizar'] ?> cambian</b> ·
                    <?= $conteo['igual'] ?> sin cambio ·
                    <b class="<?= $conteo['error'] ? 'text-red-600' : 'text-gray-400' ?>"><?= $conteo['error'] ?> con error</b>
                </p>
            </div>
            <div class="flex gap-2">
                <a href="/inventario/presentaciones/importar" class="px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50">Subir otro</a>
                <?php if ($conteo['nueva'] + $conteo['actualizar'] > 0): ?>
                <form action="/inventario/presentaciones/aplicar" method="POST" onsubmit="this.querySelector('button').disabled = true">
                    <?= csrf_field() ?>
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700 shadow-sm">
                        <i class="fas fa-check"></i> Guardar <?= $conteo['nueva'] + $conteo['actualizar'] ?>
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($conteo['error']): ?>
            <p class="text-sm bg-amber-50 text-amber-700 rounded-xl px-4 py-3 mb-4"><i class="fas fa-exclamation-triangle mr-1"></i> Las filas con error no se guardan. Puedes guardar las demás ahora y corregir esas después, o corregir el archivo y subirlo de nuevo.</p>
        <?php endif; ?>
        <?php if (!$lineas): ?>
            <p class="text-sm text-gray-500 bg-gray-50 rounded-xl px-4 py-3">El archivo no tiene ninguna fila con presentación.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm divide-y divide-gray-100">
                <thead class="bg-gray-50 text-[11px] uppercase text-gray-500">
                    <tr><th class="px-3 py-2 text-left">Fila</th><th class="px-3 py-2 text-left">Producto</th><th class="px-3 py-2 text-left">Presentación</th><th class="px-3 py-2 text-right">Trae</th><th class="px-3 py-2 text-right">Precio</th><th class="px-3 py-2 text-left">Resultado</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                <?php foreach ($lineas as $l): [$txt, $cls] = $etiquetas[$l['accion']]; ?>
                    <tr class="<?= $l['accion'] === 'error' ? 'bg-red-50/40' : '' ?>">
                        <td class="px-3 py-2 text-gray-400"><?= (int) $l['fila'] ?></td>
                        <td class="px-3 py-2"><span class="font-mono text-xs text-gray-500"><?= esc($l['codigo']) ?></span> <?= esc($l['producto']) ?></td>
                        <td class="px-3 py-2 font-semibold"><?= esc($l['nombre']) ?></td>
                        <td class="px-3 py-2 text-right"><?= $l['factor'] ? $num($l['factor']) : '' ?></td>
                        <td class="px-3 py-2 text-right"><?= $l['accion'] !== 'error' ? ($l['precio'] > 0 ? '$' . number_format($l['precio'], 2) : '<span class="text-gray-400">auto</span>') : '' ?></td>
                        <td class="px-3 py-2">
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold <?= $cls ?>"><?= $txt ?></span>
                            <?php if ($l['error']): ?><span class="text-xs text-red-600 ml-1"><?= esc($l['error']) ?></span><?php endif; ?>
                            <?php if ($l['accion'] === 'actualizar'): ?><span class="text-xs text-gray-400 ml-1">antes <?= esc($l['antes']) ?></span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php $this->endSection(); ?>

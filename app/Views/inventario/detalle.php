<?php
/**
 * Detalle del producto — diseño tipo Stock Manager (vista solamente; usa los mismos datos
 * que entrega ProductoController::detalle): código de barras y QR, foto con indicadores,
 * lista de datos, cantidad por depósito con total, presentaciones, proveedor/descripción
 * y el formulario de agregar stock.
 */
$this->extend('layouts/app');

$precios_map = [];
foreach (($precios ?? []) as $pp) {
    $tipo = is_array($pp) ? ($pp['tipo_precio'] ?? '') : ($pp->tipo_precio ?? '');
    $val  = is_array($pp) ? ($pp['precio'] ?? 0) : ($pp->precio ?? 0);
    $precios_map[$tipo] = $val;
}
$id        = (int) $producto->producto_id;
$precio    = (float) ($precios_map['a'] ?? 0);
$costo     = (float) $producto->costo;
$stockTot  = (float) array_sum(array_column($inventarios ?? [], 'existencia'));
$aqui      = null;
foreach (($inventarios ?? []) as $inv) {
    if ((int) $inv['deposito_id'] === (int) ($deposito_actual['deposito_id'] ?? 0)) {
        $aqui = $inv;
    }
}
$codigoBarra = trim((string) ($producto->codigo_barras ?: $producto->codigo));
$num    = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ','), '0'), '.');
$dinero = fn ($v) => '$' . number_format((float) $v, 2);
$fila   = fn (string $etiqueta, string $valor) => '<div class="px-4 py-2.5 grid grid-cols-3 gap-4 sm:px-6"><dt class="text-sm font-medium text-gray-500">' . $etiqueta . '</dt><dd class="text-sm text-gray-900 col-span-2">' . ($valor !== '' ? $valor : '<span class="text-gray-300">—</span>') . '</dd></div>';
$si     = fn (bool $b) => $b
    ? '<span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-600 inline-flex items-center justify-center"><i class="fas fa-check text-[10px]"></i></span>'
    : '<span class="w-5 h-5 rounded-full bg-gray-100 text-gray-400 inline-flex items-center justify-center"><i class="fas fa-xmark text-[10px]"></i></span>';
$campo  = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-sky-300 focus:border-sky-400 focus:outline-none';
?>
<?php $this->section('content'); ?>

<div class="max-w-5xl mx-auto">

<!-- Encabezado de página -->
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div class="flex items-center gap-2 text-sm text-gray-400">
        <a href="/inventario" class="hover:text-gray-600 transition-colors">Productos</a>
        <i class="fas fa-chevron-right text-xs"></i>
        <span class="text-gray-700 font-medium"><?= esc($producto->nombre) ?></span>
    </div>
    <div class="flex gap-2">
        <a href="/inventario" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50">Lista de productos</a>
        <a href="/inventario/<?= $id ?>/editar" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 shadow-sm"><i class="fas fa-pen-to-square"></i> Editar</a>
    </div>
</div>

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-4">
    <!-- Título -->
    <div class="border-b border-gray-100 px-4 sm:px-6 py-4 flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-base font-semibold text-gray-900"><?= esc($producto->nombre) ?> <span class="text-gray-400 font-mono">(<?= esc($producto->codigo) ?>)</span></h1>
            <p class="mt-1 text-sm text-gray-400">Revisa los detalles del producto</p>
        </div>
        <span class="shrink-0 px-2.5 py-1 rounded-full text-xs font-bold <?= $producto->estado === 'activo' ? 'bg-emerald-50 text-emerald-600' : 'bg-gray-100 text-gray-500' ?>"><?= ucfirst($producto->estado) ?></span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-12 gap-x-6 gap-y-6 p-4 sm:p-6">
        <!-- Código de barras y QR -->
        <div class="col-span-full flex flex-wrap items-center justify-center gap-x-8 gap-y-3">
            <svg id="codigo-barras" data-valor="<?= esc($codigoBarra, 'attr') ?>" class="max-w-full h-24"></svg>
            <div id="codigo-qr" data-valor="<?= esc((string) $producto->codigo, 'attr') ?>" class="w-24 h-24"></div>
        </div>

        <!-- Foto e indicadores -->
        <div class="sm:col-span-5">
            <?php if (!empty($producto->imagen_principal)): ?>
                <button type="button" onclick="document.getElementById('visor-foto').classList.replace('hidden', 'flex')" class="block w-full cursor-zoom-in" title="Ver en grande">
                    <img src="/assets/uploads/<?= esc($producto->imagen_principal, 'attr') ?>" alt="" class="aspect-square w-full rounded-xl bg-gray-100 object-cover">
                </button>
                <div id="visor-foto" class="fixed inset-0 z-[100] hidden items-center justify-center p-6 bg-black/85 cursor-zoom-out" onclick="this.classList.replace('flex', 'hidden')">
                    <img src="/assets/uploads/<?= esc($producto->imagen_principal, 'attr') ?>" alt="" class="max-w-full max-h-full rounded-xl object-contain shadow-2xl">
                    <button type="button" class="absolute top-4 right-4 w-10 h-10 rounded-full bg-white/20 text-white text-xl hover:bg-white/30" aria-label="Cerrar"><i class="fas fa-times"></i></button>
                </div>
                <script>document.addEventListener('keydown', function (e) { if (e.key === 'Escape') document.getElementById('visor-foto').classList.replace('flex', 'hidden'); });</script>
            <?php else: ?>
                <div class="aspect-square w-full rounded-xl bg-gray-50 border border-dashed border-gray-200 flex flex-col items-center justify-center text-gray-300">
                    <i class="fas fa-camera text-3xl mb-1"></i><span class="text-xs">Sin foto</span>
                </div>
            <?php endif; ?>
            <dl class="divide-y divide-gray-100 mt-4 text-sm">
                <div class="px-2 py-2 flex items-center justify-between"><dt class="text-gray-500">Activo (se vende)</dt><dd><?= $si($producto->estado === 'activo') ?></dd></div>
                <div class="px-2 py-2 flex items-center justify-between"><dt class="text-gray-500">Tiene precio</dt><dd><?= $si($precio > 0) ?></dd></div>
                <div class="px-2 py-2 flex items-center justify-between"><dt class="text-gray-500">Maneja lotes / vencimiento</dt><dd><?= $si((bool) $producto->maneja_lotes) ?></dd></div>
                <div class="px-2 py-2 flex items-center justify-between"><dt class="text-gray-500">Tiene presentaciones</dt><dd><?= $si(!empty($unidades)) ?></dd></div>
            </dl>
        </div>

        <!-- Datos -->
        <div class="sm:col-span-7 -mt-2.5">
            <dl class="divide-y divide-gray-100">
                <?= $fila('Nombre', esc($producto->nombre)) ?>
                <?= $fila('Código', '<span class="font-mono">' . esc($producto->codigo) . '</span>') ?>
                <?= $fila('Código de barras', !empty($producto->codigo_barras) ? '<span class="font-mono">' . esc($producto->codigo_barras) . '</span>' : '') ?>
                <?= $fila('Marca', esc((string) ($producto->marca ?? ''))) ?>
                <?= $fila('Categoría', esc((string) ($categoria->nombre ?? ''))) ?>
                <?php if (ver_costos()): ?>
                <?= $fila('Costo', $costo > 0 ? $dinero($costo) : '<span class="text-amber-600">Falta</span>') ?>
                <?php endif; ?>
                <?= $fila('Precio', $precio > 0 ? '<b class="text-sky-700">' . $dinero($precio) . '</b>' : '<span class="text-amber-600">Sin precio</span>') ?>
                <?php if (ver_costos()): ?>
                <?= $fila('Margen', $precio > 0 && $costo > 0
                    ? '<span class="' . ($precio < $costo ? 'text-red-600' : 'text-emerald-600') . '">' . $dinero($precio - $costo) . ' (' . number_format(($precio - $costo) * 100 / $precio, 1) . '%)</span>' : '') ?>
                <?php endif; ?>
                <?= $fila('Stock mínimo', $aqui ? $num($aqui['minimo'] ?? 0) . ' <span class="text-xs text-gray-400">en ' . esc($aqui['deposito_nombre'] ?? '') . '</span>' : '') ?>
                <?= $fila('Ubicación', !empty($aqui['ubicacion']) ? '📍 ' . esc($aqui['ubicacion']) : '') ?>
                <?php $vAqui = \App\Libraries\InventarioService::estadoVencimiento($aqui['fecha_vencimiento'] ?? null); ?>
                <?= $fila('Vence', $vAqui ? '<span class="' . ($vAqui['nivel'] === 'vencido' ? 'text-red-600 font-semibold' : ($vAqui['nivel'] === 'pronto' ? 'text-orange-600 font-semibold' : '')) . '">' . esc($vAqui['texto']) . '</span>' : '') ?>
            </dl>
        </div>

        <!-- Cantidad por depósito -->
        <div class="col-span-full bg-gray-50 rounded-xl divide-y divide-gray-200/70">
            <div class="px-4 py-2.5 text-center font-extrabold text-gray-800 sm:px-6">Cantidad</div>
            <?php foreach (($inventarios ?? []) as $inv): $esAqui = $aqui && (int) $inv['deposito_id'] === (int) $aqui['deposito_id']; ?>
            <div class="px-4 py-2.5 flex items-start justify-between gap-4 sm:px-6 <?= $esAqui ? 'bg-sky-50/70' : '' ?>">
                <div class="text-sm font-medium text-gray-700"><?= esc($inv['deposito_nombre'] ?? 'N/A') ?><?= $esAqui ? ' <span class="text-[10px] font-bold text-sky-600 uppercase">aquí</span>' : '' ?>
                    <?php if ($vInv = \App\Libraries\InventarioService::estadoVencimiento($inv['fecha_vencimiento'] ?? null)): ?>
                    <div class="text-[11px] <?= $vInv['nivel'] === 'vencido' ? 'text-red-600' : ($vInv['nivel'] === 'pronto' ? 'text-orange-600' : 'text-gray-400') ?>"><i class="fas fa-hourglass-half mr-1"></i><?= esc($vInv['texto']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="text-sm font-semibold <?= (float) $inv['existencia'] <= 0 ? 'text-red-600' : 'text-gray-900' ?>"><?= $num($inv['existencia']) ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($inventarios)): ?>
            <div class="px-4 py-3 text-center text-sm text-gray-400">Sin inventario registrado</div>
            <?php endif; ?>
            <div class="px-4 py-2.5 flex items-start justify-between gap-4 sm:px-6">
                <div class="text-sm font-bold text-gray-800">Total</div>
                <div class="text-sm font-bold text-gray-900"><?= $num($stockTot) ?></div>
            </div>
        </div>

        <?php if (!empty($unidades)): ?>
        <!-- Presentaciones -->
        <div class="col-span-full bg-gray-50 rounded-xl divide-y divide-gray-200/70">
            <div class="px-4 py-2.5 text-center font-extrabold text-gray-800 sm:px-6">Presentaciones</div>
            <?php foreach ($unidades as $u): $pu = (float) $u['precio_a'] > 0 ? (float) $u['precio_a'] : $precio * (float) $u['factor_conversion']; ?>
            <div class="px-4 py-2.5 flex justify-between gap-4 sm:px-6 text-sm">
                <span class="font-medium text-gray-700"><?= esc($u['nombre']) ?> <span class="text-xs text-gray-400">(<?= $num($u['factor_conversion']) ?> <?= esc(\App\Libraries\ProductoService::abreviatura($producto->unidad_medida ?? 'und')) ?>)</span></span>
                <span class="font-semibold"><?= $dinero($pu) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Proveedor y descripción -->
        <div class="col-span-full flex flex-col divide-y divide-gray-100 text-sm">
            <div class="pb-2.5 flex flex-wrap items-center gap-2">
                <span class="text-gray-400">Proveedor:</span> <?= esc((string) ($proveedor->nombre ?? '—')) ?>
                <?php if (!empty($producto->supplier_part_no)): ?><span class="text-gray-500">(ref. <?= esc($producto->supplier_part_no) ?>)</span><?php endif; ?>
            </div>
            <div class="py-2.5"><span class="text-gray-400">Descripción:</span> <?= !empty($producto->descripcion) ? esc($producto->descripcion) : '<span class="text-gray-300">—</span>' ?></div>
        </div>

        <!-- Accesos -->
        <div class="col-span-full flex flex-wrap gap-2">
            <a href="/inventario/kardex?producto_id=<?= $id ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50"><i class="fas fa-clock-rotate-left"></i> Historial de movimientos</a>
            <a href="/inventario/<?= $id ?>/precios" class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50"><i class="fas fa-tag"></i> Precio</a>
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50"><i class="fas fa-print"></i> Imprimir</button>
        </div>
    </div>
</div>

<!-- Agregar stock (mismo formulario de siempre) -->
<div id="agregar-stock" class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-4 print:hidden">
    <div class="border-b border-gray-100 px-4 sm:px-6 py-3"><h2 class="text-sm font-semibold text-gray-900">Agregar stock</h2></div>
    <form action="/inventario/<?= $id ?>/stock" method="POST" class="p-4 sm:p-6 grid sm:grid-cols-4 gap-4 items-end">
        <?= csrf_field() ?>
        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Depósito *</label>
            <select name="deposito_id" required class="<?= $campo ?>">
                <?php foreach (($inventarios ?? []) as $inv): ?>
                    <option value="<?= $inv['deposito_id'] ?>" <?= ((int) ($deposito_actual['deposito_id'] ?? 0)) === (int) $inv['deposito_id'] ? 'selected' : '' ?>><?= esc($inv['deposito_nombre'] ?? 'Depósito ' . $inv['deposito_id']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Cantidad *</label>
            <input type="number" name="cantidad" min="0.01" step="any" required placeholder="0" class="<?= $campo ?> font-bold">
        </div>
        <?php if (ver_costos()): ?>
        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Costo <span class="font-normal text-gray-400">(opcional)</span></label>
            <input type="number" name="costo" step="0.01" min="0" placeholder="<?= $costo > 0 ? number_format($costo, 2) : '0.00' ?>" class="<?= $campo ?>">
        </div>
        <?php else: ?>
        <div></div>
        <?php endif; ?>
        <button type="submit" class="inline-flex items-center justify-center gap-1.5 px-5 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 shadow-sm">
            <i class="fas fa-plus"></i> Agregar stock
        </button>
    </form>
</div>

</div>

<?php $this->endSection(); ?>

<?php $this->section('extra_js'); ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.6/JsBarcode.all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function () {
    const barra = document.getElementById('codigo-barras');
    const qr = document.getElementById('codigo-qr');
    try {
        if (window.JsBarcode && barra.dataset.valor) {
            JsBarcode(barra, barra.dataset.valor, { format: 'CODE128', width: 2, height: 75, margin: 10, fontSize: 14, textMargin: 2, fontOptions: 'bold' });
        }
    } catch (e) { barra.remove(); }
    try {
        if (window.QRCode && qr.dataset.valor) {
            new QRCode(qr, { text: qr.dataset.valor, width: 96, height: 96, correctLevel: QRCode.CorrectLevel.M });
        }
    } catch (e) { qr.remove(); }
})();
</script>
<?php $this->endSection(); ?>

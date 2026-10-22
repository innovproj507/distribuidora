<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<?php
// $product es la entidad de dominio — acceso por métodos
$productoId   = $product->id()->value();
$productoNombre = $product->name();

// Indexar precios por tipo para fácil acceso
$precioMap = [];
foreach (($precios ?? []) as $p) {
    $tipo = $p->tipo_precio ?? $p['tipo_precio'] ?? '';
    $precioMap[$tipo] = $p->precio ?? $p['precio'] ?? '';
}

$tipoConfig = [
    'a'          => ['label' => 'Precio A', 'sub' => 'Precio principal de venta', 'color' => 'sky',    'icon' => 'fa-tag'],
    'b'          => ['label' => 'Precio B', 'sub' => 'Precio mayorista',           'color' => 'pink', 'icon' => 'fa-tags'],
    'promocional'=> ['label' => 'Promocional','sub'=> 'Precio de oferta temporal', 'color' => 'amber',  'icon' => 'fa-bolt'],
];
?>

<div class="max-w-2xl mx-auto space-y-4">

    <!-- Cabecera del producto -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-sky-500 text-white flex items-center justify-center shrink-0 shadow-sm shadow-sky-500/30">
            <i class="fas fa-box text-sm"></i>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-bold text-gray-800 truncate"><?= View::e($productoNombre) ?></p>
            <p class="text-xs text-gray-400 mt-0.5">Gestión de precios de venta</p>
        </div>
        <a href="/inventario/<?= $productoId ?>"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-50 transition shrink-0">
            <i class="fas fa-arrow-left text-xs"></i> Volver
        </a>
    </div>

    <!-- Formulario de precios -->
    <form action="/inventario/<?= $productoId ?>/precios" method="POST">
        <?= View::csrf() ?>

        <div class="space-y-3">
            <?php foreach ($tipoConfig as $tipo => $cfg): ?>
            <?php $valor = $precioMap[$tipo] ?? ''; ?>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
                <div class="flex items-center gap-4">
                    <!-- Ícono + etiqueta -->
                    <div class="w-9 h-9 rounded-lg bg-<?= $cfg['color'] ?>-500 text-white flex items-center justify-center shrink-0 shadow-sm shadow-<?= $cfg['color'] ?>-500/30">
                        <i class="fas <?= $cfg['icon'] ?> text-sm"></i>
                    </div>
                    <div class="flex-1">
                        <label for="precio_<?= $tipo ?>" class="block text-sm font-semibold text-gray-700">
                            <?= $cfg['label'] ?>
                        </label>
                        <p class="text-xs text-gray-400"><?= $cfg['sub'] ?></p>
                    </div>
                    <!-- Input de precio -->
                    <div class="relative w-40 shrink-0">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-medium">$</span>
                        <input type="number" step="0.01" min="0"
                            id="precio_<?= $tipo ?>"
                            name="precio_<?= $tipo ?>"
                            value="<?= $valor !== '' ? number_format((float)$valor, 2, '.', '') : '' ?>"
                            placeholder="0.00"
                            class="w-full pl-7 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-sky-400 focus:ring-2 focus:ring-sky-100 outline-none text-right font-semibold text-gray-800 transition">
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Acciones -->
        <div class="flex justify-end gap-3 mt-4">
            <a href="/inventario/<?= $productoId ?>"
                class="inline-flex items-center gap-1.5 px-4 py-2 border border-gray-200 text-gray-500 rounded-lg text-sm hover:bg-gray-50 transition">
                <i class="fas fa-times text-xs"></i> Cancelar
            </a>
            <button type="submit"
                class="inline-flex items-center gap-1.5 px-5 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
                <i class="fas fa-save text-xs"></i> Guardar Precios
            </button>
        </div>
    </form>

</div>

<?php View::endSection('content'); ?>

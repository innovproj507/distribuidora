<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<?php
$tipos = [
    'cotizacion' => ['label' => 'Cotización', 'icon' => 'fa-file-invoice-dollar', 'color' => 'sky',    'ejemplo_campo' => 'numero de la cotización'],
    'factura'    => ['label' => 'Factura',    'icon' => 'fa-receipt',             'color' => 'emerald','ejemplo_campo' => 'número de factura'],
    'cliente'    => ['label' => 'Cliente',    'icon' => 'fa-user-friends',        'color' => 'pink',   'ejemplo_campo' => 'código de cliente'],
    'proveedor'  => ['label' => 'Proveedor',  'icon' => 'fa-truck',               'color' => 'amber',  'ejemplo_campo' => 'código de proveedor'],
];
?>

<form method="post" action="/configuracion/numeracion">
    <?= View::csrf() ?>

    <!-- Action bar -->
    <div class="flex items-center justify-between bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-2.5 mb-4">
        <div class="flex gap-2">
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
                <i class="fas fa-save"></i> Guardar Nomenclatura
            </button>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-4 text-sm text-gray-600">
        <i class="fas fa-info-circle text-sky-500 mr-1"></i>
        Define cómo se arma cada número automático: <span class="font-semibold">Prefijo + Separador + Fecha (si aplica) + Secuencial</span>.
        El secuencial siempre continúa desde el último número usado; cambiar el "Reinicio" solo afecta a partir de ahora.
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <?php foreach ($tipos as $tipo => $meta): ?>
            <?php $cfg = $config[$tipo] ?? ['prefijo' => '', 'separador' => '-', 'digitos' => 4, 'reinicio' => 'nunca']; ?>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-<?= $meta['color'] ?>-500 text-white flex items-center justify-center text-xs shrink-0 shadow-sm shadow-<?= $meta['color'] ?>-500/30"><i class="fas <?= $meta['icon'] ?>"></i></span>
                    <?= $meta['label'] ?>
                </h3>

                <div class="grid grid-cols-2 gap-x-6">
                    <div class="flex items-baseline gap-3 py-2">
                        <label class="text-xs font-semibold text-gray-500 w-20 shrink-0">Prefijo</label>
                        <input type="text" id="<?= $tipo ?>_prefijo" name="<?= $tipo ?>_prefijo"
                               value="<?= View::e($cfg['prefijo']) ?>" maxlength="20" oninput="actualizarEjemplo('<?= $tipo ?>')"
                               class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                    </div>
                    <div class="flex items-baseline gap-3 py-2">
                        <label class="text-xs font-semibold text-gray-500 w-20 shrink-0">Separador</label>
                        <input type="text" id="<?= $tipo ?>_separador" name="<?= $tipo ?>_separador"
                               value="<?= View::e($cfg['separador']) ?>" maxlength="5" oninput="actualizarEjemplo('<?= $tipo ?>')"
                               placeholder="Ej: - o vacío"
                               class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                    </div>
                    <div class="flex items-baseline gap-3 py-2">
                        <label class="text-xs font-semibold text-gray-500 w-20 shrink-0">Dígitos</label>
                        <input type="number" id="<?= $tipo ?>_digitos" name="<?= $tipo ?>_digitos" min="1" max="10"
                               value="<?= (int) $cfg['digitos'] ?>" oninput="actualizarEjemplo('<?= $tipo ?>')"
                               class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                    </div>
                    <div class="flex items-baseline gap-3 py-2">
                        <label class="text-xs font-semibold text-gray-500 w-20 shrink-0">Reinicio</label>
                        <select id="<?= $tipo ?>_reinicio" name="<?= $tipo ?>_reinicio" onchange="actualizarEjemplo('<?= $tipo ?>')"
                                class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                            <option value="nunca"   <?= $cfg['reinicio'] === 'nunca'   ? 'selected' : '' ?>>Nunca</option>
                            <option value="diario"  <?= $cfg['reinicio'] === 'diario'  ? 'selected' : '' ?>>Cada día</option>
                            <option value="mensual" <?= $cfg['reinicio'] === 'mensual' ? 'selected' : '' ?>>Cada mes</option>
                            <option value="anual"   <?= $cfg['reinicio'] === 'anual'   ? 'selected' : '' ?>>Cada año</option>
                        </select>
                    </div>
                </div>

                <div class="mt-3 pt-3 border-t border-gray-100 flex items-center gap-2">
                    <span class="text-xs text-gray-400">Ejemplo — próximo <?= $meta['ejemplo_campo'] ?>:</span>
                    <span id="<?= $tipo ?>_ejemplo" class="font-mono text-sm font-bold text-white bg-<?= $meta['color'] ?>-500 px-2.5 py-1 rounded-full"></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</form>

<?php View::endSection('content'); ?>

<?php View::section('extra_js'); ?>
<script>
    function actualizarEjemplo(tipo) {
        const prefijo   = document.getElementById(tipo + '_prefijo').value;
        const separador = document.getElementById(tipo + '_separador').value;
        const digitos   = Math.max(1, parseInt(document.getElementById(tipo + '_digitos').value, 10) || 4);
        const reinicio  = document.getElementById(tipo + '_reinicio').value;

        const hoy = new Date();
        const pad2 = n => String(n).padStart(2, '0');
        let fecha = '';
        if (reinicio === 'diario')       fecha = '' + hoy.getFullYear() + pad2(hoy.getMonth() + 1) + pad2(hoy.getDate());
        else if (reinicio === 'mensual') fecha = '' + hoy.getFullYear() + pad2(hoy.getMonth() + 1);
        else if (reinicio === 'anual')   fecha = '' + hoy.getFullYear();

        const partes = [prefijo, fecha].filter(v => v !== '');
        const base = partes.join(separador) + separador;
        const secuencial = '1'.padStart(digitos, '0');

        document.getElementById(tipo + '_ejemplo').textContent = base + secuencial;
    }

    ['cotizacion', 'factura', 'cliente', 'proveedor'].forEach(actualizarEjemplo);
</script>
<?php View::endSection('extra_js'); ?>

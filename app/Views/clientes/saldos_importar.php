<?php
$this->extend('layouts/app');

$etiquetas = [
    'nuevo'         => ['Se agrega', 'bg-emerald-100 text-emerald-700'],
    'cliente_nuevo' => ['Cliente nuevo', 'bg-sky-100 text-sky-700'],
    'repetido'      => ['Ya cargado', 'bg-gray-100 text-gray-500'],
    'error'         => ['Error', 'bg-red-100 text-red-700'],
];
$conteo = ['nuevo' => 0, 'cliente_nuevo' => 0, 'repetido' => 0, 'error' => 0];
$total = 0.0;
foreach (($lineas ?? []) as $l) {
    $conteo[$l['accion']]++;
    if (in_array($l['accion'], ['nuevo', 'cliente_nuevo'], true)) $total += (float) $l['monto'];
}
$guardar = $conteo['nuevo'] + $conteo['cliente_nuevo'];
?>
<?php $this->section('content'); ?>

<div class="max-w-5xl mx-auto">
    <div class="flex items-center gap-2 text-sm text-gray-400 mb-3">
        <a href="/clientes/cuentas-por-cobrar" class="hover:text-gray-600">Cuentas por cobrar</a>
        <i class="fas fa-chevron-right text-xs"></i>
        <span class="text-gray-700 font-medium">Importar saldos anteriores</span>
    </div>

    <?php if ($lineas === null): ?>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-4">
        <h3 class="text-base font-bold text-gray-800 mb-1">Cómo funciona</h3>
        <p class="text-sm text-gray-500 mb-4">Carga lo que cada cliente ya debía antes de usar el sistema. Queda como una deuda <b>SA-000001</b> que se cobra con abonos igual que una factura a crédito. No cuenta como venta ni toca inventario ni caja.</p>
        <div class="overflow-x-auto mb-4">
            <table class="text-xs border border-gray-200 rounded-lg">
                <thead class="bg-gray-50"><tr>
                    <?php foreach (\App\Libraries\SaldoAnteriorService::ENCABEZADOS as $h): ?><th class="px-3 py-2 text-left font-bold text-gray-600 border-b whitespace-nowrap"><?= esc($h) ?></th><?php endforeach; ?>
                </tr></thead>
                <tbody>
                    <tr><td class="px-3 py-1.5 text-gray-400">12</td><td class="px-3 py-1.5 font-mono text-gray-400">CLI-0012</td><td class="px-3 py-1.5">Juan Pérez</td><td class="px-3 py-1.5 text-gray-400">8-123-456</td><td class="px-3 py-1.5 text-gray-400">6000-0000</td><td class="px-3 py-1.5 text-gray-400">0</td><td class="px-3 py-1.5 font-semibold">350.00</td><td class="px-3 py-1.5 font-semibold">15/08/2026</td><td class="px-3 py-1.5 font-semibold">Cuaderno</td><td class="px-3 py-1.5 font-semibold">500</td></tr>
                    <tr><td class="px-3 py-1.5 text-gray-400"></td><td class="px-3 py-1.5"></td><td class="px-3 py-1.5">Tienda La Esquina</td><td class="px-3 py-1.5"></td><td class="px-3 py-1.5">6111-2222</td><td class="px-3 py-1.5"></td><td class="px-3 py-1.5 font-semibold">1,200.00</td><td class="px-3 py-1.5 font-semibold"></td><td class="px-3 py-1.5"></td><td class="px-3 py-1.5"></td></tr>
                </tbody>
            </table>
        </div>
        <ul class="text-sm text-gray-600 space-y-1 mb-5 list-disc pl-5">
            <li><b>Descarga la plantilla</b>: trae todos los clientes activos, uno por fila.</li>
            <li>Llena <b>Saldo anterior</b> en los que deben. Las filas sin saldo se ignoran.</li>
            <li><b>Fecha</b> (día/mes/año) de cuando se originó la deuda; vacía = hoy. <b>Referencia</b>: nº de factura vieja, "cuaderno"… (opcional).</li>
            <li><b>Límite de crédito</b> (opcional) actualiza el límite del cliente. Si el saldo anterior es mayor que su límite, no podrá comprar más a crédito hasta que abone.</li>
            <li>Si el cliente no existe, agrega una fila al final con su <b>nombre</b> (y teléfono/RUC si los tienes): se crea.</li>
            <li>Subir el mismo archivo dos veces no duplica: el mismo cliente con el mismo monto y fecha sale como <b>Ya cargado</b>.</li>
        </ul>
        <a href="/clientes/saldos-anteriores/plantilla" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700 shadow-sm">
            <i class="fas fa-file-excel"></i> Descargar plantilla
        </a>
    </div>

    <form action="/clientes/saldos-anteriores/importar" method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-4">
        <?= csrf_field() ?>
        <h3 class="text-base font-bold text-gray-800 mb-3">Subir el archivo lleno</h3>
        <div class="flex flex-wrap items-center gap-3">
            <input type="file" name="archivo" accept=".xlsx,.csv" required class="text-sm flex-1 min-w-[220px]">
            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 shadow-sm">
                <i class="fas fa-search"></i> Revisar
            </button>
        </div>
        <p class="text-xs text-gray-400 mt-2">Primero verás qué se va a cargar. Nada se guarda hasta que confirmes.</p>
    </form>

    <?php else: ?>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-4">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <h3 class="text-base font-bold text-gray-800">Revisión de <?= esc($archivo ?? 'archivo') ?></h3>
                <p class="text-sm text-gray-500">
                    <b class="text-emerald-700"><?= $conteo['nuevo'] ?> saldos</b> ·
                    <b class="text-sky-700"><?= $conteo['cliente_nuevo'] ?> con cliente nuevo</b> ·
                    <?= $conteo['repetido'] ?> ya cargados ·
                    <b class="<?= $conteo['error'] ? 'text-red-600' : 'text-gray-400' ?>"><?= $conteo['error'] ?> con error</b>
                    <?php if ($guardar): ?> · total <b class="text-gray-800">$<?= number_format($total, 2) ?></b><?php endif; ?>
                </p>
            </div>
            <div class="flex gap-2">
                <a href="/clientes/saldos-anteriores/importar" class="px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50">Subir otro</a>
                <?php if ($guardar > 0): ?>
                <form action="/clientes/saldos-anteriores/aplicar" method="POST" onsubmit="this.querySelector('button').disabled = true">
                    <?= csrf_field() ?>
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700 shadow-sm">
                        <i class="fas fa-check"></i> Guardar <?= $guardar ?>
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($conteo['error']): ?>
            <p class="text-sm bg-amber-50 text-amber-700 rounded-xl px-4 py-3 mb-4"><i class="fas fa-exclamation-triangle mr-1"></i> Las filas con error no se guardan. Puedes guardar las demás ahora y subir después un archivo solo con las corregidas.</p>
        <?php endif; ?>
        <?php if (!$lineas): ?>
            <p class="text-sm text-gray-500 bg-gray-50 rounded-xl px-4 py-3">El archivo no tiene ninguna fila con saldo anterior.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm divide-y divide-gray-100">
                <thead class="bg-gray-50 text-[11px] uppercase text-gray-500">
                    <tr><th class="px-3 py-2 text-left">Fila</th><th class="px-3 py-2 text-left">Cliente</th><th class="px-3 py-2 text-right">Saldo anterior</th><th class="px-3 py-2 text-left">Fecha</th><th class="px-3 py-2 text-left">Referencia</th><th class="px-3 py-2 text-right">Límite</th><th class="px-3 py-2 text-left">Resultado</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                <?php foreach ($lineas as $l): [$txt, $cls] = $etiquetas[$l['accion']]; ?>
                    <tr class="<?= $l['accion'] === 'error' ? 'bg-red-50/40' : '' ?>">
                        <td class="px-3 py-2 text-gray-400"><?= (int) $l['fila'] ?></td>
                        <td class="px-3 py-2"><?= esc($l['cliente']) ?><?php if ($l['codigo'] !== ''): ?> <span class="font-mono text-xs text-gray-400"><?= esc($l['codigo']) ?></span><?php endif; ?></td>
                        <td class="px-3 py-2 text-right font-semibold"><?= $l['accion'] !== 'error' ? '$' . number_format((float) $l['monto'], 2) : '' ?></td>
                        <td class="px-3 py-2 text-gray-500"><?= $l['fecha'] ? date('d/m/Y', strtotime($l['fecha'])) : '' ?></td>
                        <td class="px-3 py-2 text-gray-500"><?= esc($l['referencia']) ?></td>
                        <td class="px-3 py-2 text-right text-gray-500">
                            <?php if ($l['limite'] !== null && $l['accion'] !== 'error'): ?>
                                $<?= number_format((float) $l['limite'], 2) ?>
                                <?php if ($l['limite_antes'] !== null && abs($l['limite_antes'] - $l['limite']) > 0.004): ?><span class="block text-[11px] text-gray-400">antes $<?= number_format($l['limite_antes'], 2) ?></span><?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2">
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold <?= $cls ?>"><?= $txt ?></span>
                            <?php if ($l['error']): ?><span class="text-xs text-red-600 ml-1"><?= esc($l['error']) ?></span><?php endif; ?>
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

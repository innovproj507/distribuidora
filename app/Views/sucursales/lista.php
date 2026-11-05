<?php $this->extend('layouts/app'); ?>
<?php $this->section('content'); ?>

<?php
$sucursales = $sucursales ?? [];
$puedeGestionar = \App\Libraries\Auth::can('sucursales.gestionar');
$fac = $numeracion['factura'] ?? ['prefijo' => 'F', 'separador' => '', 'digitos' => 4, 'reinicio' => 'nunca'];
$cot = $numeracion['cotizacion'] ?? ['prefijo' => 'C', 'separador' => '', 'digitos' => 4, 'reinicio' => 'nunca'];
$conSerie = function (?string $pref, array $cfg): string {
    $ej = \App\Libraries\NumeracionService::ejemplo($cfg);
    return $pref ? $pref . $cfg['separador'] . $ej : $ej;
};
$actualId = (int) ($sucursal_actual['sucursal_id'] ?? 0);
?>

<div class="flex items-center justify-between bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-2.5 mb-4">
    <p class="text-sm text-gray-500">
        <?= count($sucursales) ?> sucursal<?= count($sucursales) === 1 ? '' : 'es' ?>.
        Cada una tiene su depósito, su inventario y su serie de facturas.
    </p>
    <?php if ($puedeGestionar): ?>
    <a href="/sucursales/nueva" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm shadow-sky-500/30">
        <i class="fas fa-plus"></i> Nueva sucursal
    </a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
<?php foreach ($sucursales as $s): $activa = (int) $s['activa'] === 1; ?>
    <div class="bg-white rounded-2xl border <?= (int) $s['sucursal_id'] === $actualId ? 'border-sky-300 ring-2 ring-sky-100' : 'border-gray-100' ?> shadow-sm p-5 <?= $activa ? '' : 'opacity-60' ?>">
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-3 min-w-0">
                <span class="w-11 h-11 rounded-xl bg-sky-50 text-sky-500 flex items-center justify-center shrink-0"><i class="fas fa-store"></i></span>
                <div class="min-w-0">
                    <h3 class="font-bold text-gray-800 truncate"><?= esc($s['nombre']) ?></h3>
                    <p class="text-xs text-gray-400"><?= esc($s['codigo']) ?></p>
                    <div class="flex flex-wrap gap-1 mt-1">
                        <?php if ((int) $s['es_principal'] === 1): ?>
                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-amber-50 text-amber-600">Principal</span>
                        <?php endif; ?>
                        <?php if ((int) $s['sucursal_id'] === $actualId): ?>
                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-sky-50 text-sky-600">Estás aquí</span>
                        <?php endif; ?>
                        <?php if (!$activa): ?>
                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">Inactiva</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if ($puedeGestionar): ?>
            <div class="flex gap-1 shrink-0">
                <a href="/sucursales/<?= (int) $s['sucursal_id'] ?>/editar" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-sky-50 hover:text-sky-500" title="Editar"><i class="fas fa-pen text-xs"></i></a>
                <?php if ((int) $s['es_principal'] !== 1): ?>
                <form method="POST" action="/sucursales/<?= (int) $s['sucursal_id'] ?>/estado" onsubmit="return confirm('<?= $activa ? '¿Desactivar esta sucursal? Nadie podrá vender en ella.' : '¿Activar esta sucursal?' ?>')">
                    <?= csrf_field() ?>
                    <button class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-rose-50 hover:text-rose-500" title="<?= $activa ? 'Desactivar' : 'Activar' ?>">
                        <i class="fas <?= $activa ? 'fa-toggle-on' : 'fa-toggle-off' ?> text-sm"></i>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="mt-4 space-y-1.5 text-sm text-gray-600">
            <p class="flex gap-2"><i class="fas fa-location-dot text-gray-300 w-4 mt-0.5"></i>
                <span><?= $s['direccion'] ? nl2br(esc($s['direccion'])) : '<span class="text-gray-400">Sin dirección</span>' ?></span></p>
            <?php if (!empty($s['telefono'])): ?>
            <p class="flex gap-2"><i class="fas fa-phone text-gray-300 w-4 mt-0.5"></i> <?= esc($s['telefono']) ?></p>
            <?php endif; ?>
            <?php if (!empty($s['email'])): ?>
            <p class="flex gap-2"><i class="fas fa-envelope text-gray-300 w-4 mt-0.5"></i> <?= esc($s['email']) ?></p>
            <?php endif; ?>
            <?php if (!empty($s['enlace_mapa'])): ?>
            <p class="flex gap-2"><i class="fas fa-map text-gray-300 w-4 mt-0.5"></i>
                <a href="<?= esc($s['enlace_mapa'], 'attr') ?>" target="_blank" rel="noopener noreferrer" class="text-sky-600 hover:underline">Ver en mapa</a></p>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-3 gap-2 mt-4 pt-4 border-t border-gray-50 text-center">
            <div>
                <p class="text-[11px] text-gray-400">Depósitos</p>
                <p class="font-bold text-gray-800"><?= (int) $s['depositos'] ?></p>
            </div>
            <div>
                <p class="text-[11px] text-gray-400">Stock</p>
                <p class="font-bold text-gray-800"><?= number_format((float) $s['stock']) ?></p>
            </div>
            <div>
                <p class="text-[11px] text-gray-400">Ventas del mes</p>
                <p class="font-bold text-gray-800">$<?= number_format((float) $s['total_mes'], 2) ?></p>
                <p class="text-[10px] text-gray-400"><?= (int) $s['ventas_mes'] ?> ventas</p>
            </div>
        </div>

        <p class="mt-3 text-xs text-gray-400">
            Serie: <code class="bg-gray-50 border border-gray-100 rounded px-1"><?= esc($conSerie($s['prefijo'] ?? null, $fac)) ?></code>
            · <code class="bg-gray-50 border border-gray-100 rounded px-1"><?= esc($conSerie($s['prefijo'] ?? null, $cot)) ?></code>
            <?php if (empty($s['prefijo'])): ?>(serie general)<?php endif; ?>
        </p>
    </div>
<?php endforeach; ?>
</div>

<?php $this->endSection(); ?>

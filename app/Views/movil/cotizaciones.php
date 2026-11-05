<?php
$this->extend('layouts/movil');

$estadoCot = [
    'pendiente'  => ['Pendiente',  'var(--orange-light)', 'var(--orange)'],
    'aprobada'   => ['Aprobada',   'var(--green-light)',  'var(--green)'],
    'rechazada'  => ['Rechazada',  'var(--red-light)',    'var(--red)'],
    'convertida' => ['Convertida', 'var(--navy-light)',   'var(--navy)'],
];
?>
<?php $this->section('content'); ?>

<div class="flex flex-col gap-2 pb-4">
  <?php if (empty($cotizaciones)): ?><p class="text-sm" style="color:var(--ink-soft)">Todavía no hay cotizaciones.</p><?php endif; ?>
  <?php foreach ($cotizaciones as $q):
    $e = $estadoCot[$q['estado']] ?? [ucfirst((string) $q['estado']), 'var(--line)', 'var(--ink-soft)'];
    $enlace = base_url('c?t=' . $q['token']); ?>
    <div class="card !p-3">
      <div class="flex items-center gap-3">
        <div class="badge-icon text-lg" style="background:var(--amber-light)">📝</div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium truncate"><?= esc($q['cliente_nombre'] ?: 'Sin cliente') ?></p>
          <p class="text-xs truncate" style="color:var(--ink-soft)"><?= esc($q['numero']) ?> · <?= date('d/m/Y', strtotime($q['fecha'])) ?><?= $q['fecha_vencimiento'] ? ' · vence ' . date('d/m', strtotime($q['fecha_vencimiento'])) : '' ?></p>
        </div>
        <div class="text-right flex-shrink-0">
          <p class="font-mono text-sm font-semibold"><?= '$' . number_format((float) $q['total'], 2) ?></p>
          <span class="text-[10px] px-1.5 py-0.5 rounded" style="background:<?= $e[1] ?>; color:<?= $e[2] ?>"><?= esc($e[0]) ?></span>
        </div>
      </div>
      <div class="flex gap-2 mt-3">
        <a href="<?= esc($enlace) ?>" class="flex-1 text-center text-xs py-2 rounded-lg font-medium" style="background:var(--navy-light); color:var(--navy)">Ver</a>
        <a href="https://wa.me/?text=<?= rawurlencode('Cotización ' . $q['numero'] . ': ' . $enlace) ?>" class="flex-1 text-center text-xs py-2 rounded-lg font-medium" style="background:var(--green-light); color:var(--green)">WhatsApp</a>
        <a href="/ventas/cotizaciones/<?= (int) $q['cotizacion_id'] ?>/pdf" class="flex-1 text-center text-xs py-2 rounded-lg font-medium" style="background:var(--line); color:var(--ink)">PDF</a>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php $this->endSection(); ?>

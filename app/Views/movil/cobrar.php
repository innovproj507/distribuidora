<?php
$this->extend('layouts/movil');
$total = array_sum(array_map(fn ($c) => (float) $c['saldo_pendiente'], $cuentas));
?>
<?php $this->section('content'); ?>

<div class="stat-card stat-amber mb-4">
  <div class="flex items-center justify-between">
    <div>
      <p class="text-xs text-white/80">Total por cobrar</p>
      <p class="font-mono text-2xl font-semibold"><?= '$' . number_format($total, 2) ?></p>
    </div>
    <p class="text-xs text-white/80"><?= count($cuentas) ?> deudor<?= count($cuentas) === 1 ? '' : 'es' ?></p>
  </div>
</div>

<div class="flex flex-col gap-2 pb-4">
  <?php if (empty($cuentas)): ?><p class="text-sm text-center mt-6" style="color:var(--ink-soft)">Sin saldos pendientes 🎉</p><?php endif; ?>
  <?php foreach ($cuentas as $c):
    $saldo = (float) $c['saldo_pendiente']; $limite = (float) $c['limite_credito'];
    $pct = $limite > 0 ? min(100, round($saldo / $limite * 100)) : 100;
    $tel = preg_replace('/[^0-9+]/', '', (string) $c['telefono']); ?>
    <div class="card !p-3">
      <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
          <p class="text-sm font-medium truncate"><?= esc($c['nombre']) ?></p>
          <p class="text-xs" style="color:var(--ink-soft)"><?= $c['ultima'] ? 'Último crédito ' . date('d/m/Y', strtotime($c['ultima'])) : 'Sin crédito reciente' ?></p>
        </div>
        <p class="font-mono text-sm font-semibold flex-shrink-0" style="color:var(--orange)"><?= '$' . number_format($saldo, 2) ?></p>
      </div>
      <?php if ($limite > 0): ?>
        <div class="stockbar mt-2"><div style="width:<?= $pct ?>%; background:<?= $pct >= 90 ? 'var(--red)' : 'var(--orange)' ?>"></div></div>
        <p class="text-[10px] mt-1" style="color:var(--ink-soft)">Usa <?= $pct ?>% de $<?= number_format($limite, 2) ?></p>
      <?php endif; ?>
      <?php if ($tel): ?>
        <div class="flex gap-2 mt-3">
          <a href="tel:<?= esc($tel) ?>" class="flex-1 text-center text-xs py-2 rounded-lg font-medium" style="background:var(--navy-light); color:var(--navy)">Llamar</a>
          <a href="https://wa.me/<?= esc(ltrim($tel, '+')) ?>?text=<?= rawurlencode('Hola ' . $c['nombre'] . ', le recordamos su saldo pendiente de $' . number_format($saldo, 2) . '.') ?>" class="flex-1 text-center text-xs py-2 rounded-lg font-medium" style="background:var(--green-light); color:var(--green)">WhatsApp</a>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<?php $this->endSection(); ?>

<?php
$this->extend('layouts/movil');

$prio = [
    'critica' => ['Crítica', 'var(--red-light)',    'var(--red)'],
    'alta'    => ['Alta',    'var(--orange-light)', 'var(--orange)'],
    'media'   => ['Media',   'var(--amber-light)',  'var(--amber-dark)'],
    'baja'    => ['Baja',    'var(--navy-light)',   'var(--navy)'],
];
$tipos = [
    'stock_minimo' => '📉 Stock mínimo', 'stock_exceso' => '📦 Exceso', 'vencimiento_proximo' => '⏳ Por vencer',
    'lote_vencido' => '⛔ Lote vencido', 'rotacion_lenta' => '🐢 Rotación lenta',
];
?>
<?php $this->section('content'); ?>

<p class="text-sm mb-3" style="color:var(--ink-soft)"><?= count($alertas) ?> alerta<?= count($alertas) === 1 ? '' : 's' ?> activa<?= count($alertas) === 1 ? '' : 's' ?></p>

<div class="flex flex-col gap-2 pb-4">
  <?php if (empty($alertas)): ?><p class="text-sm text-center mt-6" style="color:var(--ink-soft)">Todo en orden ✅</p><?php endif; ?>
  <?php foreach ($alertas as $a): $p = $prio[$a['prioridad']] ?? $prio['baja']; ?>
    <div class="card !p-3" id="alerta-<?= (int) $a['alerta_id'] ?>">
      <div class="flex items-center justify-between gap-2 mb-1">
        <span class="text-[10px] px-1.5 py-0.5 rounded" style="background:<?= $p[1] ?>; color:<?= $p[2] ?>"><?= $p[0] ?></span>
        <span class="text-xs" style="color:var(--ink-soft)"><?= esc($tipos[$a['tipo']] ?? $a['tipo']) ?></span>
      </div>
      <p class="text-sm font-medium"><?= esc($a['producto_nombre'] ?: 'General') ?></p>
      <p class="text-xs mt-1" style="color:var(--ink-soft)"><?= esc($a['mensaje']) ?><?= $a['deposito_nombre'] ? ' · ' . esc($a['deposito_nombre']) : '' ?></p>
      <?php if ($puedeResolver): ?>
        <button type="button" onclick="resolver(<?= (int) $a['alerta_id'] ?>)" class="mt-3 w-full text-xs py-2 rounded-lg font-medium" style="background:var(--green-light); color:var(--green)">Marcar como resuelta</button>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<?php $this->endSection(); ?>

<?php $this->section('extra_js'); ?>
<script>
async function resolver(id) {
  try {
    await api('/m/api/alerta/' + id + '/resolver', { method: 'POST', body: {} });
    const el = document.getElementById('alerta-' + id);
    if (el) el.remove();
    toast('Alerta resuelta.');
  } catch (e) { toast(e.message, 'error'); }
}
</script>
<?php $this->endSection(); ?>

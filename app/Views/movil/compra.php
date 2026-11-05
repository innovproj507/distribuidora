<?php
$this->extend('layouts/movil');

$estadoCompra = [
    'recibida'              => ['Recibida',  'var(--green-light)',  'var(--green)'],
    'pendiente'             => ['Pendiente', 'var(--orange-light)', 'var(--orange)'],
    'parcialmente_recibida' => ['Parcial',   'var(--amber-light)',  'var(--amber-dark)'],
    'cancelada'             => ['Cancelada', 'var(--red-light)',    'var(--red)'],
];
$ec = $estadoCompra[$compra['estado']] ?? [ucfirst((string) $compra['estado']), 'var(--line)', 'var(--ink-soft)'];
?>
<?php $this->section('content'); ?>

<div class="card mb-4">
  <div class="flex items-center justify-between gap-3">
    <div class="min-w-0">
      <p class="text-sm font-medium truncate"><?= esc($compra['proveedor_nombre'] ?: 'Sin proveedor') ?></p>
      <p class="text-xs" style="color:var(--ink-soft)"><?= $compra['fecha_compra'] ? date('d/m/Y', strtotime($compra['fecha_compra'])) : '' ?></p>
    </div>
    <div class="text-right flex-shrink-0">
      <p class="font-mono text-sm font-semibold"><?= '$' . number_format((float) $compra['monto_total'], 2) ?></p>
      <span class="text-[10px] px-1.5 py-0.5 rounded" style="background:<?= $ec[1] ?>; color:<?= $ec[2] ?>"><?= esc($ec[0]) ?></span>
    </div>
  </div>
  <?php if (!empty($compra['notas'])): ?><p class="text-xs mt-2" style="color:var(--ink-soft)"><?= esc($compra['notas']) ?></p><?php endif; ?>
</div>

<?php if (!empty($porEnviar) && $puedeEnviar): foreach ($porEnviar as $b): ?>
<div class="stat-card stat-navy mb-4">
  <p class="font-head text-base font-semibold mb-1">Enviar a <?= esc($b['sucursal']) ?> 🚤</p>
  <p class="text-xs text-white/80 mb-3">Parte de esta compra va para esa tienda. Ajusta lo que sí viaja en este bote.</p>
  <div class="flex flex-col gap-2 mb-3">
    <?php foreach ($b['lineas'] as $l): ?>
      <div class="flex items-center gap-2 rounded-xl p-2" style="background:rgba(255,255,255,.12)">
        <p class="flex-1 min-w-0 text-sm truncate"><?= esc($l['nombre']) ?></p>
        <input type="number" inputmode="decimal" min="0" max="<?= (float) $l['cantidad'] ?>" step="0.01" value="<?= (float) $l['cantidad'] ?>"
               class="field-input reparto-<?= (int) $b['deposito_id'] ?>" data-producto="<?= (int) $l['producto_id'] ?>" style="width:84px; text-align:right; padding:6px 8px">
      </div>
    <?php endforeach; ?>
  </div>
  <button type="button" onclick="enviarReparto(<?= (int) $b['deposito_id'] ?>, this)" class="w-full py-3 rounded-xl font-medium" style="background:white; color:var(--navy-dark)">Crear traslado</button>
</div>
<?php endforeach; endif; ?>

<h2 class="font-head text-sm font-semibold mb-2"><?= $puedeRecibir ? 'Recepción' : 'Productos' ?></h2>
<?php if ($puedeRecibir): ?>
  <p class="text-xs mb-2" style="color:var(--ink-soft)">Escribe lo que realmente llegó. Lo que no llegue queda pendiente para la próxima entrega.</p>
<?php endif; ?>
<div class="flex flex-col gap-2 mb-4">
  <?php foreach ($lineas as $l):
    $pend = (float) $l['cantidad'] - (float) $l['cantidad_recibida']; ?>
    <div class="card !p-3 flex items-center gap-3">
      <div class="flex-1 min-w-0">
        <p class="text-sm truncate"><?= esc($l['nombre']) ?></p>
        <p class="text-xs font-mono" style="color:var(--ink-soft)">Pedido <?= (float) $l['cantidad'] ?> · recibido <?= (float) $l['cantidad_recibida'] ?> · <?= '$' . number_format((float) $l['costo'], 2) ?></p>
      </div>
      <?php if ($puedeRecibir && $pend > 0): ?>
        <input type="number" inputmode="decimal" min="0" max="<?= $pend ?>" step="0.01" value="<?= $pend ?>" data-detalle="<?= (int) $l['detalle_id'] ?>" class="field-input rec-linea" style="width:84px; text-align:right; padding:6px 8px">
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($puedeRecibir): ?>
  <button type="button" id="btn-recibir" onclick="recibirCompra()" class="btn-primario mb-4" style="background:var(--green)">Registrar recepción</button>
<?php endif; ?>
<a href="/m/compras" class="block text-center text-sm mb-4" style="color:var(--navy)">← Volver a compras</a>

<?php $this->endSection(); ?>

<?php $this->section('extra_js'); ?>
<script>
const COMPRA_ID = <?= (int) $compra['compra_id'] ?>;

async function recibirCompra() {
  const recibidas = {};
  document.querySelectorAll('.rec-linea').forEach(function (el) { recibidas[el.dataset.detalle] = parseFloat(el.value) || 0; });
  const boton = document.getElementById('btn-recibir');
  boton.disabled = true; boton.textContent = 'Guardando…';
  try {
    await api('/m/api/compra/' + COMPRA_ID + '/recibir', { method: 'POST', body: { recibidas: recibidas } });
    toast('Recepción registrada.');
    setTimeout(function () { location.reload(); }, 700);
  } catch (e) {
    toast(e.message, 'error');
    boton.disabled = false; boton.textContent = 'Registrar recepción';
  }
}

async function enviarReparto(destinoId, boton) {
  const cantidades = {};
  document.querySelectorAll('.reparto-' + destinoId).forEach(function (el) { cantidades[el.dataset.producto] = parseFloat(el.value) || 0; });
  boton.disabled = true; const texto = boton.textContent; boton.textContent = 'Creando…';
  try {
    await api('/m/api/compra/' + COMPRA_ID + '/reparto', { method: 'POST', body: { destino_id: destinoId, cantidades: cantidades } });
    toast('Traslado creado. Sigue en "Traslados".');
    setTimeout(function () { location.href = '/m/traslados'; }, 900);
  } catch (e) {
    toast(e.message, 'error');
    boton.disabled = false; boton.textContent = texto;
  }
}
</script>
<?php $this->endSection(); ?>

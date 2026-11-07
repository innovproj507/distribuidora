<?php
$this->extend('layouts/movil');

$existencia = (float) ($inv['existencia'] ?? 0);
$minimo     = (float) ($inv['minimo'] ?? 0);
[$etiqueta, $fondo, $color] = $existencia <= 0
    ? ['Agotado', 'var(--red-light)', 'var(--red)']
    : ($existencia <= $minimo ? ['Stock bajo', 'var(--orange-light)', 'var(--orange)'] : ['Disponible', 'var(--green-light)', 'var(--green)']);
$tipos = [
    'entrada'          => ['Entrada', 'var(--green)', '+'],
    'salida'           => ['Salida', 'var(--red)', '−'],
    'ajuste'           => ['Ajuste', 'var(--amber-dark)', '±'],
    'traslado_salida'  => ['Traslado enviado', 'var(--red)', '−'],
    'traslado_entrada' => ['Traslado recibido', 'var(--green)', '+'],
];
$num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ','), '0'), '.');
?>
<?php $this->section('content'); ?>

<a href="/m/inventario" class="inline-flex items-center gap-1 text-xs mb-3 px-3 py-1.5 rounded-full bg-white border" style="border-color:var(--line); color:var(--ink-soft)">← Inventario</a>

<div class="card mb-4">
  <div class="flex gap-3">
    <?php if (!empty($p['imagen_principal'])): ?>
      <img src="/assets/uploads/<?= esc($p['imagen_principal'], 'attr') ?>" alt="" class="w-20 h-20 rounded-xl object-cover flex-shrink-0" onerror="this.style.display='none'">
    <?php else: ?>
      <div class="w-20 h-20 rounded-xl flex items-center justify-center text-3xl flex-shrink-0" style="background:var(--navy-light)">📦</div>
    <?php endif; ?>
    <div class="min-w-0 flex-1">
      <p class="font-head text-base font-semibold leading-tight"><?= esc($p['nombre']) ?></p>
      <p class="text-xs mt-1 font-mono" style="color:var(--ink-soft)"><?= esc($p['codigo']) ?><?= $p['codigo_barras'] ? ' · ' . esc($p['codigo_barras']) : '' ?></p>
      <div class="flex flex-wrap gap-1 mt-2">
        <span class="text-[10px] px-2 py-0.5 rounded-full" style="background:<?= $fondo ?>; color:<?= $color ?>"><?= $etiqueta ?></span>
        <?php if ($p['estado'] !== 'activo'): ?><span class="text-[10px] px-2 py-0.5 rounded-full" style="background:var(--line); color:var(--ink-soft)">Inactivo</span><?php endif; ?>
        <?php if ($p['maneja_lotes']): ?><span class="text-[10px] px-2 py-0.5 rounded-full" style="background:var(--purple-light); color:var(--purple)">Con lotes</span><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-3 gap-2 mt-4 text-center">
    <div class="rounded-xl py-2" style="background:var(--navy-light)">
      <p class="text-[10px]" style="color:var(--ink-soft)">Precio</p>
      <p class="font-mono text-sm font-semibold"><?= $precio > 0 ? '$' . number_format($precio, 2) : '—' ?></p>
    </div>
    <div class="rounded-xl py-2" style="background:var(--navy-light)">
      <p class="text-[10px]" style="color:var(--ink-soft)">Aquí hay</p>
      <p class="font-mono text-sm font-semibold"><?= $num($existencia) ?></p>
    </div>
    <div class="rounded-xl py-2" style="background:var(--navy-light)">
      <p class="text-[10px]" style="color:var(--ink-soft)">Vendido 30 días</p>
      <p class="font-mono text-sm font-semibold"><?= $num($vendido30) ?></p>
    </div>
  </div>

  <dl class="mt-4 text-sm grid grid-cols-2 gap-y-1.5">
    <dt style="color:var(--ink-soft)">Categoría</dt><dd class="text-right truncate"><?= esc($p['categoria_nombre'] ?? '—') ?></dd>
    <dt style="color:var(--ink-soft)">Marca</dt><dd class="text-right truncate"><?= esc($p['marca'] ?: '—') ?></dd>
    <dt style="color:var(--ink-soft)">Proveedor</dt><dd class="text-right truncate"><?= esc($p['proveedor_nombre'] ?? '—') ?></dd>
    <dt style="color:var(--ink-soft)">Ubicación</dt><dd class="text-right truncate"><?= !empty($inv['ubicacion']) ? '📍 ' . esc($inv['ubicacion']) : '—' ?></dd>
    <dt style="color:var(--ink-soft)">Stock mínimo</dt><dd class="text-right font-mono"><?= $num($minimo) ?></dd>
    <?php if ($verCosto): ?>
      <dt style="color:var(--ink-soft)">Costo promedio</dt><dd class="text-right font-mono">$<?= number_format((float) ($inv['costo_promedio'] ?? $p['costo']), 2) ?></dd>
      <dt style="color:var(--ink-soft)">Margen</dt><dd class="text-right font-mono"><?php $c = (float) ($inv['costo_promedio'] ?? $p['costo']); echo $precio > 0 && $c > 0 ? number_format(($precio - $c) * 100 / $precio, 1) . '%' : '—'; ?></dd>
    <?php endif; ?>
  </dl>
</div>

<?php if ($puedeEditar || ($puedeAjustar && !$p['maneja_lotes'])): ?>
<div class="flex gap-2 mb-4">
  <?php if ($puedeEditar): ?>
    <a href="/m/productos/<?= (int) $p['producto_id'] ?>/editar" class="btn-primario text-center flex-1">✏️ Editar</a>
  <?php endif; ?>
  <?php if ($puedeAjustar && !$p['maneja_lotes']): ?>
    <button type="button" onclick="abrirSheet('sheet-movimiento')" class="flex-1 py-3 rounded-xl font-medium" style="background:var(--navy-light); color:var(--navy)">↕ Entrada / salida</button>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($unidades): ?>
<h2 class="font-head text-sm font-semibold mb-2">Presentaciones</h2>
<div class="card !p-3 mb-4 flex flex-col gap-1.5">
  <div class="flex justify-between text-sm"><span>Unidad</span><span class="font-mono"><?= $precio > 0 ? '$' . number_format($precio, 2) : '—' ?></span></div>
  <?php foreach ($unidades as $u): $pu = (float) $u['precio_a'] > 0 ? (float) $u['precio_a'] : $precio * (float) $u['factor_conversion']; ?>
    <div class="flex justify-between text-sm"><span><?= esc($u['nombre']) ?> <span class="text-xs" style="color:var(--ink-soft)">(<?= $num($u['factor_conversion']) ?> uds)</span></span><span class="font-mono">$<?= number_format($pu, 2) ?></span></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<h2 class="font-head text-sm font-semibold mb-2">Stock por tienda</h2>
<div class="flex flex-col gap-2 mb-4">
  <?php foreach ($stocks as $s): $esAqui = (int) $s['deposito_id'] === (int) ($deposito_actual['deposito_id'] ?? 0); ?>
    <div class="card !p-3 flex items-center justify-between gap-2" <?= $esAqui ? 'style="border:1.5px solid var(--navy)"' : '' ?>>
      <div class="min-w-0">
        <p class="text-sm font-medium truncate"><?= esc($s['sucursal']) ?></p>
        <p class="text-xs truncate" style="color:var(--ink-soft)"><?= esc($s['deposito']) ?><?= $s['ubicacion'] ? ' · 📍 ' . esc($s['ubicacion']) : '' ?></p>
      </div>
      <p class="font-mono text-sm font-semibold flex-shrink-0" style="color:<?= (float) $s['existencia'] <= 0 ? 'var(--red)' : 'var(--ink)' ?>"><?= $num($s['existencia']) ?></p>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($lotes): ?>
<h2 class="font-head text-sm font-semibold mb-2">Lotes en este depósito</h2>
<div class="flex flex-col gap-2 mb-4">
  <?php foreach ($lotes as $l): $d = $l['dias'] === null ? null : (int) $l['dias']; ?>
    <div class="card !p-3 flex items-center justify-between gap-2">
      <div class="min-w-0">
        <p class="text-sm font-mono truncate"><?= esc($l['numero_lote']) ?></p>
        <p class="text-xs" style="color:<?= $d !== null && $d < 0 ? 'var(--red)' : ($d !== null && $d <= 30 ? 'var(--orange)' : 'var(--ink-soft)') ?>">
          <?= $l['fecha_vencimiento'] ? 'Vence ' . date('d/m/Y', strtotime($l['fecha_vencimiento'])) . ($d < 0 ? ' (vencido)' : " (en {$d} días)") : 'Sin vencimiento' ?>
        </p>
      </div>
      <p class="font-mono text-sm font-semibold"><?= $num($l['cantidad_actual']) ?></p>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<h2 class="font-head text-sm font-semibold mb-2">Últimos movimientos aquí</h2>
<div class="flex flex-col gap-2 mb-6">
  <?php if (!$movimientos): ?>
    <p class="card text-sm" style="color:var(--ink-soft)">Todavía no hay movimientos en este depósito.</p>
  <?php endif; ?>
  <?php foreach ($movimientos as $m): $t = $tipos[$m['tipo']] ?? [ucfirst($m['tipo']), 'var(--ink)', '']; ?>
    <div class="card !p-3 flex items-center justify-between gap-2">
      <div class="min-w-0">
        <p class="text-sm truncate"><?= esc($t[0]) ?><?= $m['motivo'] ? ' · <span style="color:var(--ink-soft)">' . esc($m['motivo']) . '</span>' : '' ?></p>
        <p class="text-[11px]" style="color:var(--ink-soft)"><?= date('d/m/Y H:i', strtotime($m['fecha_registro'])) ?><?= $m['username'] ? ' · ' . esc($m['username']) : '' ?></p>
      </div>
      <div class="text-right flex-shrink-0">
        <p class="font-mono text-sm font-semibold" style="color:<?= $t[1] ?>"><?= $t[2] . $num($m['cantidad']) ?></p>
        <p class="font-mono text-[11px]" style="color:var(--ink-soft)">queda <?= $num($m['saldo_nuevo']) ?></p>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($puedeAjustar && !$p['maneja_lotes']): ?>
<div id="sheet-movimiento" class="sheet" style="transform:translateY(110%)">
  <div class="sheet-handle"></div>
  <div class="flex items-center justify-between mb-1">
    <h2 class="font-head text-lg font-semibold">Registrar movimiento</h2>
    <button type="button" onclick="cerrarSheet('sheet-movimiento')" class="w-8 h-8 rounded-full flex items-center justify-center" style="background:var(--navy-light); color:var(--navy)">✕</button>
  </div>
  <p class="text-sm mb-4" style="color:var(--ink-soft)">Stock actual: <span class="font-mono"><?= $num($existencia) ?></span></p>
  <form onsubmit="return guardarMovimiento(event)">
    <div class="flex gap-2 mb-4">
      <button type="button" id="mov-btn-entrada" onclick="elegirTipo('entrada')" class="flex-1 py-2.5 rounded-xl text-sm font-medium border">+ Entrada</button>
      <button type="button" id="mov-btn-salida" onclick="elegirTipo('salida')" class="flex-1 py-2.5 rounded-xl text-sm font-medium border">− Salida</button>
    </div>
    <label class="field-label">Cantidad</label>
    <input class="field-input mb-3" type="number" inputmode="decimal" id="mov-cantidad" min="0.01" step="any" required>
    <label class="field-label">Motivo (opcional)</label>
    <input class="field-input mb-5" style="font-family:'Inter'" id="mov-motivo" placeholder="Ej: Merma, Devolución, Conteo">
    <button type="submit" id="mov-guardar" class="btn-primario">Guardar movimiento</button>
  </form>
</div>
<?php endif; ?>

<?php $this->endSection(); ?>

<?php $this->section('extra_js'); ?>
<?php if ($puedeAjustar && !$p['maneja_lotes']): ?>
<script>
let movTipo = 'entrada';
function elegirTipo(tipo) {
  movTipo = tipo;
  document.getElementById('mov-btn-entrada').style.cssText = tipo === 'entrada'
    ? 'background:var(--green-light); color:var(--green); border-color:var(--green)' : 'background:white; color:var(--ink-soft); border-color:var(--line)';
  document.getElementById('mov-btn-salida').style.cssText = tipo === 'salida'
    ? 'background:var(--red-light); color:var(--red); border-color:var(--red)' : 'background:white; color:var(--ink-soft); border-color:var(--line)';
}
async function guardarMovimiento(ev) {
  ev.preventDefault();
  const boton = document.getElementById('mov-guardar');
  boton.disabled = true;
  try {
    await api('/m/api/movimiento', { method: 'POST', body: {
      producto_id: <?= (int) $p['producto_id'] ?>, tipo: movTipo,
      cantidad: parseFloat(document.getElementById('mov-cantidad').value),
      motivo: document.getElementById('mov-motivo').value.trim(),
    }});
    location.reload();
  } catch (e) {
    toast(e.message, 'error');
    boton.disabled = false;
  }
  return false;
}
elegirTipo('entrada');
</script>
<?php endif; ?>
<?php $this->endSection(); ?>

<?php
$this->extend('layouts/movil');
?>
<?php $this->section('content'); ?>

<?php if ($puedeCrear): ?>
<button type="button" onclick="abrirSheet('sheet-nuevo-conteo')" class="stat-card stat-navy block w-full text-left mb-4">
  <div class="flex items-center justify-between">
    <div>
      <p class="font-head text-lg font-semibold">Nuevo conteo</p>
      <p class="text-xs text-white/80 mt-1">En <?= esc($deposito_actual['nombre'] ?? 'tu depósito') ?> · todo, un estante o una categoría</p>
    </div>
    <div class="w-12 h-12 rounded-full bg-white/15 flex items-center justify-center text-2xl">📋</div>
  </div>
</button>
<?php endif; ?>

<h2 class="font-head text-sm font-semibold mb-2">Conteos</h2>
<div class="flex flex-col gap-2 pb-4">
  <?php if (empty($conteos)): ?><p class="text-sm" style="color:var(--ink-soft)">Todavía no hay conteos en esta tienda.</p><?php endif; ?>
  <?php foreach ($conteos as $c):
    $abierto = $c['estado'] === 'en_proceso';
    $pct = (int) $c['total_productos'] > 0 ? round((int) $c['contados'] / (int) $c['total_productos'] * 100) : 0; ?>
    <a href="/m/conteos/<?= (int) $c['conteo_id'] ?>" class="card !p-3">
      <div class="flex items-center justify-between gap-2">
        <p class="text-sm font-medium truncate"><?= esc($c['descripcion'] ?: 'Conteo #' . $c['conteo_id']) ?></p>
        <span class="text-[10px] px-1.5 py-0.5 rounded flex-shrink-0" style="background:<?= $abierto ? 'var(--orange-light)' : 'var(--green-light)' ?>; color:<?= $abierto ? 'var(--orange)' : 'var(--green)' ?>"><?= $abierto ? 'En proceso' : 'Cerrado' ?></span>
      </div>
      <p class="text-xs mt-1" style="color:var(--ink-soft)"><?= date('d/m H:i', strtotime($c['fecha_inicio'])) ?> · <?= (int) $c['contados'] ?>/<?= (int) $c['total_productos'] ?> contados<?= (int) $c['total_diferencias'] > 0 ? ' · ' . (int) $c['total_diferencias'] . ' con diferencia' : '' ?></p>
      <?php if ($abierto): ?><div class="stockbar mt-2"><div style="width:<?= $pct ?>%; background:var(--navy)"></div></div><?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if ($puedeCrear): ?>
<div id="sheet-nuevo-conteo" class="sheet" style="transform:translateY(110%)">
  <div class="sheet-handle"></div>
  <div class="flex items-center justify-between mb-3">
    <h2 class="font-head text-lg font-semibold">Nuevo conteo</h2>
    <button type="button" onclick="cerrarSheet('sheet-nuevo-conteo')" class="w-8 h-8 rounded-full flex items-center justify-center" style="background:var(--navy-light); color:var(--navy)">✕</button>
  </div>
  <p class="text-xs mb-3" style="color:var(--ink-soft)">Se toma una foto del stock ahora. Si vendes mientras cuentas no pasa nada: el ajuste usa la diferencia, no pisa las ventas.</p>

  <label class="field-label">¿Qué vas a contar?</label>
  <div class="grid grid-cols-3 gap-2 mb-3">
    <button type="button" class="chip-pago chip-alc activo" data-alcance="todo" onclick="elegirAlcance('todo')">Todo</button>
    <button type="button" class="chip-pago chip-alc" data-alcance="ubicacion" onclick="elegirAlcance('ubicacion')">Un estante</button>
    <button type="button" class="chip-pago chip-alc" data-alcance="categoria" onclick="elegirAlcance('categoria')">Categoría</button>
  </div>

  <select id="cn-ubicacion" class="field-input mb-3" style="font-family:'Inter'; display:none">
    <?php foreach ($ubicaciones as $u): ?>
      <option value="<?= esc($u['ubicacion'], 'attr') ?>"><?= $u['ubicacion'] !== '' ? esc($u['ubicacion']) : 'Sin ubicación' ?> (<?= (int) $u['productos'] ?>)</option>
    <?php endforeach; ?>
  </select>
  <select id="cn-categoria" class="field-input mb-3" style="font-family:'Inter'; display:none">
    <?php foreach ($categorias as $c): ?>
      <option value="<?= (int) $c['categoria_id'] ?>"><?= esc($c['nombre']) ?> (<?= (int) $c['total'] ?>)</option>
    <?php endforeach; ?>
  </select>

  <input id="cn-descripcion" class="field-input mb-4" style="font-family:'Inter'" placeholder="Descripción (opcional)">
  <button type="button" id="btn-conteo" onclick="crearConteo()" class="btn-primario">Empezar a contar</button>
</div>
<?php endif; ?>

<?php $this->endSection(); ?>

<?php $this->section('extra_js'); ?>
<script>
let alcance = 'todo';
function elegirAlcance(a) {
  alcance = a;
  document.querySelectorAll('.chip-alc').forEach(function (b) { b.classList.toggle('activo', b.dataset.alcance === a); });
  document.getElementById('cn-ubicacion').style.display = a === 'ubicacion' ? 'block' : 'none';
  document.getElementById('cn-categoria').style.display = a === 'categoria' ? 'block' : 'none';
}

async function crearConteo() {
  const boton = document.getElementById('btn-conteo');
  boton.disabled = true; boton.textContent = 'Abriendo…';
  try {
    const r = await api('/m/api/conteo', { method: 'POST', body: {
      alcance: alcance,
      ubicacion: document.getElementById('cn-ubicacion').value,
      categoria_id: parseInt(document.getElementById('cn-categoria').value, 10) || 0,
      descripcion: document.getElementById('cn-descripcion').value.trim(),
    }});
    location.href = '/m/conteos/' + r.conteo_id;
  } catch (e) {
    toast(e.message, 'error');
    boton.disabled = false; boton.textContent = 'Empezar a contar';
  }
}
</script>
<?php $this->endSection(); ?>

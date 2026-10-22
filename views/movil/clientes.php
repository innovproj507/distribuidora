<?php
use App\Core\View;

View::layout('movil');

$conSaldo = array_filter($clientes, fn ($c) => (float) $c['saldo_pendiente'] > 0);
$totalPorCobrar = array_sum(array_map(fn ($c) => (float) $c['saldo_pendiente'], $conSaldo));
?>
<?php View::section('content'); ?>

<div class="mb-3 flex items-center gap-2">
  <div class="flex items-center gap-2 bg-white rounded-xl border px-3 py-2 flex-1" style="border-color:var(--line)">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6B7794" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
    <input type="search" id="input-busqueda" class="flex-1 text-sm outline-none bg-transparent" placeholder="Buscar cliente, teléfono o RUC" autocomplete="off">
  </div>
  <?php if ($puedeCrear): ?>
    <button type="button" onclick="abrirSheet('sheet-nuevo')" class="w-10 h-10 rounded-xl flex items-center justify-center text-xl flex-shrink-0 text-white" style="background:var(--navy)" title="Nuevo cliente">+</button>
  <?php endif; ?>
</div>

<?php if ($totalPorCobrar > 0): ?>
<div class="stat-card stat-amber mb-4">
  <div class="flex items-center justify-between">
    <div>
      <p class="text-xs text-white/80">Por cobrar</p>
      <p class="font-mono text-xl font-semibold"><?= '$' . number_format($totalPorCobrar, 2) ?></p>
    </div>
    <p class="text-xs text-white/80"><?= count($conSaldo) ?> cliente<?= count($conSaldo) === 1 ? '' : 's' ?> con saldo</p>
  </div>
</div>
<?php endif; ?>

<p class="text-sm text-center mt-8" id="msg-sin-busqueda" style="color:var(--ink-soft); display:none">Ningún cliente coincide con la búsqueda.</p>

<div class="flex flex-col gap-2.5 pb-4">
  <?php if (empty($clientes)): ?>
    <p class="text-sm text-center mt-8" style="color:var(--ink-soft)">Todavía no hay clientes registrados.</p>
  <?php endif; ?>

  <?php foreach ($clientes as $c):
    $saldo = (float) $c['saldo_pendiente'];
    $busqueda = mb_strtolower($c['nombre'] . ' ' . ($c['telefono'] ?? '') . ' ' . ($c['ruc'] ?? '') . ' ' . ($c['codigo'] ?? ''), 'UTF-8');
    $datos = [
        'id'         => (int) $c['cliente_id'],
        'codigo'     => $c['codigo'],
        'nombre'     => $c['nombre'],
        'telefono'   => $c['telefono'],
        'email'      => $c['email'],
        'ruc'        => $c['ruc'],
        'limite'     => (float) $c['limite_credito'],
        'saldo'      => $saldo,
        'dias'       => (int) $c['dias_credito'],
    ];
  ?>
    <button type="button" onclick='verCliente(<?= json_encode($datos, JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
            class="card text-left flex items-center gap-3 !p-3 cliente-card" data-busqueda="<?= View::e($busqueda) ?>">
      <div class="badge-icon text-lg" style="background:var(--navy-light)">👤</div>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-medium truncate"><?= View::e($c['nombre']) ?></p>
        <p class="text-xs truncate" style="color:var(--ink-soft)"><?= View::e($c['telefono'] ?: 'Sin teléfono') ?><?= $c['codigo'] ? ' · ' . View::e($c['codigo']) : '' ?></p>
      </div>
      <?php if ($saldo > 0): ?>
        <span class="font-mono text-xs font-semibold px-2 py-1 rounded-lg flex-shrink-0" style="background:var(--orange-light); color:var(--orange)"><?= '$' . number_format($saldo, 2) ?></span>
      <?php endif; ?>
    </button>
  <?php endforeach; ?>
</div>

<!-- Sheet: detalle de cliente -->
<div id="sheet-detalle" class="sheet" style="transform:translateY(110%)">
  <div class="sheet-handle"></div>
  <div class="flex items-center justify-between mb-4">
    <h2 class="font-head text-lg font-semibold truncate pr-2" id="det-nombre"></h2>
    <button type="button" onclick="cerrarSheet('sheet-detalle')" class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0" style="background:var(--navy-light); color:var(--navy)">✕</button>
  </div>
  <div class="card mb-3 text-sm flex flex-col gap-2">
    <div class="flex justify-between"><span style="color:var(--ink-soft)">Código</span><span id="det-codigo" class="font-mono"></span></div>
    <div class="flex justify-between"><span style="color:var(--ink-soft)">Teléfono</span><a id="det-telefono" style="color:var(--navy)"></a></div>
    <div class="flex justify-between"><span style="color:var(--ink-soft)">RUC</span><span id="det-ruc"></span></div>
    <div class="flex justify-between"><span style="color:var(--ink-soft)">Email</span><span id="det-email" class="truncate pl-4"></span></div>
  </div>
  <div class="grid grid-cols-2 gap-3 mb-4">
    <div class="card !p-3"><p class="text-xs" style="color:var(--ink-soft)">Saldo pendiente</p><p class="font-mono font-semibold" id="det-saldo"></p></div>
    <div class="card !p-3"><p class="text-xs" style="color:var(--ink-soft)">Crédito disponible</p><p class="font-mono font-semibold" id="det-disponible"></p></div>
  </div>
  <a id="det-web" href="#" class="block w-full py-3 rounded-xl font-medium text-center" style="background:var(--navy-light); color:var(--navy)">Ver historial completo (web)</a>
</div>

<?php if ($puedeCrear): ?>
<!-- Sheet: nuevo cliente -->
<div id="sheet-nuevo" class="sheet" style="transform:translateY(110%)">
  <div class="sheet-handle"></div>
  <div class="flex items-center justify-between mb-4">
    <h2 class="font-head text-lg font-semibold">Nuevo cliente</h2>
    <button type="button" onclick="cerrarSheet('sheet-nuevo')" class="w-8 h-8 rounded-full flex items-center justify-center" style="background:var(--navy-light); color:var(--navy)">✕</button>
  </div>
  <form onsubmit="return crearCliente(event)">
    <label class="field-label">Nombre</label>
    <input class="field-input mb-3" style="font-family:'Inter'" id="nc-nombre" required>
    <div class="grid grid-cols-2 gap-3 mb-3">
      <div>
        <label class="field-label">Teléfono</label>
        <input class="field-input" style="font-family:'Inter'" id="nc-telefono" inputmode="tel">
      </div>
      <div>
        <label class="field-label">RUC / Cédula</label>
        <input class="field-input" style="font-family:'Inter'" id="nc-ruc">
      </div>
    </div>
    <label class="field-label">Email (opcional)</label>
    <input class="field-input mb-5" style="font-family:'Inter'" type="email" id="nc-email">
    <button type="submit" id="nc-guardar" class="btn-primario">Guardar cliente</button>
    <p class="text-xs text-center mt-3" style="color:var(--ink-soft)">El límite de crédito se configura en la versión web.</p>
  </form>
</div>
<?php endif; ?>

<?php View::endSection('content'); ?>

<?php View::section('extra_js'); ?>
<script>
function verCliente(c) {
  document.getElementById('det-nombre').textContent = c.nombre;
  document.getElementById('det-codigo').textContent = c.codigo || '—';
  const tel = document.getElementById('det-telefono');
  tel.textContent = c.telefono || '—';
  if (c.telefono) { tel.href = 'tel:' + c.telefono.replace(/[^0-9+]/g, ''); } else { tel.removeAttribute('href'); }
  document.getElementById('det-ruc').textContent = c.ruc || '—';
  document.getElementById('det-email').textContent = c.email || '—';
  document.getElementById('det-saldo').textContent = dinero(c.saldo);
  document.getElementById('det-disponible').textContent = c.limite > 0 ? dinero(Math.max(0, c.limite - c.saldo)) : 'Sin crédito';
  document.getElementById('det-web').href = '/clientes/' + c.id;
  abrirSheet('sheet-detalle');
}

async function crearCliente(ev) {
  ev.preventDefault();
  const boton = document.getElementById('nc-guardar');
  boton.disabled = true;
  try {
    await api('/ventas/clientes/rapido', {
      method: 'POST',
      body: {
        nombre: document.getElementById('nc-nombre').value.trim(),
        telefono: document.getElementById('nc-telefono').value.trim(),
        ruc: document.getElementById('nc-ruc').value.trim(),
        email: document.getElementById('nc-email').value.trim(),
      },
    });
    window.location.reload();
  } catch (e) {
    toast(e.message, 'error');
    boton.disabled = false;
  }
  return false;
}

// Filtro local: la lista de clientes ya viene completa del servidor
document.getElementById('input-busqueda').addEventListener('input', function () {
  const q = this.value.trim().toLowerCase();
  let visibles = 0;
  document.querySelectorAll('.cliente-card').forEach(function (card) {
    const coincide = !q || card.dataset.busqueda.indexOf(q) !== -1;
    card.style.display = coincide ? 'flex' : 'none';
    if (coincide) visibles++;
  });
  document.getElementById('msg-sin-busqueda').style.display = visibles === 0 && q ? 'block' : 'none';
});
</script>
<?php View::endSection('extra_js'); ?>

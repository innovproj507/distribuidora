<?php
$this->extend('layouts/movil');

$estadoT = [
    'en_transito' => ['En el bote', 'var(--orange-light)', 'var(--orange)'],
    'recibido'    => ['Recibido',   'var(--green-light)',  'var(--green)'],
    'pendiente'   => ['Pendiente',  'var(--amber-light)',  'var(--amber-dark)'],
    'cancelado'   => ['Cancelado',  'var(--red-light)',    'var(--red)'],
];
?>
<?php $this->section('content'); ?>

<?php if ($puedeEnviar && $destinos): ?>
<button type="button" onclick="abrirNuevoEnvio()" class="stat-card stat-navy block w-full text-left mb-4">
  <div class="flex items-center justify-between">
    <div>
      <p class="font-head text-lg font-semibold">Enviar mercancía</p>
      <p class="text-xs text-white/80 mt-1">Sale de <?= esc($deposito_actual['nombre'] ?? 'tu depósito') ?> hacia otra tienda</p>
    </div>
    <div class="w-12 h-12 rounded-full bg-white/15 flex items-center justify-center text-2xl">🚤</div>
  </div>
</button>
<?php elseif ($puedeEnviar): ?>
<p class="card text-sm mb-4" style="color:var(--ink-soft)">Para enviar mercancía primero crea la otra tienda (versión web → Sucursales).</p>
<?php endif; ?>

<h2 class="font-head text-sm font-semibold mb-2">Por recibir en esta tienda</h2>
<div class="flex flex-col gap-2 mb-5">
  <?php if (empty($porRecibir)): ?><p class="text-sm" style="color:var(--ink-soft)">Nada en camino.</p><?php endif; ?>
  <?php foreach ($porRecibir as $t): ?>
    <button type="button" onclick="abrirRecepcion(<?= (int) $t['traslado_id'] ?>)" class="card !p-3 flex items-center gap-3 text-left" <?= $puedeRecibir ? '' : 'disabled' ?>>
      <div class="badge-icon text-lg" style="background:var(--orange-light)">🚤</div>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-medium truncate">Desde <?= esc($t['origen_sucursal']) ?></p>
        <p class="text-xs truncate" style="color:var(--ink-soft)"><?= esc($t['numero']) ?> · <?= date('d/m H:i', strtotime($t['fecha_envio'])) ?> · <?= (int) $t['lineas'] ?> producto(s)</p>
      </div>
      <span class="text-xs font-medium flex-shrink-0" style="color:var(--navy)"><?= $puedeRecibir ? 'Recibir →' : '' ?></span>
    </button>
  <?php endforeach; ?>
</div>

<h2 class="font-head text-sm font-semibold mb-2">Enviados desde esta tienda</h2>
<div class="flex flex-col gap-2 pb-4">
  <?php if (empty($enviados)): ?><p class="text-sm" style="color:var(--ink-soft)">Todavía no has enviado traslados.</p><?php endif; ?>
  <?php foreach ($enviados as $t): $e = $estadoT[$t['estado']] ?? [ucfirst((string) $t['estado']), 'var(--line)', 'var(--ink-soft)']; ?>
    <button type="button" onclick="verTraslado(<?= (int) $t['traslado_id'] ?>)" class="card !p-3 flex items-center gap-3 text-left">
      <div class="badge-icon text-lg" style="background:var(--navy-light)">📦</div>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-medium truncate">Hacia <?= esc($t['destino_sucursal']) ?></p>
        <p class="text-xs truncate" style="color:var(--ink-soft)"><?= esc($t['numero']) ?> · <?= date('d/m H:i', strtotime($t['fecha_envio'])) ?> · <?= (int) $t['lineas'] ?> producto(s)</p>
      </div>
      <span class="text-[10px] px-1.5 py-0.5 rounded flex-shrink-0" style="background:<?= $e[1] ?>; color:<?= $e[2] ?>"><?= esc($e[0]) ?></span>
    </button>
  <?php endforeach; ?>
</div>

<!-- Sheet: nuevo envío -->
<div id="sheet-envio" class="sheet" style="transform:translateY(110%); max-height:94vh">
  <div class="sheet-handle"></div>
  <div class="flex items-center justify-between mb-3">
    <h2 class="font-head text-lg font-semibold">Nuevo envío</h2>
    <button type="button" onclick="cerrarSheet('sheet-envio')" class="w-8 h-8 rounded-full flex items-center justify-center" style="background:var(--navy-light); color:var(--navy)">✕</button>
  </div>
  <label class="field-label">Destino</label>
  <select id="envio-destino" class="field-input mb-3" style="font-family:'Inter'">
    <?php foreach ($destinos as $d): ?>
      <option value="<?= (int) $d['deposito_id'] ?>"><?= esc($d['sucursal'] . ' · ' . $d['deposito']) ?></option>
    <?php endforeach; ?>
  </select>

  <div id="envio-items" class="flex flex-col gap-2 mb-3"></div>

  <label class="field-label">Agregar producto</label>
  <input type="search" id="envio-buscar" class="field-input mb-2" style="font-family:'Inter'" placeholder="Buscar nombre o código" autocomplete="off">
  <div id="envio-resultados" class="flex flex-col gap-2 mb-3" style="max-height:26vh; overflow-y:auto"></div>

  <label class="field-label">Notas (opcional)</label>
  <input id="envio-notas" class="field-input mb-4" style="font-family:'Inter'" placeholder="Ej: viaja en el bote de las 7am">
  <button type="button" id="btn-enviar" onclick="confirmarEnvio()" class="btn-primario">Enviar traslado</button>
</div>

<!-- Sheet: recibir / ver -->
<div id="sheet-recibir" class="sheet" style="transform:translateY(110%); max-height:94vh">
  <div class="sheet-handle"></div>
  <div class="flex items-center justify-between mb-1">
    <h2 class="font-head text-lg font-semibold" id="rec-titulo">Traslado</h2>
    <button type="button" onclick="cerrarSheet('sheet-recibir')" class="w-8 h-8 rounded-full flex items-center justify-center" style="background:var(--navy-light); color:var(--navy)">✕</button>
  </div>
  <p class="text-xs mb-3" id="rec-ayuda" style="color:var(--ink-soft)"></p>
  <div id="rec-items" class="flex flex-col gap-2 mb-4"></div>
  <button type="button" id="btn-recibir" onclick="confirmarRecepcion()" class="btn-primario" style="background:var(--green)">Confirmar recepción</button>
</div>

<?php $this->endSection(); ?>

<?php $this->section('extra_js'); ?>
<script>
const envio = {};          // producto_id → { p, cantidad }
let trasladoActual = null;

// ── Envío ────────────────────────────────────────────────────────────────────
const lista = new ListaProductos({
  contenedor: 'envio-resultados',
  filtros: function () { return { q: document.getElementById('envio-buscar').value.trim() }; },
  renderItem: function (p) {
    return '<button type="button" onclick="agregarEnvio(' + p.producto_id + ')" class="card !p-3 flex items-center gap-3 text-left">' +
      '<div class="flex-1 min-w-0"><p class="text-sm truncate">' + esc(p.nombre) + '</p>' +
      '<p class="text-xs" style="color:var(--ink-soft)">' + esc(p.codigo || '') + ' · hay ' + numero(p.existencia) + '</p></div>' +
      '<span class="text-lg" style="color:var(--navy)">+</span></button>';
  },
});

function abrirNuevoEnvio() {
  abrirSheet('sheet-envio');
  lista.cargar(true);
  renderEnvio();
}

function agregarEnvio(id) {
  const p = lista.get(id) || (envio[id] && envio[id].p);
  if (!p) return;
  const actual = envio[id] ? envio[id].cantidad : 0;
  if (actual + 1 > p.existencia) { toast('Solo hay ' + numero(p.existencia) + ' en existencia.', 'error'); return; }
  envio[id] = { p: p, cantidad: actual + 1 };
  renderEnvio();
}

function fijarCantidadEnvio(id, valor) {
  const item = envio[id];
  if (!item) return;
  const n = parseFloat(valor);
  if (!(n > 0)) { delete envio[id]; }
  else if (n > item.p.existencia) { toast('Solo hay ' + numero(item.p.existencia) + ' en existencia.', 'error'); item.cantidad = item.p.existencia; }
  else { item.cantidad = n; }
  renderEnvio();
}

function renderEnvio() {
  const ids = Object.keys(envio);
  document.getElementById('envio-items').innerHTML = ids.map(function (id) {
    const it = envio[id];
    return '<div class="card !p-3 flex items-center gap-3"><div class="flex-1 min-w-0"><p class="text-sm truncate">' + esc(it.p.nombre) + '</p>' +
      '<p class="text-xs" style="color:var(--ink-soft)">Disponible ' + numero(it.p.existencia) + '</p></div>' +
      '<input type="number" inputmode="decimal" step="0.01" min="0" value="' + it.cantidad + '" onchange="fijarCantidadEnvio(' + id + ', this.value)" class="field-input" style="width:90px; text-align:right"></div>';
  }).join('');
}

document.getElementById('envio-buscar').addEventListener('input', debounce(function () { lista.cargar(true); }, 300));

async function confirmarEnvio() {
  const ids = Object.keys(envio);
  if (!ids.length) { toast('Agrega al menos un producto.', 'error'); return; }
  const boton = document.getElementById('btn-enviar');
  boton.disabled = true; boton.textContent = 'Enviando…';
  try {
    await api('/m/api/traslado', { method: 'POST', body: {
      destino_id: parseInt(document.getElementById('envio-destino').value, 10),
      notas: document.getElementById('envio-notas').value.trim(),
      items: ids.map(function (id) { return { producto_id: parseInt(id, 10), cantidad: envio[id].cantidad }; }),
    }});
    toast('Traslado enviado.');
    setTimeout(function () { location.reload(); }, 700);
  } catch (e) {
    toast(e.message, 'error');
    boton.disabled = false; boton.textContent = 'Enviar traslado';
  }
}

// ── Recepción / detalle ──────────────────────────────────────────────────────
async function cargarTraslado(id, recibir) {
  try {
    const t = await api('/m/api/traslado/' + id);
    trasladoActual = t;
    const editable = recibir && t.estado === 'en_transito';
    document.getElementById('rec-titulo').textContent = t.numero;
    document.getElementById('rec-ayuda').textContent = editable
      ? 'Cuenta lo que llegó. Si falta o se rompió algo, baja la cantidad: la diferencia queda registrada.'
      : 'Estado: ' + t.estado.replace('_', ' ');
    document.getElementById('rec-items').innerHTML = t.items.map(function (i) {
      const fin = i.cantidad_recibida !== null && i.cantidad_recibida !== undefined;
      const dif = fin && Number(i.cantidad_recibida) < Number(i.cantidad) - 0.001;
      return '<div class="card !p-3 flex items-center gap-3"><div class="flex-1 min-w-0"><p class="text-sm truncate">' + esc(i.nombre) + '</p>' +
        '<p class="text-xs" style="color:' + (dif ? 'var(--red)' : 'var(--ink-soft)') + '">Enviado ' + numero(i.cantidad) +
        (fin ? ' · recibido ' + numero(i.cantidad_recibida) : '') + '</p></div>' +
        (editable ? '<input type="number" inputmode="decimal" step="0.01" min="0" max="' + i.cantidad + '" value="' + i.cantidad + '" data-producto="' + i.producto_id + '" class="field-input rec-cantidad" style="width:90px; text-align:right">' : '') + '</div>';
    }).join('');
    document.getElementById('btn-recibir').style.display = editable ? 'block' : 'none';
    abrirSheet('sheet-recibir');
  } catch (e) { toast(e.message, 'error'); }
}
function abrirRecepcion(id) { cargarTraslado(id, true); }
function verTraslado(id) { cargarTraslado(id, false); }

async function confirmarRecepcion() {
  if (!trasladoActual) return;
  const recibidas = {};
  document.querySelectorAll('.rec-cantidad').forEach(function (el) { recibidas[el.dataset.producto] = parseFloat(el.value) || 0; });
  const boton = document.getElementById('btn-recibir');
  boton.disabled = true; boton.textContent = 'Guardando…';
  try {
    await api('/m/api/traslado/' + trasladoActual.traslado_id + '/recibir', { method: 'POST', body: { recibidas: recibidas } });
    toast('Mercancía recibida.');
    setTimeout(function () { location.reload(); }, 700);
  } catch (e) {
    toast(e.message, 'error');
    boton.disabled = false; boton.textContent = 'Confirmar recepción';
  }
}
</script>
<?php $this->endSection(); ?>

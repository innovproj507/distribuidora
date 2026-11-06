<?php
$this->extend('layouts/movil');
$edita = $abierto && $puedeContar;
?>
<?php $this->section('content'); ?>

<div class="card mb-3">
  <div class="flex items-center justify-between mb-2">
    <p class="text-xs" style="color:var(--ink-soft)"><?= esc($conteo['deposito_nombre']) ?> · <?= $abierto ? 'conteo a ciegas' : 'conteo cerrado' ?></p>
    <p class="text-xs font-mono"><span id="r-contados"><?= $resumen['contados'] ?></span>/<?= $resumen['total'] ?></p>
  </div>
  <div class="stockbar"><div id="r-barra" style="width:<?= $resumen['total'] ? round($resumen['contados'] / $resumen['total'] * 100) : 0 ?>%; background:var(--navy)"></div></div>
  <p class="text-xs mt-2" style="color:var(--ink-soft)"><span id="r-dif"><?= $resumen['faltantes'] + $resumen['sobrantes'] ?></span> con diferencia</p>
</div>

<?php if ($edita): ?>
<div class="card mb-3 !p-3">
  <label class="field-label">Estás contando en (la ubicación se anota en cada producto que cuentes)</label>
  <input id="ubicacion-actual" list="lista-ubicaciones" class="field-input" style="font-family:'Inter'" placeholder="Ej: Estante A2 (opcional)" autocomplete="off">
  <datalist id="lista-ubicaciones"><?php foreach ($ubicaciones as $u): ?><option value="<?= esc($u, 'attr') ?>"><?php endforeach; ?></datalist>
</div>
<?php endif; ?>

<div class="mb-3 flex items-center gap-2 bg-white rounded-xl border px-3 py-2" style="border-color:var(--line)">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6B7794" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
  <input type="search" id="input-busqueda" class="flex-1 text-sm outline-none bg-transparent" placeholder="Buscar o escanear código" autocomplete="off">
</div>
<div class="flex gap-2 mb-3 overflow-x-auto">
  <button type="button" class="chip activo" data-f="todos" onclick="filtrar('todos')">Todos</button>
  <button type="button" class="chip" data-f="pendientes" onclick="filtrar('pendientes')">Pendientes</button>
  <button type="button" class="chip" data-f="dif" onclick="filtrar('dif')">Con diferencia</button>
</div>

<div id="lista-conteo" class="flex flex-col gap-2" style="padding-bottom:<?= $edita ? '100' : '16' ?>px"></div>

<?php if ($abierto): ?>
<div class="fixed left-0 right-0 px-5 py-3" style="bottom:78px; background:white; border-top:1px solid var(--line); z-index:35">
  <button type="button" onclick="abrirCierre()" class="btn-primario">Revisar y cerrar conteo</button>
</div>
<?php endif; ?>

<!-- Sheet: revisión / cierre -->
<div id="sheet-cierre" class="sheet" style="transform:translateY(110%); max-height:94vh">
  <div class="sheet-handle"></div>
  <div class="flex items-center justify-between mb-3">
    <h2 class="font-head text-lg font-semibold"><?= $abierto ? 'Revisión del conteo' : 'Resultado' ?></h2>
    <button type="button" onclick="cerrarSheet('sheet-cierre')" class="w-8 h-8 rounded-full flex items-center justify-center" style="background:var(--navy-light); color:var(--navy)">✕</button>
  </div>
  <div class="grid grid-cols-3 gap-2 mb-3 text-center">
    <div class="card !p-2"><p class="font-mono text-lg font-semibold" id="c-falt">0</p><p class="text-[10px]" style="color:var(--ink-soft)">Faltantes</p></div>
    <div class="card !p-2"><p class="font-mono text-lg font-semibold" id="c-sobr">0</p><p class="text-[10px]" style="color:var(--ink-soft)">Sobrantes</p></div>
    <div class="card !p-2"><p class="font-mono text-lg font-semibold" id="c-impacto">$0</p><p class="text-[10px]" style="color:var(--ink-soft)">Impacto</p></div>
  </div>
  <p class="text-xs mb-2" id="c-pend" style="color:var(--orange)"></p>
  <div id="c-lista" class="flex flex-col gap-1 mb-4" style="max-height:34vh; overflow-y:auto"></div>
  <?php if ($abierto && $puedeAjustar): ?>
    <button type="button" id="btn-aplicar" onclick="aplicar()" class="btn-primario mb-2" style="background:var(--green)">Aplicar ajustes y cerrar</button>
  <?php elseif ($abierto): ?>
    <p class="text-xs mb-2" style="color:var(--ink-soft)">Un supervisor con permiso de reconciliar debe aplicar los ajustes.</p>
  <?php endif; ?>
  <?php if ($abierto && $puedeContar): ?>
    <button type="button" onclick="cancelar()" class="w-full py-3 rounded-xl font-medium" style="background:var(--red-light); color:var(--red)">Cancelar conteo</button>
  <?php endif; ?>
</div>

<?php $this->endSection(); ?>

<?php $this->section('extra_js'); ?>
<script>
const CONTEO_ID = <?= (int) $conteo['conteo_id'] ?>;
const ABIERTO   = <?= json_encode($abierto) ?>;
const EDITA     = <?= json_encode($edita) ?>;
const ITEMS     = <?= json_encode($items, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
let filtro = 'todos', consulta = '';

function difTexto(i) {
  if (i.diferencia === null) return '';
  const d = i.diferencia;
  return d === 0 ? 'Cuadra ✓' : (d > 0 ? 'Sobran ' : 'Faltan ') + numero(Math.abs(d)) + ' (sistema ' + numero(i.sistema) + ')';
}
function difColor(i) { return i.diferencia === 0 ? 'var(--green)' : 'var(--red)'; }

function visible(i) {
  if (filtro === 'pendientes' && i.estado !== 'pendiente') return false;
  if (filtro === 'dif' && !(i.diferencia !== null && i.diferencia !== 0)) return false;
  if (consulta) {
    const t = (i.nombre + ' ' + (i.codigo || '') + ' ' + (i.barras || '') + ' ' + (i.ubicacion || '')).toLowerCase();
    return t.indexOf(consulta) !== -1;
  }
  return true;
}

function pintar() {
  let html = '', ubic = null, n = 0;
  ITEMS.forEach(function (i) {
    if (!visible(i)) return;
    n++;
    if ((i.ubicacion || '') !== ubic) {
      ubic = i.ubicacion || '';
      html += '<p class="text-[11px] font-semibold mt-2" style="color:var(--navy)">📍 ' + (ubic ? esc(ubic) : 'Sin ubicación') + '</p>';
    }
    html += '<div class="card !p-3 flex items-center gap-3" id="p-' + i.id + '">' +
      '<div class="flex-1 min-w-0"><p class="text-sm font-medium truncate">' + esc(i.nombre) + '</p>' +
      '<p class="text-xs truncate" style="color:var(--ink-soft)">' + esc(i.codigo || '') + '</p>' +
      '<p class="text-[11px] font-medium" id="d-' + i.id + '" style="color:' + (i.diferencia === null ? 'var(--ink-soft)' : difColor(i)) + '">' + esc(difTexto(i)) + '</p></div>' +
      (EDITA
        ? '<input type="number" inputmode="decimal" step="any" min="0" data-id="' + i.id + '" value="' + (i.contada === null ? '' : i.contada) + '" placeholder="—" onchange="anotar(' + i.id + ', this)" onkeydown="siguiente(event)" class="field-input" style="width:84px; text-align:right; padding:8px">'
        : '<span class="font-mono text-sm font-semibold">' + (i.contada === null ? '—' : numero(i.contada)) + '</span>') +
    '</div>';
  });
  document.getElementById('lista-conteo').innerHTML = html || '<p class="text-sm text-center py-6" style="color:var(--ink-soft)">Nada que mostrar.</p>';
}

function filtrar(f) {
  filtro = f;
  document.querySelectorAll('[data-f]').forEach(function (b) { b.classList.toggle('activo', b.dataset.f === f); });
  pintar();
}

function actualizarResumen(r) {
  document.getElementById('r-contados').textContent = r.contados;
  document.getElementById('r-barra').style.width = (r.total ? Math.round(r.contados / r.total * 100) : 0) + '%';
  document.getElementById('r-dif').textContent = r.faltantes + r.sobrantes;
}

async function anotar(id, input) {
  const i = ITEMS.find(function (x) { return x.id === id; });
  const valor = input.value.trim() === '' ? null : parseFloat(input.value);
  const ub = (document.getElementById('ubicacion-actual') || {}).value;
  // El campo no se bloquea ni recupera el foco: así se puede seguir tecleando en el siguiente producto mientras guarda
  try {
    const r = await api('/m/api/conteo/' + CONTEO_ID + '/linea', { method: 'POST', body: { producto_id: id, cantidad: valor, ubicacion: ub ? ub.trim() : null } });
    i.contada = r.linea.cantidad_contada; i.estado = r.linea.estado;
    i.sistema = r.linea.cantidad_contada === null ? null : r.linea.cantidad_sistema;
    i.diferencia = r.linea.diferencia;
    if (ub && ub.trim() && valor !== null) i.ubicacion = ub.trim();
    const d = document.getElementById('d-' + id);
    d.textContent = difTexto(i); d.style.color = i.diferencia === null ? 'var(--ink-soft)' : difColor(i);
    actualizarResumen(r.resumen);
  } catch (e) { toast(e.message, 'error'); }
}

// Enter → siguiente producto de la lista (recorrer el estante sin tocar la pantalla)
function siguiente(ev) {
  if (ev.key !== 'Enter') return;
  ev.preventDefault();
  const campos = Array.prototype.slice.call(document.querySelectorAll('#lista-conteo input[data-id]'));
  const i = campos.indexOf(ev.target);
  ev.target.blur();
  if (campos[i + 1]) campos[i + 1].focus();
}

const busq = document.getElementById('input-busqueda');
busq.addEventListener('input', debounce(function () { consulta = busq.value.trim().toLowerCase(); pintar(); }, 200));
busq.addEventListener('keydown', function (e) {
  if (e.key !== 'Enter') return;
  e.preventDefault();
  const campos = document.querySelectorAll('#lista-conteo input[data-id]');
  if (campos.length === 1) { campos[0].focus(); campos[0].select(); busq.value = ''; consulta = ''; }  // escáner: un resultado → a contar
});

// ── Revisión / cierre ──
async function abrirCierre() {
  const con = ITEMS.filter(function (i) { return i.estado !== 'pendiente' && i.diferencia !== null && i.diferencia !== 0; });
  const faltantes = con.filter(function (i) { return i.diferencia < 0; }), sobrantes = con.filter(function (i) { return i.diferencia > 0; });
  const impacto = con.reduce(function (s, i) { return s + i.diferencia * i.costo; }, 0);
  const pend = ITEMS.filter(function (i) { return i.estado === 'pendiente'; }).length;
  document.getElementById('c-falt').textContent = faltantes.length;
  document.getElementById('c-sobr').textContent = sobrantes.length;
  document.getElementById('c-impacto').textContent = (impacto < 0 ? '−' : '') + dinero(Math.abs(impacto));
  document.getElementById('c-pend').textContent = ABIERTO && pend ? pend + ' producto(s) sin contar: NO se ajustan, quedan como están.' : '';
  document.getElementById('c-lista').innerHTML = con.sort(function (a, b) { return Math.abs(b.diferencia * b.costo) - Math.abs(a.diferencia * a.costo); }).map(function (i) {
    return '<div class="flex justify-between gap-2 text-xs rounded-lg px-3 py-2" style="background:white; border:1px solid var(--line)"><span class="truncate">' + esc(i.nombre) + '</span>' +
      '<span class="font-mono flex-shrink-0" style="color:' + difColor(i) + '">' + (i.diferencia > 0 ? '+' : '−') + numero(Math.abs(i.diferencia)) + ' · ' + (i.diferencia > 0 ? '+' : '−') + dinero(Math.abs(i.diferencia * i.costo)) + '</span></div>';
  }).join('') || '<p class="text-xs" style="color:var(--ink-soft)">Sin diferencias: todo cuadra.</p>';
  abrirSheet('sheet-cierre');
}

async function aplicar() {
  if (!confirm('Se ajustará el inventario a lo contado. ¿Continuar?')) return;
  const b = document.getElementById('btn-aplicar'); b.disabled = true; b.textContent = 'Aplicando…';
  try {
    const r = await api('/m/api/conteo/' + CONTEO_ID + '/aplicar', { method: 'POST', body: {} });
    toast('Ajustes aplicados (' + r.ajustadas + ').');
    setTimeout(function () { location.reload(); }, 800);
  } catch (e) { toast(e.message, 'error'); b.disabled = false; b.textContent = 'Aplicar ajustes y cerrar'; }
}

async function cancelar() {
  if (!confirm('¿Cancelar este conteo? No se ajusta nada.')) return;
  try { await api('/m/api/conteo/' + CONTEO_ID + '/cancelar', { method: 'POST', body: {} }); location.href = '/m/conteos'; }
  catch (e) { toast(e.message, 'error'); }
}

pintar();
<?php if (!$abierto): ?>abrirCierre();<?php endif; ?>
</script>
<?php $this->endSection(); ?>

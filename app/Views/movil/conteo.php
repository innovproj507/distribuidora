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
  <input type="search" id="input-busqueda" class="flex-1 text-sm outline-none bg-transparent" placeholder="Buscar nombre, código o código de barras" autocomplete="off">
  <button type="button" onclick="escanearConteo()" class="flex-shrink-0 px-3 py-1.5 rounded-lg text-sm font-medium text-white" style="background:var(--navy)" title="Escanear con la cámara">📷 Escanear</button>
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
    <?php if (ver_costos()): ?><div class="card !p-2"><p class="font-mono text-lg font-semibold" id="c-impacto">$0</p><p class="text-[10px]" style="color:var(--ink-soft)">Impacto</p></div><?php else: ?><div class="card !p-2"><p class="font-mono text-lg font-semibold" id="c-contados">0</p><p class="text-[10px]" style="color:var(--ink-soft)">Contados</p></div><?php endif; ?>
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

<!-- Sheet: contar por presentación (cajas, bandejas… + sueltos) -->
<div id="sheet-bulto" class="sheet" style="transform:translateY(110%)">
  <div class="sheet-handle"></div>
  <div class="flex items-center justify-between mb-1">
    <h2 class="font-head text-lg font-semibold truncate pr-2" id="bulto-nombre">Producto</h2>
    <button type="button" onclick="cerrarSheet('sheet-bulto')" class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0" style="background:var(--navy-light); color:var(--navy)">✕</button>
  </div>
  <p class="text-xs mb-3" style="color:var(--ink-soft)">Escribe cuántos hay de cada uno; el sistema suma el total en unidades.</p>
  <div id="bulto-campos" class="flex flex-col gap-2 mb-3"></div>
  <p class="font-mono text-sm font-semibold mb-3" id="bulto-total" style="color:var(--teal-dark)"></p>
  <button type="button" onclick="usarBulto()" class="btn-primario">Usar este total</button>
</div>

<?php $this->endSection(); ?>

<?php $this->section('extra_js'); ?>
<script src="/assets/js/escaner.js?v=1"></script>
<script>
const CONTEO_ID = <?= (int) $conteo['conteo_id'] ?>;
const ABIERTO   = <?= json_encode($abierto) ?>;
const EDITA     = <?= json_encode($edita) ?>;
const ITEMS     = <?= json_encode($items, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
const DEPOSITO  = <?= (int) $conteo['deposito_id'] ?>;
let filtro = 'todos', consulta = '';

function difTexto(i) {
  if (i.diferencia === null) return '';
  const d = i.diferencia;
  return d === 0 ? 'Cuadra ✓' : (d > 0 ? 'Sobran ' : 'Faltan ') + numero(Math.abs(d)) + ' (sistema ' + numero(i.sistema) + ')';
}
function difColor(i) { return i.diferencia === 0 ? 'var(--green)' : 'var(--red)'; }
// "= 10 bandejas" bajo lo contado (si el producto tiene presentaciones)
function textoBulto(i) {
  const t = i.contada !== null && i.contada !== undefined ? desglose(i.contada, i.pres, i.um) : '';
  return t ? '= ' + t : '';
}
let bultoId = null;
function abrirBulto(id) {
  const i = ITEMS.find(function (x) { return x.id === id; });
  bultoId = id;
  document.getElementById('bulto-nombre').textContent = i.nombre;
  const filas = i.pres.concat([{ nombre: 'Sueltos (' + (i.um || 'uds') + ')', factor: 1 }]);
  document.getElementById('bulto-campos').innerHTML = filas.map(function (p) {
    return '<label class="flex items-center gap-3"><span class="flex-1 text-sm">' + esc(p.nombre) + (p.factor > 1 ? ' <span class="text-xs" style="color:var(--ink-soft)">× ' + numero(p.factor) + '</span>' : '') + '</span>' +
      '<input type="number" inputmode="decimal" step="any" min="0" data-factor="' + p.factor + '" class="field-input bulto-campo" style="width:96px; text-align:right; padding:8px" placeholder="0" oninput="totalBulto()"></label>';
  }).join('');
  totalBulto();
  abrirSheet('sheet-bulto');
  setTimeout(function () { const c = document.querySelector('.bulto-campo'); if (c) c.focus(); }, 300);
}
function totalBulto() {
  let t = 0;
  document.querySelectorAll('.bulto-campo').forEach(function (el) { t += (parseFloat(el.value) || 0) * parseFloat(el.dataset.factor); });
  t = Math.round(t * 100) / 100;
  const i = ITEMS.find(function (x) { return x.id === bultoId; });
  document.getElementById('bulto-total').textContent = 'Total: ' + numero(t) + ' ' + ((i && i.um) || 'uds');
  return t;
}
function usarBulto() {
  const t = totalBulto();
  const campo = document.querySelector('#lista-conteo input[data-id="' + bultoId + '"]');
  cerrarSheet('sheet-bulto');
  if (!campo) return;
  campo.value = t;
  anotar(bultoId, campo);
}
function venceColor(v) { return !v ? 'var(--ink-soft)' : (v.nivel === 'vencido' ? 'var(--red)' : (v.nivel === 'pronto' ? 'var(--orange)' : 'var(--ink-soft)')); }
// Línea de vencimiento: editable (fecha) mientras el conteo está abierto, solo texto si no
function venceHtml(i) {
  if (EDITA) {
    return '<label class="flex items-center gap-1 mt-1 text-[11px]" style="color:' + venceColor(i.venceInfo) + '"><span id="v-' + i.id + '">⏳ ' + (i.venceInfo ? esc(i.venceInfo.texto) : 'Vence:') + '</span>' +
      '<input type="date" value="' + esc(i.vence || '') + '" onchange="guardarVence(' + i.id + ', this.value)" class="border rounded-md px-1" style="border-color:var(--line); font-size:11px; max-width:7.5rem"></label>';
  }
  return i.venceInfo ? '<p class="text-[11px]" style="color:' + venceColor(i.venceInfo) + '">⏳ ' + esc(i.venceInfo.texto) + '</p>' : '';
}
async function guardarVence(id, fecha) {
  const i = ITEMS.find(function (x) { return x.id === id; });
  try {
    const r = await api('/m/api/vencimiento', { method: 'POST', body: { producto_id: id, fecha: fecha, deposito_id: DEPOSITO } });
    i.vence = r.fecha; i.venceInfo = r.estado;
    const s = document.getElementById('v-' + id);
    s.textContent = '⏳ ' + (r.estado ? r.estado.texto : 'Vence:');
    s.parentNode.style.color = venceColor(r.estado);
  } catch (e) { toast(e.message, 'error'); }
}

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
      '<p class="text-xs truncate" style="color:var(--ink-soft)">' + esc(i.codigo || '') + (i.barras ? ' · ▮▯▮ ' + esc(i.barras) : '') + '</p>' +
      '<p class="text-[11px] font-medium" id="d-' + i.id + '" style="color:' + (i.diferencia === null ? 'var(--ink-soft)' : difColor(i)) + '">' + esc(difTexto(i)) + '</p>' +
      '<p class="text-[11px]" id="x-' + i.id + '" style="color:var(--navy)">' + esc(textoBulto(i)) + '</p>' +
      (EDITA && i.pres && i.pres.length ? '<button type="button" onclick="abrirBulto(' + i.id + ')" class="text-[11px] font-semibold mt-1 px-2 py-1 rounded-lg" style="background:var(--navy-light); color:var(--navy)">📦 Contar por ' + esc(i.pres.map(function (p) { return p.nombre.toLowerCase(); }).join(' / ')) + '</button>' : '') +
      venceHtml(i) + '</div>' +
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
    const x = document.getElementById('x-' + id);
    if (x) x.textContent = textoBulto(i);
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

// ── Escanear con la cámara ──
function escanearConteo() {
  Escaner.abrir(function (codigo) {
    const c = codigo.toLowerCase();
    const it = ITEMS.find(function (i) { return (i.barras || '').toLowerCase() === c || (i.codigo || '').toLowerCase() === c; });
    if (!it) {
      busq.value = codigo; consulta = c; pintar();
      toast('El código ' + codigo + ' no está en este conteo.', 'error');
      <?php if (\App\Libraries\Auth::can('productos.crear')): ?>
      if (confirm('¿Crear un producto nuevo con el código de barras ' + codigo + '?')) location.href = '/m/productos/nuevo?barras=' + encodeURIComponent(codigo);
      <?php endif; ?>
      return;
    }
    // Deja solo ese producto en la lista y pone el cursor para escribir lo contado
    filtrar('todos');
    busq.value = codigo; consulta = c; pintar();
    const campo = document.querySelector('#lista-conteo input[data-id="' + it.id + '"]');
    const fila = document.getElementById('p-' + it.id);
    if (fila) fila.scrollIntoView({ block: 'center' });
    if (campo) { campo.focus(); campo.select(); }
  });
}

// ── Revisión / cierre ──
async function abrirCierre() {
  const con = ITEMS.filter(function (i) { return i.estado !== 'pendiente' && i.diferencia !== null && i.diferencia !== 0; });
  const faltantes = con.filter(function (i) { return i.diferencia < 0; }), sobrantes = con.filter(function (i) { return i.diferencia > 0; });
  const impacto = con.reduce(function (s, i) { return s + i.diferencia * i.costo; }, 0);
  const pend = ITEMS.filter(function (i) { return i.estado === 'pendiente'; }).length;
  document.getElementById('c-falt').textContent = faltantes.length;
  document.getElementById('c-sobr').textContent = sobrantes.length;
  if (document.getElementById('c-contados')) document.getElementById('c-contados').textContent = ITEMS.filter(function (i) { return i.estado !== 'pendiente'; }).length;
  if (document.getElementById('c-impacto')) document.getElementById('c-impacto').textContent = (impacto < 0 ? '−' : '') + dinero(Math.abs(impacto));
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

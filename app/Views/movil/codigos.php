<?php
$this->extend('layouts/movil');
$filtros = ['sin' => 'Sin código', 'interno' => 'Internos', 'todos' => 'Todos'];
?>
<?php $this->section('content'); ?>

<div class="stat-card stat-navy mb-3">
  <p class="text-xs text-white/80">Productos sin código de barras</p>
  <p class="font-mono text-2xl font-semibold"><?= (int) $sinCodigo ?></p>
  <p class="text-[11px] text-white/80 mt-1">Escanea el código del empaque (cámara o pistola). Lo que no trae código: <b>Interno</b>.</p>
</div>

<div class="flex gap-2 mb-3 overflow-x-auto">
  <?php foreach ($filtros as $k => $t): ?>
    <a href="?ver=<?= $k ?>&q=<?= urlencode($q) ?>" class="chip <?= $filtro === $k ? 'chip-activo' : '' ?>" style="<?= $filtro === $k ? 'background:var(--navy); color:#fff' : 'background:#fff; color:var(--ink)' ?>; padding:6px 12px; border-radius:999px; font-size:13px; white-space:nowrap; border:1px solid var(--line)"><?= $t ?></a>
  <?php endforeach; ?>
</div>

<form method="get" class="mb-3 flex items-center gap-2 bg-white rounded-xl border px-3 py-2" style="border-color:var(--line)">
  <input type="hidden" name="ver" value="<?= esc($filtro) ?>">
  <input type="search" name="q" value="<?= esc($q) ?>" class="flex-1 text-sm outline-none bg-transparent" placeholder="Buscar producto, código o referencia" autocomplete="off">
</form>

<button type="button" onclick="modoEscaneo()" class="btn-primario mb-3">📷 Empezar a escanear (uno tras otro)</button>

<div id="lista" class="flex flex-col gap-2 pb-4"></div>

<!-- Hoja: códigos de un producto -->
<div id="sheet-cod" class="sheet" style="transform:translateY(110%)">
  <div class="sheet-handle"></div>
  <div class="flex items-start justify-between mb-1 gap-2">
    <h2 class="font-head text-base font-semibold" id="c-nombre">Producto</h2>
    <button type="button" onclick="cerrarSheet('sheet-cod')" class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0" style="background:var(--navy-light); color:var(--navy)">✕</button>
  </div>
  <p class="text-xs mb-3 font-mono" id="c-codigo" style="color:var(--ink-soft)"></p>

  <label class="field-label">Código de barras</label>
  <div class="flex gap-2 mb-1">
    <input type="text" id="c-barras" class="field-input flex-1 min-w-0" inputmode="numeric" autocomplete="off" placeholder="Escanea o escribe" onkeydown="if(event.key==='Enter'){event.preventDefault(); guardarYSiguiente();}">
    <button type="button" onclick="escanearA('c-barras')" class="w-12 rounded-xl text-lg flex-shrink-0" style="background:var(--navy-light)">📷</button>
  </div>
  <div class="flex items-center justify-between mb-3">
    <span class="text-[11px]" id="c-tipo" style="color:var(--ink-soft)"></span>
    <button type="button" id="c-interno" onclick="document.getElementById('c-barras').value='interno'; guardarYSiguiente()" class="text-xs font-semibold px-3 py-1.5 rounded-lg" style="background:var(--navy-light); color:var(--navy)">No trae código → Interno</button>
  </div>

  <label class="field-label">Referencia del proveedor</label>
  <input type="text" id="c-ref" class="field-input mb-3" style="font-family:'Inter'" maxlength="100" autocomplete="off" placeholder="Código del producto en la factura del proveedor">

  <div id="c-pres"></div>

  <p class="text-xs font-medium mb-2" id="c-error" style="color:var(--red)"></p>
  <button type="button" onclick="guardarYSiguiente()" class="btn-primario mb-2">Guardar y siguiente →</button>
  <div class="flex gap-2">
    <button type="button" onclick="guardarActual(false)" class="flex-1 py-3 rounded-xl text-sm font-medium" style="background:var(--navy-light); color:var(--navy)">Guardar</button>
    <button type="button" onclick="etiquetaActual()" class="flex-1 py-3 rounded-xl text-sm font-medium" style="background:var(--navy-light); color:var(--navy)">🏷️ Etiqueta</button>
  </div>
</div>

<?php $this->endSection(); ?>

<?php $this->section('extra_js'); ?>
<script src="/assets/js/escaner.js?v=1"></script>
<script src="/assets/js/lector-teclado.js?v=2"></script>
<script src="/assets/js/ticket.js?v=6"></script>
<script>
const PRODUCTOS = <?= json_encode($productos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
let actual = -1;           // índice del producto abierto en la hoja
let enModoEscaneo = false; // "uno tras otro": al guardar abre el siguiente sin código

function esInterno(c) { return /^20[01]\d{10}$/.test(c || ''); }
function falta(p) { return !p.codigo_barras || p.unidades.some(function (u) { return !u.codigo_barras; }); }

function pintarLista() {
  const cont = document.getElementById('lista');
  if (!PRODUCTOS.length) { cont.innerHTML = '<p class="text-sm text-center mt-4" style="color:var(--ink-soft)">No hay productos en esta lista. 🎉</p>'; return; }
  cont.innerHTML = PRODUCTOS.map(function (p, i) {
    const etiqueta = p.codigo_barras
      ? (esInterno(p.codigo_barras) ? '<span class="text-[10px] px-1.5 py-0.5 rounded" style="background:var(--navy-light); color:var(--navy)">Interno</span>' : '<span class="text-[10px] px-1.5 py-0.5 rounded" style="background:var(--green-light); color:var(--green)">✓ Código</span>')
      : '<span class="text-[10px] px-1.5 py-0.5 rounded" style="background:var(--orange-light); color:var(--orange)">Sin código</span>';
    const presFaltan = p.unidades.filter(function (u) { return !u.codigo_barras; }).length;
    return '<button type="button" onclick="abrir(' + i + ')" class="card !p-3 text-left flex items-center gap-3">' +
      '<div class="flex-1 min-w-0"><p class="text-sm font-medium truncate">' + esc(p.nombre) + '</p>' +
      '<p class="text-xs font-mono truncate" style="color:var(--ink-soft)">' + esc(p.codigo) + (p.codigo_barras ? ' · ' + esc(p.codigo_barras) : '') + (p.supplier_part_no ? ' · Prov: ' + esc(p.supplier_part_no) : '') + '</p>' +
      (presFaltan ? '<p class="text-[11px]" style="color:var(--orange)">' + presFaltan + ' presentación(es) sin código</p>' : '') +
      '</div><div class="flex-shrink-0">' + etiqueta + '</div></button>';
  }).join('');
}

function abrir(i) {
  actual = i;
  const p = PRODUCTOS[i];
  document.getElementById('c-nombre').textContent = p.nombre;
  document.getElementById('c-codigo').textContent = p.codigo + (p.categoria ? ' · ' + p.categoria : '');
  document.getElementById('c-barras').value = p.codigo_barras || '';
  document.getElementById('c-ref').value = p.supplier_part_no || '';
  document.getElementById('c-error').textContent = '';
  document.getElementById('c-tipo').textContent = p.codigo_barras ? (esInterno(p.codigo_barras) ? 'Código interno' : 'Código del fabricante') : 'Sin código todavía';
  document.getElementById('c-interno').style.display = p.codigo_barras ? 'none' : '';
  document.getElementById('c-pres').innerHTML = p.unidades.map(function (u) {
    return '<label class="field-label">' + esc(u.nombre) + ' (×' + numero(u.factor) + ') — código de la caja</label>' +
      '<div class="flex gap-2 mb-3"><input type="text" class="field-input flex-1 min-w-0 c-uni" data-uid="' + u.unidad_id + '" inputmode="numeric" autocomplete="off" value="' + esc(u.codigo_barras || '') + '" placeholder="Escanea o deja vacío">' +
      '<button type="button" onclick="escanearA(null, ' + u.unidad_id + ')" class="w-12 rounded-xl text-lg flex-shrink-0" style="background:var(--navy-light)">📷</button>' +
      '<button type="button" onclick="this.previousElementSibling.previousElementSibling.value=\'interno\'" class="px-2 rounded-xl text-[11px] font-semibold flex-shrink-0" style="background:var(--navy-light); color:var(--navy)">Interno</button></div>';
  }).join('');
  abrirSheet('sheet-cod');
  setTimeout(function () { const el = document.getElementById('c-barras'); el.focus(); el.select(); }, 250);
}

function escanearA(idCampo, unidadId) {
  Escaner.abrir(function (codigo) {
    const el = idCampo ? document.getElementById(idCampo) : document.querySelector('.c-uni[data-uid="' + unidadId + '"]');
    if (el) el.value = codigo.trim();
    if (idCampo === 'c-barras') guardarYSiguiente();
  });
}

async function guardarActual(siguiente) {
  if (actual < 0) return false;
  const p = PRODUCTOS[actual];
  const unidades = {};
  document.querySelectorAll('.c-uni').forEach(function (el) {
    const u = p.unidades.find(function (x) { return x.unidad_id === Number(el.dataset.uid); });
    if (u && el.value.trim() !== (u.codigo_barras || '')) unidades[el.dataset.uid] = el.value.trim();
  });
  const cuerpo = { codigo_barras: document.getElementById('c-barras').value.trim(), supplier_part_no: document.getElementById('c-ref').value.trim() };
  if (Object.keys(unidades).length) cuerpo.unidades = unidades;
  try {
    const d = await api('/api/codigos/' + p.producto_id, { method: 'POST', body: cuerpo });
    p.codigo_barras = d.codigo_barras || '';
    p.supplier_part_no = d.supplier_part_no || '';
    p.unidades.forEach(function (u) { if (d.unidades && d.unidades[u.unidad_id] !== undefined) u.codigo_barras = d.unidades[u.unidad_id]; });
    toast('Guardado: ' + (p.codigo_barras || 'sin código'));
    pintarLista();
    if (siguiente) {
      const k = PRODUCTOS.findIndex(function (x, j) { return j > actual && falta(x); });
      if (k >= 0) abrir(k); else { cerrarSheet('sheet-cod'); toast('¡No quedan productos sin código en esta lista!'); }
    } else {
      abrir(actual);
    }
    return true;
  } catch (e) {
    document.getElementById('c-error').textContent = e.message;
    return false;
  }
}

function guardarYSiguiente() { return guardarActual(true); }

function modoEscaneo() {
  enModoEscaneo = true;
  const k = PRODUCTOS.findIndex(falta);
  if (k < 0) { toast('No hay productos sin código en esta lista'); return; }
  abrir(k);
}

function etiquetaActual() {
  if (actual < 0) return;
  TicketPOS.imprimirEtiquetasDe('p=' + PRODUCTOS[actual].producto_id + ':1');
}

// Pistola Bluetooth sin el cursor en un campo: va al código del producto abierto
LectorTeclado.escuchar(function (codigo) {
  if (actual < 0 || document.getElementById('sheet-cod').style.transform.indexOf('110%') !== -1) {
    const k = PRODUCTOS.findIndex(falta);
    if (k < 0) return;
    abrir(k);
  }
  document.getElementById('c-barras').value = codigo.trim();
  guardarYSiguiente();
});

pintarLista();
</script>
<?php $this->endSection(); ?>

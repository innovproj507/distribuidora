<?php
$this->extend('layouts/movil');

$tiendas = $plan['tiendas'];
$items   = $plan['items'];
$hubDep  = $tiendas ? (int) $tiendas[0]['deposito_id'] : 0;

$itemsJs = array_map(fn ($it) => [
    'id'        => $it['producto_id'],
    'nombre'    => $it['nombre'],
    'codigo'    => $it['codigo'],
    'proveedor' => $it['proveedor_nombre'],
    'costo'     => $it['costo'],
    'tiendas'   => (object) $it['tiendas'],
    'pres'      => $it['presentaciones'] ?? [],
], $items);
$tiendasJs = array_map(fn ($t) => ['deposito_id' => (int) $t['deposito_id'], 'nombre' => $t['sucursal']], $tiendas);
?>
<?php $this->section('content'); ?>

<div class="card mb-3">
  <p class="text-xs mb-2" style="color:var(--ink-soft)">Cubrir cada tienda por</p>
  <div class="flex gap-2 overflow-x-auto">
    <?php foreach ([3, 5, 7, 10, 15, 30] as $d): ?>
      <a href="/m/pedido?dias=<?= $d ?>" class="chip <?= $d === $dias ? 'activo' : '' ?>"><?= $d ?> días</a>
    <?php endforeach; ?>
  </div>
  <p class="text-[11px] mt-2" style="color:var(--ink-soft)">Se calcula con las ventas de los últimos 30 días y el stock mínimo de cada producto.</p>
</div>

<?php if (count($tiendas) < 2): ?>
  <p class="card text-sm" style="color:var(--ink-soft)">Solo hay una tienda activa; se pedirá solo para ella.</p>
<?php endif; ?>

<?php if (empty($items)): ?>
  <p class="text-sm text-center mt-8" style="color:var(--ink-soft)">Nada que pedir: el stock cubre los próximos <?= $dias ?> días. 🎉<br>
  <span class="text-xs">(Define "stock mínimo" por producto o registra ventas para que el sistema pueda sugerir.)</span></p>
<?php else: ?>

<div class="stat-card stat-teal mb-4">
  <div class="flex items-center justify-between">
    <div>
      <p class="text-xs text-white/80">Total estimado del pedido</p>
      <p class="font-mono text-2xl font-semibold" id="pedido-total">$0.00</p>
    </div>
    <p class="text-xs text-white/80 text-right"><span id="pedido-lineas">0</span> producto(s)<br><span id="pedido-prov">0</span> proveedor(es)</p>
  </div>
</div>

<div id="lista-pedido" class="flex flex-col gap-2" style="padding-bottom:90px"></div>

<div class="fixed left-0 right-0 px-5 py-3" style="bottom:78px; background:white; border-top:1px solid var(--line); z-index:35">
  <button type="button" id="btn-pedido" onclick="crearPedido()" class="btn-primario" style="background:var(--teal)">Crear órdenes de compra</button>
</div>

<?php endif; ?>

<!-- Sheet: resultado -->
<div id="sheet-ok" class="sheet text-center" style="transform:translateY(110%)">
  <div class="sheet-handle"></div>
  <div class="w-16 h-16 rounded-full mx-auto mb-3 flex items-center justify-center text-3xl" style="background:var(--green-light)">✓</div>
  <h2 class="font-head text-lg font-semibold mb-3">Órdenes creadas</h2>
  <div id="ok-lista" class="flex flex-col gap-2 mb-4 text-left"></div>
  <a href="/m/compras" class="btn-primario block" style="padding:12px">Ver compras</a>
</div>

<?php $this->endSection(); ?>

<?php $this->section('extra_js'); ?>
<script>
const TIENDAS = <?= json_encode($tiendasJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
const ITEMS   = <?= json_encode($itemsJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
const HUB     = <?= (int) $hubDep ?>;

// Por producto: incluir (entra al pedido), factor (unidades por bulto; 1 = unidad) y cant por tienda EN ESA PRESENTACIÓN.
// Al elegir "Caja ×24" lo que falta se redondea hacia arriba a cajas completas; el servidor recibe unidades base.
const estado = {};
function necesidadBase(it, dep) { return it.tiendas[dep] ? it.tiendas[dep].necesita : 0; }
function calcularCant(it, factor) {
  const c = {};
  TIENDAS.forEach(function (t) { c[t.deposito_id] = Math.ceil(necesidadBase(it, t.deposito_id) / factor - 0.0001); });
  return c;
}
ITEMS.forEach(function (it) {
  // Por defecto el bulto más grande, pero se puede cambiar (el mismo producto a veces viene en otra presentación)
  const factor = it.pres.length ? it.pres[it.pres.length - 1].factor : 1;
  estado[it.id] = { incluir: true, factor: factor, cant: calcularCant(it, factor) };
});

function cambiarPresentacion(id, factor) {
  const it = ITEMS.find(function (x) { return x.id === id; });
  estado[id].factor = parseFloat(factor);
  estado[id].cant = calcularCant(it, estado[id].factor);
  render();
}

// Total de la línea en unidades base
function totalLinea(id) {
  const e = estado[id];
  return e.factor * Object.keys(e.cant).reduce(function (s, k) { return s + (parseFloat(e.cant[k]) || 0); }, 0);
}
function nombreBulto(it, factor) {
  const p = it.pres.find(function (x) { return x.factor === factor; });
  return p ? p.nombre.toLowerCase() + 's' : 'uds';
}

function render() {
  let html = '', proveedor = null;
  ITEMS.forEach(function (it) {
    if (it.proveedor !== proveedor) {
      proveedor = it.proveedor;
      html += '<h3 class="font-head text-sm font-semibold mt-3 mb-1">' + esc(proveedor) + '</h3>';
    }
    const e = estado[it.id];
    html += '<div class="card !p-3' + (e.incluir ? '' : ' opacity-50') + '">' +
      '<div class="flex items-start gap-3 mb-2">' +
        '<input type="checkbox" ' + (e.incluir ? 'checked' : '') + ' onchange="alternar(' + it.id + ')" class="mt-1" style="width:18px;height:18px">' +
        '<div class="flex-1 min-w-0"><p class="text-sm font-medium">' + esc(it.nombre) + '</p>' +
        '<p class="text-xs" style="color:var(--ink-soft)">' + esc(it.codigo || '') + ' · costo ' + dinero(it.costo) + ' c/u</p></div>' +
        '<p class="font-mono text-sm font-semibold text-right"><span id="tot-' + it.id + '">' + numero(totalLinea(it.id)) + '</span><span class="block text-[10px] font-normal" style="color:var(--ink-soft)">uds</span></p>' +
      '</div>';
    if (it.pres.length) {
      html += '<select onchange="cambiarPresentacion(' + it.id + ', this.value)" class="field-input mb-2" style="font-family:\'Inter\'; padding:6px 8px" ' + (e.incluir ? '' : 'disabled') + '>' +
        '<option value="1"' + (e.factor === 1 ? ' selected' : '') + '>Pedir por unidad</option>' +
        it.pres.map(function (p) { return '<option value="' + p.factor + '"' + (e.factor === p.factor ? ' selected' : '') + '>Pedir por ' + esc(p.nombre.toLowerCase()) + ' (trae ' + numero(p.factor) + ')</option>'; }).join('') +
      '</select>';
    }
    html += '<div class="grid gap-2" style="grid-template-columns:repeat(' + TIENDAS.length + ', 1fr)">';
    TIENDAS.forEach(function (t) {
      const d = it.tiendas[t.deposito_id] || { existencia: 0, minimo: 0, demanda: 0 };
      html += '<div><p class="text-[10px] truncate" style="color:var(--ink-soft)">' + esc(t.nombre.replace(/^Tienda /, '')) + ' · hay ' + numero(d.existencia) + (d.demanda ? ' · ' + numero(d.demanda) + '/día' : '') + '</p>' +
        '<div class="flex items-center gap-1"><input type="number" inputmode="decimal" min="0" step="1" value="' + e.cant[t.deposito_id] + '" ' + (e.incluir ? '' : 'disabled') +
        ' onchange="cambiar(' + it.id + ',' + t.deposito_id + ', this.value)" class="field-input" style="padding:6px 8px">' +
        '<span class="text-[10px] flex-shrink-0" style="color:var(--ink-soft)">' + esc(e.factor > 1 ? nombreBulto(it, e.factor) : 'uds') + '</span></div></div>';
    });
    html += '</div></div>';
  });
  document.getElementById('lista-pedido').innerHTML = html;
  resumen();
}

function alternar(id) { estado[id].incluir = !estado[id].incluir; render(); }
function cambiar(id, dep, v) {
  estado[id].cant[dep] = Math.max(0, parseFloat(v) || 0);
  document.getElementById('tot-' + id).textContent = numero(totalLinea(id));
  resumen();
}

function resumen() {
  let total = 0, lineas = 0; const provs = {};
  ITEMS.forEach(function (it) {
    const e = estado[it.id], q = totalLinea(it.id);
    if (!e.incluir || q <= 0) return;
    lineas++; provs[it.proveedor] = 1; total += q * it.costo;
  });
  document.getElementById('pedido-total').textContent = dinero(total);
  document.getElementById('pedido-lineas').textContent = lineas;
  document.getElementById('pedido-prov').textContent = Object.keys(provs).length;
}

async function crearPedido() {
  const lineas = ITEMS.filter(function (it) { return estado[it.id].incluir && totalLinea(it.id) > 0; })
    .map(function (it) {
      // El servidor trabaja en unidades base: bultos × unidades por bulto
      const base = {};
      Object.keys(estado[it.id].cant).forEach(function (dep) { base[dep] = (parseFloat(estado[it.id].cant[dep]) || 0) * estado[it.id].factor; });
      return { producto_id: it.id, cantidades: base };
    });
  if (!lineas.length) { toast('No hay nada que pedir.', 'error'); return; }
  const boton = document.getElementById('btn-pedido');
  boton.disabled = true; boton.textContent = 'Creando…';
  try {
    const res = await api('/m/api/pedido', { method: 'POST', body: { lineas: lineas } });
    document.getElementById('ok-lista').innerHTML = res.ordenes.map(function (o) {
      return '<a href="/m/compras/' + o.compra_id + '" class="card !p-3 flex justify-between"><span class="text-sm">' + esc(o.proveedor) +
        ' <span class="text-xs" style="color:var(--ink-soft)">· ' + o.lineas + ' línea(s)</span></span><span class="font-mono text-sm font-semibold">' + dinero(o.total) + '</span></a>';
    }).join('');
    abrirSheet('sheet-ok');
  } catch (e) {
    toast(e.message, 'error');
  } finally {
    boton.disabled = false; boton.textContent = 'Crear órdenes de compra';
  }
}

render();
</script>
<?php $this->endSection(); ?>

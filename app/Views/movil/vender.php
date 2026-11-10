<?php
$this->extend('layouts/movil');

$clientesJs = array_map(fn ($c) => [
    'id'         => (int) $c['cliente_id'],
    'nombre'     => $c['nombre'],
    'disponible' => max(0, (float) $c['limite_credito'] - (float) $c['saldo_pendiente']),
    'credito'    => (float) $c['limite_credito'] > 0,
], $clientes);
$puedeDescontar = $maxDescuentoPct > 0;
?>
<?php $this->section('content'); ?>

<?php if (!empty($exigir_caja) && empty($caja_actual)): ?>
<a href="/m/caja" class="card mb-3 flex items-center gap-3" style="border:1.5px solid var(--orange); background:var(--orange-light)">
  <span class="text-2xl">🧮</span>
  <span class="flex-1">
    <span class="block text-sm font-semibold" style="color:var(--orange)">Abre tu caja para poder vender</span>
    <span class="block text-xs" style="color:var(--ink-soft)">Toca aquí y escribe el efectivo inicial.</span>
  </span>
</a>
<?php endif; ?>

<div class="flex items-center gap-2 mb-2">
  <div class="flex-1 flex items-center gap-2 bg-white rounded-xl border px-3 py-2" style="border-color:var(--line)">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6B7794" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
    <input type="search" id="input-busqueda" class="flex-1 text-sm outline-none bg-transparent" placeholder="Buscar producto o código" autocomplete="off">
  </div>
  <button type="button" onclick="escanear()" class="w-11 h-11 rounded-xl flex-shrink-0 flex items-center justify-center text-white" style="background:var(--navy)" title="Escanear código de barras">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 8v8M10 8v8M13 8v8M16 8v8"/></svg>
  </button>
</div>

<div class="flex gap-2 overflow-x-auto pb-3" id="chips-pos">
  <button type="button" id="chip-espera" onclick="abrirEspera()" class="chip whitespace-nowrap" style="display:none; background:var(--orange-light); color:var(--orange); border-color:var(--orange)">⏸ En espera · <span id="espera-n">0</span></button>
  <button type="button" class="chip activo whitespace-nowrap" data-categoria="" onclick="filtrarCategoria('')">⭐ Más vendidos</button>
  <?php foreach ($categorias as $c): ?>
    <button type="button" class="chip whitespace-nowrap" data-categoria="<?= (int) $c['categoria_id'] ?>" onclick="filtrarCategoria('<?= (int) $c['categoria_id'] ?>')"><?= esc($c['nombre']) ?></button>
  <?php endforeach; ?>
</div>

<div class="flex flex-col gap-2.5" id="lista-productos" style="padding-bottom:110px"></div>

<!-- Barra fija con el resumen del carrito -->
<div id="carrito-barra" class="fixed left-0 right-0 px-5 py-3" style="bottom:78px; background:var(--navy-dark); z-index:35; display:none">
  <button type="button" onclick="abrirCarrito()" class="w-full flex items-center justify-between text-white">
    <span class="text-sm font-medium"><span id="carrito-cantidad">0</span> artículo(s)</span>
    <span class="font-mono text-sm font-semibold">Ver carrito · <span id="carrito-total">$0.00</span></span>
  </button>
</div>

<!-- Sheet: carrito y cobro -->
<div id="sheet-carrito" class="sheet" style="transform:translateY(110%)">
  <div class="sheet-handle"></div>
  <div class="flex items-center justify-between mb-3">
    <h2 class="font-head text-lg font-semibold">Carrito</h2>
    <div class="flex items-center gap-2">
      <button type="button" onclick="ponerEnEspera()" class="text-xs px-3 py-1.5 rounded-full font-medium" style="background:var(--orange-light); color:var(--orange)">⏸ Poner en espera</button>
      <button type="button" onclick="cerrarSheet('sheet-carrito')" class="w-8 h-8 rounded-full flex items-center justify-center" style="background:var(--navy-light); color:var(--navy)">✕</button>
    </div>
  </div>

  <div id="carrito-items" class="flex flex-col gap-2 overflow-y-auto mb-3" style="max-height:28vh"></div>
  <div id="carrito-vacio" class="text-sm text-center py-4" style="color:var(--ink-soft)">Todavía no agregas productos.</div>

  <label class="field-label">Cliente</label>
  <div class="flex gap-2 mb-1">
    <select id="carrito-cliente" class="field-input" style="font-family:'Inter'" onchange="cambiarCliente(); guardarCarrito()">
      <option value="">Consumidor final</option>
      <?php foreach ($clientes as $c): ?>
        <option value="<?= (int) $c['cliente_id'] ?>"><?= esc($c['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($puedeCrearCliente): ?>
      <button type="button" onclick="abrirSheet('sheet-cliente')" class="w-11 rounded-xl text-white text-xl flex-shrink-0" style="background:var(--navy)" title="Nuevo cliente">+</button>
    <?php endif; ?>
  </div>
  <p id="cliente-credito" class="text-xs mb-3" style="color:var(--ink-soft)"></p>

  <?php if ($puedeDescontar): ?>
  <label class="field-label">Descuento al total <span class="font-normal" style="color:var(--ink-soft)">(máx. <?= (int) $maxDescuentoPct ?>%)</span></label>
  <div class="flex gap-2 mb-3">
    <input type="number" inputmode="decimal" step="0.01" min="0" id="desc-valor" class="field-input flex-1" placeholder="0" oninput="renderTotales()">
    <div class="flex rounded-xl overflow-hidden border flex-shrink-0" style="border-color:var(--line)">
      <button type="button" id="desc-pct" onclick="tipoDescuento('%')" class="w-11 text-sm font-semibold">%</button>
      <button type="button" id="desc-monto" onclick="tipoDescuento('$')" class="w-11 text-sm font-semibold">$</button>
    </div>
  </div>
  <?php endif; ?>

  <label class="field-label">Forma de pago</label>
  <select id="forma-pago" class="field-input mb-3" style="font-family:'Inter'" onchange="elegirForma(this.value)">
    <option value="EFECTIVO">💵 Efectivo</option>
    <option value="YAPPY">📱 Yappy</option>
    <option value="TRANSFERENCIA">🏦 Transferencia</option>
    <option value="TARJETA">💳 Tarjeta</option>
    <option value="CREDITO">📒 Crédito (fiado)</option>
    <option value="MIXTO">➗ Mixto (varias formas)</option>
  </select>

  <div id="bloque-efectivo" class="mb-3">
    <div class="flex items-end gap-3">
      <div class="flex-1">
        <label class="field-label">Recibido (opcional)</label>
        <input type="number" inputmode="decimal" step="0.01" min="0" id="monto-recibido" class="field-input" placeholder="0.00" oninput="renderTotales()">
      </div>
      <div class="flex-1 text-right pb-2">
        <span class="text-xs" style="color:var(--ink-soft)">Vuelto</span>
        <p class="font-mono font-semibold" id="monto-vuelto">$0.00</p>
      </div>
    </div>
    <div id="billetes" class="flex flex-wrap gap-1.5 mt-2"></div>
  </div>

  <div id="bloque-mixto" class="mb-3" style="display:none">
    <p class="text-[11px] mb-2" style="color:var(--ink-soft)">Reparte el total entre varias formas. El vuelto sale del efectivo.</p>
    <div class="grid grid-cols-2 gap-2">
      <?php foreach (['EFECTIVO' => 'Efectivo', 'YAPPY' => 'Yappy', 'TRANSFERENCIA' => 'Transferencia', 'TARJETA' => 'Tarjeta', 'CREDITO' => 'Crédito'] as $forma => $etiqueta): ?>
        <div>
          <label class="text-[11px]" style="color:var(--ink-soft)"><?= $etiqueta ?></label>
          <input type="number" inputmode="decimal" step="0.01" min="0" data-mixto="<?= $forma ?>" class="field-input mx-monto" style="padding:8px" placeholder="0.00" oninput="renderTotales()" onfocus="completarMixto(this)">
        </div>
      <?php endforeach; ?>
    </div>
    <p class="text-xs mt-2 font-medium" id="mixto-estado"></p>
  </div>

  <div class="rounded-xl p-3 mb-4" style="background:var(--teal-light); color:var(--teal-dark)">
    <div class="flex justify-between text-xs mb-1"><span>Subtotal</span><span class="font-mono" id="tot-subtotal">$0.00</span></div>
    <div class="flex justify-between text-xs mb-1"<?= $itbmsRate > 0 ? "" : " style=\"display:none\"" ?>><span>ITBMS (<?= rtrim(rtrim(number_format($itbmsRate * 100, 2), '0'), '.') ?>%)</span><span class="font-mono" id="tot-itbms">$0.00</span></div>
    <div class="flex justify-between text-xs mb-1" id="fila-desc" style="display:none"><span>Descuento</span><span class="font-mono" id="tot-desc">−$0.00</span></div>
    <div class="flex justify-between items-center mt-1"><span class="text-sm font-medium">Total</span><span class="font-mono text-xl font-semibold" id="tot-total">$0.00</span></div>
  </div>

  <button type="button" id="btn-cobrar" onclick="confirmarVenta()" class="btn-primario" style="background:var(--teal)">Cobrar</button>
</div>

<!-- Sheet: ventas en espera -->
<div id="sheet-espera" class="sheet" style="transform:translateY(110%)">
  <div class="sheet-handle"></div>
  <div class="flex items-center justify-between mb-1">
    <h2 class="font-head text-lg font-semibold">Ventas en espera</h2>
    <button type="button" onclick="cerrarSheet('sheet-espera')" class="w-8 h-8 rounded-full flex items-center justify-center" style="background:var(--navy-light); color:var(--navy)">✕</button>
  </div>
  <p class="text-xs mb-3" style="color:var(--ink-soft)">Quedan guardadas en este celular. Al retomar una, la venta que tengas abierta pasa a espera.</p>
  <div id="espera-lista" class="flex flex-col gap-2 overflow-y-auto" style="max-height:55vh"></div>
</div>

<!-- Sheet: venta registrada -->
<div id="sheet-exito" class="sheet text-center" style="transform:translateY(110%)">
  <div class="sheet-handle"></div>
  <div class="w-16 h-16 rounded-full mx-auto mb-3 flex items-center justify-center text-3xl" style="background:var(--green-light)">✓</div>
  <h2 class="font-head text-lg font-semibold">Venta registrada</h2>
  <p class="text-sm mb-4" style="color:var(--ink-soft)">Factura <span id="exito-factura" class="font-mono"></span></p>
  <div class="card mb-4 text-left">
    <div class="flex justify-between text-sm mb-1"><span>Total</span><span class="font-mono font-semibold" id="exito-total"></span></div>
    <div class="flex justify-between text-sm" id="exito-vuelto-fila"><span>Vuelto</span><span class="font-mono font-semibold text-lg" id="exito-vuelto"></span></div>
  </div>
  <a id="exito-ticket" href="#" class="block w-full py-3 rounded-xl font-medium mb-3" style="background:var(--navy-light); color:var(--navy)">Ver detalle / imprimir</a>
  <button type="button" onclick="nuevaVenta()" class="btn-primario">Nueva venta</button>
</div>

<?php if ($puedeCrearCliente): ?>
<!-- Sheet: cliente rápido -->
<div id="sheet-cliente" class="sheet" style="transform:translateY(110%)">
  <div class="sheet-handle"></div>
  <div class="flex items-center justify-between mb-4">
    <h2 class="font-head text-lg font-semibold">Nuevo cliente</h2>
    <button type="button" onclick="cerrarSheet('sheet-cliente')" class="w-8 h-8 rounded-full flex items-center justify-center" style="background:var(--navy-light); color:var(--navy)">✕</button>
  </div>
  <form onsubmit="return crearClienteRapido(event)">
    <label class="field-label">Nombre</label>
    <input class="field-input mb-3" style="font-family:'Inter'" id="cr-nombre" required>
    <div class="grid grid-cols-2 gap-3 mb-5">
      <div>
        <label class="field-label">Teléfono</label>
        <input class="field-input" style="font-family:'Inter'" id="cr-telefono" inputmode="tel">
      </div>
      <div>
        <label class="field-label">RUC / Cédula</label>
        <input class="field-input" style="font-family:'Inter'" id="cr-ruc">
      </div>
    </div>
    <button type="submit" class="btn-primario">Guardar cliente</button>
  </form>
</div>
<?php endif; ?>

<!-- Sheet: producto por peso o medida (lb, kg, gal…) -->
<div id="sheet-peso" class="sheet" style="transform:translateY(110%)">
  <div class="sheet-handle"></div>
  <div class="flex items-center justify-between mb-1">
    <h2 class="font-head text-lg font-semibold truncate pr-2" id="peso-nombre">Producto</h2>
    <button type="button" onclick="cerrarSheet('sheet-peso')" class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0" style="background:var(--navy-light); color:var(--navy)">✕</button>
  </div>
  <p class="text-xs mb-3" id="peso-info" style="color:var(--ink-soft)"></p>
  <div class="grid grid-cols-2 gap-2 mb-2">
    <div>
      <label class="field-label">Cantidad (<span class="peso-um">lb</span>)</label>
      <input type="number" inputmode="decimal" step="0.01" min="0" id="peso-cantidad" class="field-input text-lg" placeholder="0.00" oninput="document.getElementById('peso-monto').value=''; pesoTotal()" onkeydown="if(event.key==='Enter'){event.preventDefault(); agregarPeso();}">
    </div>
    <div>
      <label class="field-label">o por monto ($)</label>
      <input type="number" inputmode="decimal" step="0.01" min="0" id="peso-monto" class="field-input text-lg" placeholder="0.00" oninput="pesoTotal(true)" onkeydown="if(event.key==='Enter'){event.preventDefault(); agregarPeso();}">
    </div>
  </div>
  <div id="peso-rapidos" class="flex flex-wrap gap-1.5 mb-2"></div>
  <p class="font-mono text-sm font-semibold mb-3" id="peso-total" style="color:var(--teal-dark)"></p>
  <button type="button" onclick="agregarPeso()" class="btn-primario mb-3">Agregar al carrito</button>
  <div id="peso-pres" class="flex flex-col gap-2"></div>
</div>

<!-- Sheet: elegir presentación (unidad, docena, caja…) -->
<div id="sheet-pres" class="sheet" style="transform:translateY(110%)">
  <div class="sheet-handle"></div>
  <div class="flex items-center justify-between mb-1">
    <h2 class="font-head text-lg font-semibold truncate pr-2" id="pres-nombre">Producto</h2>
    <button type="button" onclick="cerrarSheet('sheet-pres')" class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0" style="background:var(--navy-light); color:var(--navy)">✕</button>
  </div>
  <p class="text-xs mb-3" id="pres-stock" style="color:var(--ink-soft)"></p>
  <div id="pres-opciones" class="flex flex-col gap-2"></div>
</div>

<?php $this->endSection(); ?>

<?php $this->section('extra_js'); ?>
<script src="/assets/js/escaner.js?v=1"></script>
<script>
const ITBMS_RATE  = <?= json_encode($itbmsRate) ?>;
const DEPOSITO_ID = <?= json_encode((int) ($deposito_actual['deposito_id'] ?? 0)) ?>;
const CLIENTES    = <?= json_encode($clientesJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
const MAX_DESC    = <?= json_encode((int) $maxDescuentoPct) ?>;
// Carrito y ventas en espera se guardan en este celular, por usuario y depósito
const KEY_CARRITO = 'pos_carrito_<?= (int) $usuarioId ?>_' + DEPOSITO_ID;
const KEY_ESPERA  = 'pos_espera_<?= (int) $usuarioId ?>_' + DEPOSITO_ID;

// carrito: clave "producto|presentación" → { p, u, cantidad }. u = presentación (caja, docena…) o null = unidad.
// cantidad va en la presentación vendida (admite decimales, p. ej. 2.5 lb); el stock se compara en unidades base.
const carrito = {};
let formaPago = 'EFECTIVO';
let categoria = '';
let descTipo = '%';

const clave = function (id, u) { return id + '|' + (u ? u.unidad_id : 0); };
const precioItem = function (it) { return it.u ? it.u.precio : it.p.precio_a; };
const factorItem = function (it) { return it.u ? it.u.factor : 1; };
const redondear = function (n) { return Math.round(n * 100) / 100; };

function leerLocal(k, def) { try { const v = localStorage.getItem(k); return v ? JSON.parse(v) : def; } catch (e) { return def; } }
function escribirLocal(k, v) { try { if (v === null) localStorage.removeItem(k); else localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* sin almacenamiento: sigue funcionando sin guardar */ } }

function baseEnCarrito(productoId) {
  let total = 0;
  Object.keys(carrito).forEach(function (k) {
    const it = carrito[k];
    if (it.p.producto_id === productoId) total += it.cantidad * factorItem(it);
  });
  return total;
}

const um = function (p) { return p.um || 'uds'; };

function textoStock(p) {
  // "300 uds (10 bandejas)" o "23.5 lb" (desglose() de app.js)
  const d = desglose(p.existencia, p.unidades, um(p));
  return numero(p.existencia) + ' ' + um(p) + (d ? ' (' + d + ')' : '');
}

const lista = new ListaProductos({
  contenedor: 'lista-productos',
  filtros: function () { return { q: document.getElementById('input-busqueda').value.trim(), categoria: categoria, orden: 'vendidos' }; },
  renderItem: function (p) {
    const agotado = p.existencia <= 0;
    const pres = (p.unidades || []).map(function (u) { return esc(u.nombre) + ' ×' + numero(u.factor) + ' ' + dinero(u.precio); }).join(' · ');
    const enCarrito = baseEnCarrito(p.producto_id);
    return '<button type="button" onclick="tocarProducto(' + p.producto_id + ')" ' +
      'class="card flex gap-3 !p-3 text-left' + (agotado ? ' opacity-50' : '') + '">' +
      htmlAvatarProducto(p, 'w-12 h-12') +
      '<div class="flex-1 min-w-0">' +
        '<p class="text-sm font-medium truncate">' + esc(p.nombre) + '</p>' +
        '<p class="text-xs" style="color:var(--ink-soft)">' + esc(p.codigo || '') + ' · ' + (agotado ? 'Agotado' : numero(p.existencia) + ' ' + esc(um(p))) +
          (enCarrito > 0 ? ' · <b style="color:var(--teal)">' + numero(enCarrito) + ' en carrito</b>' : '') + '</p>' +
        (pres ? '<p class="text-[10px] truncate" style="color:var(--navy)">' + pres + '</p>' : '') +
      '</div>' +
      '<p class="font-mono text-sm font-semibold flex-shrink-0 self-center">' + dinero(p.precio_a) + (p.fraccion ? '<span class="text-[10px] font-normal" style="color:var(--ink-soft)">/' + esc(um(p)) + '</span>' : '') + '</p>' +
    '</button>';
  },
});

function filtrarCategoria(id) {
  categoria = id;
  document.querySelectorAll('#chips-pos [data-categoria]').forEach(function (b) { b.classList.toggle('activo', b.dataset.categoria === id); });
  lista.cargar(true);
}

// ── Carrito ──────────────────────────────────────────────────────────────────

// Producto por peso o medida (lb, kg, gal…): se escribe cuánto lleva, o cuánto dinero ("$5 de pollo")
let pesoId = null;
function abrirPeso(p) {
  pesoId = p.producto_id;
  document.getElementById('peso-nombre').textContent = p.nombre;
  document.getElementById('peso-info').textContent = dinero(p.precio_a) + ' por ' + um(p) + ' · hay ' + textoStock(p);
  document.querySelectorAll('.peso-um').forEach(function (e) { e.textContent = um(p); });
  document.getElementById('peso-cantidad').value = '';
  document.getElementById('peso-monto').value = '';
  document.getElementById('peso-rapidos').innerHTML = [0.5, 1, 2, 5].map(function (n) {
    return '<button type="button" onclick="pesoRapido(' + n + ')" class="chip font-mono">' + numero(n) + ' ' + esc(um(p)) + '</button>';
  }).join('');
  document.getElementById('peso-pres').innerHTML = (p.unidades || []).map(function (u) {
    return '<button type="button" onclick="agregarAlCarrito(' + p.producto_id + ', ' + u.unidad_id + '); cerrarSheet(\'sheet-peso\')" class="card !p-3 flex items-center justify-between text-left">' +
      '<span class="text-sm font-medium">' + esc(u.nombre) + ' <span class="text-xs font-normal" style="color:var(--ink-soft)">· ' + numero(u.factor) + ' ' + esc(um(p)) + '</span></span>' +
      '<span class="font-mono text-sm font-semibold">' + dinero(u.precio) + '</span></button>';
  }).join('');
  pesoTotal();
  abrirSheet('sheet-peso');
  setTimeout(function () { document.getElementById('peso-cantidad').focus(); }, 300);
}
function pesoProducto() { return lista.get(pesoId) || Object.keys(carrito).map(function (k) { return carrito[k].p; }).find(function (x) { return x.producto_id === pesoId; }); }
function pesoRapido(n) { document.getElementById('peso-cantidad').value = n; document.getElementById('peso-monto').value = ''; pesoTotal(); }
// Escribió la cantidad → calcula el monto; escribió el monto → calcula la cantidad (2 decimales, como se guarda)
function pesoTotal(desdeMonto) {
  const p = pesoProducto();
  if (!p) return;
  const cant = document.getElementById('peso-cantidad'), monto = document.getElementById('peso-monto');
  if (desdeMonto) {
    const m = parseFloat(monto.value) || 0;
    cant.value = m > 0 && p.precio_a > 0 ? redondear(m / p.precio_a) : '';
  }
  const c = parseFloat(cant.value) || 0;
  const total = redondear(c * p.precio_a);
  document.getElementById('peso-total').textContent = c > 0 ? numero(c) + ' ' + um(p) + ' = ' + dinero(total) : '';
}
function agregarPeso() {
  const c = redondear(parseFloat(document.getElementById('peso-cantidad').value) || 0);
  if (!(c > 0)) { toast('Escribe cuánto lleva.', 'error'); return; }
  if (agregarAlCarrito(pesoId, 0, c)) cerrarSheet('sheet-peso');
}

function tocarProducto(id) {
  const p = lista.get(id);
  if (!p) return;
  if (p.fraccion) { abrirPeso(p); return; }
  if (p.unidades && p.unidades.length) {
    document.getElementById('pres-nombre').textContent = p.nombre;
    document.getElementById('pres-stock').textContent = 'Hay ' + textoStock(p);
    const opciones = [{ id: 0, nombre: 'Unidad', factor: 1, precio: p.precio_a }].concat(
      p.unidades.map(function (u) { return { id: u.unidad_id, nombre: u.nombre, factor: u.factor, precio: u.precio }; })
    );
    document.getElementById('pres-opciones').innerHTML = opciones.map(function (o) {
      return '<button type="button" onclick="agregarAlCarrito(' + id + ', ' + o.id + '); cerrarSheet(\'sheet-pres\')" class="card !p-3 flex items-center justify-between text-left">' +
        '<span><span class="text-sm font-medium">' + esc(o.nombre) + '</span>' + (o.factor !== 1 ? '<span class="text-xs" style="color:var(--ink-soft)"> · trae ' + numero(o.factor) + '</span>' : '') + '</span>' +
        '<span class="font-mono text-sm font-semibold">' + dinero(o.precio) + '</span></button>';
    }).join('');
    abrirSheet('sheet-pres');
    return;
  }
  agregarAlCarrito(id, 0);
}

function agregarAlCarrito(id, unidadId, cantidad) {
  cantidad = cantidad > 0 ? cantidad : 1;
  const p = lista.get(id) || Object.keys(carrito).map(function (k) { return carrito[k].p; }).find(function (x) { return x.producto_id === id; });
  if (!p) return false;
  const u = unidadId ? ((p.unidades || []).find(function (x) { return x.unidad_id === unidadId; }) || null) : null;
  const k = clave(id, u);
  const item = carrito[k] || { p: p, u: u, cantidad: 0 };
  if (precioItem(item) <= 0) {
    toast('"' + p.nombre + '" no tiene precio. Asígnalo en la versión web.', 'error');
    return false;
  }
  if (baseEnCarrito(id) + factorItem(item) * cantidad > p.existencia + 0.0001) {
    toast(p.existencia <= 0 ? 'Producto agotado.' : 'Solo hay ' + textoStock(p) + '.', 'error');
    return false;
  }
  item.cantidad = redondear(item.cantidad + cantidad);
  carrito[k] = item;
  if (navigator.vibrate) navigator.vibrate(15);
  toast('+' + numero(cantidad) + ' ' + (u ? u.nombre + ' ' : (p.fraccion ? um(p) + ' ' : '')) + p.nombre);
  actualizarCarrito();
  return true;
}

function cambiarCantidad(k, delta) {
  const item = carrito[k];
  if (item) fijarCantidad(k, redondear(item.cantidad + delta));
}

// Cantidad escrita a mano (admite decimales: peso, medias cajas…)
function fijarCantidad(k, valor) {
  const item = carrito[k];
  if (!item) return;
  const n = parseFloat(valor);
  if (!(n > 0)) { delete carrito[k]; actualizarCarrito(); return; }
  const otros = baseEnCarrito(item.p.producto_id) - item.cantidad * factorItem(item);
  if (otros + n * factorItem(item) > item.p.existencia + 0.0001) {
    toast('Solo hay ' + textoStock(item.p) + '.', 'error');
    actualizarCarrito();
    return;
  }
  item.cantidad = redondear(n);
  actualizarCarrito();
}

function descuentoMonto(subtotal) {
  const el = document.getElementById('desc-valor');
  if (!el) return 0;
  const v = parseFloat(el.value) || 0;
  if (v <= 0) return 0;
  return redondear(Math.min(subtotal, descTipo === '%' ? subtotal * v / 100 : v));
}

function totales() {
  let subtotal = 0;
  Object.keys(carrito).forEach(function (k) { subtotal += carrito[k].cantidad * precioItem(carrito[k]); });
  const itbms = subtotal * ITBMS_RATE;
  const desc = descuentoMonto(subtotal);
  // Mismo redondeo que VentaService (el descuento al total no cambia el ITBMS)
  return { subtotal: subtotal, itbms: itbms, desc: desc, total: redondear(subtotal + itbms - desc) };
}

function actualizarCarrito() {
  const claves = Object.keys(carrito);
  document.getElementById('carrito-barra').style.display = claves.length ? 'block' : 'none';
  document.getElementById('carrito-cantidad').textContent = claves.length;
  document.getElementById('carrito-total').textContent = dinero(totales().total);
  document.getElementById('carrito-vacio').style.display = claves.length ? 'none' : 'block';

  let html = '';
  claves.forEach(function (k) {
    const item = carrito[k];
    const precio = precioItem(item);
    const nombrePres = item.u ? item.u.nombre + ' ×' + numero(item.u.factor) : (item.p.fraccion ? 'Por ' + um(item.p) : 'Unidad');
    html += '<div class="card !p-3 flex items-center gap-3">' +
      '<div class="flex-1 min-w-0">' +
        '<p class="text-sm font-medium truncate">' + esc(item.p.nombre) + '</p>' +
        '<p class="text-xs" style="color:var(--ink-soft)">' + esc(nombrePres) + '</p>' +
        '<p class="text-xs font-mono" style="color:var(--ink-soft)">' + dinero(precio) + ' × ' + numero(item.cantidad) + ' = ' + dinero(precio * item.cantidad) + '</p>' +
      '</div>' +
      '<div class="flex items-center gap-1.5 flex-shrink-0">' +
        '<button type="button" onclick="cambiarCantidad(\'' + k + '\', -1)" class="w-8 h-8 rounded-full font-semibold" style="background:var(--navy-light); color:var(--navy)">−</button>' +
        '<input type="number" inputmode="decimal" step="any" min="0" value="' + item.cantidad + '" onchange="fijarCantidad(\'' + k + '\', this.value)" class="field-input" style="width:64px; text-align:center; padding:6px 4px">' +
        '<button type="button" onclick="cambiarCantidad(\'' + k + '\', 1)" class="w-8 h-8 rounded-full font-semibold" style="background:var(--navy-light); color:var(--navy)">+</button>' +
      '</div>' +
    '</div>';
  });
  document.getElementById('carrito-items').innerHTML = html;
  renderTotales();
  guardarCarrito();
}

function renderTotales() {
  const t = totales();
  document.getElementById('tot-subtotal').textContent = dinero(t.subtotal);
  document.getElementById('tot-itbms').textContent = dinero(t.itbms);
  document.getElementById('tot-total').textContent = dinero(t.total);
  document.getElementById('carrito-total').textContent = dinero(t.total);
  document.getElementById('fila-desc').style.display = t.desc > 0 ? 'flex' : 'none';
  document.getElementById('tot-desc').textContent = '−' + dinero(t.desc);

  // Efectivo: vuelto y botones de billete (exacto + los billetes que alcanzan)
  const recibido = parseFloat(document.getElementById('monto-recibido').value) || 0;
  document.getElementById('monto-vuelto').textContent = dinero(Math.max(0, recibido - t.total));
  const sugeridos = [];
  if (t.total > 0) {
    [1, 5, 10, 20, 50, 100].forEach(function (b) {
      const v = Math.ceil((t.total - 0.001) / b) * b;
      if (v > t.total + 0.001 && sugeridos.indexOf(v) === -1 && sugeridos.length < 4) sugeridos.push(v);
    });
  }
  document.getElementById('billetes').innerHTML = t.total > 0
    ? '<button type="button" onclick="fijarRecibido(' + t.total + ')" class="chip" style="font-weight:600">Exacto</button>' +
      sugeridos.map(function (v) { return '<button type="button" onclick="fijarRecibido(' + v + ')" class="chip font-mono">' + dinero(v) + '</button>'; }).join('')
    : '';

  // Mixto: lo que falta o el vuelto
  if (formaPago === 'MIXTO') {
    const m = montosMixto();
    const falta = redondear(t.total - m.suma);
    const el = document.getElementById('mixto-estado');
    if (falta > 0.005) { el.textContent = 'Falta ' + dinero(falta); el.style.color = 'var(--red)'; }
    else if (falta < -0.005) { el.textContent = 'Vuelto ' + dinero(-falta) + (m.EFECTIVO + 0.005 < -falta ? ' — el vuelto solo puede salir del efectivo' : ''); el.style.color = m.EFECTIVO + 0.005 < -falta ? 'var(--red)' : 'var(--teal-dark)'; }
    else { el.textContent = 'Completo ✓'; el.style.color = 'var(--green)'; }
  }

  // Descuento: aviso si pasa del máximo
  const dv = document.getElementById('desc-valor');
  if (dv) dv.style.borderColor = t.subtotal > 0 && t.desc * 100 / t.subtotal > MAX_DESC + 0.01 ? 'var(--red)' : '';
}

function fijarRecibido(v) {
  document.getElementById('monto-recibido').value = v.toFixed(2);
  renderTotales();
}

function tipoDescuento(tipo) {
  descTipo = tipo;
  ['desc-pct', 'desc-monto'].forEach(function (id) {
    const activo = (id === 'desc-pct') === (tipo === '%');
    const b = document.getElementById(id);
    if (b) b.style.cssText = activo ? 'background:var(--navy); color:white' : 'background:white; color:var(--ink-soft)';
  });
  renderTotales();
}

function montosMixto() {
  const m = { EFECTIVO: 0, YAPPY: 0, TRANSFERENCIA: 0, TARJETA: 0, CREDITO: 0, suma: 0 };
  document.querySelectorAll('.mx-monto').forEach(function (el) {
    const v = redondear(parseFloat(el.value) || 0);
    m[el.dataset.mixto] = v;
    m.suma += v;
  });
  m.suma = redondear(m.suma);
  return m;
}

// Al tocar un campo vacío del pago mixto, lo llena con lo que falta
function completarMixto(el) {
  if (el.value !== '') return;
  const falta = redondear(totales().total - montosMixto().suma);
  if (falta > 0) { el.value = falta.toFixed(2); renderTotales(); }
}

function abrirCarrito() {
  actualizarCarrito();
  abrirSheet('sheet-carrito');
}

function elegirForma(forma) {
  formaPago = forma;
  document.getElementById('forma-pago').value = forma;
  document.getElementById('bloque-efectivo').style.display = forma === 'EFECTIVO' ? 'block' : 'none';
  document.getElementById('bloque-mixto').style.display = forma === 'MIXTO' ? 'block' : 'none';
  cambiarCliente();
  renderTotales();
}

function clienteSeleccionado() {
  const id = parseInt(document.getElementById('carrito-cliente').value, 10);
  return CLIENTES.find(function (c) { return c.id === id; }) || null;
}

function cambiarCliente() {
  const c = clienteSeleccionado();
  const el = document.getElementById('cliente-credito');
  const conCredito = formaPago === 'CREDITO' || formaPago === 'MIXTO';
  if (!c) {
    el.textContent = conCredito ? 'Para vender a crédito elige un cliente.' : '';
  } else if (!c.credito) {
    el.textContent = 'Este cliente no tiene línea de crédito.';
  } else {
    el.textContent = 'Crédito disponible: ' + dinero(c.disponible);
  }
}

// ── Guardar el carrito en el celular (sobrevive a cerrar la app o perder señal) ──

function carritoComoDatos() {
  return {
    items: Object.keys(carrito).map(function (k) {
      const it = carrito[k];
      return { producto_id: it.p.producto_id, unidad_id: it.u ? it.u.unidad_id : 0, cantidad: it.cantidad, nombre: it.p.nombre };
    }),
    cliente_id: document.getElementById('carrito-cliente').value || '',
    total: totales().total,
    ts: Date.now(),
  };
}

function guardarCarrito() {
  const datos = carritoComoDatos();
  escribirLocal(KEY_CARRITO, datos.items.length ? datos : null);
}

function vaciarCarrito() {
  Object.keys(carrito).forEach(function (k) { delete carrito[k]; });
  document.getElementById('monto-recibido').value = '';
  document.getElementById('carrito-cliente').value = '';
  const dv = document.getElementById('desc-valor');
  if (dv) dv.value = '';
  document.querySelectorAll('.mx-monto').forEach(function (el) { el.value = ''; });
  elegirForma('EFECTIVO');
  actualizarCarrito();
}

// Rearma un carrito guardado con precios, presentaciones y stock actuales del servidor
async function cargarCarrito(datos) {
  const ids = datos.items.map(function (i) { return i.producto_id; });
  if (!ids.length) return;
  let productos = {};
  try {
    const res = await api('/m/api/productos?ids=' + ids.join(','));
    res.productos.forEach(function (p) { productos[p.producto_id] = p; });
  } catch (e) {
    toast('No se pudo recuperar el carrito: ' + e.message, 'error');
    return false;
  }
  const quitados = [], sinStock = [];
  datos.items.forEach(function (i) {
    const p = productos[i.producto_id];
    const u = p && i.unidad_id ? (p.unidades || []).find(function (x) { return x.unidad_id === i.unidad_id; }) : null;
    if (!p || (i.unidad_id && !u)) { quitados.push(i.nombre || ('#' + i.producto_id)); return; }
    const k = clave(p.producto_id, u);
    const it = carrito[k] || { p: p, u: u || null, cantidad: 0 };
    it.cantidad = redondear(it.cantidad + i.cantidad);
    if (precioItem(it) <= 0) { quitados.push(p.nombre); return; }
    carrito[k] = it;
    if (baseEnCarrito(p.producto_id) > p.existencia + 0.0001 && sinStock.indexOf(p.nombre) === -1) sinStock.push(p.nombre);
  });
  document.getElementById('carrito-cliente').value = CLIENTES.some(function (c) { return String(c.id) === String(datos.cliente_id); }) ? datos.cliente_id : '';
  cambiarCliente();
  actualizarCarrito();
  if (quitados.length) toast('Ya no se pueden vender: ' + quitados.join(', '), 'error');
  if (sinStock.length) toast('Revisa la cantidad (no alcanza el stock): ' + sinStock.join(', '), 'error');
  return true;
}

// ── Ventas en espera ─────────────────────────────────────────────────────────

function ventasEnEspera() { return leerLocal(KEY_ESPERA, []); }

function pintarEspera() {
  const lista = ventasEnEspera();
  document.getElementById('chip-espera').style.display = lista.length ? '' : 'none';
  document.getElementById('espera-n').textContent = lista.length;
  document.getElementById('espera-lista').innerHTML = lista.length ? lista.map(function (v) {
    const hora = new Date(v.ts).toLocaleTimeString('es-PA', { hour: '2-digit', minute: '2-digit' });
    return '<div class="card !p-3 flex items-center gap-3">' +
      '<div class="flex-1 min-w-0">' +
        '<p class="text-sm font-medium truncate">' + esc(v.nombre) + '</p>' +
        '<p class="text-xs" style="color:var(--ink-soft)">' + hora + ' · ' + v.items.length + ' producto(s) · <span class="font-mono">' + dinero(v.total) + '</span></p>' +
      '</div>' +
      '<button type="button" onclick="retomarEspera(' + v.id + ')" class="px-3 py-2 rounded-xl text-xs font-semibold text-white" style="background:var(--teal)">Retomar</button>' +
      '<button type="button" onclick="borrarEspera(' + v.id + ')" class="w-8 h-8 rounded-full" style="background:var(--red-light); color:var(--red)" title="Descartar">✕</button>' +
    '</div>';
  }).join('') : '<p class="text-sm text-center py-6" style="color:var(--ink-soft)">No hay ventas en espera.</p>';
}

function abrirEspera() {
  pintarEspera();
  abrirSheet('sheet-espera');
}

function guardarEnEspera(nombre) {
  const datos = carritoComoDatos();
  datos.id = Date.now();
  datos.nombre = nombre;
  const lista = ventasEnEspera();
  lista.push(datos);
  escribirLocal(KEY_ESPERA, lista);
}

function ponerEnEspera() {
  if (!Object.keys(carrito).length) { toast('El carrito está vacío.', 'error'); return; }
  const c = clienteSeleccionado();
  const sugerido = c ? c.nombre : 'Cliente ' + (ventasEnEspera().length + 1);
  const nombre = prompt('Nombre para reconocer esta venta:', sugerido);
  if (nombre === null) return;
  guardarEnEspera(nombre.trim() || sugerido);
  vaciarCarrito();
  cerrarSheets();
  pintarEspera();
  toast('Venta en espera. Retómala desde "⏸ En espera".');
}

async function retomarEspera(id) {
  const lista = ventasEnEspera();
  const v = lista.find(function (x) { return x.id === id; });
  if (!v) return;
  const resto = lista.filter(function (x) { return x.id !== id; });
  // La venta abierta no se pierde: pasa a espera
  if (Object.keys(carrito).length) {
    const c = clienteSeleccionado();
    escribirLocal(KEY_ESPERA, resto);
    guardarEnEspera(c ? c.nombre : 'Venta de las ' + new Date().toLocaleTimeString('es-PA', { hour: '2-digit', minute: '2-digit' }));
    toast('La venta que tenías abierta quedó en espera.');
  } else {
    escribirLocal(KEY_ESPERA, resto);
  }
  vaciarCarrito();
  cerrarSheets();
  await cargarCarrito(v);
  pintarEspera();
  abrirCarrito();
}

function borrarEspera(id) {
  if (!confirm('¿Descartar esta venta en espera?')) return;
  escribirLocal(KEY_ESPERA, ventasEnEspera().filter(function (x) { return x.id !== id; }));
  pintarEspera();
}

// ── Cobro ────────────────────────────────────────────────────────────────────

async function confirmarVenta() {
  const claves = Object.keys(carrito);
  if (!claves.length) {
    toast('El carrito está vacío.', 'error');
    return;
  }

  const t = totales();
  const cliente = clienteSeleccionado();
  const recibido = parseFloat(document.getElementById('monto-recibido').value) || 0;

  if (t.desc > 0 && t.desc * 100 / t.subtotal > MAX_DESC + 0.01) {
    toast('El descuento pasa del máximo (' + MAX_DESC + '%).', 'error');
    return;
  }

  let pagos, vuelto = 0;
  if (formaPago === 'MIXTO') {
    const m = montosMixto();
    if (m.suma < t.total - 0.005) { toast('Falta ' + dinero(t.total - m.suma) + ' por repartir.', 'error'); return; }
    const exceso = redondear(m.suma - t.total);
    if (exceso > m.EFECTIVO + 0.005) { toast('Lo que no es efectivo no puede pasar del total (el vuelto sale del efectivo).', 'error'); return; }
    if (m.CREDITO > 0) {
      if (!cliente) { toast('Elige un cliente para la parte a crédito.', 'error'); return; }
      if (m.CREDITO > cliente.disponible + 0.01) { toast('Crédito insuficiente. Disponible: ' + dinero(cliente.disponible), 'error'); return; }
    }
    pagos = ['EFECTIVO', 'YAPPY', 'TRANSFERENCIA', 'TARJETA', 'CREDITO'].filter(function (f) { return m[f] > 0; }).map(function (f) { return { forma_pago: f, monto: m[f] }; });
    vuelto = Math.max(0, exceso);
  } else {
    if (formaPago === 'CREDITO') {
      if (!cliente) { toast('Elige un cliente para vender a crédito.', 'error'); return; }
      if (t.total > cliente.disponible + 0.01) { toast('Crédito insuficiente. Disponible: ' + dinero(cliente.disponible), 'error'); return; }
    }
    if (formaPago === 'EFECTIVO' && recibido > 0 && recibido < t.total - 0.005) {
      toast('El monto recibido es menor al total.', 'error');
      return;
    }
    // Monto 0 = el servidor registra el pago por el total exacto. El vuelto se calcula aquí.
    pagos = [{ forma_pago: formaPago, monto: 0 }];
    vuelto = formaPago === 'EFECTIVO' && recibido > 0 ? Math.max(0, recibido - t.total) : 0;
  }

  const payload = {
    tipo: 'venta',
    cliente_id: cliente ? cliente.id : null,
    pagos: pagos,
    forma_pago: formaPago,
    descuento: t.desc,
    notas: 'Venta desde móvil',
    aplicar_itbms: true,
    items: claves.map(function (k) {
      const it = carrito[k];
      return {
        producto_id: it.p.producto_id,
        unidad_id: it.u ? it.u.unidad_id : null,
        cantidad: it.cantidad,
        precio: precioItem(it),
        deposito_id: DEPOSITO_ID,
      };
    }),
  };

  const boton = document.getElementById('btn-cobrar');
  boton.disabled = true;
  boton.textContent = 'Procesando…';

  try {
    const res = await api('/ventas/procesar', { method: 'POST', body: payload });
    // Vendida: el carrito guardado se borra ya (si se cierra la app aquí, no se vuelve a cobrar)
    Object.keys(carrito).forEach(function (k) { delete carrito[k]; });
    escribirLocal(KEY_CARRITO, null);
    cerrarSheet('sheet-carrito');
    document.getElementById('exito-factura').textContent = res.numero_factura;
    document.getElementById('exito-total').textContent = dinero(res.total);
    document.getElementById('exito-vuelto').textContent = dinero(vuelto);
    document.getElementById('exito-vuelto-fila').style.display = vuelto > 0.005 ? 'flex' : 'none';
    document.getElementById('exito-ticket').href = '/m/ventas?venta=' + res.venta_id;
    abrirSheet('sheet-exito');
  } catch (e) {
    toast(e.message, 'error');
  } finally {
    boton.disabled = false;
    boton.textContent = 'Cobrar';
  }
}

function nuevaVenta() {
  vaciarCarrito();
  cerrarSheets();
  document.getElementById('input-busqueda').value = '';
  lista.cargar(true); // existencias actualizadas
}

// ── Cliente rápido ───────────────────────────────────────────────────────────

async function crearClienteRapido(ev) {
  ev.preventDefault();
  const nombre = document.getElementById('cr-nombre').value.trim();
  try {
    const res = await api('/ventas/clientes/rapido', {
      method: 'POST',
      body: {
        nombre: nombre,
        telefono: document.getElementById('cr-telefono').value.trim(),
        ruc: document.getElementById('cr-ruc').value.trim(),
      },
    });
    CLIENTES.push({ id: res.cliente_id, nombre: res.nombre, disponible: 0, credito: false });
    const select = document.getElementById('carrito-cliente');
    select.add(new Option(res.nombre, res.cliente_id, true, true));
    cambiarCliente();
    guardarCarrito();
    ev.target.reset();
    cerrarSheet('sheet-cliente');
    toast('Cliente creado.');
  } catch (e) {
    toast(e.message, 'error');
  }
  return false;
}

// ── Búsqueda y escáner (lector de código de barras o cámara) ─────────────────

// Busca un código: si coincide exacto con el código de barras de una presentación (caja…) la agrega;
// si coincide con el código o barras del producto, lo agrega (o pide presentación); si hay uno solo, igual.
async function agregarPorCodigo(codigo) {
  const input = document.getElementById('input-busqueda');
  input.value = codigo;
  await lista.cargar(true);
  const c = codigo.toLowerCase();
  const productos = Object.keys(lista.productos).map(function (id) { return lista.productos[id]; });
  for (const p of productos) {
    const u = (p.unidades || []).find(function (x) { return (x.codigo_barras || '').toLowerCase() === c && c !== ''; });
    if (u) { agregarAlCarrito(p.producto_id, u.unidad_id); return limpiarBusqueda(); }
  }
  const exacto = productos.find(function (p) { return (p.codigo_barras || '').toLowerCase() === c || (p.codigo || '').toLowerCase() === c; });
  const p = exacto || (productos.length === 1 ? productos[0] : null);
  if (p) { tocarProducto(p.producto_id); return limpiarBusqueda(); }
  if (!productos.length) toast('No se encontró el código ' + codigo + '.', 'error');
}

function limpiarBusqueda() {
  document.getElementById('input-busqueda').value = '';
  lista.cargar(true);
}

function escanear() {
  Escaner.abrir(function (codigo) { agregarPorCodigo(codigo.trim()); });
}

const inputBusqueda = document.getElementById('input-busqueda');
inputBusqueda.addEventListener('input', debounce(function () { lista.cargar(true); }, 300));
// Lector de código de barras (pistola Bluetooth/USB): escribe el código y Enter
inputBusqueda.addEventListener('keydown', function (e) {
  if (e.key !== 'Enter') return;
  e.preventDefault();
  const v = inputBusqueda.value.trim();
  if (v) agregarPorCodigo(v);
});

// ── Inicio ───────────────────────────────────────────────────────────────────

tipoDescuento('%');
pintarEspera();
lista.cargar(true);
(async function () {
  const guardado = leerLocal(KEY_CARRITO, null);
  if (guardado && guardado.items && guardado.items.length && await cargarCarrito(guardado)) {
    toast('Se recuperó la venta que tenías abierta.');
  }
})();
</script>
<?php $this->endSection(); ?>

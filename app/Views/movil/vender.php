<?php
$this->extend('layouts/movil');

$clientesJs = array_map(fn ($c) => [
    'id'         => (int) $c['cliente_id'],
    'nombre'     => $c['nombre'],
    'disponible' => max(0, (float) $c['limite_credito'] - (float) $c['saldo_pendiente']),
    'credito'    => (float) $c['limite_credito'] > 0,
], $clientes);
?>
<?php $this->section('content'); ?>

<div class="mb-3 flex items-center gap-2 bg-white rounded-xl border px-3 py-2" style="border-color:var(--line)">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6B7794" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
  <input type="search" id="input-busqueda" class="flex-1 text-sm outline-none bg-transparent" placeholder="Buscar producto, código o escanear" autocomplete="off">
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
    <button type="button" onclick="cerrarSheet('sheet-carrito')" class="w-8 h-8 rounded-full flex items-center justify-center" style="background:var(--navy-light); color:var(--navy)">✕</button>
  </div>

  <div id="carrito-items" class="flex flex-col gap-2 overflow-y-auto mb-3" style="max-height:30vh"></div>
  <div id="carrito-vacio" class="text-sm text-center py-4" style="color:var(--ink-soft)">Todavía no agregas productos.</div>

  <label class="field-label">Cliente</label>
  <div class="flex gap-2 mb-1">
    <select id="carrito-cliente" class="field-input" style="font-family:'Inter'" onchange="cambiarCliente()">
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

  <label class="field-label">Forma de pago</label>
  <div class="grid grid-cols-4 gap-2 mb-3">
    <button type="button" class="chip-pago chip-forma activo" data-forma="EFECTIVO" onclick="elegirForma('EFECTIVO')">Efectivo</button>
    <button type="button" class="chip-pago chip-forma" data-forma="YAPPY" onclick="elegirForma('YAPPY')">Yappy</button>
    <button type="button" class="chip-pago chip-forma" data-forma="TARJETA" onclick="elegirForma('TARJETA')">Tarjeta</button>
    <button type="button" class="chip-pago chip-forma" data-forma="CREDITO" onclick="elegirForma('CREDITO')">Crédito</button>
  </div>

  <div id="bloque-efectivo" class="flex items-end gap-3 mb-3">
    <div class="flex-1">
      <label class="field-label">Recibido (opcional)</label>
      <input type="number" inputmode="decimal" step="0.01" min="0" id="monto-recibido" class="field-input" placeholder="0.00" oninput="renderTotales()">
    </div>
    <div class="flex-1 text-right pb-2">
      <span class="text-xs" style="color:var(--ink-soft)">Vuelto</span>
      <p class="font-mono font-semibold" id="monto-vuelto">$0.00</p>
    </div>
  </div>

  <div class="rounded-xl p-3 mb-4" style="background:var(--teal-light); color:var(--teal-dark)">
    <div class="flex justify-between text-xs mb-1"><span>Subtotal</span><span class="font-mono" id="tot-subtotal">$0.00</span></div>
    <div class="flex justify-between text-xs mb-2"<?= $itbmsRate > 0 ? "" : " style=\"display:none\"" ?>><span>ITBMS (<?= rtrim(rtrim(number_format($itbmsRate * 100, 2), '0'), '.') ?>%)</span><span class="font-mono" id="tot-itbms">$0.00</span></div>
    <div class="flex justify-between items-center"><span class="text-sm font-medium">Total</span><span class="font-mono text-xl font-semibold" id="tot-total">$0.00</span></div>
  </div>

  <button type="button" id="btn-cobrar" onclick="confirmarVenta()" class="btn-primario" style="background:var(--teal)">Cobrar</button>
</div>

<!-- Sheet: venta registrada -->
<div id="sheet-exito" class="sheet text-center" style="transform:translateY(110%)">
  <div class="sheet-handle"></div>
  <div class="w-16 h-16 rounded-full mx-auto mb-3 flex items-center justify-center text-3xl" style="background:var(--green-light)">✓</div>
  <h2 class="font-head text-lg font-semibold">Venta registrada</h2>
  <p class="text-sm mb-4" style="color:var(--ink-soft)">Factura <span id="exito-factura" class="font-mono"></span></p>
  <div class="card mb-4 text-left">
    <div class="flex justify-between text-sm mb-1"><span>Total</span><span class="font-mono font-semibold" id="exito-total"></span></div>
    <div class="flex justify-between text-sm" id="exito-vuelto-fila"><span>Vuelto</span><span class="font-mono font-semibold" id="exito-vuelto"></span></div>
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
<script>
const ITBMS_RATE  = <?= json_encode($itbmsRate) ?>;
const DEPOSITO_ID = <?= json_encode((int) ($deposito_actual['deposito_id'] ?? 0)) ?>;
const CLIENTES    = <?= json_encode($clientesJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;

// carrito: clave "producto|presentación" → { p, u, cantidad }. u = presentación (caja, docena…) o null = unidad.
// cantidad va en la presentación vendida (admite decimales, p. ej. 2.5 lb); el stock se compara en unidades base.
const carrito = {};
let formaPago = 'EFECTIVO';

const clave = function (id, u) { return id + '|' + (u ? u.unidad_id : 0); };
const precioItem = function (it) { return it.u ? it.u.precio : it.p.precio_a; };
const factorItem = function (it) { return it.u ? it.u.factor : 1; };

function baseEnCarrito(productoId) {
  let total = 0;
  Object.keys(carrito).forEach(function (k) {
    const it = carrito[k];
    if (it.p.producto_id === productoId) total += it.cantidad * factorItem(it);
  });
  return total;
}

function textoStock(p) {
  // "141 uds (5 cajas de 24 + 21)" con la presentación más grande
  const grande = (p.unidades || []).slice().sort(function (a, b) { return b.factor - a.factor; })[0];
  if (!grande || grande.factor <= 1 || p.existencia < grande.factor) return numero(p.existencia) + ' uds';
  const n = Math.floor(p.existencia / grande.factor);
  const resto = Math.round((p.existencia - n * grande.factor) * 100) / 100;
  return numero(p.existencia) + ' uds (' + n + ' ' + grande.nombre.toLowerCase() + (n === 1 ? '' : 's') + ' de ' + numero(grande.factor) + (resto > 0 ? ' + ' + numero(resto) : '') + ')';
}

const lista = new ListaProductos({
  contenedor: 'lista-productos',
  filtros: function () { return { q: document.getElementById('input-busqueda').value.trim() }; },
  renderItem: function (p) {
    const agotado = p.existencia <= 0;
    const pres = (p.unidades || []).map(function (u) { return esc(u.nombre) + ' ×' + numero(u.factor); }).join(' · ');
    return '<button type="button" onclick="tocarProducto(' + p.producto_id + ')" ' +
      'class="card flex gap-3 !p-3 text-left' + (agotado ? ' opacity-50' : '') + '">' +
      htmlAvatarProducto(p, 'w-12 h-12') +
      '<div class="flex-1 min-w-0">' +
        '<p class="text-sm font-medium truncate">' + esc(p.nombre) + '</p>' +
        '<p class="text-xs" style="color:var(--ink-soft)">' + esc(p.codigo || '') + ' · ' + (agotado ? 'Agotado' : numero(p.existencia) + ' uds') + '</p>' +
        (pres ? '<p class="text-[10px] truncate" style="color:var(--navy)">' + pres + '</p>' : '') +
      '</div>' +
      '<p class="font-mono text-sm font-semibold flex-shrink-0 self-center">' + dinero(p.precio_a) + '</p>' +
    '</button>';
  },
});

// ── Carrito ──────────────────────────────────────────────────────────────────

function tocarProducto(id) {
  const p = lista.get(id);
  if (!p) return;
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

function agregarAlCarrito(id, unidadId) {
  const p = lista.get(id) || Object.keys(carrito).map(function (k) { return carrito[k].p; }).find(function (x) { return x.producto_id === id; });
  if (!p) return;
  const u = unidadId ? ((p.unidades || []).find(function (x) { return x.unidad_id === unidadId; }) || null) : null;
  const k = clave(id, u);
  const item = carrito[k] || { p: p, u: u, cantidad: 0 };
  if (precioItem(item) <= 0) {
    toast('"' + p.nombre + '" no tiene precio. Asígnalo en la versión web.', 'error');
    return;
  }
  if (baseEnCarrito(id) + factorItem(item) > p.existencia + 0.0001) {
    toast(p.existencia <= 0 ? 'Producto agotado.' : 'Solo hay ' + textoStock(p) + '.', 'error');
    return;
  }
  item.cantidad += 1;
  carrito[k] = item;
  if (navigator.vibrate) navigator.vibrate(15);
  actualizarCarrito();
}

function cambiarCantidad(k, delta) {
  const item = carrito[k];
  if (item) fijarCantidad(k, Math.round((item.cantidad + delta) * 100) / 100);
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
  item.cantidad = Math.round(n * 100) / 100;
  actualizarCarrito();
}

function totales() {
  let subtotal = 0;
  Object.keys(carrito).forEach(function (k) { subtotal += carrito[k].cantidad * precioItem(carrito[k]); });
  const itbms = subtotal * ITBMS_RATE;
  // Mismo redondeo que VentaService
  return { subtotal: subtotal, itbms: itbms, total: Math.round((subtotal + itbms) * 100) / 100 };
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
    const nombrePres = item.u ? item.u.nombre + ' ×' + numero(item.u.factor) : 'Unidad';
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
}

function renderTotales() {
  const t = totales();
  document.getElementById('tot-subtotal').textContent = dinero(t.subtotal);
  document.getElementById('tot-itbms').textContent = dinero(t.itbms);
  document.getElementById('tot-total').textContent = dinero(t.total);

  const recibido = parseFloat(document.getElementById('monto-recibido').value) || 0;
  document.getElementById('monto-vuelto').textContent = dinero(Math.max(0, recibido - t.total));
}

function abrirCarrito() {
  actualizarCarrito();
  abrirSheet('sheet-carrito');
}

function elegirForma(forma) {
  formaPago = forma;
  document.querySelectorAll('.chip-forma').forEach(function (b) {
    b.classList.toggle('activo', b.dataset.forma === forma);
  });
  document.getElementById('bloque-efectivo').style.display = forma === 'EFECTIVO' ? 'flex' : 'none';
  cambiarCliente();
}

function clienteSeleccionado() {
  const id = parseInt(document.getElementById('carrito-cliente').value, 10);
  return CLIENTES.find(function (c) { return c.id === id; }) || null;
}

function cambiarCliente() {
  const c = clienteSeleccionado();
  const el = document.getElementById('cliente-credito');
  if (!c) {
    el.textContent = formaPago === 'CREDITO' ? 'Para vender a crédito elige un cliente.' : '';
  } else if (!c.credito) {
    el.textContent = 'Este cliente no tiene línea de crédito.';
  } else {
    el.textContent = 'Crédito disponible: ' + dinero(c.disponible);
  }
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

  if (formaPago === 'CREDITO') {
    if (!cliente) { toast('Elige un cliente para vender a crédito.', 'error'); return; }
    if (t.total > cliente.disponible + 0.01) { toast('Crédito insuficiente. Disponible: ' + dinero(cliente.disponible), 'error'); return; }
  }
  if (formaPago === 'EFECTIVO' && recibido > 0 && recibido < t.total - 0.005) {
    toast('El monto recibido es menor al total.', 'error');
    return;
  }

  // Monto 0 = el servidor registra el pago por el total exacto. El vuelto se calcula aquí.
  const vuelto = formaPago === 'EFECTIVO' && recibido > 0 ? Math.max(0, recibido - t.total) : 0;

  const payload = {
    tipo: 'venta',
    cliente_id: cliente ? cliente.id : null,
    pagos: [{ forma_pago: formaPago, monto: 0 }],
    forma_pago: formaPago,
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
  Object.keys(carrito).forEach(function (k) { delete carrito[k]; });
  document.getElementById('monto-recibido').value = '';
  document.getElementById('carrito-cliente').value = '';
  elegirForma('EFECTIVO');
  actualizarCarrito();
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
    ev.target.reset();
    cerrarSheet('sheet-cliente');
    toast('Cliente creado.');
  } catch (e) {
    toast(e.message, 'error');
  }
  return false;
}

// ── Búsqueda (también funciona con lector de código de barras: Enter agrega directo) ──

const inputBusqueda = document.getElementById('input-busqueda');
inputBusqueda.addEventListener('input', debounce(function () { lista.cargar(true); }, 300));
inputBusqueda.addEventListener('keydown', async function (e) {
  if (e.key !== 'Enter') return;
  e.preventDefault();
  await lista.cargar(true);
  const ids = Object.keys(lista.productos);
  if (ids.length === 1) {
    tocarProducto(parseInt(ids[0], 10));
    inputBusqueda.value = '';
    lista.cargar(true);
  }
});

lista.cargar(true);
</script>
<?php $this->endSection(); ?>

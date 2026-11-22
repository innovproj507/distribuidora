<?php
$this->extend('layouts/movil');

$estadoVenta = [
    'pagada'    => ['Pagada',  'var(--green-light)',  'var(--green)'],
    'completada'=> ['Pagada',  'var(--green-light)',  'var(--green)'],
    'pendiente' => ['Crédito', 'var(--orange-light)', 'var(--orange)'],
    'anulada'   => ['Anulada', 'var(--red-light)',    'var(--red)'],
];
$totalHoy = 0;
foreach ($ventas as $v) {
    if ($v['estado'] !== 'anulada' && date('Y-m-d', strtotime($v['fecha'])) === date('Y-m-d')) $totalHoy += (float) $v['total'];
}
?>
<?php $this->section('content'); ?>

<div class="stat-card stat-navy mb-4">
  <p class="text-xs text-white/80">Vendido hoy</p>
  <p class="font-mono text-2xl font-semibold"><?= '$' . number_format($totalHoy, 2) ?></p>
</div>

<div class="mb-3 flex items-center gap-2 bg-white rounded-xl border px-3 py-2" style="border-color:var(--line)">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6B7794" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
  <input type="search" id="input-busqueda" class="flex-1 text-sm outline-none bg-transparent" placeholder="Buscar factura o cliente" autocomplete="off">
</div>

<p class="text-sm text-center mt-6" id="msg-vacio" style="color:var(--ink-soft); display:none">Ninguna venta coincide.</p>
<div class="flex flex-col gap-2 pb-4">
  <?php if (empty($ventas)): ?><p class="text-sm" style="color:var(--ink-soft)">Todavía no hay ventas.</p><?php endif; ?>
  <?php foreach ($ventas as $v):
    $e = $estadoVenta[$v['estado']] ?? [ucfirst((string) $v['estado']), 'var(--line)', 'var(--ink-soft)'];
    $busq = mb_strtolower($v['numero_factura'] . ' ' . ($v['cliente_nombre'] ?? ''), 'UTF-8'); ?>
    <button type="button" onclick="verVenta(<?= (int) $v['venta_id'] ?>)" class="card !p-3 flex items-center gap-3 text-left venta-card" data-busqueda="<?= esc($busq) ?>">
      <div class="badge-icon text-lg" style="background:var(--purple-light)">🧾</div>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-medium truncate"><?= esc($v['cliente_nombre'] ?: 'Consumidor final') ?></p>
        <p class="text-xs truncate" style="color:var(--ink-soft)"><?= esc($v['numero_factura']) ?> · <?= date('d/m H:i', strtotime($v['fecha'])) ?> · <?= esc($v['forma_pago']) ?><?= !empty($v['tiene_comprobante']) ? ' 📎' : '' ?></p>
      </div>
      <div class="text-right flex-shrink-0">
        <p class="font-mono text-sm font-semibold"><?= '$' . number_format((float) $v['total'], 2) ?></p>
        <span class="text-[10px] px-1.5 py-0.5 rounded" style="background:<?= $e[1] ?>; color:<?= $e[2] ?>"><?= esc($e[0]) ?></span>
      </div>
    </button>
  <?php endforeach; ?>
</div>

<div id="sheet-venta" class="sheet" style="transform:translateY(110%)">
  <div class="sheet-handle"></div>
  <div class="flex items-center justify-between mb-3">
    <h2 class="font-head text-lg font-semibold" id="v-factura">Venta</h2>
    <button type="button" onclick="cerrarSheet('sheet-venta')" class="w-8 h-8 rounded-full flex items-center justify-center" style="background:var(--navy-light); color:var(--navy)">✕</button>
  </div>
  <p class="text-xs mb-3" style="color:var(--ink-soft)" id="v-meta"></p>
  <div id="v-items" class="flex flex-col gap-2 mb-3"></div>
  <div class="rounded-xl p-3 mb-3" style="background:var(--teal-light); color:var(--teal-dark)">
    <div class="flex justify-between text-xs mb-1"><span>Subtotal</span><span class="font-mono" id="v-sub"></span></div>
    <div class="flex justify-between text-xs mb-2"><span>ITBMS</span><span class="font-mono" id="v-itbms"></span></div>
    <div class="flex justify-between items-center"><span class="text-sm font-medium">Total</span><span class="font-mono text-xl font-semibold" id="v-total"></span></div>
  </div>
  <p class="text-xs mb-1" id="v-pagos" style="color:var(--ink-soft)"></p>
  <div id="v-comp-bloque" class="rounded-xl p-3 mb-3 mt-2" style="background:var(--navy-light); display:none">
    <p class="text-xs font-semibold mb-2" style="color:var(--navy)">📎 Comprobante de pago</p>
    <a id="v-comprobante" href="#" target="_blank" style="display:none">
      <img id="v-comp-img" alt="Comprobante de pago" class="w-full max-h-72 object-contain rounded-lg bg-white mb-2">
    </a>
    <p id="v-comp-vacio" class="text-xs mb-2" style="color:var(--ink-soft); display:none">Esta venta no tiene foto del comprobante.</p>
    <div id="v-comp-botones" class="flex items-center gap-2">
      <label class="flex-1 text-center py-2.5 rounded-xl text-sm font-medium cursor-pointer bg-white" style="color:var(--navy)">📷 Tomar foto
        <input type="file" accept="image/*" capture="environment" class="hidden" onchange="adjuntarComprobante(this)">
      </label>
      <label class="flex-1 text-center py-2.5 rounded-xl text-sm font-medium cursor-pointer bg-white" style="color:var(--navy)">🖼️ Galería
        <input type="file" accept="image/*" class="hidden" onchange="adjuntarComprobante(this)">
      </label>
    </div>
  </div>
  <div class="mb-2"></div>
  <button type="button" id="v-agregar" onclick="abrirAgregar()" class="w-full py-3 rounded-xl font-semibold text-center mb-2" style="display:none; background:var(--navy-light); color:var(--navy)">➕ Agregar producto que se quedó fuera</button>
  <button type="button" onclick="if (ventaActual) TicketPOS.imprimirVenta(ventaActual.venta_id)" class="block w-full py-3 rounded-xl font-semibold text-center text-white mb-2" style="background:var(--navy)">🖨️ Imprimir ticket</button>
  <button type="button" onclick="compartirTicket()" class="block w-full py-3 rounded-xl font-medium text-center text-white mb-2" style="background:var(--green)">📤 Compartir ticket (WhatsApp…)</button>
  <?php if (!in_array(\App\Libraries\Auth::rol(), \App\Filters\SoloMovilFilter::ROLES, true)): ?>
  <a id="v-web" href="#" class="block w-full py-3 rounded-xl font-medium text-center" style="background:var(--navy-light); color:var(--navy)">Anular / abonar (versión web)</a>
  <?php endif; ?>
</div>

<!-- Devolver un producto de la venta -->
<div id="sheet-devolver" class="sheet" style="transform:translateY(110%)">
  <div class="sheet-handle"></div>
  <div class="flex items-center justify-between mb-1">
    <h2 class="font-head text-lg font-semibold">↶ Devolver producto</h2>
    <button type="button" onclick="volverAVenta('sheet-devolver')" class="w-8 h-8 rounded-full flex items-center justify-center" style="background:var(--navy-light); color:var(--navy)">✕</button>
  </div>
  <p class="text-sm mb-3" style="color:var(--ink-soft)" id="dv-nombre"></p>
  <label class="block text-xs font-semibold mb-1">Cantidad que devuelve</label>
  <input type="number" id="dv-cantidad" min="0.01" step="any" inputmode="decimal" oninput="dvResumen()" class="w-full px-3 py-3 rounded-xl border text-lg font-semibold mb-1" style="border-color:var(--line)">
  <p class="text-xs mb-3" style="color:var(--ink-soft)" id="dv-max"></p>
  <p class="text-xs font-semibold mb-1">¿Cómo está el producto?</p>
  <label class="flex items-start gap-2 p-3 rounded-xl border mb-2" style="border-color:var(--line)">
    <input type="radio" name="dv-estado" value="0" checked class="mt-1">
    <span class="text-sm"><b>Dañado / no funciona</b><span class="block text-xs" style="color:var(--ink-soft)">No vuelve al inventario de venta (para reclamar al proveedor).</span></span>
  </label>
  <label class="flex items-start gap-2 p-3 rounded-xl border mb-3" style="border-color:var(--line)">
    <input type="radio" name="dv-estado" value="1" class="mt-1">
    <span class="text-sm"><b>En buen estado</b><span class="block text-xs" style="color:var(--ink-soft)">Vuelve al stock y se puede vender otra vez.</span></span>
  </label>
  <label class="block text-xs font-semibold mb-1">Motivo *</label>
  <input type="text" id="dv-motivo" maxlength="200" placeholder="Ej: la estufa no enciende" class="w-full px-3 py-3 rounded-xl border mb-3" style="border-color:var(--line)">
  <div id="dv-reembolso-bloque" class="mb-3">
    <label class="block text-xs font-semibold mb-1">¿Cómo se le devuelve el dinero?</label>
    <select id="dv-reembolso" class="w-full px-3 py-3 rounded-xl border bg-white" style="border-color:var(--line)">
      <option value="EFECTIVO">Efectivo (sale de tu caja)</option>
      <option value="YAPPY">Yappy</option>
      <option value="TRANSFERENCIA">Transferencia</option>
    </select>
  </div>
  <div class="rounded-xl p-3 mb-3 text-sm" style="background:#fff1f2; color:#9f1239" id="dv-resumen"></div>
  <button type="button" id="dv-boton" onclick="dvGuardar()" class="block w-full py-3.5 rounded-xl font-semibold text-white" style="background:#e11d48">Registrar devolución</button>
</div>

<!-- Agregar un producto que se quedó fuera (facturas de hoy) -->
<div id="sheet-agregar" class="sheet" style="transform:translateY(110%)">
  <div class="sheet-handle"></div>
  <div class="flex items-center justify-between mb-1">
    <h2 class="font-head text-lg font-semibold">➕ Agregar producto</h2>
    <button type="button" onclick="volverAVenta('sheet-agregar')" class="w-8 h-8 rounded-full flex items-center justify-center" style="background:var(--navy-light); color:var(--navy)">✕</button>
  </div>
  <p class="text-xs mb-3" style="color:var(--ink-soft)" id="ag-ayuda"></p>
  <input type="search" id="ag-buscar" autocomplete="off" placeholder="Buscar producto o código…" oninput="agBuscar()" class="w-full px-3 py-3 rounded-xl border mb-2" style="border-color:var(--line)">
  <div id="ag-resultados" class="flex flex-col gap-1.5 mb-2 max-h-60 overflow-y-auto"></div>
  <div id="ag-form" style="display:none">
    <p class="text-sm font-semibold mb-2" style="color:var(--navy)" id="ag-elegido"></p>
    <div class="grid grid-cols-2 gap-2 mb-3">
      <div>
        <label class="block text-xs font-semibold mb-1">Cantidad</label>
        <input type="number" id="ag-cantidad" min="0.01" step="any" inputmode="decimal" value="1" oninput="agTotal()" class="w-full px-3 py-3 rounded-xl border text-lg font-semibold" style="border-color:var(--line)">
      </div>
      <div>
        <label class="block text-xs font-semibold mb-1">Presentación</label>
        <select id="ag-unidad" onchange="agTotal()" class="w-full px-3 py-3 rounded-xl border bg-white" style="border-color:var(--line)"></select>
      </div>
    </div>
    <div id="ag-forma-bloque" class="mb-3">
      <label class="block text-xs font-semibold mb-1">¿Cómo paga lo agregado?</label>
      <select id="ag-forma" class="w-full px-3 py-3 rounded-xl border bg-white" style="border-color:var(--line)">
        <option value="EFECTIVO">Efectivo</option><option value="YAPPY">Yappy</option>
        <option value="TRANSFERENCIA">Transferencia</option><option value="TARJETA">Tarjeta</option>
        <option value="CREDITO" id="ag-op-credito">Crédito</option>
      </select>
    </div>
    <p class="text-sm mb-3">Se suma a la factura: <b class="font-mono" id="ag-total">$0.00</b></p>
    <button type="button" id="ag-boton" onclick="agGuardar()" class="block w-full py-3.5 rounded-xl font-semibold text-white" style="background:var(--navy)">Agregar a la factura</button>
  </div>
</div>

<?php $this->endSection(); ?>

<?php $this->section('extra_js'); ?>
<script src="/assets/js/foto-producto.js?v=1"></script>
<script src="/assets/js/ticket.js?v=7"></script>
<script>
document.getElementById('input-busqueda').addEventListener('input', function () {
  const q = this.value.trim().toLowerCase();
  let visibles = 0;
  document.querySelectorAll('.venta-card').forEach(function (c) {
    const ok = !q || c.dataset.busqueda.indexOf(q) !== -1;
    c.style.display = ok ? '' : 'none';
    if (ok) visibles++;
  });
  document.getElementById('msg-vacio').style.display = visibles ? 'none' : 'block';
});

let ventaActual = null;

/** Ticket en texto para enviarlo por WhatsApp u otra app (sin impresora). */
function compartirTicket() {
  const v = ventaActual;
  if (!v) return;
  const lineas = [
    <?= json_encode($empresa_actual['nombre_comercial'] ?? 'Distribuidora') ?> + ' — ' + <?= json_encode($sucursal_actual['nombre'] ?? '') ?>,
    'Factura ' + v.numero_factura + ' · ' + v.fecha,
    'Cliente: ' + (v.cliente_nombre || 'Consumidor final'),
    '',
  ];
  v.items.forEach(function (i) {
    lineas.push(numero(i.cantidad) + (i.unidad_nombre ? ' ' + i.unidad_nombre.toLowerCase() : '') + ' x ' + i.nombre + '  ' + dinero(i.total_linea));
  });
  lineas.push('', 'Subtotal: ' + dinero(v.subtotal), 'ITBMS: ' + dinero(v.itbms), 'TOTAL: ' + dinero(v.total));
  if (v.pagos && v.pagos.length) lineas.push('Pago: ' + v.pagos.map(function (p) { return p.forma_pago + ' ' + dinero(p.monto); }).join(', '));
  if (v.estado === 'anulada') lineas.push('*** ANULADA ***');
  lineas.push('', '¡Gracias por su compra!');
  const texto = lineas.join('\n');
  if (navigator.share) {
    navigator.share({ title: 'Factura ' + v.numero_factura, text: texto }).catch(function () {});
  } else {
    window.open('https://wa.me/?text=' + encodeURIComponent(texto), '_blank');
  }
}

async function verVenta(id) {
  try {
    const v = await api('/m/api/venta/' + id);
    document.getElementById('v-factura').textContent = 'Factura ' + v.numero_factura;
    document.getElementById('v-meta').textContent = (v.cliente_nombre || 'Consumidor final') + ' · ' + v.fecha + (v.estado === 'anulada' ? ' · ANULADA' : '');
    document.getElementById('v-items').innerHTML = v.items.map(function (i, n) {
      const devuelta = Number(i.cantidad_devuelta) || 0;
      const quedan = Math.round((Number(i.cantidad) - devuelta) * 100) / 100;
      return '<div class="card !p-3"><div class="flex justify-between gap-3"><div class="min-w-0"><p class="text-sm truncate' + (quedan <= 0 ? ' line-through' : '') + '">' + esc(i.nombre) + '</p>' +
        '<p class="text-xs font-mono" style="color:var(--ink-soft)">' + numero(i.cantidad) + (i.unidad_nombre ? ' ' + esc(i.unidad_nombre.toLowerCase()) : '') + ' × ' + dinero(i.precio) + '</p>' +
        (devuelta > 0 ? '<span class="inline-block mt-1 px-2 py-0.5 rounded-full text-[11px] font-bold" style="background:#ffe4e6; color:#be123c">Devuelto ' + numero(devuelta) + '</span>' : '') + '</div>' +
        '<p class="font-mono text-sm font-semibold">' + dinero(i.total_linea) + '</p></div>' +
        (v.puede_devolver && quedan > 0 ? '<button type="button" onclick="abrirDevolucion(' + n + ')" class="mt-2 w-full py-2 rounded-lg text-xs font-semibold" style="background:#fff1f2; color:#be123c">↶ Devolver este producto</button>' : '') +
        '</div>';
    }).join('');
    document.getElementById('v-agregar').style.display = v.puede_agregar ? 'block' : 'none';
    document.getElementById('v-sub').textContent = dinero(v.subtotal);
    document.getElementById('v-itbms').textContent = dinero(v.itbms);
    document.getElementById('v-total').textContent = dinero(v.total);
    document.getElementById('v-pagos').textContent = 'Pagos: ' + (v.pagos.length ? v.pagos.map(function (p) { return p.forma_pago + ' ' + dinero(p.monto) + (p.referencia ? ' (ref. ' + p.referencia + ')' : ''); }).join(' · ') : v.forma_pago)
      + (Number(v.vuelto) > 0 ? ' · vuelto en efectivo −' + dinero(v.vuelto) : '')
      + (v.notas && v.notas !== 'Venta desde móvil' ? ' — ' + v.notas.replace(/^Venta desde móvil — /, '') : '');
    mostrarComprobante(v, id);
    const web = document.getElementById('v-web');
    if (web) web.href = '/ventas/venta/' + id;
    ventaActual = v;
    abrirSheet('sheet-venta');
  } catch (e) { toast(e.message, 'error'); }
}

/** Foto del comprobante: se ve si hay; se puede adjuntar o cambiar en ventas con Yappy / transferencia. */
function mostrarComprobante(v, id) {
  const tiene   = Number(v.tiene_comprobante) > 0;
  const digital = (v.pagos || []).some(function (p) { return p.forma_pago === 'YAPPY' || p.forma_pago === 'TRANSFERENCIA'; })
    || ['YAPPY', 'TRANSFERENCIA', 'MIXTO'].indexOf(v.forma_pago) !== -1;
  const puede   = !!v.puede_comprobante;
  document.getElementById('v-comp-bloque').style.display = tiene || (digital && puede) ? 'block' : 'none';
  const url = '/m/api/venta/' + Number(id) + '/comprobante?v=' + encodeURIComponent(v.comprobante_v || '');
  const enlace = document.getElementById('v-comprobante');
  enlace.style.display = tiene ? 'block' : 'none';
  enlace.href = url;
  if (tiene) document.getElementById('v-comp-img').src = url;
  else document.getElementById('v-comp-img').removeAttribute('src');
  document.getElementById('v-comp-vacio').style.display = tiene ? 'none' : 'block';
  document.getElementById('v-comp-botones').style.display = puede ? 'flex' : 'none';
}

async function adjuntarComprobante(input) {
  const f = input.files && input.files[0];
  input.value = '';
  if (!f || !ventaActual) return;
  let archivo = f;
  try { if (window.FotoProducto) archivo = await FotoProducto.reducir(f); } catch (e) { archivo = f; }
  const datos = new FormData();
  datos.append('foto', archivo, 'comprobante.jpg');
  toast('Subiendo comprobante…');
  try {
    const r = await fetch('/m/api/venta/' + ventaActual.venta_id + '/comprobante', {
      method: 'POST', credentials: 'same-origin', body: datos,
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF_TOKEN },
    });
    const res = await r.json().catch(function () { return {}; });
    if (!r.ok) throw new Error(res.error || 'No se pudo subir');
    ventaActual.tiene_comprobante = 1;
    ventaActual.comprobante_v = res.v || String(Date.now());
    mostrarComprobante(ventaActual, ventaActual.venta_id);
    toast('Comprobante guardado', 'success');
  } catch (e) {
    toast('No se pudo subir el comprobante: ' + e.message, 'error');
  }
}

/** Cierra una hoja secundaria y vuelve al detalle de la venta. */
function volverAVenta(hoja) {
  cerrarSheet(hoja);
  if (ventaActual) setTimeout(function () { abrirSheet('sheet-venta'); }, 200);
}

/** Después de devolver o agregar: se recarga el detalle (totales y líneas al día). */
function recargarVenta(hoja, mensaje) {
  cerrarSheet(hoja);
  toast(mensaje, 'success');
  const id = ventaActual.venta_id;
  setTimeout(function () { verVenta(id); }, 250);
}

// ── Devolución parcial de un producto ──
let dvLinea = null;
function abrirDevolucion(n) {
  const i = ventaActual.items[n];
  const quedan = Math.round((Number(i.cantidad) - (Number(i.cantidad_devuelta) || 0)) * 100) / 100;
  dvLinea = { id: Number(i.detalle_id), quedan: quedan, unit: Number(i.total_linea) / Math.max(0.0001, Number(i.cantidad)), um: (i.unidad_nombre || '').toLowerCase() };
  document.getElementById('dv-nombre').textContent = i.nombre + ' · ' + ventaActual.numero_factura;
  document.getElementById('dv-cantidad').value = quedan;
  document.getElementById('dv-max').textContent = 'Se pueden devolver hasta ' + numero(quedan) + (dvLinea.um ? ' ' + dvLinea.um : '');
  document.getElementById('dv-motivo').value = '';
  document.querySelector('input[name="dv-estado"][value="0"]').checked = true;
  document.getElementById('dv-boton').disabled = false;
  dvResumen();
  cerrarSheet('sheet-venta');
  setTimeout(function () { abrirSheet('sheet-devolver'); }, 200);
}
function dvResumen() {
  if (!dvLinea) return;
  const c = Math.min(parseFloat(document.getElementById('dv-cantidad').value) || 0, dvLinea.quedan);
  const monto = Math.round(dvLinea.unit * c * 100) / 100;
  const aCredito = Math.min(monto, Number(ventaActual.falta_pagar) || 0);
  const devolver = Math.round((monto - aCredito) * 100) / 100;
  document.getElementById('dv-reembolso-bloque').style.display = devolver > 0.004 ? '' : 'none';
  document.getElementById('dv-resumen').innerHTML = 'La factura baja <b>' + dinero(monto) + '</b>.'
    + (aCredito > 0 ? '<br>Se descuentan <b>' + dinero(aCredito) + '</b> de lo que debe a crédito.' : '')
    + (devolver > 0.004 ? '<br>Se le devuelven <b>' + dinero(devolver) + '</b> al cliente.' : '');
}
async function dvGuardar() {
  const motivo = document.getElementById('dv-motivo').value.trim();
  const cantidad = parseFloat(document.getElementById('dv-cantidad').value) || 0;
  if (cantidad <= 0 || cantidad > dvLinea.quedan + 0.001) { toast('Cantidad entre 0 y ' + numero(dvLinea.quedan), 'error'); return; }
  if (!motivo) { toast('Escribe el motivo', 'error'); return; }
  const boton = document.getElementById('dv-boton');
  boton.disabled = true;
  try {
    const r = await api('/m/api/venta/' + ventaActual.venta_id + '/devolver', { method: 'POST', body: {
      detalle_id: dvLinea.id, cantidad: cantidad, motivo: motivo,
      reingresa: document.querySelector('input[name="dv-estado"]:checked').value === '1',
      reembolso: document.getElementById('dv-reembolso').value,
    } });
    recargarVenta('sheet-devolver', 'Devolución registrada' + (r.nota ? ' (' + r.nota + ')' : ''));
  } catch (e) { toast(e.message, 'error'); boton.disabled = false; }
}

// ── Agregar un producto que se quedó fuera de la factura ──
let agProducto = null, agTimer = null, agLista = [];
function abrirAgregar() {
  const v = ventaActual;
  const credito = (v.pagos || []).some(function (p) { return p.forma_pago === 'CREDITO'; });
  agProducto = null;
  document.getElementById('ag-buscar').value = '';
  document.getElementById('ag-resultados').innerHTML = '';
  document.getElementById('ag-form').style.display = 'none';
  document.getElementById('ag-boton').disabled = false;
  document.getElementById('ag-ayuda').innerHTML = 'Se cobra al precio de la tienda, sale del inventario y se suma a <b>' + esc(v.numero_factura) + '</b>'
    + (credito ? ' y al <b>crédito</b> del cliente.' : '.');
  document.getElementById('ag-forma-bloque').style.display = credito ? 'none' : '';
  document.getElementById('ag-op-credito').hidden = !v.tiene_cliente;
  document.getElementById('ag-forma').value = 'EFECTIVO';
  cerrarSheet('sheet-venta');
  setTimeout(function () { abrirSheet('sheet-agregar'); document.getElementById('ag-buscar').focus(); }, 200);
}
function agBuscar() {
  clearTimeout(agTimer);
  agTimer = setTimeout(async function () {
    const q = document.getElementById('ag-buscar').value.trim();
    const caja = document.getElementById('ag-resultados');
    if (q.length < 2) { caja.innerHTML = ''; return; }
    try {
      const r = await api('/m/api/productos?q=' + encodeURIComponent(q));
      agLista = (r.productos || []).slice(0, 15);
      caja.innerHTML = agLista.length ? agLista.map(function (p, n) {
        return '<button type="button" onclick="agElegir(' + n + ')" class="card !p-2.5 text-left flex justify-between gap-2"><span class="text-sm min-w-0">' + esc(p.nombre)
          + '<span class="block text-[11px]" style="color:var(--ink-soft)">' + esc(p.codigo) + ' · hay ' + numero(p.existencia) + ' ' + esc(p.um) + '</span></span>'
          + '<span class="font-mono text-sm font-semibold">' + dinero(p.precio_a) + '</span></button>';
      }).join('') : '<p class="text-sm text-center py-2" style="color:var(--ink-soft)">Sin resultados</p>';
    } catch (e) { toast(e.message, 'error'); }
  }, 250);
}
function agElegir(n) {
  const p = agLista[n];
  agProducto = p;
  document.getElementById('ag-resultados').innerHTML = '';
  document.getElementById('ag-buscar').value = p.nombre;
  document.getElementById('ag-elegido').textContent = p.nombre + ' — hay ' + numero(p.existencia) + ' ' + p.um;
  document.getElementById('ag-unidad').innerHTML = '<option value="" data-precio="' + Number(p.precio_a) + '">' + esc(p.um) + ' (' + dinero(p.precio_a) + ')</option>'
    + (p.unidades || []).filter(function (u) { return Number(u.factor) > 1; }).map(function (u) {
      return '<option value="' + u.unidad_id + '" data-precio="' + Number(u.precio) + '">' + esc(u.nombre) + ' ×' + numero(u.factor) + ' (' + dinero(u.precio) + ')</option>';
    }).join('');
  document.getElementById('ag-cantidad').value = 1;
  document.getElementById('ag-form').style.display = 'block';
  agTotal();
}
function agTotal() {
  const sel = document.getElementById('ag-unidad');
  const precio = sel.selectedIndex >= 0 ? Number(sel.options[sel.selectedIndex].dataset.precio) : 0;
  const c = parseFloat(document.getElementById('ag-cantidad').value) || 0;
  document.getElementById('ag-total').textContent = dinero(Math.round(precio * c * 100) / 100);
}
async function agGuardar() {
  if (!agProducto) { toast('Elige el producto', 'error'); return; }
  const cantidad = parseFloat(document.getElementById('ag-cantidad').value) || 0;
  if (cantidad <= 0) { toast('Escribe la cantidad', 'error'); return; }
  const boton = document.getElementById('ag-boton');
  boton.disabled = true;
  const credito = (ventaActual.pagos || []).some(function (p) { return p.forma_pago === 'CREDITO'; });
  try {
    await api('/m/api/venta/' + ventaActual.venta_id + '/agregar', { method: 'POST', body: {
      producto_id: agProducto.producto_id, cantidad: cantidad,
      unidad_id: document.getElementById('ag-unidad').value || null,
      forma_pago: credito ? null : document.getElementById('ag-forma').value,
    } });
    recargarVenta('sheet-agregar', agProducto.nombre + ' agregado a la factura');
  } catch (e) { toast(e.message, 'error'); boton.disabled = false; }
}

// /m/ventas?venta=ID abre directamente el detalle (enlaces del inicio y del ticket de venta)
(function () {
  const id = parseInt(new URLSearchParams(location.search).get('venta'), 10);
  if (id > 0) verVenta(id);
})();
</script>
<?php $this->endSection(); ?>

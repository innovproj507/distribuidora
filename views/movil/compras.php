<?php
use App\Core\View;

View::layout('movil');

$estadoCompra = [
    'recibida'              => ['Recibida',  'var(--green-light)',  'var(--green)'],
    'pendiente'             => ['Pendiente', 'var(--orange-light)', 'var(--orange)'],
    'parcialmente_recibida' => ['Parcial',   'var(--amber-light)',  'var(--amber-dark)'],
    'cancelada'             => ['Cancelada', 'var(--red-light)',    'var(--red)'],
];
$proveedoresJs = array_map(fn ($p) => ['id' => (int) $p['proveedor_id'], 'nombre' => $p['nombre']], $proveedores);
?>
<?php View::section('content'); ?>

<?php if ($puedeCrear): ?>
<button type="button" onclick="abrirNuevaCompra()" class="stat-card stat-teal block w-full text-left mb-4">
  <div class="flex items-center justify-between">
    <div>
      <p class="font-head text-lg font-semibold">Registrar compra</p>
      <p class="text-xs text-white/80 mt-1">Factura de proveedor · entra al inventario</p>
    </div>
    <div class="w-12 h-12 rounded-full bg-white/15 flex items-center justify-center text-2xl">📥</div>
  </div>
</button>
<?php endif; ?>

<h2 class="font-head text-sm font-semibold mb-2">Compras recientes</h2>
<div class="flex flex-col gap-2 pb-4">
  <?php if (empty($compras)): ?>
    <p class="text-sm" style="color:var(--ink-soft)">Todavía no hay compras registradas.</p>
  <?php endif; ?>
  <?php foreach ($compras as $c): $ec = $estadoCompra[$c['estado']] ?? [ucfirst((string) $c['estado']), 'var(--line)', 'var(--ink-soft)']; ?>
    <a href="/compras/<?= (int) $c['compra_id'] ?>" class="card !p-3 flex items-center gap-3">
      <div class="badge-icon text-lg" style="background:var(--teal-light)">🚚</div>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-medium truncate"><?= View::e($c['proveedor_nombre'] ?: 'Sin proveedor') ?></p>
        <p class="text-xs truncate" style="color:var(--ink-soft)">
          <?= View::e($c['numero_factura_proveedor'] ?: $c['numero_factura']) ?> · <?= $c['fecha_compra'] ? date('d/m/Y', strtotime($c['fecha_compra'])) : '' ?> · <?= (int) $c['items'] ?> ítem(s)
        </p>
      </div>
      <div class="text-right flex-shrink-0">
        <p class="font-mono text-sm font-semibold"><?= '$' . number_format((float) $c['monto_total'], 2) ?></p>
        <span class="text-[10px] px-1.5 py-0.5 rounded" style="background:<?= $ec[1] ?>; color:<?= $ec[2] ?>"><?= View::e($ec[0]) ?></span>
      </div>
    </a>
  <?php endforeach; ?>
</div>

<?php if ($puedeCrear): ?>
<!-- Sheet: nueva compra (mismo flujo que compras.php de arango) -->
<div id="sheet-compra" class="sheet" style="transform:translateY(110%); max-height:94vh">
  <div class="sheet-handle"></div>
  <div class="flex items-center justify-between mb-3">
    <h2 class="font-head text-lg font-semibold">Nueva compra</h2>
    <button type="button" onclick="cerrarSheet('sheet-compra')" class="w-8 h-8 rounded-full flex items-center justify-center" style="background:var(--navy-light); color:var(--navy)">✕</button>
  </div>

  <div class="grid grid-cols-2 gap-3 mb-3">
    <div>
      <label class="field-label">Proveedor</label>
      <input class="field-input" style="font-family:'Inter'" id="compra-proveedor" list="lista-proveedores" placeholder="Nombre" autocomplete="off">
      <datalist id="lista-proveedores">
        <?php foreach ($proveedores as $prov): ?>
          <option value="<?= View::e($prov['nombre']) ?>">
        <?php endforeach; ?>
      </datalist>
    </div>
    <div>
      <label class="field-label">N.º de factura</label>
      <input class="field-input" style="font-family:'Inter'" id="compra-factura" placeholder="Opcional">
    </div>
  </div>

  <div class="mb-2 flex items-center gap-2 bg-white rounded-xl border px-3 py-2" style="border-color:var(--line)">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6B7794" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
    <input type="search" id="compra-busqueda" class="flex-1 text-sm outline-none bg-transparent" placeholder="Buscar producto para agregar" autocomplete="off">
  </div>
  <div class="flex flex-col gap-2 mb-3 overflow-y-auto" id="compra-productos" style="max-height:24vh"></div>

  <div class="flex items-center justify-between mb-2">
    <h3 class="font-head text-sm font-semibold">Factura en curso</h3>
    <span class="font-mono text-sm font-semibold">Total: <span id="compra-total">$0.00</span></span>
  </div>
  <div id="compra-items" class="flex flex-col gap-2 mb-2"></div>
  <p id="compra-vacio" class="text-sm text-center py-3" style="color:var(--ink-soft)">Toca un producto para agregarlo.</p>
  <p class="text-xs mb-4" style="color:var(--ink-soft)">Costo unitario tal como viene en la factura. La compra se registra como <b>recibida</b> y suma al inventario del depósito actual.</p>

  <button type="button" id="btn-registrar-compra" onclick="registrarCompra()" class="btn-primario mb-2" style="background:var(--teal)">Registrar compra</button>
</div>
<?php endif; ?>

<?php View::endSection('content'); ?>

<?php if ($puedeCrear): ?>
<?php View::section('extra_js'); ?>
<script>
const DEPOSITO_ID = <?= json_encode((int) ($deposito_actual['deposito_id'] ?? 0)) ?>;
const PROVEEDORES = <?= json_encode($proveedoresJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
const compra = {}; // producto_id → { p, cantidad, costo }

const listaCompra = new ListaProductos({
  contenedor: 'compra-productos',
  filtros: function () { return { q: document.getElementById('compra-busqueda').value.trim() }; },
  renderItem: function (p) {
    return '<button type="button" onclick="agregarACompra(' + p.producto_id + ')" class="card flex gap-3 !p-2.5 text-left">' +
      htmlAvatarProducto(p, 'w-10 h-10') +
      '<div class="flex-1 min-w-0">' +
        '<p class="text-sm font-medium truncate">' + esc(p.nombre) + '</p>' +
        '<p class="text-xs" style="color:var(--ink-soft)">' + esc(p.codigo || '') + ' · ' + numero(p.existencia) + ' uds' +
          (p.costo !== undefined ? ' · costo ' + dinero(p.costo) : '') + '</p>' +
      '</div>' +
    '</button>';
  },
});

function abrirNuevaCompra() {
  abrirSheet('sheet-compra');
  if (!listaCompra.offset) listaCompra.cargar(true);
}

function agregarACompra(id) {
  const p = listaCompra.get(id);
  if (!p) return;
  if (p.maneja_lotes) {
    toast('Este producto maneja lotes; registra la compra desde la versión web.', 'error');
    return;
  }
  if (compra[id]) {
    compra[id].cantidad += 1;
  } else {
    compra[id] = { p: p, cantidad: 1, costo: p.costo || 0 };
  }
  renderCompra();
}

function quitarDeCompra(id) {
  delete compra[id];
  renderCompra();
}

function actualizarLinea(id, campo, valor) {
  compra[id][campo] = parseFloat(valor) || 0;
  document.getElementById('linea-' + id).textContent = dinero(compra[id].cantidad * compra[id].costo);
  document.getElementById('compra-total').textContent = dinero(totalCompra());
}

function totalCompra() {
  return Object.keys(compra).reduce(function (s, id) { return s + compra[id].cantidad * compra[id].costo; }, 0);
}

function renderCompra() {
  const ids = Object.keys(compra);
  document.getElementById('compra-vacio').style.display = ids.length ? 'none' : 'block';
  let html = '';
  ids.forEach(function (id) {
    const it = compra[id];
    html += '<div class="card !p-3">' +
      '<div class="flex items-center justify-between mb-2">' +
        '<p class="text-sm font-medium truncate pr-2">' + esc(it.p.nombre) + '</p>' +
        '<button type="button" onclick="quitarDeCompra(' + id + ')" class="text-xs flex-shrink-0" style="color:var(--red)">Quitar</button>' +
      '</div>' +
      '<div class="grid grid-cols-3 gap-2 items-end">' +
        '<div><label class="field-label">Cantidad</label>' +
          '<input type="number" inputmode="decimal" min="0.01" step="any" class="field-input !py-2" value="' + it.cantidad + '" oninput="actualizarLinea(' + id + ', \'cantidad\', this.value)"></div>' +
        '<div><label class="field-label">Costo unit.</label>' +
          '<input type="number" inputmode="decimal" min="0" step="0.01" class="field-input !py-2" value="' + it.costo + '" oninput="actualizarLinea(' + id + ', \'costo\', this.value)"></div>' +
        '<p class="font-mono text-sm font-semibold text-right pb-2" id="linea-' + id + '">' + dinero(it.cantidad * it.costo) + '</p>' +
      '</div>' +
    '</div>';
  });
  document.getElementById('compra-items').innerHTML = html;
  document.getElementById('compra-total').textContent = dinero(totalCompra());
}

/** Devuelve el proveedor_id; si el nombre no existe lo crea con el endpoint de compras web. */
async function resolverProveedor() {
  const nombre = document.getElementById('compra-proveedor').value.trim();
  if (!nombre) return null;
  const existente = PROVEEDORES.find(function (p) { return p.nombre.toLowerCase() === nombre.toLowerCase(); });
  if (existente) return existente.id;
  const res = await api('/api/compras/proveedor-rapido', { method: 'POST', body: { nombre: nombre } });
  PROVEEDORES.push({ id: res.proveedor.id, nombre: res.proveedor.nombre });
  return res.proveedor.id;
}

async function registrarCompra() {
  const ids = Object.keys(compra);
  if (!ids.length) { toast('Agrega al menos un producto.', 'error'); return; }
  for (let i = 0; i < ids.length; i++) {
    if (compra[ids[i]].cantidad <= 0) { toast('Revisa las cantidades: deben ser mayores que cero.', 'error'); return; }
  }

  const boton = document.getElementById('btn-registrar-compra');
  boton.disabled = true;
  boton.textContent = 'Registrando…';
  try {
    const proveedorId = await resolverProveedor();
    const total = Math.round(totalCompra() * 100) / 100;
    const res = await api('/compras/guardar', {
      method: 'POST',
      body: {
        proveedor_id: proveedorId,
        numero_factura: document.getElementById('compra-factura').value.trim(),
        fecha_compra: new Date().toLocaleDateString('en-CA'),
        notas: 'Compra desde móvil',
        subtotal: total,
        itbms: 0,
        total: total,
        items: ids.map(function (id) {
          return {
            producto_id: parseInt(id, 10),
            cantidad: compra[id].cantidad,
            costo: compra[id].costo,
            deposito_id: DEPOSITO_ID,
          };
        }),
      },
    });
    toast('Compra #' + res.compra_id + ' registrada.');
    setTimeout(function () { window.location.reload(); }, 900);
  } catch (e) {
    toast(e.message, 'error');
    boton.disabled = false;
    boton.textContent = 'Registrar compra';
  }
}

document.getElementById('compra-busqueda').addEventListener('input', debounce(function () { listaCompra.cargar(true); }, 300));
</script>
<?php View::endSection('extra_js'); ?>
<?php endif; ?>

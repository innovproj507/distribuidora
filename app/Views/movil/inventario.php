<?php
use App\Controllers\MovilController;
$this->extend('layouts/movil');
?>
<?php $this->section('content'); ?>

<div class="mb-1 flex items-center gap-2 bg-white rounded-xl border px-3 py-2" style="border-color:var(--line)">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6B7794" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
  <input type="search" id="input-busqueda" class="flex-1 text-sm outline-none bg-transparent" placeholder="Buscar producto o código" autocomplete="off">
</div>

<div class="flex gap-2 overflow-x-auto py-3">
  <button type="button" id="chip-todos" onclick="filtrarStockBajo(false)" class="text-xs px-3 py-1.5 rounded-full whitespace-nowrap">Todos</button>
  <button type="button" id="chip-bajo" onclick="filtrarStockBajo(true)" class="text-xs px-3 py-1.5 rounded-full whitespace-nowrap">Stock bajo</button>
</div>

<?php if (!empty($categorias)): ?>
<div class="flex gap-2 overflow-x-auto pb-3" id="chips-categoria">
  <button type="button" class="chip activo" data-categoria="" onclick="filtrarCategoria('')">Todas</button>
  <?php foreach ($categorias as $c): $icono = MovilController::iconoCategoria($c['nombre']); ?>
    <button type="button" class="chip flex items-center gap-1.5" data-categoria="<?= (int) $c['categoria_id'] ?>" onclick="filtrarCategoria('<?= (int) $c['categoria_id'] ?>')">
      <span><?= $icono['emoji'] ?></span> <?= esc($c['nombre']) ?> <span class="opacity-60">· <?= (int) $c['total'] ?></span>
    </button>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="flex flex-col gap-2.5 pb-4" id="lista-productos"></div>

<?php if ($puedeAjustar): ?>
<!-- Sheet: registrar entrada / salida (mismo diseño que sheet-movimiento.php de arango) -->
<div id="sheet-movimiento" class="sheet" style="transform:translateY(110%)">
  <div class="sheet-handle"></div>
  <div class="flex items-center justify-between mb-1">
    <h2 class="font-head text-lg font-semibold">Registrar movimiento</h2>
    <button type="button" onclick="cerrarSheet('sheet-movimiento')" class="w-8 h-8 rounded-full flex items-center justify-center" style="background:var(--navy-light); color:var(--navy)">✕</button>
  </div>
  <p class="text-sm mb-4" style="color:var(--ink-soft)">
    <span id="mov-producto-nombre" class="font-medium" style="color:var(--ink)"></span>
    · stock actual: <span id="mov-producto-stock" class="font-mono"></span>
  </p>

  <form onsubmit="return guardarMovimiento(event)">
    <div class="flex gap-2 mb-4">
      <button type="button" id="mov-btn-entrada" onclick="elegirTipoMovimiento('entrada')" class="flex-1 py-2.5 rounded-xl text-sm font-medium border">+ Entrada</button>
      <button type="button" id="mov-btn-salida" onclick="elegirTipoMovimiento('salida')" class="flex-1 py-2.5 rounded-xl text-sm font-medium border">− Salida</button>
    </div>

    <label class="field-label">Cantidad</label>
    <input class="field-input mb-3" type="number" inputmode="decimal" id="mov-cantidad" min="0.01" step="any" required>

    <label class="field-label">Motivo (opcional)</label>
    <input class="field-input mb-5" style="font-family:'Inter'" id="mov-motivo" placeholder="Ej: Merma, Devolución, Conteo">

    <button type="submit" id="mov-guardar" class="btn-primario">Guardar movimiento</button>
  </form>
</div>
<?php endif; ?>

<?php if ($puedeEditar): ?>
<!-- Sheet: presentaciones (unidad, docena, caja…) del producto -->
<div id="sheet-presentaciones" class="sheet" style="transform:translateY(110%)">
  <div class="sheet-handle"></div>
  <div class="flex items-center justify-between mb-1">
    <h2 class="font-head text-lg font-semibold truncate pr-2" id="pp-nombre">Presentaciones</h2>
    <button type="button" onclick="cerrarSheet('sheet-presentaciones')" class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0" style="background:var(--navy-light); color:var(--navy)">✕</button>
  </div>
  <p class="text-xs mb-3" style="color:var(--ink-soft)">Cómo se compra o vende este producto además de por unidad (docena, caja, bulto…). El inventario siempre se cuenta en unidades.</p>
  <div id="pp-lista" class="flex flex-col gap-2 mb-4"></div>

  <p class="field-label">Agregar presentación</p>
  <div class="grid grid-cols-2 gap-2 mb-2">
    <input id="pp-nueva-nombre" class="field-input" style="font-family:'Inter'" placeholder="Nombre (Caja, Docena…)" list="pp-sugerencias">
    <input id="pp-nueva-factor" type="number" inputmode="decimal" min="0" step="any" class="field-input" placeholder="Unidades que trae">
  </div>
  <datalist id="pp-sugerencias"><option value="Docena"><option value="Caja"><option value="Bulto"><option value="Paca"><option value="Saco"></datalist>
  <input id="pp-nueva-precio" type="number" inputmode="decimal" min="0" step="0.01" class="field-input mb-3" placeholder="Precio de venta (vacío = unidad × cantidad)">
  <button type="button" onclick="agregarPresentacion()" class="btn-primario">Agregar</button>
</div>
<?php endif; ?>

<?php $this->endSection(); ?>

<?php $this->section('extra_js'); ?>
<script>
const PUEDE_AJUSTAR = <?= json_encode($puedeAjustar) ?>;
const PUEDE_EDITAR  = <?= json_encode($puedeEditar) ?>;

let soloStockBajo = <?= json_encode($soloStockBajo) ?>;
let categoriaActual = '';
let movProductoId = null;
let movTipo = 'entrada';
let presProductoId = null;

// ── Presentaciones (caja, docena…) ──────────────────────────────────────────
function pintarPresentaciones(p) {
  document.getElementById('pp-nombre').textContent = p.nombre;
  document.getElementById('pp-lista').innerHTML = (p.unidades || []).map(function (u) {
    return '<div class="card !p-3 flex items-center justify-between gap-2">' +
      '<span class="text-sm"><span class="font-medium">' + esc(u.nombre) + '</span> <span class="text-xs" style="color:var(--ink-soft)">trae ' + numero(u.factor) + '</span></span>' +
      '<span class="font-mono text-sm">' + dinero(u.precio) + '</span>' +
      '<button type="button" onclick="quitarPresentacion(' + u.unidad_id + ')" class="w-7 h-7 rounded-full text-sm" style="background:var(--red-light); color:var(--red)" title="Quitar">✕</button></div>';
  }).join('') || '<p class="text-xs" style="color:var(--ink-soft)">Solo se vende por unidad.</p>';
}

function abrirPresentaciones(id) {
  const p = lista.get(id);
  if (!p) return;
  presProductoId = id;
  document.getElementById('pp-nueva-nombre').value = '';
  document.getElementById('pp-nueva-factor').value = '';
  document.getElementById('pp-nueva-precio').value = '';
  pintarPresentaciones(p);
  abrirSheet('sheet-presentaciones');
}

async function agregarPresentacion() {
  const nombre = document.getElementById('pp-nueva-nombre').value.trim();
  const factor = parseFloat(document.getElementById('pp-nueva-factor').value);
  if (!nombre || !(factor > 0)) { toast('Escribe el nombre y cuántas unidades trae.', 'error'); return; }
  try {
    const r = await api('/m/api/producto/' + presProductoId + '/presentacion', { method: 'POST', body: {
      nombre: nombre, factor: factor, precio: parseFloat(document.getElementById('pp-nueva-precio').value) || 0,
    }});
    const p = lista.get(presProductoId);
    p.unidades = r.unidades;
    pintarPresentaciones(p);
    document.getElementById('pp-nueva-nombre').value = '';
    document.getElementById('pp-nueva-factor').value = '';
    document.getElementById('pp-nueva-precio').value = '';
    toast('Presentación agregada.');
  } catch (e) { toast(e.message, 'error'); }
}

async function quitarPresentacion(unidadId) {
  try {
    const r = await api('/m/api/presentacion/' + unidadId + '/eliminar', { method: 'POST', body: {} });
    const p = lista.get(presProductoId);
    p.unidades = r.unidades;
    pintarPresentaciones(p);
  } catch (e) { toast(e.message, 'error'); }
}

const lista = new ListaProductos({
  contenedor: 'lista-productos',
  filtros: function () {
    return {
      q: document.getElementById('input-busqueda').value.trim(),
      categoria: categoriaActual,
      stock_bajo: soloStockBajo ? '1' : '',
    };
  },
  renderItem: function (p) {
    const estado = estadoStock(p);
    const colores = coloresStock(estado);
    const etiqueta = estado === 'agotado' ? 'Agotado' : numero(p.existencia) + ' uds';
    let detalle = esc(p.codigo || '') + (p.ubicacion ? ' · 📍 ' + esc(p.ubicacion) : '');
    if (PUEDE_EDITAR && p.costo !== undefined) detalle += ' · Costo ' + dinero(p.costo);

    const contenido =
      htmlAvatarProducto(p, 'w-14 h-14') +
      '<div class="flex-1 min-w-0">' +
        '<div class="flex justify-between items-start">' +
          '<p class="text-sm font-medium truncate pr-2">' + esc(p.nombre) + '</p>' +
          '<p class="font-mono text-sm font-semibold">' + dinero(p.precio_a) + '</p>' +
        '</div>' +
        '<p class="text-xs truncate" style="color:var(--ink-soft)">' + detalle + '</p>' +
        '<div class="flex items-center gap-2 mt-2">' +
          '<div class="stockbar flex-1"><div style="width:' + porcentajeStock(p) + '%; background:' + colores.bar + '"></div></div>' +
          '<span class="font-mono text-xs" style="color:' + colores.text + '">' + etiqueta + '</span>' +
        '</div>' +
      '</div>';

    // Editar el producto (precios, datos) se hace en la versión web
    const cuerpo = PUEDE_EDITAR
      ? '<a href="/inventario/' + p.producto_id + '/editar" class="text-left flex gap-3 flex-1 min-w-0">' + contenido + '</a>'
      : '<div class="text-left flex gap-3 flex-1 min-w-0">' + contenido + '</div>';

    const botonMov = PUEDE_AJUSTAR
      ? '<button type="button" onclick="abrirMovimiento(' + p.producto_id + ')" class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 self-center" style="background:var(--navy-light); color:var(--navy)" title="Registrar entrada/salida">' +
          '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20V4M6 10l6-6 6 6M6 14l6 6 6-6"/></svg>' +
        '</button>'
      : '';

    const botonPres = PUEDE_EDITAR
      ? '<button type="button" onclick="abrirPresentaciones(' + p.producto_id + ')" class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 self-center text-base" style="background:var(--amber-light)" title="Presentaciones (caja, docena…)">📦</button>'
      : '';

    return '<div class="card flex gap-3 !p-3" id="prod-' + p.producto_id + '">' + cuerpo + botonPres + botonMov + '</div>';
  },
});

function pintarChipsStock() {
  const todos = document.getElementById('chip-todos');
  const bajo = document.getElementById('chip-bajo');
  todos.className = 'text-xs px-3 py-1.5 rounded-full whitespace-nowrap ' + (soloStockBajo ? 'bg-white border' : 'text-white');
  todos.style.cssText = soloStockBajo ? 'border-color:var(--line)' : 'background:var(--navy)';
  bajo.className = 'text-xs px-3 py-1.5 rounded-full whitespace-nowrap';
  bajo.style.cssText = soloStockBajo ? 'background:var(--orange); color:white' : 'background:var(--orange-light); color:var(--orange)';
}

function filtrarStockBajo(valor) {
  soloStockBajo = valor;
  pintarChipsStock();
  lista.cargar(true);
}

function filtrarCategoria(id) {
  categoriaActual = id;
  document.querySelectorAll('#chips-categoria .chip').forEach(function (b) {
    b.classList.toggle('activo', b.dataset.categoria === id);
  });
  lista.cargar(true);
}

// ── Movimientos ──────────────────────────────────────────────────────────────

function abrirMovimiento(id) {
  const p = lista.get(id);
  if (!p) return;
  if (p.maneja_lotes) {
    toast('Este producto maneja lotes; registra el movimiento desde la versión web.', 'error');
    return;
  }
  movProductoId = id;
  document.getElementById('mov-producto-nombre').textContent = p.nombre;
  document.getElementById('mov-producto-stock').textContent = numero(p.existencia);
  document.getElementById('mov-cantidad').value = '';
  document.getElementById('mov-motivo').value = '';
  elegirTipoMovimiento('entrada');
  abrirSheet('sheet-movimiento');
}

function elegirTipoMovimiento(tipo) {
  movTipo = tipo;
  const entrada = document.getElementById('mov-btn-entrada');
  const salida = document.getElementById('mov-btn-salida');
  entrada.style.cssText = tipo === 'entrada'
    ? 'background:var(--green-light); color:var(--green); border-color:var(--green)'
    : 'background:white; color:var(--ink-soft); border-color:var(--line)';
  salida.style.cssText = tipo === 'salida'
    ? 'background:var(--red-light); color:var(--red); border-color:var(--red)'
    : 'background:white; color:var(--ink-soft); border-color:var(--line)';
}

async function guardarMovimiento(ev) {
  ev.preventDefault();
  const boton = document.getElementById('mov-guardar');
  boton.disabled = true;
  try {
    const res = await api('/m/api/movimiento', {
      method: 'POST',
      body: {
        producto_id: movProductoId,
        tipo: movTipo,
        cantidad: parseFloat(document.getElementById('mov-cantidad').value),
        motivo: document.getElementById('mov-motivo').value.trim(),
      },
    });
    const p = lista.get(movProductoId);
    p.existencia = res.existencia;
    document.getElementById('prod-' + p.producto_id).outerHTML = lista.opciones.renderItem(p);
    cerrarSheet('sheet-movimiento');
    toast('Movimiento registrado. Stock: ' + numero(res.existencia));
  } catch (e) {
    toast(e.message, 'error');
  } finally {
    boton.disabled = false;
  }
  return false;
}

document.getElementById('input-busqueda').addEventListener('input', debounce(function () { lista.cargar(true); }, 300));

pintarChipsStock();
lista.cargar(true);
</script>
<?php $this->endSection(); ?>

<?php
use App\Controllers\MovilController;
use App\Core\View;

View::layout('movil');
?>
<?php View::section('content'); ?>

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
      <span><?= $icono['emoji'] ?></span> <?= View::e($c['nombre']) ?> <span class="opacity-60">· <?= (int) $c['total'] ?></span>
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

<?php View::endSection('content'); ?>

<?php View::section('extra_js'); ?>
<script>
const PUEDE_AJUSTAR = <?= json_encode($puedeAjustar) ?>;
const PUEDE_EDITAR  = <?= json_encode($puedeEditar) ?>;

let soloStockBajo = <?= json_encode($soloStockBajo) ?>;
let categoriaActual = '';
let movProductoId = null;
let movTipo = 'entrada';

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
    let detalle = esc(p.codigo || '');
    if (p.precio_b > 0 && p.precio_b !== p.precio_a) detalle += ' · B ' + dinero(p.precio_b);
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

    return '<div class="card flex gap-3 !p-3" id="prod-' + p.producto_id + '">' + cuerpo + botonMov + '</div>';
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
<?php View::endSection('extra_js'); ?>

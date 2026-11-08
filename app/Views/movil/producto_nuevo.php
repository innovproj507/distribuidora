<?php
$this->extend('layouts/movil');
$v = fn (string $campo, $def = '') => old($campo, $def);
?>
<?php $this->section('content'); ?>

<a href="/m/inventario" class="inline-flex items-center gap-1 text-xs mb-3 px-3 py-1.5 rounded-full bg-white border" style="border-color:var(--line); color:var(--ink-soft)">← Inventario</a>

<form method="POST" action="/m/productos/nuevo" enctype="multipart/form-data" onsubmit="this.querySelector('[type=submit]').disabled = true">
  <?= csrf_field() ?>

  <div class="card mb-4">
    <label class="field-label">Nombre *</label>
    <input class="field-input mb-3" style="font-family:'Inter'" name="nombre" required maxlength="200" autofocus value="<?= esc($v('nombre'), 'attr') ?>" placeholder="Ej. Aceite Mazola 1 L">

    <label class="field-label">Categoría</label>
    <select class="field-input mb-3" style="font-family:'Inter'" name="categoria_id" id="f-categoria" onchange="generarCodigo()">
      <option value="">Sin categoría</option>
      <?php foreach ($categorias as $c): ?>
        <option value="<?= (int) $c['categoria_id'] ?>" <?= (int) $v('categoria_id') === (int) $c['categoria_id'] ? 'selected' : '' ?>><?= esc($c['nombre']) ?></option>
      <?php endforeach; ?>
    </select>

    <div class="grid grid-cols-2 gap-2">
      <div>
        <label class="field-label">Código</label>
        <input class="field-input mb-1" name="codigo" id="f-codigo" maxlength="50" value="<?= esc($v('codigo', $codigo), 'attr') ?>" oninput="codigoManual = true">
        <p class="text-[10px] mb-2" style="color:var(--ink-soft)">Se genera según la categoría</p>
      </div>
      <div>
        <label class="field-label">Código de barras</label>
        <div class="flex gap-1">
          <input class="field-input mb-3 flex-1 min-w-0" name="codigo_barras" id="f-barras" inputmode="numeric" maxlength="100" value="<?= esc($v('codigo_barras', $codigoBarras), 'attr') ?>">
          <button type="button" id="btn-escanear" onclick="escanear()" class="w-10 h-10 rounded-xl flex-shrink-0" style="background:var(--navy-light); color:var(--navy)" title="Escanear con la cámara">📷</button>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-2">
      <div>
        <label class="field-label">Marca</label>
        <input class="field-input mb-3" style="font-family:'Inter'" name="marca" list="lista-marcas" maxlength="100" value="<?= esc($v('marca'), 'attr') ?>">
        <datalist id="lista-marcas"><?php foreach ($marcas as $m): ?><option value="<?= esc($m, 'attr') ?>"><?php endforeach; ?></datalist>
      </div>
      <div>
        <label class="field-label">Proveedor</label>
        <select class="field-input mb-3" style="font-family:'Inter'" name="proveedor_id">
          <option value="">—</option>
          <?php foreach ($proveedores as $pr): ?>
            <option value="<?= (int) $pr['proveedor_id'] ?>" <?= (int) $v('proveedor_id') === (int) $pr['proveedor_id'] ? 'selected' : '' ?>><?= esc($pr['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="grid <?= ver_costos() ? 'grid-cols-2' : 'grid-cols-1' ?> gap-2">
      <div>
        <label class="field-label">Precio de venta</label>
        <input class="field-input mb-1" type="number" inputmode="decimal" step="0.01" min="0" name="precio" id="f-precio" value="<?= esc((string) $v('precio'), 'attr') ?>" oninput="margen()">
      </div>
      <?php if (ver_costos()): ?>
      <div>
        <label class="field-label">Costo</label>
        <input class="field-input mb-1" type="number" inputmode="decimal" step="0.0001" min="0" name="costo" id="f-costo" value="<?= esc((string) $v('costo'), 'attr') ?>" oninput="margen()">
      </div>
      <?php endif; ?>
    </div>
    <p class="text-xs" id="f-margen" style="color:var(--ink-soft)"></p>
    <?php if (!ver_costos()): ?><p class="text-[11px]" style="color:var(--ink-soft)">El costo lo pone el gerente o el dueño después.</p><?php endif; ?>
  </div>

  <div class="card mb-4">
    <p class="text-xs mb-3" style="color:var(--ink-soft)">En <b><?= esc($deposito_actual['nombre'] ?? 'este depósito') ?></b>:</p>
    <div class="grid grid-cols-3 gap-2">
      <div>
        <label class="field-label">Hay ahora</label>
        <input class="field-input mb-1" type="number" inputmode="decimal" step="any" min="0" name="stock" value="<?= esc((string) $v('stock', '0'), 'attr') ?>">
      </div>
      <div>
        <label class="field-label">Mínimo</label>
        <input class="field-input mb-1" type="number" inputmode="decimal" step="any" min="0" name="minimo" value="<?= esc((string) $v('minimo', '0'), 'attr') ?>">
      </div>
      <div>
        <label class="field-label">Estante</label>
        <input class="field-input mb-1" style="font-family:'Inter'" name="ubicacion" maxlength="100" placeholder="A-3" value="<?= esc($v('ubicacion'), 'attr') ?>">
      </div>
    </div>
    <p class="text-[11px] mt-1" style="color:var(--ink-soft)">El stock inicial queda en el kardex. En las otras tiendas el producto aparece con 0.</p>
  </div>

  <div class="card mb-4">
    <label class="field-label">Foto</label>
    <div class="flex items-center gap-3">
      <img alt="" class="w-14 h-14 rounded-xl object-cover hidden" id="f-foto-prev">
      <input type="file" name="imagen" accept="image/*" capture="environment" class="text-xs flex-1" onchange="previa(this)">
    </div>
  </div>

  <button type="submit" class="btn-primario mb-6">Crear producto</button>
</form>

<?php $this->endSection(); ?>

<?php $this->section('extra_js'); ?>
<script src="/assets/js/foto-producto.js?v=1"></script>
<script src="/assets/js/escaner.js?v=1"></script>
<script>
let codigoManual = <?= json_encode(old('codigo') !== null && old('codigo') !== $codigo) ?>;

async function generarCodigo() {
  if (codigoManual) return;
  try {
    const r = await fetch('/api/inventario/siguiente-codigo?categoria_id=' + encodeURIComponent(document.getElementById('f-categoria').value), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const d = await r.json();
    if (d.codigo && !codigoManual) document.getElementById('f-codigo').value = d.codigo;
  } catch (e) { /* sin red: el servidor genera el código si queda vacío */ }
}

function margen() {
  const precio = parseFloat(document.getElementById('f-precio').value) || 0;
  const campoCosto = document.getElementById('f-costo');
  const costo = campoCosto ? (parseFloat(campoCosto.value) || 0) : 0;
  document.getElementById('f-margen').textContent = precio > 0 && costo > 0
    ? 'Ganancia ' + dinero(precio - costo) + ' por unidad (' + ((precio - costo) * 100 / precio).toFixed(1) + '%)'
    : (precio <= 0 ? 'Sin precio no se puede vender.' : '');
}

async function previa(input) {
  if (!input.files || !input.files[0]) return;
  const reducido = await FotoProducto.reducir(input.files[0]);
  const dt = new DataTransfer();
  dt.items.add(reducido);
  input.files = dt.files;
  const img = document.getElementById('f-foto-prev');
  img.src = URL.createObjectURL(reducido);
  img.classList.remove('hidden');
}

// ── Escanear código de barras (escaner.js: nativo o librería, sirve en cualquier celular) ──
function escanear() {
  Escaner.abrir(function (codigo) { document.getElementById('f-barras').value = codigo; });
}

margen();
</script>
<?php $this->endSection(); ?>

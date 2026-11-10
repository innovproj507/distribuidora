<?php
$this->extend('layouts/movil');
$id = (int) $p['producto_id'];
$v  = fn (string $campo, $def = '') => old($campo, $def);
?>
<?php $this->section('content'); ?>

<a href="/m/productos/<?= $id ?>" class="inline-flex items-center gap-1 text-xs mb-3 px-3 py-1.5 rounded-full bg-white border" style="border-color:var(--line); color:var(--ink-soft)">← Volver al producto</a>

<form method="POST" action="/m/productos/<?= $id ?>/editar" enctype="multipart/form-data" onsubmit="this.querySelector('[type=submit]').disabled = true">
  <?= csrf_field() ?>

  <div class="card mb-4">
    <label class="field-label">Nombre *</label>
    <input class="field-input mb-3" style="font-family:'Inter'" name="nombre" required maxlength="200" value="<?= esc($v('nombre', $p['nombre']), 'attr') ?>">

    <?php if ($completo): ?>
    <div class="grid grid-cols-2 gap-2">
      <div>
        <label class="field-label">Código *</label>
        <input class="field-input mb-3" name="codigo" required maxlength="50" value="<?= esc($v('codigo', $p['codigo']), 'attr') ?>">
      </div>
      <div>
        <label class="field-label">Código de barras</label>
        <input class="field-input mb-3" name="codigo_barras" inputmode="numeric" maxlength="100" value="<?= esc($v('codigo_barras', $p['codigo_barras'] ?? ''), 'attr') ?>">
      </div>
    </div>

    <label class="field-label">Categoría</label>
    <select class="field-input mb-3" style="font-family:'Inter'" name="categoria_id">
      <option value="">Sin categoría</option>
      <?php foreach ($categorias as $c): ?>
        <option value="<?= (int) $c['categoria_id'] ?>" <?= (int) $v('categoria_id', $p['categoria_id']) === (int) $c['categoria_id'] ? 'selected' : '' ?>><?= esc($c['nombre']) ?></option>
      <?php endforeach; ?>
    </select>

    <div class="grid grid-cols-2 gap-2">
      <div>
        <label class="field-label">Marca</label>
        <input class="field-input mb-3" style="font-family:'Inter'" name="marca" list="lista-marcas" maxlength="100" value="<?= esc($v('marca', $p['marca'] ?? ''), 'attr') ?>">
        <datalist id="lista-marcas"><?php foreach ($marcas as $m): ?><option value="<?= esc($m, 'attr') ?>"><?php endforeach; ?></datalist>
      </div>
      <div>
        <label class="field-label">Proveedor</label>
        <select class="field-input mb-3" style="font-family:'Inter'" name="proveedor_id">
          <option value="">—</option>
          <?php foreach ($proveedores as $pr): ?>
            <option value="<?= (int) $pr['proveedor_id'] ?>" <?= (int) $v('proveedor_id', $p['proveedor_id']) === (int) $pr['proveedor_id'] ? 'selected' : '' ?>><?= esc($pr['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <?php else: ?>
    <p class="text-xs" style="color:var(--ink-soft)">Código <span class="font-mono"><?= esc($p['codigo']) ?></span>. Precio, costo y demás datos los cambia el administrador.</p>
    <?php endif; ?>
  </div>

  <?php if ($completo): ?>
  <div class="card mb-4">
    <label class="field-label">Se vende por</label>
    <select class="field-input mb-3" style="font-family:'Inter'" name="unidad_medida" id="f-um" onchange="cambiarUm()">
      <?php $umActual = $v('unidad_medida', $p['unidad_medida'] ?? 'und'); foreach (\App\Libraries\ProductoService::UNIDADES_MEDIDA as $clave => [$nombreUm, $abrev]): ?>
        <option value="<?= $clave ?>" data-abrev="<?= $abrev ?>" <?= $umActual === $clave ? 'selected' : '' ?>><?= $nombreUm ?><?= $clave !== 'und' ? " ($abrev)" : '' ?></option>
      <?php endforeach; ?>
    </select>
    <div class="grid <?= ver_costos() ? 'grid-cols-2' : 'grid-cols-1' ?> gap-2">
      <div>
        <label class="field-label">Precio de venta <span class="um-txt"></span></label>
        <input class="field-input mb-1" type="number" inputmode="decimal" step="any" min="0" name="precio" id="f-precio" value="<?= esc((string) $v('precio', $precio > 0 ? rtrim(rtrim(number_format($precio, 10, '.', ''), '0'), '.') : ''), 'attr') ?>" oninput="margen()">
      </div>
      <?php if (ver_costos()): ?>
      <div>
        <label class="field-label">Costo <span class="um-txt"></span></label>
        <input class="field-input mb-1" type="number" inputmode="decimal" step="any" min="0" name="costo" id="f-costo" value="<?= esc((string) $v('costo', (float) $p['costo']), 'attr') ?>" oninput="margen()">
      </div>
      <?php endif; ?>
    </div>
    <p class="text-xs mb-1" id="f-margen" style="color:var(--ink-soft)"></p>
    <?php if (ver_costos()): ?><p class="text-[11px]" style="color:var(--ink-soft)">El costo promedio de cada depósito lo calculan las compras; este es el costo de referencia del producto.</p><?php endif; ?>
  </div>

  <div class="card mb-4">
    <p class="font-head text-sm font-semibold">¿También lo vendes por caja, bulto, paca…?</p>
    <p class="text-[11px] mb-3" style="color:var(--ink-soft)">El precio de arriba es por <span class="um-nombre">unidad</span>. Aquí cada presentación dice cuántas <span class="um-nombre">unidad</span>(es) trae (ej. Saco = 50 lb) y a cuánto se vende. El stock siempre se cuenta en <span class="um-nombre">unidad</span>.</p>
    <input type="hidden" name="pres_enviado" value="1">
    <div id="pres-lista" class="flex flex-col gap-2"></div>
    <button type="button" onclick="agregarPres()" class="w-full mt-2 py-2.5 rounded-xl text-sm font-medium" style="background:var(--navy-light); color:var(--navy)">+ Agregar presentación</button>
  </div>
  <?php endif; ?>

  <div class="card mb-4">
    <p class="text-xs mb-3" style="color:var(--ink-soft)">En <b><?= esc($deposito_actual['nombre'] ?? 'este depósito') ?></b>:</p>
    <div class="grid <?= $completo ? 'grid-cols-2' : 'grid-cols-1' ?> gap-2">
      <?php if ($completo): ?>
      <div>
        <label class="field-label">Stock mínimo</label>
        <input class="field-input mb-3" type="number" inputmode="decimal" step="any" min="0" name="minimo" value="<?= esc((string) $v('minimo', (float) ($inv['minimo'] ?? 0)), 'attr') ?>">
      </div>
      <?php endif; ?>
      <div>
        <label class="field-label">Ubicación</label>
        <input class="field-input mb-3" style="font-family:'Inter'" name="ubicacion" maxlength="100" placeholder="Estante A-3" value="<?= esc($v('ubicacion', $inv['ubicacion'] ?? ''), 'attr') ?>">
      </div>
    </div>
    <?php $cv = !empty($p['controla_vencimiento']); ?>
    <?php if ($completo): ?>
    <label class="flex items-center gap-2 text-sm font-medium mb-2 select-none">
      <input type="checkbox" name="controla_vencimiento" value="1" <?= $cv ? 'checked' : '' ?> onchange="document.getElementById('bloque-vence').style.display = this.checked ? '' : 'none'" style="width:18px; height:18px">
      ⏳ Lleva fecha de vencimiento
    </label>
    <?php endif; ?>
    <div id="bloque-vence" style="<?= $cv ? '' : 'display:none' ?>">
      <label class="field-label">Vence (próximo vencimiento)</label>
      <input class="field-input mb-1" type="date" name="fecha_vencimiento" value="<?= esc((string) $v('fecha_vencimiento', $inv['fecha_vencimiento'] ?? ''), 'attr') ?>">
      <p class="text-[11px]" style="color:var(--ink-soft)">Avisa 30 días antes. Se pide también al recibir compras y al contar.</p>
    </div>
  </div>

  <div class="card mb-4">
    <label class="field-label">Foto</label>
    <div class="flex items-center gap-3 mb-3">
      <?php if (!empty($p['imagen_principal'])): ?>
        <img src="/assets/uploads/<?= esc($p['imagen_principal'], 'attr') ?>" alt="" class="w-14 h-14 rounded-xl object-cover" id="f-foto-prev">
      <?php else: ?>
        <img alt="" class="w-14 h-14 rounded-xl object-cover hidden" id="f-foto-prev">
      <?php endif; ?>
      <input type="file" name="imagen" accept="image/*" class="hidden" id="f-imagen">
      <div class="flex flex-1 gap-2">
        <label class="flex-1 py-2.5 rounded-xl text-sm font-medium text-center cursor-pointer" style="background:var(--navy); color:white">📷 Tomar foto<input type="file" accept="image/*" capture="environment" class="hidden" onchange="previa(this)"></label>
        <label class="flex-1 py-2.5 rounded-xl text-sm font-medium text-center cursor-pointer" style="background:var(--navy-light); color:var(--navy)">🖼️ Galería<input type="file" accept="image/*" class="hidden" onchange="previa(this)"></label>
      </div>
    </div>

    <?php if ($completo): ?>
    <label class="field-label">Estado</label>
    <select class="field-input" style="font-family:'Inter'" name="estado">
      <option value="activo" <?= $v('estado', $p['estado']) === 'activo' ? 'selected' : '' ?>>Activo (se vende)</option>
      <option value="inactivo" <?= $v('estado', $p['estado']) === 'inactivo' ? 'selected' : '' ?>>Inactivo (no aparece para vender)</option>
    </select>
    <?php endif; ?>
  </div>

  <button type="submit" class="btn-primario mb-6">Guardar cambios</button>
</form>

<?php $this->endSection(); ?>

<?php $this->section('extra_js'); ?>
<script src="/assets/js/foto-producto.js?v=1"></script>
<script>
// Unidad de venta elegida: "lb", "kg"… ("unidad" si es por unidad)
function umTexto() {
  const s = document.getElementById('f-um');
  const a = s ? s.selectedOptions[0].dataset.abrev : 'uds';
  return a === 'uds' ? 'unidad' : a;
}
function cambiarUm() {
  const u = umTexto();
  document.querySelectorAll('.um-txt').forEach(function (e) { e.textContent = u === 'unidad' ? '' : '(por ' + u + ')'; });
  document.querySelectorAll('.um-nombre').forEach(function (e) { e.textContent = u; });
  margen();
  document.querySelectorAll('#pres-lista input[type=number]').forEach(function (el) { if (typeof pintarPres === 'function') pintarPres(el); });
}
function margen() {
  if (!document.getElementById('f-precio') || !document.getElementById('f-costo')) return; // sin precio o sin costo visible
  const precio = parseFloat(document.getElementById('f-precio').value) || 0;
  const costo = parseFloat(document.getElementById('f-costo').value) || 0;
  document.getElementById('f-margen').textContent = precio > 0 && costo > 0
    ? 'Ganancia ' + dinero(precio - costo) + ' por ' + umTexto() + ' (' + ((precio - costo) * 100 / precio).toFixed(1) + '% del precio)'
    : '';
}
async function previa(input) {
  if (!input.files || !input.files[0]) return;
  // Se reduce antes de enviar (una foto de celular pasa de varios MB a ~200 KB)
  const reducido = await FotoProducto.reducir(input.files[0]);
  const dt = new DataTransfer();
  dt.items.add(reducido);
  document.getElementById('f-imagen').files = dt.files;
  input.value = '';
  const img = document.getElementById('f-foto-prev');
  img.src = URL.createObjectURL(reducido);
  img.classList.remove('hidden');
}
margen();
<?php if ($completo): ?>
// Presentaciones (caja, bulto…): se guardan con el formulario como pres[n][unidad_id|nombre|factor|precio]
let presN = 0;
function agregarPres(u) {
  u = u || { unidad_id: '', nombre: '', factor: '', precio: '' };
  const n = presN++;
  const fila = document.createElement('div');
  fila.className = 'rounded-xl p-2.5';
  fila.style.background = 'var(--navy-light)';
  fila.innerHTML =
    '<input type="hidden" name="pres[' + n + '][unidad_id]" value="' + esc(String(u.unidad_id)) + '">' +
    '<div class="flex gap-2 items-end">' +
      '<div class="flex-1 min-w-0"><label class="text-[11px]" style="color:var(--ink-soft)">Nombre</label>' +
        '<input class="field-input" style="font-family:Inter; padding:8px" name="pres[' + n + '][nombre]" maxlength="50" placeholder="Caja" value="' + esc(u.nombre) + '"></div>' +
      '<div style="width:70px"><label class="text-[11px]" style="color:var(--ink-soft)">Trae</label>' +
        '<input class="field-input" style="padding:8px; text-align:center" type="number" inputmode="decimal" step="any" min="0" name="pres[' + n + '][factor]" placeholder="4" value="' + esc(String(u.factor)) + '" oninput="pintarPres(this)"></div>' +
      '<div style="width:86px"><label class="text-[11px]" style="color:var(--ink-soft)">Precio $</label>' +
        '<input class="field-input" style="padding:8px; text-align:right" type="number" inputmode="decimal" step="any" min="0" name="pres[' + n + '][precio]" value="' + esc(String(u.precio)) + '" oninput="pintarPres(this)"></div>' +
      '<button type="button" onclick="this.closest(\'.rounded-xl\').remove()" class="w-8 h-9 rounded-lg flex-shrink-0" style="background:var(--red-light); color:var(--red)" title="Quitar">✕</button>' +
    '</div>' +
    '<p class="text-[11px] mt-1 pres-info" style="color:var(--ink-soft)"></p>';
  document.getElementById('pres-lista').appendChild(fila);
  pintarPres(fila.querySelector('input[type=number]'));
}
// Ayuda: precio por unidad dentro de la presentación (ej. caja de 4 a $9 = $2.25 c/u)
function pintarPres(el) {
  const fila = el.closest('.rounded-xl');
  const nums = fila.querySelectorAll('input[type=number]');
  const factor = parseFloat(nums[0].value) || 0, precio = parseFloat(nums[1].value) || 0;
  const unidad = parseFloat((document.getElementById('f-precio') || {}).value) || 0;
  fila.querySelector('.pres-info').textContent = factor > 0
    ? (precio > 0 ? 'Sale a ' + dinero(precio / factor) + ' cada ' + umTexto() : 'Sin precio: se cobra ' + dinero(unidad * factor) + ' (' + factor + ' × ' + dinero(unidad) + ')')
    : '';
}
<?= json_encode(array_map(fn ($u) => ['unidad_id' => (int) $u['unidad_id'], 'nombre' => $u['nombre'], 'factor' => (float) $u['factor_conversion'], 'precio' => (float) $u['precio_a'] > 0 ? (float) $u['precio_a'] : ''], $unidades), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>.forEach(agregarPres);
<?php endif; ?>
cambiarUm();
</script>
<?php $this->endSection(); ?>

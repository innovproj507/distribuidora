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
    <div class="grid <?= ver_costos() ? 'grid-cols-2' : 'grid-cols-1' ?> gap-2">
      <div>
        <label class="field-label">Precio de venta</label>
        <input class="field-input mb-1" type="number" inputmode="decimal" step="0.01" min="0" name="precio" id="f-precio" value="<?= esc((string) $v('precio', $precio > 0 ? number_format($precio, 2, '.', '') : ''), 'attr') ?>" oninput="margen()">
      </div>
      <?php if (ver_costos()): ?>
      <div>
        <label class="field-label">Costo</label>
        <input class="field-input mb-1" type="number" inputmode="decimal" step="0.0001" min="0" name="costo" id="f-costo" value="<?= esc((string) $v('costo', (float) $p['costo']), 'attr') ?>" oninput="margen()">
      </div>
      <?php endif; ?>
    </div>
    <p class="text-xs mb-1" id="f-margen" style="color:var(--ink-soft)"></p>
    <?php if (ver_costos()): ?><p class="text-[11px]" style="color:var(--ink-soft)">El costo promedio de cada depósito lo calculan las compras; este es el costo de referencia del producto.</p><?php endif; ?>
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
    <label class="field-label">Vence (próximo vencimiento)</label>
    <input class="field-input mb-1" type="date" name="fecha_vencimiento" value="<?= esc((string) $v('fecha_vencimiento', $inv['fecha_vencimiento'] ?? ''), 'attr') ?>">
    <p class="text-[11px]" style="color:var(--ink-soft)">Déjalo vacío si no vence. Avisa 30 días antes.</p>
  </div>

  <div class="card mb-4">
    <label class="field-label">Foto</label>
    <div class="flex items-center gap-3 mb-3">
      <?php if (!empty($p['imagen_principal'])): ?>
        <img src="/assets/uploads/<?= esc($p['imagen_principal'], 'attr') ?>" alt="" class="w-14 h-14 rounded-xl object-cover" id="f-foto-prev">
      <?php else: ?>
        <img alt="" class="w-14 h-14 rounded-xl object-cover hidden" id="f-foto-prev">
      <?php endif; ?>
      <input type="file" name="imagen" accept="image/*" capture="environment" class="text-xs flex-1" onchange="previa(this)">
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
function margen() {
  if (!document.getElementById('f-precio') || !document.getElementById('f-costo')) return; // sin precio o sin costo visible
  const precio = parseFloat(document.getElementById('f-precio').value) || 0;
  const costo = parseFloat(document.getElementById('f-costo').value) || 0;
  document.getElementById('f-margen').textContent = precio > 0 && costo > 0
    ? 'Ganancia ' + dinero(precio - costo) + ' por unidad (' + ((precio - costo) * 100 / precio).toFixed(1) + '% del precio)'
    : '';
}
async function previa(input) {
  if (!input.files || !input.files[0]) return;
  // Se reduce antes de enviar (una foto de celular pasa de varios MB a ~200 KB)
  const reducido = await FotoProducto.reducir(input.files[0]);
  const dt = new DataTransfer();
  dt.items.add(reducido);
  input.files = dt.files;
  const img = document.getElementById('f-foto-prev');
  img.src = URL.createObjectURL(reducido);
  img.classList.remove('hidden');
}
margen();
</script>
<?php $this->endSection(); ?>

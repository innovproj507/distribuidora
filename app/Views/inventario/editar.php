<?php
$this->extend('layouts/app');

$precios_map = [];
foreach (($precios ?? []) as $p) {
    $tipo = is_array($p) ? ($p['tipo_precio'] ?? '') : ($p->tipo_precio ?? '');
    $val  = is_array($p) ? ($p['precio'] ?? 0)      : ($p->precio ?? 0);
    $precios_map[$tipo] = $val;
}
$precioActual = (float) ($precios_map['a'] ?? 0);
$campo = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-sky-300 focus:border-sky-400 focus:outline-none';
$label = 'block text-sm font-semibold text-gray-600 mb-1';
?>
<?php $this->section('content'); ?>

<form action="/inventario/<?= $producto->producto_id ?>/editar" method="POST" enctype="multipart/form-data" class="max-w-4xl mx-auto"
      x-data='{
          precio: <?= json_encode($precioActual) ?>,
          costo: <?= json_encode((float) $producto->costo) ?>,
          unidades: <?= json_encode(array_values($unidades ?? [])) ?>,
          get ganancia() { return (parseFloat(this.precio) || 0) - (parseFloat(this.costo) || 0); },
          get margen() { const p = parseFloat(this.precio) || 0; return p > 0 ? (this.ganancia / p) * 100 : 0; },
          agregarUnidad() {
              this.unidades.push({ nombre: "", factor_conversion: 12, precio_a: 0, precio_b: 0, precio_c: 0, codigo_barras: "" });
          },
          eliminarUnidad(index) { this.unidades.splice(index, 1); },
          sugerido(u) { return ((parseFloat(this.precio) || 0) * (parseFloat(u.factor_conversion) || 0)).toFixed(2); }
      }'>
    <?= csrf_field() ?>
    <input type="hidden" name="unidades_json" :value="JSON.stringify(unidades)">

    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-sm text-gray-400 mb-3">
        <a href="/inventario" class="hover:text-gray-600 transition-colors">Inventario</a>
        <i class="fas fa-chevron-right text-xs"></i>
        <a href="/inventario/<?= $producto->producto_id ?>" class="hover:text-gray-600 transition-colors"><?= esc($producto->nombre) ?></a>
        <i class="fas fa-chevron-right text-xs"></i>
        <span class="text-gray-700 font-medium">Editar</span>
    </div>

    <!-- Barra de acciones -->
    <div class="flex items-center justify-between bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-2.5 mb-4 sticky top-2 z-10">
        <div class="flex gap-2">
            <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
                <i class="fas fa-save"></i> Guardar
            </button>
            <a href="/inventario/<?= $producto->producto_id ?>" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">Cancelar</a>
        </div>
        <span class="px-3 py-1.5 rounded-full text-xs font-bold <?= $producto->estado === 'activo' ? 'bg-emerald-500 text-white' : 'bg-gray-400 text-white' ?>"><?= ucfirst($producto->estado) ?></span>
    </div>

    <!-- 1. El producto -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-4">
        <h3 class="text-base font-bold text-gray-800 mb-4">1. El producto</h3>
        <div class="flex flex-wrap items-start gap-6">
            <div class="shrink-0"><?= view('partials/foto_producto', ['imagen' => $producto->imagen_principal ?? '']) ?></div>
            <div class="flex-1 min-w-[260px] grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="<?= $label ?>">Nombre *</label>
                    <input type="text" name="nombre" value="<?= esc($producto->nombre) ?>" required class="<?= $campo ?> text-base font-semibold">
                </div>
                <div>
                    <label class="<?= $label ?>">Código *</label>
                    <input type="text" name="codigo" value="<?= esc($producto->codigo) ?>" required class="<?= $campo ?>">
                </div>
                <div>
                    <label class="<?= $label ?>">Categoría</label>
                    <select name="categoria_id" class="<?= $campo ?>">
                        <option value="">Sin categoría</option>
                        <?php foreach (($categorias ?? []) as $cat):
                            $cId  = is_array($cat) ? $cat['categoria_id'] : ($cat->categoria_id ?? '');
                            $cNom = is_array($cat) ? $cat['nombre']       : ($cat->nombre ?? '');
                        ?>
                            <option value="<?= $cId ?>" <?= (string) $producto->categoria_id === (string) $cId ? 'selected' : '' ?>><?= esc($cNom) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="<?= $label ?>">Marca</label>
                    <select name="marca" class="<?= $campo ?>">
                        <option value="">Sin marca</option>
                        <?php foreach (($marcas ?? []) as $mar): ?>
                            <option value="<?= esc($mar['nombre']) ?>" <?= $producto->marca === $mar['nombre'] ? 'selected' : '' ?>><?= esc($mar['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Precio y costo -->
    <div class="bg-white rounded-2xl border-2 border-sky-100 shadow-sm p-6 mb-4">
        <h3 class="text-base font-bold text-gray-800 mb-1">2. Precio y costo</h3>
        <p class="text-xs text-gray-400 mb-4">Por cada unidad del producto. No lleva ITBMS.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <label class="<?= $label ?>">Precio de venta ($)</label>
                <div class="relative">
                    <span class="absolute left-3 top-2.5 text-gray-400">$</span>
                    <input type="number" name="precio_a" x-model.number="precio" step="0.01" min="0"
                           class="w-full border-2 border-sky-300 rounded-xl pl-7 pr-3 py-2.5 text-2xl font-black text-sky-700 bg-white focus:ring-2 focus:ring-sky-300 focus:outline-none">
                </div>
                <p class="text-xs text-gray-400 mt-1">Lo que paga el cliente por cada unidad.</p>
            </div>
            <div>
                <label class="<?= $label ?>">Costo ($) *</label>
                <div class="relative">
                    <span class="absolute left-3 top-2.5 text-gray-400">$</span>
                    <input type="number" name="costo" x-model.number="costo" step="0.01" min="0" required
                           class="w-full border-2 border-gray-200 rounded-xl pl-7 pr-3 py-2.5 text-2xl font-black text-gray-700 bg-white focus:ring-2 focus:ring-sky-300 focus:outline-none">
                </div>
                <p class="text-xs text-gray-400 mt-1">Lo que te cuesta cada unidad (si compras por caja: precio de la caja ÷ unidades).</p>
            </div>
        </div>
        <div class="mt-4 rounded-xl px-4 py-3 text-sm"
             :class="costo <= 0 ? 'bg-amber-50 text-amber-700' : (ganancia < 0 ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700')">
            <template x-if="costo <= 0"><span><i class="fas fa-exclamation-triangle mr-1"></i> Falta el costo: sin él no se puede calcular la ganancia.</span></template>
            <template x-if="costo > 0 && ganancia < 0"><span><i class="fas fa-exclamation-triangle mr-1"></i> El precio es <b>menor que el costo</b>: pierdes <b x-text="'$' + Math.abs(ganancia).toFixed(2)"></b> por unidad.</span></template>
            <template x-if="costo > 0 && ganancia >= 0"><span><i class="fas fa-check-circle mr-1"></i> Ganas <b x-text="'$' + ganancia.toFixed(2)"></b> por unidad (<b x-text="margen.toFixed(0) + '%'"></b> del precio).</span></template>
        </div>
    </div>

    <!-- 3. Presentaciones (opcional) -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-4">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-1">
            <h3 class="text-base font-bold text-gray-800">3. ¿También lo vendes por caja, docena o bulto? <span class="text-xs font-normal text-gray-400">(opcional)</span></h3>
            <button type="button" @click="agregarUnidad()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-sky-500 text-white rounded-lg text-xs font-semibold hover:bg-sky-600 transition shadow-sm">
                <i class="fas fa-plus"></i> Agregar presentación
            </button>
        </div>
        <p class="text-xs text-gray-400 mb-3">El stock siempre se cuenta en unidades. Una presentación solo dice cuántas unidades trae y a cuánto se vende.</p>
        <template x-if="unidades.length === 0">
            <p class="text-sm text-gray-400 bg-gray-50 rounded-xl px-4 py-3">Solo se vende por unidad.</p>
        </template>
        <div class="space-y-3">
            <template x-for="(u, index) in unidades" :key="index">
                <div class="grid grid-cols-12 gap-3 items-end bg-gray-50 rounded-xl p-3">
                    <div class="col-span-12 sm:col-span-4">
                        <label class="text-xs font-semibold text-gray-500">Nombre</label>
                        <input type="text" x-model="u.nombre" placeholder="Ej: Caja, Docena, Bulto" class="<?= $campo ?>">
                    </div>
                    <div class="col-span-5 sm:col-span-3">
                        <label class="text-xs font-semibold text-gray-500">Trae (unidades)</label>
                        <input type="number" x-model.number="u.factor_conversion" step="0.01" min="1" class="<?= $campo ?> text-center font-bold">
                    </div>
                    <div class="col-span-5 sm:col-span-4">
                        <label class="text-xs font-semibold text-gray-500">Precio de la presentación ($)</label>
                        <input type="number" x-model.number="u.precio_a" step="0.01" min="0" :placeholder="sugerido(u)" class="<?= $campo ?> font-bold text-sky-700">
                    </div>
                    <div class="col-span-2 sm:col-span-1 text-center">
                        <button type="button" @click="eliminarUnidad(index)" title="Quitar" class="w-8 h-8 bg-red-100 text-red-500 rounded-full hover:bg-red-500 hover:text-white transition-all"><i class="fas fa-times text-xs"></i></button>
                    </div>
                    <p class="col-span-12 text-[11px] text-gray-400 -mt-1">Si dejas el precio vacío se usa precio × unidades (<span x-text="'$' + sugerido(u)"></span>).</p>
                </div>
            </template>
        </div>
    </div>

    <!-- 4. Stock (solo lectura) -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-4">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <h3 class="text-base font-bold text-gray-800">4. Stock por tienda</h3>
            <a href="/inventario/<?= $producto->producto_id ?>" class="text-xs font-semibold text-sky-600 hover:text-sky-800">Agregar stock o ver movimientos <i class="fas fa-arrow-right ml-1"></i></a>
        </div>
        <div class="flex flex-wrap gap-3">
            <?php foreach (($inventariosActuales ?? []) as $inv): ?>
                <div class="bg-gray-50 border border-gray-100 px-4 py-2 rounded-xl">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider"><?= esc($inv['deposito_nombre']) ?></p>
                    <p class="text-lg font-black text-slate-800"><?= number_format((float) $inv['existencia'], 0) ?> <span class="text-xs font-normal text-gray-400">unidades</span></p>
                </div>
            <?php endforeach; ?>
            <?php if (empty($inventariosActuales)): ?>
                <p class="text-sm text-amber-700 bg-amber-50 rounded-xl px-4 py-3">Sin registros de inventario para este producto.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Más opciones -->
    <details class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-8 group">
        <summary class="cursor-pointer select-none px-6 py-4 text-sm font-bold text-gray-600 hover:text-gray-800">Más opciones (código de barras, proveedor, stock mínimo, estado…)</summary>
        <div class="px-6 pb-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="<?= $label ?>">Código de barras</label>
                <input type="text" name="codigo_barras" value="<?= esc($producto->codigo_barras) ?>" class="<?= $campo ?>">
            </div>
            <div>
                <label class="<?= $label ?>">Ref. del proveedor</label>
                <input type="text" name="supplier_part_no" value="<?= esc($producto->supplier_part_no ?? '') ?>" placeholder="Ej: REF-123" class="<?= $campo ?>">
            </div>
            <div>
                <label class="<?= $label ?>">Proveedor</label>
                <select name="proveedor_id" class="<?= $campo ?>">
                    <option value="">Ninguno</option>
                    <?php foreach (($proveedores ?? []) as $prov):
                        $pId  = is_array($prov) ? $prov['proveedor_id'] : ($prov->proveedor_id ?? '');
                        $pNom = is_array($prov) ? $prov['nombre']       : ($prov->nombre ?? '');
                    ?>
                        <option value="<?= $pId ?>" <?= (string) $producto->proveedor_id === (string) $pId ? 'selected' : '' ?>><?= esc($pNom) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="<?= $label ?>">Stock mínimo (avisa cuando baje de aquí)</label>
                <input type="number" name="stock_minimo" value="<?= $producto->stock_minimo ?>" min="0" class="<?= $campo ?>">
            </div>
            <div>
                <label class="<?= $label ?>">Estado</label>
                <select name="estado" class="<?= $campo ?>">
                    <option value="activo" <?= $producto->estado === 'activo' ? 'selected' : '' ?>>Activo</option>
                    <option value="inactivo" <?= $producto->estado === 'inactivo' ? 'selected' : '' ?>>Inactivo (no se vende)</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="<?= $label ?>">Descripción</label>
                <textarea name="descripcion" rows="2" class="<?= $campo ?>"><?= esc($producto->descripcion) ?></textarea>
            </div>
            <div class="sm:col-span-2">
                <label class="flex items-center gap-3 cursor-pointer select-none">
                    <input type="checkbox" name="maneja_lotes" value="1" <?= $producto->maneja_lotes ? 'checked' : '' ?> class="w-4 h-4 rounded border-gray-300 text-sky-600 focus:ring-sky-500">
                    <span class="text-sm font-semibold text-gray-700">Maneja lotes con fecha de vencimiento</span>
                </label>
                <p class="text-xs text-gray-400 mt-1 ml-7">Pide lote y vencimiento al comprar y vende primero lo que vence antes.</p>
            </div>
        </div>
    </details>
</form>

<?php $this->endSection(); ?>

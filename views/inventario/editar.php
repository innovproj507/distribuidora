<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<form action="/inventario/<?= $producto->producto_id ?>/editar" method="POST" enctype="multipart/form-data"
      x-data='{
          tab: "precios",
          unidades: <?= json_encode(array_values($unidades ?? [])) ?>,
          agregarUnidad() {
              this.unidades.push({ nombre: "", factor_conversion: 1, precio_a: 0, precio_b: 0, precio_c: 0, codigo_barras: "" });
          },
          eliminarUnidad(index) {
              this.unidades.splice(index, 1);
          }
      }'>
    <?= View::csrf() ?>
    <input type="hidden" name="unidades_json" :value="JSON.stringify(unidades)">

    <?php
    $precios_map = [];
    foreach (($precios ?? []) as $p) {
        $tipo = is_array($p) ? ($p['tipo_precio'] ?? '') : ($p->tipo_precio ?? '');
        $val  = is_array($p) ? ($p['precio'] ?? 0)      : ($p->precio ?? 0);
        $precios_map[$tipo] = $val;
    }
    ?>

    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-sm text-gray-400 mb-3">
        <a href="/inventario" class="hover:text-gray-600 transition-colors">Inventario</a>
        <i class="fas fa-chevron-right text-xs"></i>
        <a href="/inventario/<?= $producto->producto_id ?>" class="hover:text-gray-600 transition-colors"><?= View::e($producto->nombre) ?></a>
        <i class="fas fa-chevron-right text-xs"></i>
        <span class="text-gray-700 font-medium">Editar</span>
    </div>

    <!-- Action bar -->
    <div class="flex items-center justify-between bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-2.5 mb-4">
        <div class="flex gap-2">
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
                <i class="fas fa-save"></i> Guardar
            </button>
            <a href="/inventario/<?= $producto->producto_id ?>" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                Cancelar
            </a>
        </div>
        <!-- Estado actual -->
        <span class="px-3 py-1.5 rounded-full text-xs font-bold <?= $producto->estado === 'activo' ? 'bg-emerald-500 text-white' : 'bg-gray-400 text-white' ?>">
            <?= ucfirst($producto->estado) ?>
        </span>
    </div>

    <!-- Document card -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-3xl font-bold text-gray-900 mb-6"><?= View::e($producto->nombre) ?></h2>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-16">
                <!-- Columna izquierda -->
                <div>
                    <div class="flex items-baseline gap-4 py-2">
                        <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Código *</label>
                        <input type="text" name="codigo" value="<?= View::e($producto->codigo) ?>" required
                               class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                    </div>
                    <div class="flex items-baseline gap-4 py-2">
                        <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Nombre *</label>
                        <input type="text" name="nombre" value="<?= View::e($producto->nombre) ?>" required
                               class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                    </div>
                    <div class="flex items-baseline gap-4 py-2">
                        <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Categoría</label>
                        <select name="categoria_id" class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                            <option value="">Sin categoría</option>
                            <?php foreach (($categorias ?? []) as $cat):
                                $cId  = is_array($cat) ? $cat['categoria_id'] : ($cat->categoria_id ?? '');
                                $cNom = is_array($cat) ? $cat['nombre']       : ($cat->nombre ?? '');
                            ?>
                                <option value="<?= $cId ?>" <?= (string)$producto->categoria_id === (string)$cId ? 'selected' : '' ?>><?= View::e($cNom) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-baseline gap-4 py-2">
                        <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Marca</label>
                        <select name="marca" class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                            <option value="">Sin marca</option>
                            <?php foreach (($marcas ?? []) as $mar): ?>
                                <option value="<?= View::e($mar['nombre']) ?>" <?= $producto->marca === $mar['nombre'] ? 'selected' : '' ?>><?= View::e($mar['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-baseline gap-4 py-2">
                        <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Proveedor</label>
                        <select name="proveedor_id" class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                            <option value="">Ninguno</option>
                            <?php foreach (($proveedores ?? []) as $prov):
                                $pId  = is_array($prov) ? $prov['proveedor_id'] : ($prov->proveedor_id ?? '');
                                $pNom = is_array($prov) ? $prov['nombre']       : ($prov->nombre ?? '');
                            ?>
                                <option value="<?= $pId ?>" <?= (string)$producto->proveedor_id === (string)$pId ? 'selected' : '' ?>><?= View::e($pNom) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Columna derecha -->
                <div>
                    <div class="flex items-baseline gap-4 py-2">
                        <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Código Barras</label>
                        <input type="text" name="codigo_barras" value="<?= View::e($producto->codigo_barras) ?>"
                               class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                    </div>
                    <div class="flex items-baseline gap-4 py-2">
                        <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Ref. Proveedor</label>
                        <input type="text" name="supplier_part_no" value="<?= View::e($producto->supplier_part_no ?? '') ?>" placeholder="Ej: REF-123"
                               class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                    </div>
                    <div class="flex items-baseline gap-4 py-2">
                        <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Costo ($) *</label>
                        <input type="number" name="costo" value="<?= $producto->costo ?>" step="0.01" min="0" required
                               class="flex-1 py-1.5 px-0 text-sm font-semibold bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                    </div>
                    <div class="flex items-baseline gap-4 py-2">
                        <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">ITBMS ($)</label>
                        <input type="number" name="itbms" value="<?= $producto->itbms ?>" step="0.01"
                               class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                    </div>
                    <div class="flex items-baseline gap-4 py-2">
                        <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Stock Mínimo</label>
                        <input type="number" name="stock_minimo" value="<?= $producto->stock_minimo ?>" min="0"
                               class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                    </div>
                    <div class="flex items-baseline gap-4 py-2">
                        <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Estado</label>
                        <select name="estado" class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                            <option value="activo" <?= $producto->estado === 'activo' ? 'selected' : '' ?>>Activo</option>
                            <option value="inactivo" <?= $producto->estado === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                        </select>
                    </div>
                </div>

                <!-- Descripción — full width -->
                <div class="lg:col-span-2 flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Descripción</label>
                    <textarea name="descripcion" rows="2"
                              class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none resize-none"><?= View::e($producto->descripcion) ?></textarea>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="border-b border-gray-100 px-6 flex gap-6">
            <button type="button" @click="tab = 'precios'"
                :class="tab === 'precios' ? 'border-b-2 border-sky-500 text-sky-600' : 'text-gray-400 hover:text-gray-600'"
                class="py-3 text-sm font-semibold -mb-px transition-colors">
                Presentaciones y Precios
            </button>
            <button type="button" @click="tab = 'inventario'"
                :class="tab === 'inventario' ? 'border-b-2 border-sky-500 text-sky-600' : 'text-gray-400 hover:text-gray-600'"
                class="py-3 text-sm font-semibold -mb-px transition-colors">
                Inventario
            </button>
            <button type="button" @click="tab = 'opciones'"
                :class="tab === 'opciones' ? 'border-b-2 border-sky-500 text-sky-600' : 'text-gray-400 hover:text-gray-600'"
                class="py-3 text-sm font-semibold -mb-px transition-colors">
                Opciones
            </button>
        </div>

        <!-- Tab: Precios -->
        <div x-show="tab === 'precios'" class="p-6">
            <div class="flex justify-between items-center mb-4">
                <p class="text-xs text-gray-400">Unidad base siempre visible. Agrega presentaciones adicionales (Caja, Docena, etc.).</p>
                <button type="button" @click="agregarUnidad()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-sky-500 text-white rounded-lg text-xs font-semibold hover:bg-sky-600 transition shadow-sm">
                    <i class="fas fa-plus"></i> Agregar Presentación
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm border border-gray-100 rounded-xl overflow-hidden">
                    <thead class="bg-gray-50">
                        <tr class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                            <th class="px-4 py-3 text-left">Presentación</th>
                            <th class="px-4 py-3 text-center w-24">Conv.</th>
                            <th class="px-4 py-3 text-center text-sky-500">Precio 1</th>
                            <th class="px-4 py-3 text-center text-emerald-500">Precio 2</th>
                            <th class="px-4 py-3 text-center text-pink-500">Precio 3</th>
                            <th class="px-4 py-3 text-center">Cód. Barras</th>
                            <th class="w-8"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr class="bg-gray-50/50">
                            <td class="px-4 py-3 font-semibold text-gray-700 text-sm">Unidad base</td>
                            <td class="px-4 py-3 text-center text-gray-400 text-xs">1</td>
                            <td class="px-4 py-2 w-28"><div class="relative"><span class="absolute left-2 top-2 text-gray-400 text-xs">$</span><input type="number" name="precio_a" value="<?= $precios_map['a'] ?? '0' ?>" step="0.01" min="0" class="w-full pl-5 pr-2 py-1.5 border border-gray-200 rounded-lg text-sm font-bold text-sky-700 bg-white focus:ring-2 focus:ring-sky-300 focus:outline-none"></div></td>
                            <td class="px-4 py-2 w-28"><div class="relative"><span class="absolute left-2 top-2 text-gray-400 text-xs">$</span><input type="number" name="precio_b" value="<?= $precios_map['b'] ?? '0' ?>" step="0.01" min="0" class="w-full pl-5 pr-2 py-1.5 border border-gray-200 rounded-lg text-sm font-bold text-emerald-700 bg-white focus:ring-2 focus:ring-emerald-300 focus:outline-none"></div></td>
                            <td class="px-4 py-2 w-28"><div class="relative"><span class="absolute left-2 top-2 text-gray-400 text-xs">$</span><input type="number" name="precio_c" value="<?= $precios_map['c'] ?? '0' ?>" step="0.01" min="0" class="w-full pl-5 pr-2 py-1.5 border border-gray-200 rounded-lg text-sm font-bold text-pink-700 bg-white focus:ring-2 focus:ring-pink-300 focus:outline-none"></div></td>
                            <td class="px-4 py-3 text-center text-gray-300 text-xs">—</td>
                            <td></td>
                        </tr>
                        <template x-for="(u, index) in unidades" :key="index">
                            <tr class="bg-white group hover:bg-sky-50/30 transition-all">
                                <td class="px-4 py-2"><input type="text" x-model="u.nombre" placeholder="Ej: Caja de 24" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm font-semibold focus:ring-2 focus:ring-sky-300 focus:border-sky-400 bg-white focus:outline-none"></td>
                                <td class="px-4 py-2"><input type="number" x-model.number="u.factor_conversion" step="0.01" min="1" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm text-center font-black text-gray-700 focus:ring-2 focus:ring-sky-300 bg-white focus:outline-none"></td>
                                <td class="px-4 py-2"><div class="relative"><span class="absolute left-2 top-2 text-gray-400 text-xs">$</span><input type="number" x-model.number="u.precio_a" step="0.01" min="0" class="w-full pl-5 pr-2 py-1.5 border border-gray-200 rounded-lg text-sm font-bold text-sky-700 bg-white focus:ring-2 focus:ring-sky-300 focus:outline-none"></div></td>
                                <td class="px-4 py-2"><div class="relative"><span class="absolute left-2 top-2 text-gray-400 text-xs">$</span><input type="number" x-model.number="u.precio_b" step="0.01" min="0" class="w-full pl-5 pr-2 py-1.5 border border-gray-200 rounded-lg text-sm font-bold text-emerald-700 bg-white focus:ring-2 focus:ring-emerald-300 focus:outline-none"></div></td>
                                <td class="px-4 py-2"><div class="relative"><span class="absolute left-2 top-2 text-gray-400 text-xs">$</span><input type="number" x-model.number="u.precio_c" step="0.01" min="0" class="w-full pl-5 pr-2 py-1.5 border border-gray-200 rounded-lg text-sm font-bold text-pink-700 bg-white focus:ring-2 focus:ring-pink-300 focus:outline-none"></div></td>
                                <td class="px-4 py-2"><input type="text" x-model="u.codigo_barras" placeholder="Opcional" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-xs text-gray-500 focus:ring-2 focus:ring-sky-300 bg-white focus:outline-none"></td>
                                <td class="pr-2 py-2 text-center"><button type="button" @click="eliminarUnidad(index)" class="w-7 h-7 bg-red-100 text-red-500 rounded-full hover:bg-red-500 hover:text-white transition-all flex items-center justify-center text-xs"><i class="fas fa-times"></i></button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab: Inventario -->
        <div x-show="tab === 'inventario'" class="p-6">
            <div class="flex justify-between items-center mb-4">
                <p class="text-xs text-gray-400">Stock actual por depósito. Para ajustes usa los movimientos de inventario.</p>
                <a href="/inventario/<?= $producto->producto_id ?>" class="text-xs font-semibold text-sky-600 hover:text-sky-800 transition-colors">
                    Ver movimientos <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <?php
            $inventariosActuales = \App\Core\Database::query(
                "SELECT i.*, d.nombre as deposito_nombre FROM inventario i JOIN depositos d ON i.deposito_id = d.deposito_id WHERE i.producto_id = ?",
                [$producto->producto_id]
            )->fetchAll();
            ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php foreach ($inventariosActuales as $inv): ?>
                <div class="bg-gray-50 border border-gray-100 p-4 rounded-xl flex justify-between items-center">
                    <div>
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest"><?= View::e($inv['deposito_nombre']) ?></p>
                        <p class="text-lg font-black text-slate-800"><?= number_format((float)$inv['existencia'], 0) ?> <span class="text-xs font-normal text-gray-400">unds</span></p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-white border border-gray-100 flex items-center justify-center text-gray-400">
                        <i class="fas fa-warehouse"></i>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($inventariosActuales)): ?>
                <div class="md:col-span-3 bg-amber-50 border border-amber-100 p-4 rounded-xl text-center">
                    <p class="text-xs font-bold text-amber-700 uppercase tracking-widest">Sin registros de inventario para este producto</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab: Opciones -->
        <div x-show="tab === 'opciones'" class="p-6 space-y-6">
            <div>
                <label class="flex items-center gap-3 cursor-pointer select-none">
                    <input type="checkbox" name="maneja_lotes" value="1" <?= $producto->maneja_lotes ? 'checked' : '' ?> class="w-4 h-4 rounded border-gray-300 text-sky-600 focus:ring-sky-500">
                    <span class="text-sm font-semibold text-gray-700">Maneja Lotes por Fecha de Vencimiento</span>
                </label>
                <p class="text-xs text-gray-400 mt-1 ml-7">El sistema pedirá lote y fecha de vencimiento al registrar compras y usará FEFO al descontar en ventas.</p>
            </div>
            <div>
                <?php if (!empty($producto->imagen_principal)): ?>
                <p class="text-sm font-semibold text-gray-700 mb-2">Imagen actual</p>
                <img src="/assets/uploads/<?= View::e($producto->imagen_principal) ?>" class="h-20 rounded-lg mb-3">
                <?php endif; ?>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Nueva imagen</label>
                <input type="file" name="imagen_principal" accept="image/*"
                       class="text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-sky-500 file:bg-opacity-10 file:text-sky-600 file:font-medium hover:file:bg-opacity-20">
            </div>
        </div>
    </div>
</form>

<?php View::endSection('content'); ?>

<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<div x-data="productoForm()">
<form action="/inventario/nuevo" method="POST" enctype="multipart/form-data">
    <?= View::csrf() ?>
    <input type="hidden" name="unidades_json"  :value="JSON.stringify(unidades)">
    <input type="hidden" name="categoria_id"   :value="selCategoria">
    <input type="hidden" name="marca"          :value="selMarca">
    <input type="hidden" name="proveedor_id"   :value="selProveedor">

    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-sm text-gray-400 mb-3">
        <a href="/inventario" class="hover:text-gray-600 transition-colors">Inventario</a>
        <i class="fas fa-chevron-right text-xs"></i>
        <span class="text-gray-700 font-medium">Nuevo Producto</span>
    </div>

    <!-- Action bar -->
    <div class="flex items-center justify-between bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-2.5 mb-4">
        <div class="flex gap-2">
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
                <i class="fas fa-save"></i> Guardar
            </button>
            <a href="/inventario" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                Cancelar
            </a>
        </div>
    </div>

    <!-- Document card -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-3xl font-bold text-gray-900 mb-6">Nuevo Producto</h2>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-16">
                <!-- Columna izquierda -->
                <div>
                    <div class="flex items-baseline gap-4 py-2 border-b border-gray-50">
                        <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Código *</label>
                        <input type="text" name="codigo" required
                               class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                    </div>
                    <div class="flex items-baseline gap-4 py-2">
                        <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Nombre *</label>
                        <input type="text" name="nombre" required
                               class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                    </div>
                    <!-- Categoría -->
                    <div class="flex items-baseline gap-4 py-2">
                        <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Categoría</label>
                        <div class="flex-1 flex items-center gap-2">
                            <select x-model="selCategoria" class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                                <option value="">Sin categoría</option>
                                <template x-for="c in categorias" :key="c.id">
                                    <option :value="c.id" x-text="c.nombre"></option>
                                </template>
                            </select>
                            <button type="button" @click="modalCat = true"
                                class="shrink-0 text-emerald-500 hover:text-emerald-700 transition-colors" title="Nueva categoría">
                                <i class="fas fa-plus-circle text-lg"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Marca -->
                    <div class="flex items-baseline gap-4 py-2">
                        <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Marca</label>
                        <div class="flex-1 flex items-center gap-2">
                            <select x-model="selMarca" class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                                <option value="">Sin marca</option>
                                <template x-for="m in marcas" :key="m.nombre">
                                    <option :value="m.nombre" x-text="m.nombre"></option>
                                </template>
                            </select>
                            <button type="button" @click="modalMarca = true"
                                class="shrink-0 text-emerald-500 hover:text-emerald-700 transition-colors" title="Nueva marca">
                                <i class="fas fa-plus-circle text-lg"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Proveedor -->
                    <div class="flex items-baseline gap-4 py-2">
                        <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Proveedor</label>
                        <div class="flex-1 flex items-center gap-2">
                            <select x-model="selProveedor" class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                                <option value="">Ninguno</option>
                                <template x-for="p in proveedores" :key="p.id">
                                    <option :value="p.id" x-text="p.nombre"></option>
                                </template>
                            </select>
                            <button type="button" @click="modalProv = true"
                                class="shrink-0 text-emerald-500 hover:text-emerald-700 transition-colors" title="Nuevo proveedor">
                                <i class="fas fa-plus-circle text-lg"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Columna derecha -->
                <div>
                    <div class="flex items-baseline gap-4 py-2">
                        <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Código Barras</label>
                        <input type="text" name="codigo_barras" placeholder="Auto-generado si vacío"
                               class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                    </div>
                    <div class="flex items-baseline gap-4 py-2">
                        <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Ref. Proveedor</label>
                        <input type="text" name="supplier_part_no" placeholder="Ej: REF-123"
                               class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                    </div>
                    <div class="flex items-baseline gap-4 py-2">
                        <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Costo ($) *</label>
                        <input type="number" name="costo_inicial" step="0.01" min="0" required
                               class="flex-1 py-1.5 px-0 text-sm font-semibold bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                    </div>
                    <div class="flex items-baseline gap-4 py-2">
                        <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">ITBMS ($)</label>
                        <input type="number" name="itbms" value="0.00" step="0.01"
                               class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                    </div>
                    <div class="flex items-baseline gap-4 py-2">
                        <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Stock Mínimo</label>
                        <input type="number" name="stock_minimo" value="0" min="0"
                               class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                    </div>
                </div>

                <!-- Descripción — full width -->
                <div class="lg:col-span-2 flex items-baseline gap-4 py-2">
                    <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Descripción</label>
                    <textarea name="descripcion" rows="2"
                              class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none resize-none"></textarea>
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
            <button type="button" @click="tab = 'stock'"
                :class="tab === 'stock' ? 'border-b-2 border-sky-500 text-sky-600' : 'text-gray-400 hover:text-gray-600'"
                class="py-3 text-sm font-semibold -mb-px transition-colors">
                Stock Inicial
            </button>
            <button type="button" @click="tab = 'opciones'"
                :class="tab === 'opciones' ? 'border-b-2 border-sky-500 text-sky-600' : 'text-gray-400 hover:text-gray-600'"
                class="py-3 text-sm font-semibold -mb-px transition-colors">
                Opciones
            </button>
        </div>

        <!-- Tab: Presentaciones y Precios -->
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
                            <th class="px-4 py-3 text-center w-24" title="Unidades base que contiene">Conv.</th>
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
                            <td class="px-4 py-2 w-28"><div class="relative"><span class="absolute left-2 top-2 text-gray-400 text-xs">$</span><input type="number" name="precio_a" value="0" step="0.01" min="0" class="w-full pl-5 pr-2 py-1.5 border border-gray-200 rounded-lg text-sm font-bold text-sky-700 bg-white focus:ring-2 focus:ring-sky-300 focus:outline-none"></div></td>
                            <td class="px-4 py-2 w-28"><div class="relative"><span class="absolute left-2 top-2 text-gray-400 text-xs">$</span><input type="number" name="precio_b" value="0" step="0.01" min="0" class="w-full pl-5 pr-2 py-1.5 border border-gray-200 rounded-lg text-sm font-bold text-emerald-700 bg-white focus:ring-2 focus:ring-emerald-300 focus:outline-none"></div></td>
                            <td class="px-4 py-2 w-28"><div class="relative"><span class="absolute left-2 top-2 text-gray-400 text-xs">$</span><input type="number" name="precio_c" value="0" step="0.01" min="0" class="w-full pl-5 pr-2 py-1.5 border border-gray-200 rounded-lg text-sm font-bold text-pink-700 bg-white focus:ring-2 focus:ring-pink-300 focus:outline-none"></div></td>
                            <td class="px-4 py-3 text-center text-gray-300 text-xs">—</td>
                            <td></td>
                        </tr>
                        <template x-for="(u, index) in unidades" :key="index">
                            <tr class="bg-white group hover:bg-sky-50/30 transition-all">
                                <td class="px-4 py-2"><input type="text" x-model="u.nombre" placeholder="Ej: Caja de 24" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm font-semibold focus:ring-2 focus:ring-sky-300 focus:border-sky-400 bg-white focus:outline-none"></td>
                                <td class="px-4 py-2"><input type="number" x-model.number="u.factor_conversion" step="1" min="1" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm text-center font-black text-gray-700 focus:ring-2 focus:ring-sky-300 bg-white focus:outline-none"></td>
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

        <!-- Tab: Stock Inicial -->
        <div x-show="tab === 'stock'" class="p-6">
            <p class="text-xs text-gray-400 mb-4">Se generará un registro de inventario para cada depósito con stock mayor a cero.</p>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-[10px] font-black text-gray-500 uppercase tracking-wider">Depósito</th>
                            <th class="px-6 py-3 text-left text-[10px] font-black text-gray-500 uppercase tracking-wider">Stock Inicial</th>
                            <th class="px-6 py-3 text-left text-[10px] font-black text-gray-500 uppercase tracking-wider">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach (($depositos ?? []) as $dep): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <span class="text-sm font-bold text-slate-700"><?= View::e($dep['nombre']) ?></span>
                                <input type="hidden" name="depositos_ids[]" value="<?= $dep['deposito_id'] ?>">
                            </td>
                            <td class="px-6 py-4">
                                <div class="relative w-32">
                                    <input type="number" name="stocks[]" value="0" min="0"
                                           class="w-full pl-3 pr-10 py-2 border border-gray-200 rounded-lg text-sm font-black text-slate-800 focus:ring-2 focus:ring-sky-500 bg-white focus:outline-none">
                                    <span class="absolute right-3 top-2.5 text-[9px] font-black text-slate-400 uppercase">UNDS</span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 bg-emerald-500 text-white text-[9px] font-black rounded-full uppercase">Activo</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($depositos)): ?>
                        <tr><td colspan="3" class="px-6 py-4 text-center text-xs text-gray-400 italic">No hay depósitos activos disponibles</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab: Opciones -->
        <div x-show="tab === 'opciones'" class="p-6 space-y-6">
            <div>
                <label class="flex items-center gap-3 cursor-pointer select-none">
                    <input type="checkbox" name="maneja_lotes" value="1" class="w-4 h-4 rounded border-gray-300 text-sky-600 focus:ring-sky-500">
                    <span class="text-sm font-semibold text-gray-700">Maneja Lotes por Fecha de Vencimiento</span>
                </label>
                <p class="text-xs text-gray-400 mt-1 ml-7">El sistema pedirá lote y fecha de vencimiento al registrar compras y usará FEFO al descontar en ventas.</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Imagen del Producto</label>
                <input type="file" name="imagen_principal" accept="image/*"
                       class="text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-sky-500 file:bg-opacity-10 file:text-sky-600 file:font-medium hover:file:bg-opacity-20">
            </div>
        </div>
    </div>
</form>

<!-- ── Modales de creación rápida (dentro del scope x-data del div padre) ──── -->

<!-- Modal: Nueva Categoría -->
<div x-show="modalCat" class="fixed inset-0 z-50 flex items-center justify-center" x-cloak>
    <div class="fixed inset-0 bg-gray-900/50" @click="modalCat = false"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4 z-10 p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-9 h-9 bg-sky-500 text-white rounded-xl flex items-center justify-center shrink-0 shadow-sm shadow-sky-500/30">
                <i class="fas fa-tags text-sm"></i>
            </div>
            <div>
                <h3 class="text-base font-bold text-gray-900">Nueva Categoría</h3>
                <p class="text-xs text-gray-400">Se creará al nivel raíz</p>
            </div>
        </div>
        <div class="mb-4">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Nombre *</label>
            <input type="text" x-model="nuevaCat.nombre" @keydown.enter.prevent="crearCategoria()"
                placeholder="Ej: Herramientas Manuales"
                class="w-full py-2 px-3 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-sky-400 focus:border-sky-400 outline-none">
        </div>
        <div class="flex gap-2">
            <button type="button" @click="modalCat = false"
                class="flex-1 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">Cancelar</button>
            <button type="button" @click="crearCategoria()" :disabled="creandoCat"
                class="flex-1 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm disabled:opacity-50">
                <span x-text="creandoCat ? 'Creando...' : '+ Crear'"></span>
            </button>
        </div>
    </div>
</div>

<!-- Modal: Nueva Marca -->
<div x-show="modalMarca" class="fixed inset-0 z-50 flex items-center justify-center" x-cloak>
    <div class="fixed inset-0 bg-gray-900/50" @click="modalMarca = false"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4 z-10 p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-9 h-9 bg-amber-500 text-white rounded-xl flex items-center justify-center shrink-0 shadow-sm shadow-amber-500/30">
                <i class="fas fa-star text-sm"></i>
            </div>
            <div>
                <h3 class="text-base font-bold text-gray-900">Nueva Marca</h3>
                <p class="text-xs text-gray-400">Se añadirá al catálogo de marcas</p>
            </div>
        </div>
        <div class="mb-4">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Nombre *</label>
            <input type="text" x-model="nuevaMarca.nombre" @keydown.enter.prevent="crearMarca()"
                placeholder="Ej: Stanley, Bosch, 3M"
                class="w-full py-2 px-3 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-amber-400 focus:border-amber-400 outline-none">
        </div>
        <div class="flex gap-2">
            <button type="button" @click="modalMarca = false"
                class="flex-1 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">Cancelar</button>
            <button type="button" @click="crearMarca()" :disabled="creandoMarca"
                class="flex-1 py-2 bg-amber-500 text-white rounded-lg text-sm font-semibold hover:bg-amber-600 transition shadow-sm disabled:opacity-50">
                <span x-text="creandoMarca ? 'Creando...' : '+ Crear'"></span>
            </button>
        </div>
    </div>
</div>

<!-- Modal: Nuevo Proveedor -->
<div x-show="modalProv" class="fixed inset-0 z-50 flex items-center justify-center" x-cloak>
    <div class="fixed inset-0 bg-gray-900/50" @click="modalProv = false"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4 z-10 p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-9 h-9 bg-emerald-500 text-white rounded-xl flex items-center justify-center shrink-0 shadow-sm shadow-emerald-500/30">
                <i class="fas fa-truck text-sm"></i>
            </div>
            <div>
                <h3 class="text-base font-bold text-gray-900">Nuevo Proveedor</h3>
                <p class="text-xs text-gray-400">Se creará en el directorio de proveedores</p>
            </div>
        </div>
        <div class="space-y-3 mb-4">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Nombre *</label>
                <input type="text" x-model="nuevoProv.nombre" @keydown.enter.prevent="crearProveedor()"
                    placeholder="Ej: Distribuidora Nacional S.A."
                    class="w-full py-2 px-3 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-emerald-400 focus:border-emerald-400 outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Teléfono</label>
                <input type="text" x-model="nuevoProv.telefono"
                    placeholder="+507 000-0000"
                    class="w-full py-2 px-3 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-emerald-400 outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">RUC</label>
                <input type="text" x-model="nuevoProv.ruc"
                    placeholder="Ej: 888-000-00000"
                    class="w-full py-2 px-3 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-emerald-400 outline-none font-mono">
            </div>
        </div>
        <div class="flex gap-2">
            <button type="button" @click="modalProv = false"
                class="flex-1 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">Cancelar</button>
            <button type="button" @click="crearProveedor()" :disabled="creandoProv"
                class="flex-1 py-2 bg-emerald-500 text-white rounded-lg text-sm font-semibold hover:bg-emerald-600 transition shadow-sm disabled:opacity-50">
                <span x-text="creandoProv ? 'Creando...' : '+ Crear'"></span>
            </button>
        </div>
    </div>
</div>

</div><!-- /x-data productoForm() -->

<?php View::endSection('content'); ?>

<?php View::section('extra_js'); ?>
<script>
function productoForm() {
    return {
        tab: 'precios',
        unidades: [],

        // Listas dinámicas inicializadas desde PHP
        categorias: <?= json_encode(array_map(function($c) {
            return ['id' => is_array($c) ? ($c['categoria_id'] ?? '') : ($c->categoria_id ?? ''),
                    'nombre' => is_array($c) ? ($c['nombre'] ?? '') : ($c->nombre ?? '')];
        }, $categorias ?? [])) ?>,
        marcas: <?= json_encode(array_map(fn($m) => ['nombre' => $m['nombre']], $marcas ?? [])) ?>,
        proveedores: <?= json_encode(array_map(function($p) {
            return ['id' => is_array($p) ? ($p['proveedor_id'] ?? '') : ($p->proveedor_id ?? ''),
                    'nombre' => is_array($p) ? ($p['nombre'] ?? '') : ($p->nombre ?? '')];
        }, $proveedores ?? [])) ?>,

        // Valores seleccionados
        selCategoria: '',
        selMarca: '',
        selProveedor: '',

        // Estado modales
        modalCat: false,   nuevaCat:   { nombre: '' },         creandoCat: false,
        modalMarca: false, nuevaMarca: { nombre: '' },         creandoMarca: false,
        modalProv: false,  nuevoProv:  { nombre: '', ruc: '', telefono: '' }, creandoProv: false,

        // Presentaciones
        agregarUnidad() {
            this.unidades.push({ nombre: '', factor_conversion: 1, precio_a: 0, precio_b: 0, precio_c: 0, codigo_barras: '' });
        },
        eliminarUnidad(index) {
            this.unidades.splice(index, 1);
        },

        // Crear categoría
        async crearCategoria() {
            if (!this.nuevaCat.nombre.trim()) return;
            this.creandoCat = true;
            try {
                const r = await fetch('/api/inventario/categoria-rapida', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.nuevaCat)
                });
                const d = await r.json();
                if (d.success) {
                    this.categorias.push(d.categoria);
                    this.selCategoria = d.categoria.id;
                    this.modalCat = false;
                    this.nuevaCat = { nombre: '' };
                } else { alert(d.error); }
            } catch(e) { alert('Error al crear la categoría.'); }
            this.creandoCat = false;
        },

        // Crear marca
        async crearMarca() {
            if (!this.nuevaMarca.nombre.trim()) return;
            this.creandoMarca = true;
            try {
                const r = await fetch('/api/inventario/marca-rapida', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.nuevaMarca)
                });
                const d = await r.json();
                if (d.success) {
                    if (!this.marcas.find(function(m) { return m.nombre === d.marca.nombre; })) {
                        this.marcas.push(d.marca);
                    }
                    this.selMarca = d.marca.nombre;
                    this.modalMarca = false;
                    this.nuevaMarca = { nombre: '' };
                } else { alert(d.error); }
            } catch(e) { alert('Error al crear la marca.'); }
            this.creandoMarca = false;
        },

        // Crear proveedor
        async crearProveedor() {
            if (!this.nuevoProv.nombre.trim()) return;
            this.creandoProv = true;
            try {
                const r = await fetch('/api/compras/proveedor-rapido', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.nuevoProv)
                });
                const d = await r.json();
                if (d.success) {
                    this.proveedores.push(d.proveedor);
                    this.selProveedor = d.proveedor.id;
                    this.modalProv = false;
                    this.nuevoProv = { nombre: '', ruc: '', telefono: '' };
                } else { alert(d.error); }
            } catch(e) { alert('Error al crear el proveedor.'); }
            this.creandoProv = false;
        }
    };
}
</script>
<?php View::endSection('extra_js'); ?>

<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<div x-data="compraForm()">

    <!-- Breadcrumb -->
    <p class="text-xs text-gray-400 mb-2">
        <a href="/compras" class="hover:text-sky-500 transition-colors">Compras</a>
        <span class="mx-1">/</span>
        <span class="text-gray-600 font-medium">Nueva Orden</span>
    </p>

    <!-- Barra de acciones + pipeline de estado -->
    <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
        <div class="flex items-center gap-2">
            <button @click="saveCompra(false)"
                class="bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold py-2 px-5 rounded-lg shadow-sm transition-colors flex items-center gap-2">
                <i class="fas fa-clock text-xs"></i> Orden Pendiente
            </button>
            <button @click="saveCompra(true)"
                class="bg-sky-500 hover:bg-sky-600 text-white text-sm font-semibold py-2 px-5 rounded-lg shadow-sm transition-colors flex items-center gap-2">
                <i class="fas fa-save text-xs"></i> Recibir Ahora
            </button>
            <a href="/compras"
                class="text-sm font-medium text-gray-500 hover:text-red-500 py-2 px-4 rounded-lg border border-gray-200 hover:border-red-200 transition-colors">
                Cancelar
            </a>
        </div>

        <!-- Pipeline de estado (estilo Odoo) -->
        <div class="flex items-stretch text-xs font-semibold select-none">
            <div class="flex items-center bg-sky-500 text-white pl-4 pr-6 py-2 rounded-l-lg relative">
                Solicitud
                <span class="absolute right-0 top-0 h-full w-3 overflow-hidden translate-x-2.5 z-10">
                    <span class="block w-6 h-6 bg-sky-500 rotate-45 origin-top-left mt-0.5 ml-0.5"></span>
                </span>
            </div>
            <div class="flex items-center bg-gray-100 text-gray-400 pl-7 pr-5 py-2 rounded-r-lg">
                Recibida
            </div>
        </div>
    </div>

    <!-- Documento principal -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

        <!-- Título grande -->
        <div class="px-8 pt-7 pb-5">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">Nueva Orden de Compra</p>
            <h1 class="text-3xl font-bold text-gray-800">Nueva Compra</h1>
        </div>

        <!-- Campos del encabezado: 2 columnas -->
        <div class="px-8 pb-6 grid grid-cols-1 md:grid-cols-2 gap-x-20 gap-y-5 border-b border-gray-100">

            <!-- Columna izquierda -->
            <div class="space-y-5">
                <div class="flex items-baseline gap-4">
                    <label class="text-sm font-semibold text-gray-700 w-44 shrink-0">Proveedor</label>
                    <div class="flex-1 flex items-center gap-2">
                        <select x-model="compra.proveedor_id"
                            class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none text-gray-800">
                            <option value="">— Seleccionar —</option>
                            <template x-for="p in proveedores" :key="p.id">
                                <option :value="p.id" x-text="p.nombre"></option>
                            </template>
                        </select>
                        <button type="button" @click="abrirModalProveedor()"
                            class="shrink-0 text-emerald-500 hover:text-emerald-700 transition-colors" title="Crear proveedor nuevo">
                            <i class="fas fa-plus-circle text-lg"></i>
                        </button>
                    </div>
                </div>

                <!-- Modal: Crear proveedor rápido -->
                <div x-show="modalProveedor" class="fixed inset-0 z-50 flex items-center justify-center" x-cloak>
                    <div class="fixed inset-0 bg-gray-900/50" @click="modalProveedor = false"></div>
                    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4 z-10">
                        <div class="p-6">
                            <div class="flex items-center gap-3 mb-5">
                                <div class="w-9 h-9 bg-sky-500 rounded-xl flex items-center justify-center shrink-0 shadow-sm shadow-sky-500/30">
                                    <i class="fas fa-truck text-white text-sm"></i>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-gray-900">Nuevo proveedor</h3>
                                    <p class="text-xs text-gray-400">Se creará en el directorio</p>
                                </div>
                            </div>
                            <div class="space-y-3 mb-5">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Nombre *</label>
                                    <input type="text" x-model="nuevoProveedor.nombre" @keydown.enter.prevent="crearProveedor()"
                                        placeholder="Ej: Distribuidora Nacional S.A."
                                        class="w-full py-2 px-3 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-sky-400 focus:border-sky-400 outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Teléfono</label>
                                    <input type="text" x-model="nuevoProveedor.telefono" @keydown.enter.prevent="crearProveedor()"
                                        placeholder="+507 000-0000"
                                        class="w-full py-2 px-3 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-sky-400 focus:border-sky-400 outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">RUC</label>
                                    <input type="text" x-model="nuevoProveedor.ruc" @keydown.enter.prevent="crearProveedor()"
                                        placeholder="Ej: 888-000-00000"
                                        class="w-full py-2 px-3 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-sky-400 focus:border-sky-400 outline-none font-mono">
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" @click="modalProveedor = false"
                                    class="flex-1 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                                    Cancelar
                                </button>
                                <button type="button" @click="crearProveedor()" :disabled="creandoProv"
                                    class="flex-1 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm disabled:opacity-50">
                                    <i class="fas fa-plus mr-1"></i>
                                    <span x-text="creandoProv ? 'Creando...' : 'Crear y seleccionar'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex items-baseline gap-4">
                    <label class="text-sm font-semibold text-gray-700 w-44 shrink-0">
                        N° Factura Proveedor
                        <span class="text-gray-400 font-normal text-xs block">opcional</span>
                    </label>
                    <input type="text" x-model="compra.numero_factura" placeholder="Ej. FAC-001  (si no tiene, déjelo vacío)"
                        class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none text-gray-800">
                </div>
            </div>

            <!-- Columna derecha -->
            <div class="space-y-5">
                <div class="flex items-baseline gap-4">
                    <label class="text-sm font-semibold text-gray-700 w-44 shrink-0">Fecha de Compra</label>
                    <input type="date" x-model="compra.fecha_compra"
                        class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none text-gray-800">
                </div>
                <div class="flex items-baseline gap-4">
                    <label class="text-sm font-semibold text-gray-700 w-44 shrink-0">Llegada Prevista</label>
                    <input type="date" x-model="compra.fecha_esperada"
                        class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none text-gray-800">
                </div>
            </div>
        </div>

        <!-- TABS -->
        <div x-data="{ tab: 'productos' }">

            <!-- Cabeceras de tabs -->
            <div class="flex border-b border-gray-100 px-8">
                <button @click="tab = 'productos'"
                    :class="tab === 'productos' ? 'border-b-2 border-sky-500 text-sky-600 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                    class="text-sm py-3 px-1 mr-8 transition-colors -mb-px">
                    Productos
                </button>
                <button @click="tab = 'notas'"
                    :class="tab === 'notas' ? 'border-b-2 border-sky-500 text-sky-600 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                    class="text-sm py-3 px-1 transition-colors -mb-px">
                    Notas
                </button>
            </div>

            <!-- Tab: Productos -->
            <div x-show="tab === 'productos'" class="px-6 pt-4 pb-2">

                <!-- Buscador de productos -->
                <div class="relative mb-3" @click.away="resultados = []">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-sm"></i>
                    <input type="text" x-model="searchQuery" @input.debounce.300ms="buscarProductos()"
                        placeholder="Añadir un producto..."
                        class="w-full pl-9 pr-4 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-sky-400 focus:border-sky-400 outline-none bg-gray-50">

                    <!-- Dropdown resultados -->
                    <div x-show="resultados.length > 0 || (searchQuery.length >= 2 && buscado)"
                         class="absolute z-50 left-0 right-0 mt-1 bg-white border border-gray-100 rounded-xl shadow-2xl max-h-80 overflow-y-auto">
                        <template x-for="p in resultados" :key="p.producto_id">
                            <div @click="seleccionarProducto(p)"
                                 class="px-4 py-3 border-b border-gray-50 hover:bg-sky-50 cursor-pointer flex items-center justify-between group">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center group-hover:bg-white">
                                        <i class="fas fa-box text-gray-300 group-hover:text-sky-400 text-xs"></i>
                                    </div>
                                    <div>
                                        <div class="text-sm font-semibold text-gray-800" x-text="p.nombre"></div>
                                        <div class="text-xs text-gray-400">
                                            <span class="text-sky-500" x-text="p.codigo"></span>
                                            <span x-show="p.codigo_barras"> · <span x-text="p.codigo_barras"></span></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs font-bold text-gray-600" x-text="'$' + (parseFloat(p.costo)||0).toFixed(4)"></div>
                                    <div class="text-[10px] text-gray-400 uppercase tracking-wide">Costo</div>
                                </div>
                            </div>
                        </template>

                        <!-- Opción crear nuevo -->
                        <div x-show="searchQuery.length >= 2 && buscado"
                             @click="abrirModalCrear()"
                             class="px-4 py-3 flex items-center gap-3 cursor-pointer hover:bg-emerald-50 border-t border-gray-100 group">
                            <div class="w-8 h-8 bg-emerald-500 rounded-lg flex items-center justify-center shrink-0 shadow-sm shadow-emerald-500/30">
                                <i class="fas fa-plus text-white text-xs"></i>
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-emerald-700">Crear producto nuevo</div>
                                <div class="text-xs text-emerald-500" x-text="'\"' + searchQuery + '\" no existe — registrar ahora'"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal: Crear producto rápido -->
                <div x-show="modalCrear" class="fixed inset-0 z-50 flex items-center justify-center" x-cloak>
                    <div class="fixed inset-0 bg-gray-900/50" @click="modalCrear = false"></div>
                    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4 z-10">
                        <div class="p-6">
                            <div class="flex items-center gap-3 mb-5">
                                <div class="w-9 h-9 bg-emerald-500 rounded-xl flex items-center justify-center shrink-0 shadow-sm shadow-emerald-500/30">
                                    <i class="fas fa-box text-white text-sm"></i>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-gray-900">Nuevo producto</h3>
                                    <p class="text-xs text-gray-400">Se creará en el inventario</p>
                                </div>
                            </div>

                            <div class="space-y-3 mb-5">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Nombre *</label>
                                    <input type="text" x-model="nuevoProducto.nombre" @keydown.enter.prevent="crearProducto()"
                                        placeholder="Ej: Martillo de carpintero"
                                        class="w-full py-2 px-3 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-emerald-400 focus:border-emerald-400 outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Código *</label>
                                    <input type="text" x-model="nuevoProducto.codigo" @keydown.enter.prevent="crearProducto()"
                                        placeholder="Ej: MART-001"
                                        class="w-full py-2 px-3 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-emerald-400 focus:border-emerald-400 outline-none font-mono">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Costo ($)</label>
                                        <input type="number" step="0.0001" min="0" x-model.number="nuevoProducto.costo"
                                            placeholder="0.00"
                                            class="w-full py-2 px-3 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-emerald-400 focus:border-emerald-400 outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Categoría</label>
                                        <select x-model="nuevoProducto.categoria_id"
                                            class="w-full py-2 px-3 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-emerald-400 focus:border-emerald-400 outline-none bg-white">
                                            <option value="">— Sin categoría —</option>
                                            <?php foreach (($categorias ?? []) as $cat): ?>
                                            <option value="<?= $cat['categoria_id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Error inline -->
                            <div x-show="errorProducto" class="mb-3 flex items-start gap-2 bg-red-50 border border-red-200 text-red-700 rounded-lg px-3 py-2.5">
                                <i class="fas fa-exclamation-circle mt-0.5 shrink-0 text-sm"></i>
                                <span class="text-xs font-medium" x-text="errorProducto"></span>
                            </div>

                            <div class="flex gap-2">
                                <button type="button" @click="modalCrear = false"
                                    class="flex-1 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                                    Cancelar
                                </button>
                                <button type="button" @click="crearProducto()" :disabled="creando"
                                    class="flex-1 py-2 bg-emerald-500 text-white rounded-lg text-sm font-semibold hover:bg-emerald-600 transition shadow-sm disabled:opacity-50">
                                    <i class="fas fa-plus mr-1"></i>
                                    <span x-text="creando ? 'Creando...' : 'Crear y agregar'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de productos -->
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="py-2.5 pr-4 text-left text-xs font-semibold text-gray-500">Producto</th>
                                <th class="py-2.5 px-3 text-center text-xs font-semibold text-gray-500 w-24">Cantidad</th>
                                <th class="py-2.5 px-3 text-center text-xs font-semibold text-gray-500 w-28">Costo Unit.</th>
                                <th class="py-2.5 px-3 text-center text-xs font-semibold text-emerald-500 w-20">Margen %</th>
                                <th class="py-2.5 px-3 text-center text-xs font-semibold text-sky-500 w-24">Precio 1</th>
                                <th class="py-2.5 px-3 text-center text-xs font-semibold text-emerald-500 w-24">Precio 2</th>
                                <th class="py-2.5 px-3 text-center text-xs font-semibold text-pink-500 w-24">Precio 3</th>
                                <th class="py-2.5 px-3 text-center text-xs font-semibold text-gray-500">Depósito</th>
                                <th class="py-2.5 pl-3 text-right text-xs font-semibold text-gray-500 w-24">Importe</th>
                                <th class="w-8"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <template x-for="(item, index) in items" :key="index">
                                <tr class="hover:bg-gray-50/60 transition-colors">
                                    <td class="py-3 pr-4">
                                        <div class="text-sm font-semibold text-gray-800" x-text="item.nombre"></div>
                                        <div class="text-xs text-gray-400" x-text="item.codigo"></div>
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <input type="number" x-model.number="item.cantidad" @input="updateTotals()" min="1"
                                            class="w-20 text-center text-sm py-1 bg-gray-50 rounded border-gray-200 focus:border-sky-400 focus:ring-0">
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <input type="number" step="0.0001" x-model.number="item.costo" @input="recalcPrecioA(item); updateTotals()"
                                            class="w-24 text-center text-sm py-1 bg-gray-50 rounded border-gray-200 focus:border-sky-400 focus:ring-0 font-semibold text-sky-600">
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <div class="relative inline-flex items-center">
                                            <input type="number" step="0.1" min="0" x-model.number="item.margen_pct" @input="recalcPrecioA(item)"
                                                class="w-16 text-center text-sm py-1 bg-emerald-50 rounded border-emerald-200 focus:border-emerald-400 focus:ring-0 font-semibold text-emerald-700 pr-4">
                                            <span class="absolute right-1.5 text-xs text-emerald-500 pointer-events-none font-bold">%</span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <input type="number" step="0.01" min="0" x-model.number="item.precio_a" @input="recalcMargen(item)"
                                            class="w-22 text-center text-sm py-1 bg-sky-50 rounded border-sky-200 focus:border-sky-400 focus:ring-0 font-semibold text-sky-700">
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <input type="number" step="0.01" min="0" x-model.number="item.precio_b"
                                            class="w-22 text-center text-sm py-1 bg-emerald-50 rounded border-emerald-200 focus:border-emerald-400 focus:ring-0 font-semibold text-emerald-700">
                                        <div class="text-[10px] text-emerald-500 mt-0.5" x-text="margenDe(item, 'precio_b')"></div>
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <input type="number" step="0.01" min="0" x-model.number="item.precio_c"
                                            class="w-22 text-center text-sm py-1 bg-pink-50 rounded border-pink-200 focus:border-pink-400 focus:ring-0 font-semibold text-pink-700">
                                        <div class="text-[10px] text-pink-500 mt-0.5" x-text="margenDe(item, 'precio_c')"></div>
                                    </td>
                                    <td class="py-3 px-3">
                                        <select x-model="item.deposito_id"
                                            class="text-xs py-1 bg-gray-50 rounded border-gray-200 focus:border-sky-400 focus:ring-0">
                                            <?php foreach ($depositos as $d): ?>
                                            <option value="<?= $d['deposito_id'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="py-3 pl-3 text-right text-sm font-semibold text-gray-700"
                                        x-text="'$' + (item.cantidad * item.costo).toFixed(2)"></td>
                                    <td class="py-3 text-center">
                                        <button @click="removeItem(index)" class="text-gray-300 hover:text-red-400 transition-colors text-sm">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="items.length === 0">
                                <tr>
                                    <td colspan="10" class="py-12 text-center text-sm text-gray-400 italic">
                                        <i class="fas fa-shopping-basket text-gray-200 text-2xl block mb-2"></i>
                                        No hay productos en esta orden
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab: Notas -->
            <div x-show="tab === 'notas'" class="px-8 py-5">
                <textarea x-model="compra.notas" rows="5"
                    placeholder="Defina sus términos, condiciones u observaciones de la compra..."
                    class="w-full text-sm text-gray-700 bg-transparent border-0 resize-none outline-none placeholder-gray-300 focus:ring-0 p-0"></textarea>
            </div>

        </div><!-- /tabs -->

        <!-- Pie del documento: notas rápidas + totales -->
        <div class="px-8 py-5 border-t border-gray-100 flex items-end justify-between gap-8">
            <div class="flex-1 max-w-sm">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Términos y condiciones</p>
                <textarea x-model="compra.terminos" rows="2"
                    placeholder="Defina sus términos y condiciones..."
                    class="w-full text-sm text-gray-500 bg-transparent border-0 resize-none outline-none placeholder-gray-300 focus:ring-0 p-0"></textarea>
            </div>

            <div class="text-right space-y-1.5 shrink-0">
                <div class="flex items-center justify-between gap-16 text-sm text-gray-500">
                    <span>Importe base:</span>
                    <span class="font-semibold text-gray-700" x-text="'$' + totals.subtotal.toFixed(2)">$0.00</span>
                </div>
                <div class="flex items-center justify-between gap-16 text-sm text-gray-500">
                    <span>ITBMS (7%):</span>
                    <span class="font-semibold text-gray-700" x-text="'$' + totals.itbms.toFixed(2)">$0.00</span>
                </div>
                <div class="flex items-center justify-between gap-16 pt-2 border-t border-gray-100 text-sm">
                    <span class="font-semibold text-gray-700">Total:</span>
                    <span class="text-xl font-extrabold text-sky-600" x-text="'$' + totals.total.toFixed(2)">$0.00</span>
                </div>
            </div>
        </div>

    </div><!-- /documento -->

</div><!-- /x-data -->

<script>
function compraForm() {
    return {
        proveedores: <?= json_encode(array_map(fn($p) => ['id' => $p->proveedor_id, 'nombre' => $p->nombre], $proveedores ?? [])) ?>,
        modalProveedor: false,
        creandoProv: false,
        nuevoProveedor: { nombre: '', telefono: '', ruc: '' },

        searchQuery: '',
        resultados: [],
        buscado: false,
        modalCrear: false,
        creando: false,
        errorProducto: '',
        nuevoProducto: { nombre: '', codigo: '', costo: 0, categoria_id: '' },
        // Márgenes por defecto (Configuración → Sistema → Márgenes por Defecto en Compras)
        margenDefaultA: <?= json_encode($margen_precio_a ?? 35) ?>,
        margenDefaultB: <?= json_encode($margen_precio_b ?? 20) ?>,
        margenDefaultC: <?= json_encode($margen_precio_c ?? 10) ?>,
        items: [],
        compra: {
            proveedor_id: '',
            numero_factura: '',
            fecha_compra: new Date().toISOString().split('T')[0],
            fecha_esperada: '',
            terminos: '',
            notas: ''
        },
        totals: { subtotal: 0, itbms: 0, total: 0 },

        async buscarProductos() {
            if (this.searchQuery.length < 2) { this.resultados = []; this.buscado = false; return; }
            try {
                const r = await fetch(`/api/productos/buscar?q=${encodeURIComponent(this.searchQuery)}`);
                const d = await r.json();
                this.resultados = d.productos || [];
                this.buscado = true;
            } catch (e) { console.error(e); }
        },

        abrirModalProveedor() {
            this.nuevoProveedor = { nombre: '', telefono: '', ruc: '' };
            this.modalProveedor = true;
            this.$nextTick(() => this.$el.querySelector('[x-model="nuevoProveedor.nombre"]')?.focus());
        },

        async crearProveedor() {
            if (!this.nuevoProveedor.nombre.trim()) return alert('El nombre es requerido.');
            this.creandoProv = true;
            try {
                const r = await fetch('/api/compras/proveedor-rapido', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.nuevoProveedor)
                });
                const d = await r.json();
                if (d.success) {
                    this.proveedores.push(d.proveedor);
                    this.compra.proveedor_id = d.proveedor.id;
                    this.modalProveedor = false;
                    this.nuevoProveedor = { nombre: '', telefono: '', ruc: '' };
                } else {
                    alert('Error: ' + d.error);
                }
            } catch (e) {
                alert('Error al crear el proveedor.');
            }
            this.creandoProv = false;
        },

        async abrirModalCrear() {
            const q = this.searchQuery.trim();
            this.nuevoProducto = { nombre: q, codigo: 'Generando...', costo: 0, categoria_id: '' };
            this.errorProducto = '';
            this.resultados = [];
            this.buscado = false;
            this.modalCrear = true;

            try {
                const r = await fetch('/api/compras/siguiente-codigo');
                const d = await r.json();
                this.nuevoProducto.codigo = d.codigo || '';
            } catch (e) {
                this.nuevoProducto.codigo = '';
            }
        },

        async crearProducto() {
            this.errorProducto = '';
            if (!this.nuevoProducto.nombre.trim()) {
                this.errorProducto = 'El nombre es requerido.'; return;
            }
            if (!this.nuevoProducto.codigo.trim()) {
                this.errorProducto = 'El código es requerido.'; return;
            }
            this.creando = true;
            try {
                const r = await fetch('/api/compras/producto-rapido', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.nuevoProducto)
                });
                const d = await r.json();
                if (d.success) {
                    this.seleccionarProducto(d.producto);
                    this.modalCrear = false;
                    this.errorProducto = '';
                    this.searchQuery = '';
                    this.nuevoProducto = { nombre: '', codigo: '', costo: 0, categoria_id: '' };
                } else {
                    this.errorProducto = d.error || 'No se pudo crear el producto.';
                }
            } catch (e) {
                this.errorProducto = 'Error de conexión al crear el producto.';
            }
            this.creando = false;
        },

        seleccionarProducto(p) {
            const exists = this.items.find(i => i.producto_id === p.producto_id);
            if (exists) {
                exists.cantidad++;
            } else {
                const costo = parseFloat(p.costo || 0);
                const margen = this.margenDefaultA;
                this.items.push({
                    producto_id: p.producto_id,
                    nombre: p.nombre,
                    codigo: p.codigo,
                    cantidad: 1,
                    costo: costo,
                    margen_pct: margen,
                    precio_a: parseFloat(p.precio_a) || parseFloat((costo * (1 + margen / 100)).toFixed(2)),
                    precio_b: parseFloat(p.precio_b) || parseFloat((costo * (1 + this.margenDefaultB / 100)).toFixed(2)),
                    precio_c: parseFloat(p.precio_c) || parseFloat((costo * (1 + this.margenDefaultC / 100)).toFixed(2)),
                    deposito_id: <?= $depositos[0]['deposito_id'] ?? 'null' ?>,
                    itbms: 0
                });
            }
            this.searchQuery = '';
            this.resultados = [];
            this.updateTotals();
        },

        recalcPrecioA(item) {
            const costo  = parseFloat(item.costo)      || 0;
            const margen = parseFloat(item.margen_pct) || 0;
            item.precio_a = parseFloat((costo * (1 + margen / 100)).toFixed(2));
        },

        recalcMargen(item) {
            const costo   = parseFloat(item.costo)   || 0;
            const precioA = parseFloat(item.precio_a) || 0;
            if (costo > 0 && precioA > 0) {
                item.margen_pct = parseFloat(((precioA / costo - 1) * 100).toFixed(2));
            }
        },

        // Margen % de Precio 2 / Precio 3 respecto al Costo Unit. (solo lectura, informativo)
        margenDe(item, campo) {
            const costo  = parseFloat(item.costo)  || 0;
            const precio = parseFloat(item[campo]) || 0;
            if (costo > 0) {
                return 'Margen ' + ((precio / costo - 1) * 100).toFixed(1) + '%';
            }
            // Sin Costo Unit. registrado (0): no hay con qué calcular el margen real,
            // así que se compara contra Precio 1 para no dejar el dato en blanco.
            const precioA = parseFloat(item.precio_a) || 0;
            if (precioA > 0) {
                const diff = ((precio / precioA - 1) * 100).toFixed(1);
                return 'vs P1 ' + (diff > 0 ? '+' : '') + diff + '%';
            }
            return 'Margen —';
        },

        removeItem(index) {
            this.items.splice(index, 1);
            this.updateTotals();
        },

        updateTotals() {
            let sub = 0;
            this.items.forEach(i => { sub += i.cantidad * i.costo; });
            this.totals.subtotal = sub;
            this.totals.itbms    = sub * 0.07;
            this.totals.total    = sub + this.totals.itbms;
        },

        async saveCompra(recibir = true) {
            if (!this.compra.proveedor_id) return alert('Seleccione un proveedor');
            if (this.items.length === 0)   return alert('Agregue al menos un producto');

            const accion = recibir ? 'registrar esta entrada de inventario' : 'guardar como orden pendiente';
            if (!confirm('¿Seguro que desea ' + accion + '?')) return;

            try {
                const res = await fetch('/compras/guardar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        ...this.compra,
                        ...this.totals,
                        items: this.items,
                        guardar_como_pendiente: !recibir
                    })
                });
                const json = await res.json();
                if (json.success) {
                    window.location.href = recibir ? '/compras' : `/compras/${json.compra_id}`;
                } else {
                    alert('Error: ' + json.error);
                }
            } catch (e) {
                alert('Ocurrió un error al guardar la compra');
            }
        }
    }
}
</script>

<?php View::endSection('content'); ?>

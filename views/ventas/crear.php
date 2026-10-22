<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<div x-data="ventasForm()">

    <!-- Toast notifications -->
    <div class="fixed top-6 right-6 z-[100] flex flex-col gap-3 pointer-events-none">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-show="toast.show"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-x-8 scale-95"
                 x-transition:enter-end="opacity-100 translate-x-0 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-x-0 scale-100"
                 x-transition:leave-end="opacity-0 translate-x-8 scale-95"
                 class="pointer-events-auto flex items-center p-4 rounded-xl shadow-2xl border min-w-[300px] max-w-md"
                 :class="{
                    'bg-emerald-500 border-emerald-400 text-white': toast.type === 'success',
                    'bg-red-500 border-red-400 text-white': toast.type === 'error',
                    'bg-amber-500 border-amber-400 text-white': toast.type === 'warning'
                 }">
                <i class="fas mr-3" :class="{
                    'fa-check-circle': toast.type === 'success',
                    'fa-exclamation-circle': toast.type === 'error',
                    'fa-exclamation-triangle': toast.type === 'warning'
                }"></i>
                <p class="text-sm font-semibold flex-1" x-text="toast.message"></p>
                <button @click="removeToast(toast.id)" class="ml-3 opacity-70 hover:opacity-100">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
        </template>
    </div>

    <!-- Breadcrumb -->
    <p class="text-xs text-gray-400 mb-2">
        <?php if (($tipo_documento ?? 'venta') === 'cotizacion'): ?>
        <a href="/ventas/cotizaciones" class="hover:text-sky-500 transition-colors">Cotizaciones</a>
        <span class="mx-1">/</span>
        <span class="text-gray-600 font-medium">Nueva Cotización</span>
        <?php else: ?>
        <a href="/ventas" class="hover:text-sky-500 transition-colors">Ventas</a>
        <span class="mx-1">/</span>
        <span class="text-gray-600 font-medium">Nueva Venta</span>
        <?php endif; ?>
    </p>

    <!-- Barra de acciones + pipeline -->
    <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
        <div class="flex items-center gap-2">
            <!-- Tipo documento toggle -->
            <div class="flex items-center bg-white p-1 rounded-lg border border-gray-200 shadow-sm">
                <button @click="tipoDocumento = 'venta'; estado = 'pagada'"
                        :class="tipoDocumento === 'venta' ? 'bg-sky-500 text-white shadow-sm' : 'text-gray-500 hover:bg-gray-50'"
                        class="px-4 py-1.5 rounded-md text-sm font-semibold transition-all flex items-center gap-1.5">
                    <i class="fas fa-cash-register text-xs"></i> Factura
                </button>
                <button @click="tipoDocumento = 'cotizacion'; estado = 'pendiente'"
                        :class="tipoDocumento === 'cotizacion' ? 'bg-amber-500 text-white shadow-sm' : 'text-gray-500 hover:bg-gray-50'"
                        class="px-4 py-1.5 rounded-md text-sm font-semibold transition-all flex items-center gap-1.5">
                    <i class="fas fa-file-alt text-xs"></i> Cotización
                </button>
            </div>

            <button @click="procesarDocumento()" :disabled="processing"
                    class="bg-sky-500 hover:bg-sky-600 text-white text-sm font-semibold py-2 px-5 rounded-lg shadow-sm transition-colors flex items-center gap-2 disabled:opacity-50">
                <i x-show="!processing" class="fas fa-save text-xs"></i>
                <i x-show="processing" class="fas fa-spinner fa-spin text-xs"></i>
                <span x-text="tipoDocumento === 'venta' ? 'Guardar Venta' : 'Guardar Cotización'"></span>
            </button>
            <a href="<?= ($tipo_documento ?? 'venta') === 'cotizacion' ? '/ventas/cotizaciones' : '/ventas' ?>"
               class="text-sm font-medium text-gray-500 hover:text-red-500 py-2 px-4 rounded-lg border border-gray-200 hover:border-red-200 transition-colors">
                Cancelar
            </a>
        </div>

        <!-- Pipeline venta -->
        <div x-show="tipoDocumento === 'venta'" class="flex items-stretch text-xs font-semibold select-none">
            <div class="flex items-center bg-sky-500 text-white pl-4 pr-6 py-2 rounded-l-lg relative">
                Borrador
                <span class="absolute right-0 top-0 h-full w-3 overflow-hidden translate-x-2.5 z-10">
                    <span class="block w-6 h-6 bg-sky-500 rotate-45 origin-top-left mt-0.5 ml-0.5"></span>
                </span>
            </div>
            <div class="flex items-center bg-gray-100 text-gray-400 pl-7 pr-5 py-2 rounded-r-lg">
                Pagada
            </div>
        </div>

        <!-- Pipeline cotización -->
        <div x-show="tipoDocumento === 'cotizacion'" class="flex items-stretch text-xs font-semibold select-none">
            <div class="flex items-center bg-amber-500 text-white pl-4 pr-6 py-2 rounded-l-lg relative">
                Solicitud
                <span class="absolute right-0 top-0 h-full w-3 overflow-hidden translate-x-2.5 z-10">
                    <span class="block w-6 h-6 bg-amber-500 rotate-45 origin-top-left mt-0.5 ml-0.5"></span>
                </span>
            </div>
            <div class="flex items-center bg-gray-100 text-gray-400 pl-7 pr-6 py-2 relative">
                Aprobada
                <span class="absolute right-0 top-0 h-full w-3 overflow-hidden translate-x-2.5 z-10">
                    <span class="block w-6 h-6 bg-gray-100 rotate-45 origin-top-left mt-0.5 ml-0.5"></span>
                </span>
            </div>
            <div class="flex items-center bg-gray-100 text-gray-400 pl-7 pr-5 py-2 rounded-r-lg">
                Convertida
            </div>
        </div>
    </div>

    <!-- Documento principal -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

        <!-- Título -->
        <div class="px-8 pt-7 pb-5">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1"
               x-text="tipoDocumento === 'venta' ? 'Nueva Factura · N° <?= $siguiente_factura ?>' : 'Nueva Cotización · N° <?= $siguiente_cotizacion ?>'"></p>
            <h1 class="text-3xl font-bold text-gray-800"
                x-text="tipoDocumento === 'venta' ? 'Nueva Venta' : 'Nueva Cotización'"></h1>
        </div>

        <!-- Campos encabezado: 2 columnas -->
        <div class="px-8 pb-6 grid grid-cols-1 md:grid-cols-2 gap-x-20 gap-y-5 border-b border-gray-100">

            <!-- Columna izquierda: Cliente -->
            <div class="space-y-5" x-data="{ open: false }">
                <div class="flex items-baseline gap-4 relative">
                    <label class="text-sm font-semibold text-gray-700 w-44 shrink-0">Cliente</label>
                    <div class="flex-1 relative">
                        <input type="text" x-model="searchCliente"
                               @click="open = true" @click.away="open = false" @input="open = true"
                               placeholder="Buscar cliente..."
                               class="w-full py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none text-gray-800">
                        <div x-show="open" x-transition.opacity
                             class="absolute z-50 left-0 right-0 mt-2 bg-white border border-gray-100 rounded-xl shadow-2xl max-h-64 overflow-y-auto">
                            <div @click="seleccionarCliente('', 'Consumidor Final', 'N/A', 'N/A', 'Consumidor Final'); open = false"
                                 class="px-4 py-3 border-b border-gray-50 hover:bg-sky-50 cursor-pointer flex items-center gap-3">
                                <div class="w-7 h-7 bg-gray-100 rounded-lg flex items-center justify-center shrink-0">
                                    <i class="fas fa-users text-gray-400 text-xs"></i>
                                </div>
                                <span class="text-sm font-semibold text-gray-700">Consumidor Final</span>
                            </div>
                            <?php foreach (($clientes ?? []) as $cliente): ?>
                            <div x-show="'<?= addslashes(strtolower($cliente['nombre'])) ?>'.includes(searchCliente.toLowerCase()) || '<?= $cliente['ruc'] ?>'.includes(searchCliente)"
                                 @click="seleccionarCliente('<?= $cliente['cliente_id'] ?>', '<?= addslashes($cliente['nombre']) ?>', '<?= $cliente['ruc'] ?>', '<?= $cliente['telefono'] ?>', '<?= addslashes($cliente['direccion']) ?>'); open = false"
                                 class="px-4 py-3 border-b border-gray-50 hover:bg-sky-50 cursor-pointer flex items-center justify-between group">
                                <div class="flex items-center gap-3">
                                    <div class="w-7 h-7 bg-gray-100 rounded-lg flex items-center justify-center shrink-0 group-hover:bg-white">
                                        <i class="fas fa-user text-gray-300 group-hover:text-sky-400 text-xs"></i>
                                    </div>
                                    <div>
                                        <div class="text-sm font-semibold text-gray-800"><?= View::e($cliente['nombre']) ?></div>
                                        <div class="text-xs text-gray-400 font-medium"><?= View::e($cliente['ruc']) ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div x-show="clienteId" class="flex items-baseline gap-4">
                    <label class="text-sm font-semibold text-gray-700 w-44 shrink-0">RUC</label>
                    <span x-text="clienteRuc" class="text-sm text-gray-500"></span>
                </div>

                <div x-show="clienteId" class="flex items-baseline gap-4">
                    <label class="text-sm font-semibold text-gray-700 w-44 shrink-0">Teléfono</label>
                    <span x-text="clienteTelefono" class="text-sm text-gray-500"></span>
                </div>
            </div>

            <!-- Columna derecha: Estado, Pago, Vencimiento -->
            <div class="space-y-5">
                <div class="flex items-baseline gap-4">
                    <label class="text-sm font-semibold text-gray-700 w-44 shrink-0"
                           x-text="tipoDocumento === 'venta' ? 'Estado Factura' : 'Estado Cotización'"></label>
                    <select x-model="estado"
                            class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                        <template x-if="tipoDocumento === 'venta'">
                            <optgroup label="Estados de Venta">
                                <option value="pagada">Pagada</option>
                                <option value="pendiente">Pendiente</option>
                                <option value="credito">Crédito</option>
                                <option value="anulada">Anulada</option>
                            </optgroup>
                        </template>
                        <template x-if="tipoDocumento === 'cotizacion'">
                            <optgroup label="Estados de Cotización">
                                <option value="borrador">Borrador</option>
                                <option value="pendiente">Pendiente</option>
                                <option value="aprobada">Aprobada</option>
                                <option value="rechazada">Rechazada</option>
                            </optgroup>
                        </template>
                    </select>
                </div>

                <div x-show="tipoDocumento === 'venta'" class="flex items-baseline gap-4">
                    <label class="text-sm font-semibold text-gray-700 w-44 shrink-0">Forma de Pago</label>
                    <select x-model="formaPago"
                            class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                        <option value="efectivo">Efectivo</option>
                        <option value="tarjeta">Tarjeta</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="yappy">Yappy</option>
                        <option value="credito">Crédito</option>
                    </select>
                </div>

                <div class="flex items-baseline gap-4">
                    <label class="text-sm font-semibold text-gray-700 w-44 shrink-0">Vencimiento</label>
                    <input type="date" value="<?= $fecha_validez ?>"
                           class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                </div>
            </div>
        </div>

        <!-- TABS -->
        <div x-data="{ tab: 'productos' }">

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

                <!-- Buscador -->
                <div class="flex gap-2 mb-3" @click.away="resultados = []">
                    <div class="relative flex-1">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-sm"></i>
                        <input type="text" x-model="query" @input.debounce.300ms="buscarProductos()"
                               placeholder="Escanea código de barras o escribe el nombre del producto..."
                               class="w-full pl-9 pr-4 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-sky-400 focus:border-sky-400 outline-none bg-gray-50">

                        <div x-show="resultados.length > 0" x-transition.opacity
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
                                                · Stock: <span :class="p.stock > 0 ? 'text-emerald-600' : 'text-red-500'" x-text="p.stock"></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-bold text-sky-600" x-text="'$' + parseFloat(p.precio_a).toFixed(2)"></div>
                                        <div class="text-[10px] text-gray-400 uppercase">Precio 1</div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="w-20">
                        <input type="number" x-model.number="cantidadBusqueda" min="1"
                               @keyup.enter="agregarProductoSiSeleccionado()"
                               class="w-full py-2 text-center text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-sky-400 outline-none bg-gray-50 font-semibold">
                    </div>
                    <button @click="agregarProductoSiSeleccionado()"
                            class="px-4 py-2 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-sm font-semibold transition-colors flex items-center gap-1.5">
                        <i class="fas fa-plus text-xs"></i> Añadir
                    </button>
                </div>

                <!-- Tabla de items -->
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="py-2.5 pr-4 text-left text-xs font-semibold text-gray-500 w-8">#</th>
                                <th class="py-2.5 pr-4 text-left text-xs font-semibold text-gray-500 w-64">Producto</th>
                                <th class="py-2.5 px-3 text-center text-xs font-semibold text-gray-500 w-20">Cant.</th>
                                <th class="py-2.5 px-3 text-center text-xs font-semibold text-gray-500 w-24">Unidad</th>
                                <th class="py-2.5 px-3 text-right text-xs font-semibold text-gray-500 w-52">Precio</th>
                                <th class="py-2.5 pl-3 text-right text-xs font-semibold text-gray-500 w-24">Total</th>
                                <th class="w-8"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <template x-for="(item, index) in items" :key="item.id">
                                <tr class="hover:bg-gray-50/60 transition-colors">
                                    <td class="py-3 pr-4">
                                        <span class="text-[10px] font-bold text-sky-500 bg-sky-50 px-1.5 py-0.5 rounded" x-text="index + 1"></span>
                                    </td>
                                    <td class="py-3 pr-4">
                                        <div class="text-sm font-semibold text-gray-800" x-text="item.nombre"></div>
                                        <div class="text-xs text-gray-400 font-mono" x-text="item.codigo"></div>
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <input type="number" x-model.number="item.cantidad" @change="calcularTotales()" min="1"
                                               class="w-16 text-center text-sm py-1 bg-gray-50 rounded border border-gray-200 focus:border-sky-400 focus:ring-0">
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <select x-model="item.unidad_id" @change="cambiarUnidad(item)"
                                                class="w-full text-xs py-1 bg-gray-50 rounded border border-gray-200 focus:border-sky-400 focus:ring-0">
                                            <option value="">Base</option>
                                            <template x-for="u in item.unidades_adicionales" :key="u.unidad_id">
                                                <option :value="u.unidad_id" x-text="u.nombre"></option>
                                            </template>
                                        </select>
                                    </td>
                                    <td class="py-3 px-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <button type="button" @click="item.precio = item.precio_base_a; calcularTotales()"
                                                :class="item.precio === item.precio_base_a ? 'bg-sky-500 text-white' : 'bg-gray-100 text-gray-400 hover:bg-sky-50'"
                                                class="px-1.5 py-1 rounded text-[10px] font-bold transition-colors leading-none">P1</button>
                                            <button type="button" x-show="item.precio_base_b > 0" @click="item.precio = item.precio_base_b; calcularTotales()"
                                                :class="item.precio === item.precio_base_b ? 'bg-emerald-500 text-white' : 'bg-gray-100 text-gray-400 hover:bg-emerald-50'"
                                                class="px-1.5 py-1 rounded text-[10px] font-bold transition-colors leading-none">P2</button>
                                            <button type="button" x-show="item.precio_base_c > 0" @click="item.precio = item.precio_base_c; calcularTotales()"
                                                :class="item.precio === item.precio_base_c ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-400 hover:bg-orange-50'"
                                                class="px-1.5 py-1 rounded text-[10px] font-bold transition-colors leading-none">P3</button>
                                            <div class="relative inline-flex items-center">
                                                <span class="absolute left-1.5 text-xs text-gray-400">$</span>
                                                <input type="number" x-model.number="item.precio" @change="calcularTotales()" step="0.01"
                                                       class="w-24 pl-5 text-right text-sm py-1 bg-gray-50 rounded border border-gray-200 focus:border-sky-400 focus:ring-0 font-semibold">
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 pl-3 text-right text-sm font-bold text-gray-700"
                                        x-text="'$' + calcularTotalLinea(item).toFixed(2)"></td>
                                    <td class="py-3 text-center">
                                        <button @click="eliminarItem(index)" class="text-gray-300 hover:text-red-400 transition-colors">
                                            <i class="fas fa-times text-sm"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="items.length === 0">
                                <tr>
                                    <td colspan="7" class="py-16 text-center text-sm text-gray-400 italic">
                                        <i class="fas fa-shopping-basket text-gray-200 text-3xl block mb-2"></i>
                                        No hay productos. Busca un producto para comenzar.
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab: Notas -->
            <div x-show="tab === 'notas'" class="px-8 py-5">
                <textarea x-model="observaciones" rows="5"
                          placeholder="Notas internas o para el cliente..."
                          class="w-full text-sm text-gray-700 bg-transparent border-0 resize-none outline-none placeholder-gray-300 focus:ring-0 p-0"></textarea>
            </div>

        </div>

        <!-- Pie: Totales -->
        <div class="px-8 py-5 border-t border-gray-100 flex items-end justify-end">
            <div class="text-right space-y-1.5 min-w-[280px]">
                <div class="flex items-center justify-between gap-16 text-sm text-gray-500">
                    <span>Subtotal:</span>
                    <span class="font-semibold text-gray-700" x-text="'$' + subtotal.toFixed(2)"></span>
                </div>
                <div class="flex items-center justify-between gap-16 text-sm text-gray-500">
                    <span>Descuento:</span>
                    <div class="flex items-center gap-1">
                        <span class="text-gray-400 text-xs">$</span>
                        <input type="number" x-model.number="descuentoGlobal" @change="calcularTotales()"
                               class="w-20 text-right py-0.5 px-2 text-sm bg-gray-50 rounded border border-gray-200 focus:border-sky-400 focus:ring-0">
                    </div>
                </div>
                <div class="flex items-center justify-between gap-16 text-sm text-gray-500">
                    <span>ITBMS (7%):</span>
                    <span class="font-semibold text-gray-700" x-text="'$' + itbms.toFixed(2)"></span>
                </div>
                <div class="flex items-center justify-between gap-16 pt-2 border-t border-gray-100 text-sm">
                    <span class="font-semibold text-gray-700">Total:</span>
                    <span class="text-xl font-extrabold text-sky-600" x-text="'$' + total.toFixed(2)"></span>
                </div>
            </div>
        </div>

    </div><!-- /documento -->

</div><!-- /x-data -->

<script>
    function ventasForm() {
        return {
            tipoDocumento: '<?= $tipo_documento ?? 'venta' ?>',
            clienteId: '',
            clienteNombre: 'Consumidor Final',
            searchCliente: 'Consumidor Final',
            clienteRuc: 'N/A',
            clienteTelefono: 'N/A',
            clienteDireccion: 'Consumidor Final',
            depositoId: '<?= $depositos[0]->deposito_id ?? '' ?>',
            formaPago: 'efectivo',
            estado: '<?= ($tipo_documento ?? 'venta') === 'cotizacion' ? 'pendiente' : 'pagada' ?>',
            observaciones: '',
            query: '',
            cantidadBusqueda: 1,
            resultados: [],
            productoSeleccionado: null,
            items: [],
            subtotal: 0,
            itbms: 0,
            descuentoGlobal: 0,
            total: 0,
            cotizacionOrigenId: null,
            processing: false,
            toasts: [],

            init() {
                const preload = <?= json_encode($cotizacion_preload ?? null) ?>;
                if (preload) {
                    this.clienteId = preload.cliente_id || '';
                    this.clienteNombre = preload.cliente_nombre;
                    this.searchCliente = preload.cliente_nombre;
                    this.clienteRuc = preload.cliente_ruc;
                    this.clienteTelefono = preload.cliente_telefono;
                    this.clienteDireccion = preload.cliente_direccion;
                    this.observaciones = preload.notas;
                    this.items = preload.items;
                    this.cotizacionOrigenId = preload.cotizacion_id;
                    this.calcularTotales();
                    this.notify('Datos de cotización cargados', 'success');
                }
                this.$watch('tipoDocumento', (val) => {
                    this.estado = val === 'venta' ? 'pagada' : 'pendiente';
                });
            },

            notify(message, type = 'success') {
                const id = Date.now();
                this.toasts.push({ id, message, type, show: true });
                setTimeout(() => this.removeToast(id), 5000);
            },

            removeToast(id) {
                const index = this.toasts.findIndex(t => t.id === id);
                if (index !== -1) {
                    this.toasts[index].show = false;
                    setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 500);
                }
            },

            seleccionarCliente(id, nombre, ruc, tel, dir) {
                this.clienteId = id;
                this.clienteNombre = nombre;
                this.searchCliente = nombre;
                this.clienteRuc = ruc || 'N/A';
                this.clienteTelefono = tel || 'N/A';
                this.clienteDireccion = dir || 'Consumidor Final';
            },

            buscarProductos() {
                if (this.query.length < 1) { this.resultados = []; return; }
                fetch(`/api/productos/buscar?q=${encodeURIComponent(this.query)}`)
                    .then(r => r.json())
                    .then(data => {
                        this.resultados = data.productos || [];
                        if (this.resultados.length === 0) this.notify('No se encontraron productos', 'warning');
                    });
            },

            seleccionarProducto(p) {
                this.productoSeleccionado = p;
                this.query = p.nombre;
                this.resultados = [];
            },

            agregarProductoSiSeleccionado() {
                if (!this.productoSeleccionado) { this.notify('Selecciona un producto de la lista', 'warning'); return; }
                this.agregarItem(this.productoSeleccionado, this.cantidadBusqueda);
                this.productoSeleccionado = null;
                this.query = '';
                this.cantidadBusqueda = 1;
            },

            agregarItem(p, cant) {
                const index = this.items.findIndex(i => i.id === p.producto_id);
                if (index !== -1) {
                    this.items[index].cantidad += cant;
                } else {
                    this.items.push({
                        id: p.producto_id,
                        codigo: p.codigo,
                        nombre: p.nombre,
                        cantidad: cant,
                        unidad_id: '',
                        unidades_adicionales: p.unidades || [],
                        precio_base_a: parseFloat(p.precio_a) || 0,
                        precio_base_b: parseFloat(p.precio_b) || 0,
                        precio_base_c: parseFloat(p.precio_c) || 0,
                        precio: parseFloat(p.precio_a) || 0,
                        descuento: 0,
                        applica_itbms: p.itbms == 1
                    });
                }
                this.calcularTotales();
                this.notify(`Producto añadido: ${p.nombre}`, 'success');
            },

            cambiarUnidad(item) {
                if (!item.unidad_id) {
                    item.precio = item.precio_base_a;
                } else {
                    const unit = item.unidades_adicionales.find(u => u.unidad_id == item.unidad_id);
                    if (unit) item.precio = parseFloat(unit.precio_a) || 0;
                }
                this.calcularTotales();
            },

            eliminarItem(index) {
                this.items.splice(index, 1);
                this.calcularTotales();
            },

            calcularTotalLinea(item) {
                return (item.cantidad * item.precio) - (item.descuento || 0);
            },

            calcularTotales() {
                this.subtotal = this.items.reduce((sum, item) => sum + this.calcularTotalLinea(item), 0);
                let gravable = this.items.reduce((sum, item) => {
                    return item.applica_itbms ? sum + this.calcularTotalLinea(item) : sum;
                }, 0);
                this.itbms = (gravable - (this.descuentoGlobal * (gravable / this.subtotal || 0))) * 0.07;
                if (this.itbms < 0) this.itbms = 0;
                this.total = this.subtotal - this.descuentoGlobal + this.itbms;
            },

            async procesarDocumento() {
                if (this.items.length === 0) { this.notify('Agrega al menos un producto.', 'error'); return; }
                this.processing = true;
                const payload = {
                    tipo: this.tipoDocumento,
                    cliente_id: this.clienteId,
                    deposito_id: this.depositoId,
                    forma_pago: this.formaPago,
                    estado: this.estado,
                    descuento: this.descuentoGlobal,
                    notas: this.observaciones,
                    cotizacion_origen_id: this.cotizacionOrigenId,
                    items: this.items.map(i => ({
                        producto_id: i.id,
                        cantidad: i.cantidad,
                        unidad_id: i.unidad_id,
                        precio: i.precio,
                        descuento: i.descuento
                    }))
                };
                try {
                    const endpoint = this.tipoDocumento === 'venta' ? '/ventas/procesar' : '/ventas/cotizaciones/guardar';
                    const res = await fetch(endpoint, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '<?= $_SESSION['csrf_token'] ?? '' ?>' },
                        body: JSON.stringify(payload)
                    });
                    const data = await res.json();
                    if (!res.ok) throw new Error(data.error || 'Error al procesar');
                    this.notify('Guardado con éxito', 'success');
                    setTimeout(() => {
                        window.location.href = this.tipoDocumento === 'venta'
                            ? `/ventas/venta/${data.venta_id}`
                            : `/ventas/cotizaciones/${data.cotizacion_id}`;
                    }, 1200);
                } catch (e) {
                    this.notify(e.message, 'error');
                } finally {
                    this.processing = false;
                }
            }
        }
    }
</script>

<?php View::endSection('content'); ?>

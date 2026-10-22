<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<div x-data="trasladoForm()">

    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-sm text-gray-400 mb-3">
        <a href="/inventario/traslados" class="hover:text-gray-600 transition-colors">Traslados</a>
        <i class="fas fa-chevron-right text-xs"></i>
        <span class="text-gray-700 font-medium">Nuevo Traslado</span>
    </div>

    <!-- Action bar -->
    <div class="flex items-center justify-between bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-2.5 mb-4">
        <div class="flex gap-2">
            <button type="button" @click="saveTraslado()"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
                <i class="fas fa-paper-plane"></i> Enviar Mercancía
            </button>
            <a href="/inventario/traslados" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                Cancelar
            </a>
        </div>

        <!-- Pipeline -->
        <div class="flex items-stretch text-xs font-semibold select-none">
            <div class="flex items-center bg-sky-500 text-white pl-4 pr-6 py-2 rounded-l-lg relative">
                Borrador
                <span class="absolute right-0 top-0 h-full w-3 overflow-hidden translate-x-2.5 z-10">
                    <span class="block w-6 h-6 bg-sky-500 rotate-45 origin-top-left mt-0.5 ml-0.5"></span>
                </span>
            </div>
            <div class="flex items-center bg-gray-100 text-gray-400 pl-7 pr-4 py-2 relative">
                En Tránsito
                <span class="absolute right-0 top-0 h-full w-3 overflow-hidden translate-x-2.5 z-10">
                    <span class="block w-6 h-6 bg-gray-100 rotate-45 origin-top-left mt-0.5 ml-0.5"></span>
                </span>
            </div>
            <div class="flex items-center bg-gray-100 text-gray-400 pl-7 pr-5 py-2 rounded-r-lg">
                Completado
            </div>
        </div>
    </div>

    <!-- Document card -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
        <!-- Encabezado: depósitos -->
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-3xl font-bold text-gray-900 mb-6">Nuevo Traslado</h2>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-16">
                <div>
                    <div class="flex items-baseline gap-4 py-2">
                        <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Origen</label>
                        <select x-model="traslado.origen_id" class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                            <option value="">Seleccione depósito de origen</option>
                            <?php foreach ($depositos_origen as $d): ?>
                            <option value="<?= $d['deposito_id'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-baseline gap-4 py-2">
                        <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Destino</label>
                        <select x-model="traslado.destino_id" class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                            <option value="">Seleccione depósito de destino</option>
                            <?php foreach ($depositos_destino as $d): ?>
                            <option value="<?= $d['deposito_id'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div x-data="{ tab: 'productos' }">
            <div class="border-b border-gray-100 px-6 flex gap-6">
                <button type="button" @click="tab = 'productos'"
                    :class="tab === 'productos' ? 'border-b-2 border-sky-500 text-sky-600' : 'text-gray-400 hover:text-gray-600'"
                    class="py-3 text-sm font-semibold -mb-px transition-colors">
                    Productos
                </button>
                <button type="button" @click="tab = 'notas'"
                    :class="tab === 'notas' ? 'border-b-2 border-sky-500 text-sky-600' : 'text-gray-400 hover:text-gray-600'"
                    class="py-3 text-sm font-semibold -mb-px transition-colors">
                    Notas
                </button>
            </div>

            <!-- Tab: Productos -->
            <div x-show="tab === 'productos'" class="p-6">
                <!-- Buscador -->
                <div class="relative mb-4" @click.away="resultados = []">
                    <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-sm"></i>
                    <input type="text" x-model="searchQuery" @input.debounce.300ms="buscarProductos()"
                        placeholder="Buscar producto para agregar al traslado..."
                        class="w-full pl-9 pr-4 py-2 text-sm rounded-lg border border-gray-200 focus:outline-none focus:ring-1 focus:ring-sky-500">

                    <!-- Dropdown resultados -->
                    <div x-show="resultados.length > 0"
                         class="absolute z-50 left-0 right-0 mt-1 bg-white border border-gray-100 rounded-xl shadow-2xl max-h-72 overflow-y-auto">
                        <template x-for="p in resultados" :key="p.producto_id">
                            <div @click="seleccionarProducto(p)"
                                 class="p-3 border-b border-gray-50 hover:bg-sky-50 cursor-pointer transition-all flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 bg-gray-50 rounded-lg flex items-center justify-center text-gray-400">
                                        <i class="fas fa-box text-xs"></i>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-gray-800 text-sm" x-text="p.nombre"></div>
                                        <div class="text-xs text-gray-400" x-text="p.codigo"></div>
                                    </div>
                                </div>
                                <i class="fas fa-plus-circle text-sky-400 text-sm"></i>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Tabla de productos -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-[10px] font-black text-gray-500 uppercase tracking-wider">Producto</th>
                                <th class="px-4 py-3 text-center text-[10px] font-black text-gray-500 uppercase tracking-wider">Cantidad</th>
                                <th class="px-4 py-3 w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="(item, index) in items" :key="index">
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3">
                                        <div class="text-sm font-semibold text-gray-800" x-text="item.nombre"></div>
                                        <div class="text-xs text-gray-400" x-text="item.codigo"></div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-center items-center gap-2">
                                            <button type="button" @click="item.cantidad > 1 ? item.cantidad-- : null"
                                                class="w-7 h-7 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 text-sm">−</button>
                                            <input type="number" x-model.number="item.cantidad"
                                                class="w-16 text-center py-1.5 text-sm bg-gray-50 rounded-lg border border-gray-200 focus:border-sky-400 focus:ring-0 outline-none font-semibold">
                                            <button type="button" @click="item.cantidad++"
                                                class="w-7 h-7 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 text-sm">+</button>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button type="button" @click="removeItem(index)"
                                            class="text-gray-300 hover:text-red-500 transition-colors">
                                            <i class="fas fa-times-circle"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="items.length === 0">
                                <tr>
                                    <td colspan="3" class="px-4 py-12 text-center text-sm text-gray-400 italic">
                                        Busca y agrega productos para trasladar.
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab: Notas -->
            <div x-show="tab === 'notas'" class="p-6">
                <textarea x-model="traslado.notas" rows="4"
                    placeholder="Motivo del traslado u observaciones adicionales..."
                    class="w-full py-2 px-3 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:border-sky-400 focus:ring-0 outline-none resize-none"></textarea>

                <div class="mt-4 flex items-start gap-3 bg-blue-50 border border-blue-100 rounded-lg p-4">
                    <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
                    <p class="text-xs text-blue-700 leading-relaxed">
                        Al enviar, los productos se descontarán del inventario origen y quedarán "En Tránsito" hasta confirmar su recepción en destino.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function trasladoForm() {
    return {
        searchQuery: '',
        resultados: [],
        items: [],
        traslado: {
            origen_id: '',
            destino_id: '',
            notas: ''
        },
        async buscarProductos() {
            if (this.searchQuery.length < 2) { this.resultados = []; return; }
            try {
                const response = await fetch(`/api/productos/buscar?q=${encodeURIComponent(this.searchQuery)}`);
                const data = await response.json();
                this.resultados = data.productos || [];
            } catch (error) {
                console.error('Error buscando productos:', error);
            }
        },
        seleccionarProducto(p) {
            const existing = this.items.find(i => i.producto_id === p.producto_id);
            if (existing) {
                existing.cantidad++;
            } else {
                this.items.push({ producto_id: p.producto_id, nombre: p.nombre, codigo: p.codigo, cantidad: 1 });
            }
            this.searchQuery = '';
            this.resultados = [];
        },
        removeItem(index) {
            this.items.splice(index, 1);
        },
        async saveTraslado() {
            if (!this.traslado.origen_id) return alert('Seleccione un depósito de origen');
            if (!this.traslado.destino_id) return alert('Seleccione un depósito de destino');
            if (this.traslado.origen_id == this.traslado.destino_id) return alert('Origen y destino deben ser diferentes');
            if (this.items.length === 0) return alert('Agregue al menos un producto');
            if (!confirm('¿Desea realizar el envío de mercancía?')) return;
            try {
                const response = await fetch('/inventario/traslados/guardar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ...this.traslado, items: this.items })
                });
                const res = await response.json();
                if (res.success) {
                    window.location.href = '/inventario/traslados';
                } else {
                    alert('Error: ' + res.error);
                }
            } catch (error) {
                alert('Ocurrió un error al guardar el traslado');
            }
        }
    }
}
</script>

<?php View::endSection('content'); ?>

<?php $this->extend('layouts/app'); ?>
<?php $this->section('content'); ?>
<?php $puede_fecha_anterior = ($tipo_documento ?? 'venta') === 'venta' && \App\Libraries\Auth::can('ventas.fecha_anterior'); ?>

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
        <div x-show="false" class="flex items-stretch text-xs font-semibold select-none">
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
        <div class="px-8 pb-6 grid grid-cols-1 md:grid-cols-2 gap-x-16 gap-y-6 border-b border-gray-100">

            <!-- Columna izquierda: Cliente -->
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Cliente</label>

                <!-- Cliente elegido -->
                <div x-show="clienteId" class="rounded-xl border border-sky-100 bg-sky-50/60 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-bold text-gray-800 truncate" x-text="clienteNombre"></p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                <span x-show="clienteRuc && clienteRuc !== 'N/A'">RUC <span x-text="clienteRuc"></span> · </span>
                                <span x-show="clienteTelefono && clienteTelefono !== 'N/A'">Tel. <span x-text="clienteTelefono"></span></span>
                            </p>
                        </div>
                        <button type="button" @click="quitarCliente()" class="shrink-0 text-xs font-semibold text-sky-600 hover:text-sky-800">
                            <i class="fas fa-rotate"></i> Cambiar
                        </button>
                    </div>
                    <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-lg bg-white px-2 py-1.5">
                            <p class="text-[10px] uppercase text-gray-400 font-semibold">Debe</p>
                            <p class="text-sm font-bold" :class="clienteSaldo > 0 ? 'text-red-600' : 'text-gray-700'" x-text="'$' + clienteSaldo.toFixed(2)"></p>
                        </div>
                        <div class="rounded-lg bg-white px-2 py-1.5">
                            <p class="text-[10px] uppercase text-gray-400 font-semibold">Límite crédito</p>
                            <p class="text-sm font-bold text-gray-700" x-text="'$' + clienteLimite.toFixed(2)"></p>
                        </div>
                        <div class="rounded-lg bg-white px-2 py-1.5">
                            <p class="text-[10px] uppercase text-gray-400 font-semibold">Disponible</p>
                            <p class="text-sm font-bold text-emerald-600" x-text="'$' + Math.max(0, clienteLimite - clienteSaldo).toFixed(2)"></p>
                        </div>
                    </div>
                </div>

                <!-- Buscar / crear cliente -->
                <div x-show="!clienteId" class="relative" @click.away="abiertoCliente = false">
                    <div class="relative">
                        <i class="fas fa-user absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-sm"></i>
                        <input type="text" x-model="searchCliente" x-ref="buscarCliente"
                               @focus="abiertoCliente = true" @input="abiertoCliente = true"
                               @keydown.escape="abiertoCliente = false"
                               placeholder="Consumidor Final — escribe para buscar o crear un cliente"
                               class="w-full pl-9 pr-3 py-2.5 text-sm rounded-xl border border-gray-200 focus:ring-1 focus:ring-sky-400 focus:border-sky-400 outline-none">
                    </div>
                    <div x-show="abiertoCliente" x-cloak
                         class="absolute z-50 left-0 right-0 mt-1 bg-white border border-gray-100 rounded-xl shadow-2xl max-h-80 overflow-y-auto">
                        <template x-for="c in clientesFiltrados()" :key="c.id">
                            <div @click="elegirCliente(c)"
                                 class="px-4 py-2.5 border-b border-gray-50 hover:bg-sky-50 cursor-pointer flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-800 truncate" x-text="c.nombre"></p>
                                    <p class="text-xs text-gray-400" x-text="[c.codigo, c.ruc, c.telefono].filter(Boolean).join(' · ')"></p>
                                </div>
                                <span x-show="c.saldo > 0" class="shrink-0 text-xs font-semibold text-red-600" x-text="'Debe $' + c.saldo.toFixed(2)"></span>
                            </div>
                        </template>
                        <div x-show="searchCliente.trim() && !clientesFiltrados().length" class="px-4 py-3 text-sm text-gray-400">No hay clientes con ese nombre.</div>
                        <?php if (\App\Libraries\Auth::can('clientes.crear')): ?>
                        <div @click="abrirNuevoCliente()"
                             class="px-4 py-3 bg-emerald-50 hover:bg-emerald-100 cursor-pointer text-sm font-semibold text-emerald-700 sticky bottom-0">
                            <i class="fas fa-user-plus mr-1"></i>
                            <span x-text="searchCliente.trim() ? 'Crear cliente «' + searchCliente.trim() + '»' : 'Crear cliente nuevo'"></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <p class="mt-1.5 text-xs text-gray-400">Sin cliente la venta queda a <b>Consumidor Final</b>.</p>
                </div>

                <!-- Crear cliente rápido -->
                <div x-show="nuevoCliente.abierto" x-cloak class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 space-y-3">
                    <p class="text-sm font-bold text-emerald-800"><i class="fas fa-user-plus mr-1"></i> Nuevo cliente</p>
                    <input type="text" x-model="nuevoCliente.nombre" placeholder="Nombre *" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-emerald-400 outline-none">
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" x-model="nuevoCliente.telefono" placeholder="Teléfono" class="px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-emerald-400 outline-none">
                        <input type="text" x-model="nuevoCliente.ruc" placeholder="Cédula / RUC" class="px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-emerald-400 outline-none">
                    </div>
                    <?php if (\App\Libraries\Auth::can('clientes.editar')): ?>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Límite de crédito <span class="font-normal text-gray-400">(para venderle a crédito)</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-gray-400 text-sm">$</span>
                            <input type="number" min="0" step="0.01" x-model="nuevoCliente.limite" class="w-full pl-7 pr-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-emerald-400 outline-none">
                        </div>
                    </div>
                    <?php endif; ?>
                    <p x-show="nuevoCliente.error" class="text-xs text-red-600" x-text="nuevoCliente.error"></p>
                    <div class="flex gap-2">
                        <button type="button" @click="crearCliente()" :disabled="nuevoCliente.guardando"
                                class="flex-1 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg disabled:opacity-50">
                            <span x-text="nuevoCliente.guardando ? 'Creando…' : 'Crear y usar'"></span>
                        </button>
                        <button type="button" @click="nuevoCliente.abierto = false" class="px-4 py-2 text-sm text-gray-500 border border-gray-200 rounded-lg bg-white">Cancelar</button>
                    </div>
                </div>
            </div>

            <!-- Columna derecha: Pago y fecha -->
            <div class="space-y-4">
                <div x-show="tipoDocumento === 'venta'">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Forma de pago</label>
                    <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
                        <template x-for="f in [['efectivo','Efectivo','fa-money-bill-wave'],['yappy','Yappy','fa-mobile-screen'],['transferencia','Transferencia','fa-building-columns'],['tarjeta','Tarjeta','fa-credit-card'],['credito','Crédito','fa-hand-holding-dollar']]" :key="f[0]">
                            <button type="button" @click="formaPago = f[0]"
                                    :class="formaPago === f[0] ? (f[0] === 'credito' ? 'bg-amber-500 border-amber-500 text-white' : 'bg-sky-500 border-sky-500 text-white') : 'bg-white border-gray-200 text-gray-600 hover:border-sky-300'"
                                    class="flex flex-col items-center gap-1 rounded-xl border px-2 py-2 text-xs font-semibold transition">
                                <i class="fas" :class="f[2]"></i><span x-text="f[1]"></span>
                            </button>
                        </template>
                    </div>
                    <!-- Aviso de crédito -->
                    <div x-show="formaPago === 'credito'" class="mt-3 rounded-xl border px-3 py-2 text-xs"
                         :class="avisoCredito().nivel === 'ok' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800'">
                        <span x-text="avisoCredito().texto"></span>
                        <?php if (\App\Libraries\Auth::can('clientes.editar')): ?>
                        <button type="button" x-show="avisoCredito().subirA > 0" @click="subirLimite(avisoCredito().subirA)"
                                class="ml-1 font-bold underline" x-text="'Subir límite a $' + avisoCredito().subirA.toFixed(2)"></button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($puede_fecha_anterior): ?>
                <div x-show="tipoDocumento === 'venta'">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Fecha de la venta</label>
                    <input type="date" x-model="fechaVenta" max="<?= date('Y-m-d') ?>"
                           class="w-full px-3 py-2.5 text-sm rounded-xl border border-gray-200 focus:ring-1 focus:ring-sky-400 outline-none">
                    <p x-show="fechaVenta && fechaVenta !== hoy" class="mt-1.5 text-xs text-amber-700">
                        Día anterior: solo a <b>crédito</b>. Entra en la caja de ese día de esta tienda (aunque ya esté cerrada) y no cambia su efectivo.
                    </p>
                </div>
                <?php endif; ?>

                <div x-show="tipoDocumento === 'cotizacion'">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Estado de la cotización</label>
                    <select x-model="estado" class="w-full px-3 py-2.5 text-sm rounded-xl border border-gray-200 focus:ring-1 focus:ring-sky-400 outline-none">
                        <option value="borrador">Borrador</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="aprobada">Aprobada</option>
                        <option value="rechazada">Rechazada</option>
                    </select>
                </div>
                <div x-show="tipoDocumento === 'cotizacion'">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Válida hasta</label>
                    <input type="date" value="<?= $fecha_validez ?>" class="w-full px-3 py-2.5 text-sm rounded-xl border border-gray-200 focus:ring-1 focus:ring-sky-400 outline-none">
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
                <div class="flex items-center justify-between gap-16 text-sm text-gray-500"<?= tasa_itbms() > 0 ? "" : " style=\"display:none\"" ?>>
                    <span>ITBMS (<?= rtrim(rtrim(number_format(tasa_itbms() * 100, 2), "0"), ".") ?>%):</span>
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
            searchCliente: '',
            clienteSaldo: 0,
            clienteLimite: 0,
            abiertoCliente: false,
            nuevoCliente: { abierto: false, nombre: '', telefono: '', ruc: '', limite: '', guardando: false, error: '' },
            // Clientes activos (para buscar sin ir al servidor) con su saldo y límite de crédito
            clientes: <?= json_encode(array_map(fn ($c) => [
                'id'        => (string) $c['cliente_id'],
                'codigo'    => (string) ($c['codigo'] ?? ''),
                'nombre'    => (string) ($c['nombre'] ?? ''),
                'ruc'       => (string) ($c['ruc'] ?? ''),
                'telefono'  => (string) ($c['telefono'] ?? ''),
                'direccion' => (string) ($c['direccion'] ?? ''),
                'saldo'     => (float) ($c['saldo_pendiente'] ?? 0),
                'limite'    => (float) ($c['limite_credito'] ?? 0),
            ], $clientes ?? []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>,
            clienteRuc: 'N/A',
            clienteTelefono: 'N/A',
            clienteDireccion: 'Consumidor Final',
            depositoId: '<?= $depositos[0]->deposito_id ?? '' ?>',
            formaPago: 'efectivo',
            hoy: '<?= date('Y-m-d') ?>',
            fechaVenta: '<?= date('Y-m-d') ?>',
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
                    const pc = this.clientes.find(c => c.id === String(preload.cliente_id || ''));
                    if (pc) { this.clienteSaldo = pc.saldo; this.clienteLimite = pc.limite; }
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
                this.clienteRuc = ruc || 'N/A';
                this.clienteTelefono = tel || 'N/A';
                this.clienteDireccion = dir || 'Consumidor Final';
            },

            // Búsqueda sin acentos por nombre, código, RUC o teléfono (máx. 40 resultados)
            normalizar(t) { return String(t || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); },
            clientesFiltrados() {
                const q = this.normalizar(this.searchCliente.trim());
                const lista = q
                    ? this.clientes.filter(c => this.normalizar([c.nombre, c.codigo, c.ruc, c.telefono].join(' ')).includes(q))
                    : this.clientes;
                return lista.slice(0, 40);
            },
            elegirCliente(c) {
                this.seleccionarCliente(c.id, c.nombre, c.ruc, c.telefono, c.direccion);
                this.clienteSaldo = c.saldo;
                this.clienteLimite = c.limite;
                this.abiertoCliente = false;
                this.searchCliente = '';
            },
            quitarCliente() {
                this.seleccionarCliente('', 'Consumidor Final', 'N/A', 'N/A', 'Consumidor Final');
                this.clienteSaldo = 0;
                this.clienteLimite = 0;
                this.$nextTick(() => { this.$refs.buscarCliente?.focus(); this.abiertoCliente = true; });
            },
            abrirNuevoCliente() {
                this.nuevoCliente = { abierto: true, nombre: this.searchCliente.trim(), telefono: '', ruc: '', limite: '', guardando: false, error: '' };
                this.abiertoCliente = false;
            },
            async crearCliente() {
                const n = this.nuevoCliente;
                n.error = '';
                if (!n.nombre.trim()) { n.error = 'Escribe el nombre.'; return; }
                const existe = this.clientes.find(c => this.normalizar(c.nombre) === this.normalizar(n.nombre.trim()));
                if (existe && !confirm('Ya existe un cliente llamado "' + existe.nombre + '". ¿Crear otro igual?')) return;
                n.guardando = true;
                try {
                    const r = await fetch('/ventas/clientes/rapido', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '<?= csrf_hash() ?>' },
                        body: JSON.stringify({ nombre: n.nombre.trim(), telefono: n.telefono.trim(), ruc: n.ruc.trim(), limite_credito: n.limite === '' ? 0 : n.limite })
                    });
                    const d = await r.json();
                    if (!r.ok || !d.success) throw new Error(d.error || 'No se pudo crear el cliente.');
                    const c = { id: String(d.cliente_id), codigo: d.codigo || '', nombre: d.nombre, ruc: n.ruc.trim(), telefono: n.telefono.trim(), direccion: '', saldo: 0, limite: parseFloat(d.limite_credito) || 0 };
                    this.clientes.unshift(c);
                    this.elegirCliente(c);
                    n.abierto = false;
                    this.notify('Cliente creado: ' + c.nombre, 'success');
                } catch (e) {
                    n.error = e.message;
                }
                n.guardando = false;
            },
            // Si es a crédito: ¿le alcanza el límite al cliente para este total?
            avisoCredito() {
                if (!this.clienteId) return { nivel: 'mal', texto: 'Elige el cliente al que se le fía.', subirA: 0 };
                const disp = Math.max(0, this.clienteLimite - this.clienteSaldo);
                const total = Math.round(this.total * 100) / 100;
                if (total <= disp + 0.005) return { nivel: 'ok', texto: 'Queda debiendo $' + (this.clienteSaldo + total).toFixed(2) + ' (límite $' + this.clienteLimite.toFixed(2) + ').', subirA: 0 };
                const necesita = Math.ceil((this.clienteSaldo + total) * 100) / 100;
                return { nivel: 'mal', texto: 'El límite de crédito no alcanza: disponible $' + disp.toFixed(2) + ', esta venta $' + total.toFixed(2) + '.', subirA: total > 0 ? necesita : 0 };
            },
            async subirLimite(monto) {
                if (!confirm('¿Subir el límite de crédito de ' + this.clienteNombre + ' a $' + monto.toFixed(2) + '?')) return;
                try {
                    const r = await fetch('/ventas/clientes/' + this.clienteId + '/limite', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '<?= csrf_hash() ?>' },
                        body: JSON.stringify({ limite_credito: monto })
                    });
                    const d = await r.json();
                    if (!r.ok || !d.success) throw new Error(d.error || 'No se pudo cambiar el límite.');
                    this.clienteLimite = parseFloat(d.limite_credito);
                    const c = this.clientes.find(x => x.id === String(this.clienteId));
                    if (c) c.limite = this.clienteLimite;
                    this.notify('Límite de crédito actualizado', 'success');
                } catch (e) {
                    this.notify(e.message, 'error');
                }
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
                this.itbms = (gravable - (this.descuentoGlobal * (gravable / this.subtotal || 0))) * <?= json_encode(tasa_itbms()) ?>;
                if (this.itbms < 0) this.itbms = 0;
                this.total = this.subtotal - this.descuentoGlobal + this.itbms;
            },

            async procesarDocumento() {
                if (this.items.length === 0) { this.notify('Agrega al menos un producto.', 'error'); return; }
                const anterior = this.tipoDocumento === 'venta' && this.fechaVenta && this.fechaVenta !== this.hoy;
                if (anterior) {
                    if (this.formaPago !== 'credito') { this.notify('Una venta de un día anterior solo puede ser a crédito.', 'error'); return; }
                    if (!this.clienteId) { this.notify('Elige el cliente del crédito.', 'error'); return; }
                    if (!confirm('¿Registrar esta venta a crédito con fecha ' + this.fechaVenta.split('-').reverse().join('/') + '?')) return;
                }
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
                    fecha: anterior ? this.fechaVenta : null,
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
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '<?= csrf_hash() ?>' },
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

<?php // Quien puede cargar ventas de días anteriores no necesita caja abierta para eso (las de hoy la siguen pidiendo en el servidor) ?>
<?= view('cajas/_aviso_abrir', ['caja_actual' => $caja_actual ?? null, 'exigir_caja' => ($exigir_caja ?? false) && !$puede_fecha_anterior, 'current_uri' => '/ventas/nueva']) ?>

<?php $this->endSection(); ?>

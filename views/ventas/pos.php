<?php use App\Core\View; ?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>POS | Sistema POS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: "#3B82F6",
                        secondary: "#8B5CF6",
                        dark: "#1F2937",
                        light: "#F9FAFB",
                        success: "#10B981",
                        danger: "#EF4444",
                        warning: "#F59E0B",
                        info: "#06B6D4",
                        pos: "#0EA5E9",
                    },
                },
            },
        };
    </script>
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap");

        body {
            font-family: "Inter", sans-serif;
            background: #f8fafc;
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow: hidden;
        }

        .receipt-font {
            font-family: "Courier New", monospace;
        }

        .btn-cotizar {
            background-color: #2563eb;
        }

        .btn-pagar {
            background-color: #16a34a;
        }

        .btn-cancelar {
            background-color: #dc2626;
        }

        .btn-imprimir {
            background-color: #f59e0b;
        }

        /* Scroll personalizado */
        .scroll-custom::-webkit-scrollbar {
            width: 6px;
        }

        .scroll-custom::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .scroll-custom::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        .action-btn {
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            transform: scale(1.05);
        }

        /* Select2 Tailwind styling */
        .select2-container--default .select2-selection--single {
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            height: 38px;
            padding-top: 4px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }
        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: #0EA5E9;
            box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.2);
        }
    </style>
    <style>
        #toast-container { position: fixed; top: 1.5rem; right: 1.5rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.75rem; }
        .toast { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.25rem; border-radius: 0.75rem; box-shadow: 0 8px 24px rgba(0,0,0,0.18); font-size: 1rem; font-weight: 600; min-width: 320px; max-width: 460px; animation: toastIn 0.3s ease; }
        .toast i { font-size: 1.4rem; flex-shrink: 0; }
        .toast.success { background: #dcfce7; color: #14532d; border-left: 6px solid #16a34a; }
        .toast.error   { background: #fee2e2; color: #7f1d1d; border-left: 6px solid #dc2626; }
        .toast.info    { background: #dbeafe; color: #1e3a8a; border-left: 6px solid #2563eb; }
        @keyframes toastIn { from { opacity:0; transform:translateX(2rem); } to { opacity:1; transform:translateX(0); } }
    </style>
</head>

<body class="min-h-screen bg-gray-50 flex flex-col">
    <div id="toast-container"></div>
    <!-- Header fijo premium -->
    <header class="bg-white shadow-md border-b border-gray-200 sticky top-0 z-50">
        <div class="px-4 py-2">
            <div class="flex justify-between items-center">
                <!-- Logo y Empresa -->
                <div class="flex items-center space-x-4">
                    <div class="bg-pos p-2 rounded-xl shadow-lg shadow-pos/20">
                        <i class="fas fa-cash-register text-white text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-sm font-black text-slate-800 uppercase tracking-tight leading-none"><?= View::e($empresa_actual['nombre_comercial'] ?? $empresa_actual['razon_social'] ?? 'Mi Empresa') ?></h1>
                        <div class="flex items-center gap-2 mt-0.5">
                            <p class="text-[10px] text-pos font-bold uppercase tracking-widest"><i class="fas fa-map-marker-alt mr-1"></i>Sede Principal</p>
                            <span class="text-[9px] text-slate-400 font-mono bg-slate-100 rounded px-1 py-0.5">v<?= APP_VERSION ?></span>
                        </div>
                    </div>
                </div>

                <!-- Centro: Reloj y Accesos Rápidos -->
                <div class="hidden lg:flex items-center space-x-8">
                    <div class="text-center">
                        <p id="clock-date" class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter"></p>
                        <p id="clock-time" class="text-lg font-black text-slate-700 leading-none tabular-nums"></p>
                    </div>
                    <div class="h-8 w-[1px] bg-gray-200"></div>
                    <nav class="flex items-center space-x-2">
                        <!-- Panel de cotizaciones activas -->
                        <div class="relative">
                            <button id="btn-cotizaciones"
                                class="flex items-center px-3 py-1.5 text-xs font-bold text-slate-600 hover:text-amber-500 hover:bg-amber-50 rounded-lg transition-all group">
                                <i class="fas fa-file-invoice-dollar mr-2 text-slate-400 group-hover:text-amber-500"></i>
                                Cotizaciones
                                <span id="badge-cotizaciones"
                                    class="ml-1.5 bg-amber-400 text-white text-[10px] font-black rounded-full px-1.5 py-0.5 leading-none hidden">0</span>
                            </button>

                            <!-- Dropdown panel -->
                            <div id="panel-cotizaciones"
                                class="hidden absolute top-full mt-2 left-0 w-[420px] bg-white rounded-xl shadow-2xl border border-gray-100 z-50 overflow-hidden">

                                <!-- Cabecera -->
                                <div class="px-4 py-3 bg-amber-50 border-b border-amber-100 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-file-invoice-dollar text-amber-500 text-sm"></i>
                                        <span class="text-sm font-bold text-amber-800">Cotizaciones Activas</span>
                                    </div>
                                    <a href="/ventas/cotizaciones" target="_blank"
                                        class="text-[10px] font-semibold text-amber-600 hover:text-amber-800 hover:underline">
                                        Ver todas →
                                    </a>
                                </div>

                                <!-- Buscador -->
                                <div class="px-3 py-2 border-b border-gray-100">
                                    <div class="relative">
                                        <i class="fas fa-search absolute left-2.5 top-2 text-gray-300 text-xs"></i>
                                        <input type="text" id="buscar-cotizacion"
                                            placeholder="Número o nombre de cliente..."
                                            autocomplete="off"
                                            class="w-full pl-7 pr-3 py-1.5 text-xs border border-gray-200 rounded-lg focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-200 bg-gray-50">
                                    </div>
                                </div>

                                <!-- Lista -->
                                <div id="lista-cotizaciones" class="max-h-72 overflow-y-auto divide-y divide-gray-50">
                                    <div class="px-4 py-6 text-center text-xs text-gray-400">
                                        <i class="fas fa-spinner fa-spin mr-1"></i> Cargando...
                                    </div>
                                </div>
                            </div>
                        </div>

                        <a href="/configuracion" class="flex items-center px-3 py-1.5 text-xs font-bold text-slate-600 hover:text-amber-500 hover:bg-amber-50 rounded-lg transition-all group" title="Configurar descuentos POS">
                            <i class="fas fa-cog mr-2 text-slate-400 group-hover:text-amber-500"></i>Configuración
                        </a>
                    </nav>
                </div>

                <!-- Usuario y Salida -->
                <div class="flex items-center space-x-4">
                    <a href="/dashboard" onclick="return salirDelPos();" class="flex items-center px-3 py-2 text-xs font-bold text-slate-600 hover:text-sky-600 hover:bg-sky-50 rounded-lg transition-all border border-slate-100" title="Salir del POS y volver al panel">
                        <i class="fas fa-sign-out-alt mr-2"></i>Salir del POS
                    </a>
                    <div class="flex items-center bg-slate-50 border border-slate-100 p-1 rounded-full pr-4 group hover:bg-white hover:shadow-sm transition-all cursor-pointer">
                        <div class="w-8 h-8 rounded-full bg-pos flex items-center justify-center text-white font-black text-xs shadow-md shadow-pos/20 ring-2 ring-white">
                            <?= strtoupper(substr($auth['name'] ?? 'U', 0, 1)) ?>
                        </div>
                        <div class="ml-2">
                            <p class="text-[11px] font-black text-slate-800 leading-none"><?= View::e($auth['name'] ?? 'Usuario') ?></p>
                            <p class="text-[9px] text-slate-400 font-bold uppercase mt-0.5"><?= ($auth['isSuperuser'] ?? false) ? 'Administrador' : 'Vendedor' ?></p>
                        </div>
                    </div>
                    
                    <a href="/logout" class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-100 text-slate-400 hover:bg-red-50 hover:text-red-500 transition-all border border-transparent hover:border-red-100" title="Cerrar Sesión">
                        <i class="fas fa-power-off"></i>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Contenido principal - Ocupa todo el espacio disponible -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Barra de búsqueda y controles -->
        <div class="bg-white border-b border-gray-200 p-4">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                <!-- Campo Código/Nombre -->
                <div class="md:col-span-5">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Código/Nombre</label>
                    <div class="relative">
                        <input type="text" id="txcodigo" placeholder="Buscar producto por código o nombre..."
                            class="w-full pl-10 pr-9 py-2 text-sm rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-pos focus:border-transparent"
                            autofocus />
                        <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-sm"></i>
                        <button type="button" id="btn-limpiar-busqueda" onclick="limpiarBusqueda()" title="Limpiar búsqueda (Esc)"
                            class="hidden absolute right-2 top-1.5 w-6 h-6 rounded-full text-gray-400 hover:text-red-500 hover:bg-red-50 transition">
                            <i class="fas fa-times text-sm"></i>
                        </button>
                    </div>
                    <input type="hidden" id="txhcodigo" />
                </div>

                <!-- Selector Cliente -->
                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                    <div class="flex gap-2">
                        <div class="flex-1 min-w-0">
                            <select id="txcliente" style="width:100%"
                                class="px-4 py-2 text-sm rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-pos focus:border-transparent">
                                <option value="">Consumidor Final</option>
                                <?php foreach (($clientes ?? []) as $cliente): ?>
                                <option value="<?= $cliente['cliente_id'] ?>"><?= View::e(mb_substr($cliente['nombre'], 0, 40)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" id="btn-nuevo-cliente" title="Agregar nuevo cliente"
                            class="px-3 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition shrink-0 text-sm">
                            <i class="fas fa-user-plus"></i>
                        </button>
                    </div>
                </div>

                <!-- Info crédito cliente -->
                <div class="md:col-span-3" id="panel-credito-cliente" style="display:none;">
                    <div id="credito-badge" class="text-xs rounded-lg px-3 py-2 border"></div>
                </div>
            </div>
        </div>

        <!-- Área de contenido dividida -->
        <div class="flex-1 flex overflow-hidden">
            <!-- Lista de productos en carrito (2/3 del ancho) -->
            <div class="flex-1 overflow-auto p-4 scroll-custom">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 h-full">
                    <div class="border-b border-gray-200 p-4 flex justify-between">
                        <h3 class="text-lg font-semibold text-gray-900" id="titulo-tabla">Carrito de Venta</h3>
                        <div class="flex items-center gap-2">
                            <button type="button" id="btn-cerrar-busqueda" onclick="limpiarBusqueda()"
                                class="hidden text-xs font-bold text-slate-600 hover:text-red-600 bg-slate-100 hover:bg-red-50 border border-slate-200 rounded px-2 py-1 transition">
                                <i class="fas fa-times mr-1"></i>Cerrar búsqueda (Esc)
                            </button>
                            <div class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded font-bold"><span id="item-count">0</span> items</div>
                        </div>
                    </div>
                    <div class="overflow-auto h-[calc(100%-60px)]">
                        <div id="contenedor-carrito">
                            <table class="min-w-full divide-y divide-gray-200" id="tabla-carrito">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">N°
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                            Código</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                            Descripción</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                                            Cantidad</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                                            Precio</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                            Desc %</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                                            Subtotal</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                            Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="posTable" class="bg-white divide-y divide-gray-200 receipt-font">
                                    <tr id="sin-productos">
                                        <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500">
                                            <i class="fas fa-shopping-cart text-3xl text-gray-300 mb-2"></i>
                                            <p>No hay productos en el carrito</p>
                                            <p class="text-xs mt-2">Busque productos usando el campo de búsqueda
                                                superior</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div id="contenedor-busqueda" class="hidden">
                            <table class="min-w-full divide-y divide-gray-200" id="tabla-busqueda">
                                <thead class="bg-pos text-white">
                                    <tr>
                                        <th class="px-3 py-3 text-left text-xs font-medium uppercase">N°</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium uppercase">Código</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium uppercase">Descripción</th>
                                        <th class="px-3 py-3 text-center text-xs font-medium uppercase">Cantidad</th>
                                        <th class="px-3 py-3 text-center text-xs font-medium uppercase">Precio A</th>
                                        <th class="px-3 py-3 text-center text-xs font-medium uppercase">Precio B</th>
                                        <th class="px-3 py-3 text-center text-xs font-medium uppercase">Precio C</th>
                                        <th class="px-3 py-3 text-center text-xs font-medium uppercase">Imagen</th>
                                    </tr>
                                </thead>
                                <tbody id="cuerpo-busqueda" class="bg-white divide-y divide-gray-200">
                                    <!-- Los resultados de búsqueda se cargarán aquí -->
                                </tbody>
                            </table>

                            <!-- Paginación para resultados de búsqueda -->
                            <div id="paginacion-busqueda" class="px-4 py-3 bg-gray-50 border-t border-gray-200">
                                <div class="flex justify-between items-center">
                                    <div class="text-sm text-gray-700" id="info-paginacion"></div>
                                    <div class="flex space-x-1" id="controles-paginacion"></div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Panel lateral derecho (1/3 del ancho) -->
            <div class="w-96 border-l border-gray-200 flex flex-col">
                <!-- Totales -->
                <div class="p-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">
                        Totales
                        <span class="text-xs font-normal text-gray-400 ml-2">Desc. máx: <?= (int)($max_descuento_pct ?? 10) ?>%</span>
                    </h3>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Subtotal:</span>
                            <span id="subtotal" class="text-sm font-bold">$0.00</span>
                        </div>
                        <div id="fila-descuento" class="flex justify-between items-center py-1 px-2 rounded-lg transition-colors">
                            <span class="text-sm font-semibold" id="label-descuento">Descuento:</span>
                            <span id="descuento" class="text-sm font-bold">- $0.00</span>
                        </div>

                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">ITBMS (7%)</span>
                            <span id="itbms" class="text-sm font-bold text-green-600">$0.00</span>
                        </div>

                        <div class="flex justify-between pt-2 border-t border-gray-200 mt-2">
                            <span class="text-lg font-bold text-gray-900">TOTAL:</span>
                            <span id="total" class="text-lg font-bold text-pos">$0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Panel de Pagos -->
                <div class="p-4 border-b border-gray-200">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Pagos</h3>
                        <button type="button" id="btn-agregar-pago"
                            class="text-xs text-pos font-semibold hover:underline flex items-center gap-1">
                            <i class="fas fa-plus-circle"></i> Agregar método
                        </button>
                    </div>

                    <div id="filas-pagos" class="space-y-2 mb-3">
                        <!-- filas generadas por JS -->
                    </div>

                    <!-- Resumen pagado / vuelto -->
                    <div class="border-t border-gray-100 pt-2 space-y-1 text-xs">
                        <div class="flex justify-between text-gray-500">
                            <span>Pagado:</span>
                            <span id="lbl-pagado" class="font-bold">$0.00</span>
                        </div>
                        <div class="flex justify-between" id="fila-vuelto">
                            <span class="text-gray-500">Vuelto:</span>
                            <span id="lbl-vuelto" class="font-bold text-emerald-600">$0.00</span>
                        </div>
                        <div class="flex justify-between hidden" id="fila-faltante">
                            <span class="text-red-500 font-semibold">Faltante:</span>
                            <span id="lbl-faltante" class="font-bold text-red-500">$0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Comentarios -->
                <div class="p-4 border-b border-gray-200 flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Referencia FISCAL</label>
                    <textarea id="txreferencia"
                        class="w-full h-16 p-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-pos focus:border-transparent"
                        placeholder="Notas o referencia para la factura..."></textarea>
                </div>

                <!-- Botones de acción -->
                <div class="p-4">
                    <div class="grid grid-cols-2 gap-3">
                        <button id="btncotizar"
                            class="btn-cotizar w-full text-white text-sm py-3 rounded-lg font-bold hover:opacity-90 action-btn">
                            <i class="fas fa-file-invoice-dollar mr-2"></i>COTIZAR
                        </button>
                        <button id="btnpagar"
                            class="btn-pagar w-full text-white text-sm py-3 rounded-lg font-bold hover:opacity-90 action-btn">
                            <i class="fas fa-cash-register mr-2"></i>PAGAR
                        </button>
                        <button id="btncancelar"
                            class="btn-cancelar w-full text-white text-sm py-3 rounded-lg font-bold hover:opacity-90 action-btn">
                            <i class="fas fa-times mr-2"></i>CANCELAR
                        </button>
                        <button id="btnimprimir"
                            class="btn-imprimir w-full text-white text-sm py-3 rounded-lg font-bold hover:opacity-90 action-btn">
                            <i class="fas fa-print mr-2"></i>IMPRIMIR
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Nuevo Cliente -->
    <div id="modal-nuevo-cliente" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 sticky top-0 bg-white z-10">
                <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-user-plus text-emerald-500 text-sm"></i> Nuevo Cliente
                </h3>
                <button onclick="cerrarModalCliente()" class="text-gray-300 hover:text-gray-500 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Código</label>
                        <input type="text" id="nc-codigo" placeholder="Automático (CLI-0000)"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Nombre / Razón Social <span class="text-red-500">*</span></label>
                        <input type="text" id="nc-nombre" placeholder="Ej: Juan Pérez"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Tipo</label>
                        <select id="nc-tipo" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent">
                            <option value="natural">Persona Natural</option>
                            <option value="juridico">Persona Jurídica</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">RUC / Cédula</label>
                            <input type="text" id="nc-ruc" placeholder="Ej: 8-000-000"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">DV</label>
                            <input type="text" id="nc-dv" placeholder="00"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Teléfono</label>
                        <input type="text" id="nc-telefono" placeholder="+507 000-0000"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Correo Electrónico</label>
                        <input type="email" id="nc-email" placeholder="cliente@ejemplo.com"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Aplica ITBMS</label>
                        <select id="nc-itbms" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent">
                            <option value="SI" selected>Sí</option>
                            <option value="NO">No (Exento)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Estado</label>
                        <select id="nc-estado" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent">
                            <option value="activo" selected>Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Dirección</label>
                        <textarea id="nc-direccion" rows="2" placeholder="Dirección completa..."
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent resize-none"></textarea>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-3">Crédito</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Límite de Crédito ($)</label>
                            <input type="number" id="nc-limite-credito" value="0" step="0.01" min="0"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Días de Crédito</label>
                            <input type="number" id="nc-dias-credito" value="0" step="1" min="0"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent">
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex gap-3 justify-end px-6 py-4 border-t border-gray-100 sticky bottom-0 bg-white">
                <button onclick="cerrarModalCliente()"
                    class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition">
                    Cancelar
                </button>
                <button onclick="guardarNuevoCliente()" id="btn-guardar-cliente"
                    class="px-4 py-2 bg-emerald-500 text-white rounded-lg text-sm font-semibold hover:bg-emerald-600 transition">
                    <i class="fas fa-save mr-1"></i> Guardar
                </button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        function toast(msg, type = 'info', duration = 5000) {
            const icons = { success: 'fa-check-circle', error: 'fa-times-circle', info: 'fa-info-circle' };
            const el = document.createElement('div');
            el.className = `toast ${type}`;
            el.innerHTML = `<i class="fas ${icons[type] || icons.info}"></i><span>${msg}</span>`;
            document.getElementById('toast-container').appendChild(el);
            setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity 0.3s'; setTimeout(() => el.remove(), 300); }, duration);
        }

        const METODOS_PAGO = ['EFECTIVO','TARJETA','YAPPY','CHEQUE','TRANSFERENCIA','CREDITO'];

        let carrito = [];
        let filaEditando = null; // índice de la línea del carrito que se está reemplazando (doble clic), o null
        let resultadosBusqueda = [];
        let paginaActual = 1;
        const resultadosPorPagina = 5;
        const TASA_ITBMS = 0.07;
        const MAX_DESCUENTO_PCT = <?= (int)($max_descuento_pct ?? 10) ?>;
        let elementosDOM = {};
        let pagos = [{ forma_pago: 'EFECTIVO', monto: 0 }];
        let creditoCliente = null;

        document.addEventListener('DOMContentLoaded', function () {
            elementosDOM = {
                txcodigo: document.getElementById('txcodigo'),
                txcliente: document.getElementById('txcliente'),
                txpago: document.getElementById('txpago'),
                txreferencia: document.getElementById('txreferencia'),
                tituloTabla: document.getElementById('titulo-tabla'),
                contenedorCarrito: document.getElementById('contenedor-carrito'),
                contenedorBusqueda: document.getElementById('contenedor-busqueda'),
                subtotal: document.getElementById('subtotal'),
                itbms: document.getElementById('itbms'),
                descuento: document.getElementById('descuento'),
                total: document.getElementById('total'),
                itemCount: document.getElementById('item-count')
            };

            elementosDOM.txcodigo.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    clearTimeout(window._posSearchTimer);
                    if (e.target.value.trim().length > 0) {
                        buscarProductos(e.target.value.trim());
                    }
                }
            });

            elementosDOM.txcodigo.addEventListener('input', function() {
                clearTimeout(window._posSearchTimer);
                const q = this.value.trim();
                document.getElementById('btn-limpiar-busqueda').classList.toggle('hidden', this.value.length === 0);
                if (q.length >= 2) {
                    window._posSearchTimer = setTimeout(() => buscarProductos(q), 400);
                } else if (q.length === 0) {
                    volverAlCarrito();
                }
            });
            
            document.getElementById('btnpagar').onclick = () => procesarDocumento('venta');
            document.getElementById('btncotizar').onclick = () => procesarDocumento('cotizacion');
            document.getElementById('btncancelar').onclick = limpiarCarrito;

            // Initialize Select2
            $(document).ready(function() {
                $('#txcliente').select2({
                    width: '100%',
                    language: { noResults: function() { return "No se encontraron clientes"; } }
                });
                $('#txcliente').on('select2:select select2:unselect', function() {
                    const id = $(this).val();
                    if (id) cargarCreditoCliente(parseInt(id));
                    else limpiarCreditoCliente();
                });
            });

            // Panel de pagos
            document.getElementById('btn-agregar-pago').addEventListener('click', function() {
                const totalActual = calcularTotal();
                const pagado = pagos.reduce((s, p) => s + p.monto, 0);
                const faltante = Math.max(0, totalActual - pagado);
                pagos.push({ forma_pago: 'EFECTIVO', monto: faltante });
                renderPagos();
            });

            cargarCarritoLocal();
            initClock();
        });

        function initClock() {
            const dateEl = document.getElementById('clock-date');
            const timeEl = document.getElementById('clock-time');
            
            function update() {
                const now = new Date();
                const days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
                
                dateEl.textContent = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]}`;
                timeEl.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
            }
            
            update();
            setInterval(update, 1000);
        }

        function cargarCarritoLocal() {
            try {
                let s = localStorage.getItem('pos_cart');
                if (s) {
                    carrito = JSON.parse(s).map(item => ({
                        ...item,
                        descuento_pct: item.descuento_pct ?? 0
                    }));
                }
            } catch (e) { carrito = []; }
            // Siempre dibuja la tabla: así la fila de entrada de código existe aunque no haya carrito guardado
            actualizarTabla();
        }
        function guardarCarritoLocal() {
            localStorage.setItem('pos_cart', JSON.stringify(carrito));
        }

        /* ── Crédito cliente ── */
        function cargarCreditoCliente(clienteId) {
            fetch('/api/clientes/' + clienteId + '/credito')
                .then(r => r.json())
                .then(data => {
                    creditoCliente = data;
                    const panel = document.getElementById('panel-credito-cliente');
                    const badge = document.getElementById('credito-badge');
                    panel.style.display = 'block';

                    if (data.tiene_credito) {
                        badge.className = 'text-xs rounded-lg px-3 py-2 border bg-emerald-50 border-emerald-200 text-emerald-800';
                        badge.innerHTML = `<i class="fas fa-credit-card mr-1"></i>
                            <span class="font-bold">Crédito disponible: $${data.credito_disponible.toFixed(2)}</span>
                            <span class="text-emerald-600 ml-2">${data.dias_credito} días</span>
                            ${data.saldo_pendiente > 0 ? `<span class="ml-2 text-amber-600">Saldo: $${data.saldo_pendiente.toFixed(2)}</span>` : ''}`;
                        // Pre-seleccionar CRÉDITO si el cliente tiene crédito disponible
                        if (pagos.length === 1 && pagos[0].forma_pago === 'EFECTIVO') {
                            pagos[0].forma_pago = 'CREDITO';
                            renderPagos();
                        }
                    } else {
                        badge.className = 'text-xs rounded-lg px-3 py-2 border bg-gray-50 border-gray-200 text-gray-500';
                        badge.innerHTML = '<i class="fas fa-ban mr-1"></i> Sin crédito asignado — pago al contado';
                        // Asegurar que no quede CRÉDITO seleccionado
                        pagos = pagos.map(p => p.forma_pago === 'CREDITO' ? { ...p, forma_pago: 'EFECTIVO' } : p);
                        renderPagos();
                    }
                })
                .catch(() => limpiarCreditoCliente());
        }

        function limpiarCreditoCliente() {
            creditoCliente = null;
            document.getElementById('panel-credito-cliente').style.display = 'none';
            pagos = pagos.map(p => p.forma_pago === 'CREDITO' ? { ...p, forma_pago: 'EFECTIVO' } : p);
            renderPagos();
        }

        /* ── Panel de pagos ── */
        function renderPagos() {
            const cont = document.getElementById('filas-pagos');
            cont.innerHTML = '';
            pagos.forEach(function(pago, i) {
                const div = document.createElement('div');
                div.className = 'flex items-center gap-2';

                const opciones = METODOS_PAGO.map(m =>
                    `<option value="${m}" ${pago.forma_pago === m ? 'selected' : ''}>${m}</option>`
                ).join('');

                div.innerHTML = `
                    <select class="flex-1 border border-gray-200 rounded-lg px-2 py-1.5 text-xs font-bold focus:outline-none focus:border-pos bg-white"
                            onchange="pagos[${i}].forma_pago=this.value; actualizarResumenPagos();">
                        ${opciones}
                    </select>
                    <div class="relative w-24">
                        <span class="absolute left-2 top-1.5 text-gray-400 text-xs pointer-events-none">$</span>
                        <input type="number" step="0.01" min="0" value="${pago.monto.toFixed(2)}"
                            class="w-full pl-5 pr-1 py-1.5 border border-gray-200 rounded-lg text-xs font-bold text-right focus:outline-none focus:border-pos"
                            onchange="pagos[${i}].monto=parseFloat(this.value)||0; actualizarResumenPagos();"
                            onkeyup="if(event.key==='Enter'){pagos[${i}].monto=parseFloat(this.value)||0;actualizarResumenPagos();}">
                    </div>
                    ${pagos.length > 1
                        ? `<button type="button" onclick="eliminarPago(${i})" class="w-7 h-7 rounded-lg bg-red-50 text-red-400 hover:bg-red-100 flex items-center justify-center text-xs shrink-0"><i class="fas fa-times"></i></button>`
                        : '<div class="w-7"></div>'}
                `;
                cont.appendChild(div);
            });
            actualizarResumenPagos();
        }

        function eliminarPago(i) {
            if (pagos.length <= 1) return;
            pagos.splice(i, 1);
            renderPagos();
        }

        function actualizarResumenPagos() {
            const total  = calcularTotal();
            const pagado = pagos.reduce(function(s, p) { return s + p.monto; }, 0);
            const vuelto = pagado - total;

            document.getElementById('lbl-pagado').textContent = '$' + pagado.toFixed(2);

            if (vuelto >= -0.005) {
                document.getElementById('fila-vuelto').classList.remove('hidden');
                document.getElementById('fila-faltante').classList.add('hidden');
                document.getElementById('lbl-vuelto').textContent = '$' + Math.max(0, vuelto).toFixed(2);
            } else {
                document.getElementById('fila-vuelto').classList.add('hidden');
                document.getElementById('fila-faltante').classList.remove('hidden');
                document.getElementById('lbl-faltante').textContent = '$' + Math.abs(vuelto).toFixed(2);
            }

            // Habilitar/deshabilitar botón PAGAR
            const btn = document.getElementById('btnpagar');
            if (pagado >= total - 0.005 || total === 0) {
                btn.disabled = false;
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                btn.disabled = true;
                btn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        }

        function calcularTotal() {
            let sub = carrito.reduce((s, item) => s + (item.cantidad * item.precio), 0);
            let desc = carrito.reduce((s, item) => s + (item.cantidad * item.precio * ((item.descuento_pct || 0) / 100)), 0);
            let neto = sub - desc;
            return neto + (neto * TASA_ITBMS);
        }

        function buscarProductos(q) {
            fetch(`/api/productos/buscar?q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(data => {
                    resultadosBusqueda = data.productos || [];
                    if (resultadosBusqueda.length === 1) {
                        const p = resultadosBusqueda[0];
                        const pr = parseFloat(p.precio_a) || 0;
                        agregarAlCarrito(p, 'a', pr, null, 'Unid.');
                        volverAlCarrito();
                    } else if (resultadosBusqueda.length > 1) {
                        paginaActual = 1;
                        mostrarResultados();
                    } else {
                        toast('No se encontraron productos', 'info');
                        elementosDOM.txcodigo.select();
                    }
                })
                .catch(e => {
                    toast('Error en búsqueda', 'error');
                });
        }

        function mostrarResultados() {
            elementosDOM.tituloTabla.textContent = `Resultados: ${resultadosBusqueda.length} encontrados`;
            elementosDOM.contenedorCarrito.classList.add('hidden');
            elementosDOM.contenedorBusqueda.classList.remove('hidden');
            document.getElementById('btn-cerrar-busqueda').classList.remove('hidden');

            const tbody = document.getElementById('cuerpo-busqueda');
            tbody.innerHTML = '';
            
            const inicio = (paginaActual - 1) * resultadosPorPagina;
            const fin = inicio + resultadosPorPagina;
            const pagina = resultadosBusqueda.slice(inicio, fin);

            if (pagina.length === 0) {
                tbody.innerHTML = `<tr><td colspan="8" class="p-4 text-center text-gray-500">Sin resultados</td></tr>`;
            } else {
                const btnPrecio = (id, tipo, valor, color) =>
                    `<button onclick="seleccionarYAgregar(${id}, '${tipo}', ${valor}, null, 'Unid.')" class="block w-full h-12 ${color} text-white text-sm font-bold rounded transition-colors">$${valor.toFixed(2)}</button>`;
                const sinPrecio = '<span class="text-gray-300">—</span>';

                pagina.forEach((p, idx) => {
                    const imgUrl = p.imagen_principal ? '/assets/uploads/' + p.imagen_principal : '/assets/img/no-image.svg';
                    const prA = parseFloat(p.precio_a || 0);
                    const prB = parseFloat(p.precio_b || 0);
                    const prC = parseFloat(p.precio_c || 0);
                    const stock = parseFloat(p.stock) || 0;
                    const stockTxt = Number.isInteger(stock) ? String(stock) : stock.toFixed(2);
                    const unidades = p.unidades || [];

                    // Presentaciones (caja, docena...): botones pequeños bajo la descripción
                    let presentaciones = '';
                    if (unidades.length) {
                        presentaciones = '<div class="flex flex-wrap gap-1 mt-1">';
                        unidades.forEach(u => {
                            const uPrecio = parseFloat(u.precio_a || 0) || prA * parseFloat(u.factor_conversion);
                            presentaciones += `<button onclick="seleccionarYAgregar(${p.producto_id}, 'a', ${uPrecio}, ${u.unidad_id}, '${u.nombre}')" class="px-2 py-0.5 bg-sky-700 hover:bg-sky-800 text-white text-[11px] font-bold rounded transition-colors" title="Agregar por ${u.nombre}">${u.nombre} $${uPrecio.toFixed(2)}</button>`;
                        });
                        presentaciones += '</div>';
                    }

                    // Resalta stock bajo (1 unidad) en amarillo pastel y sin stock (0) en rojo pastel
                    const filaColor = stock === 0 ? 'bg-red-50 hover:bg-red-100' : (stock === 1 ? 'bg-amber-50 hover:bg-amber-100' : 'hover:bg-gray-50');
                    const tr = document.createElement('tr');
                    tr.className = filaColor + " transition-colors";
                    tr.innerHTML = `
                        <td class="px-3 py-0.5 text-sm text-gray-400">${inicio + idx + 1}</td>
                        <td class="px-3 py-0.5 text-xs font-mono text-gray-500">${p.codigo}</td>
                        <td class="px-3 py-0.5">
                            <div class="text-sm font-semibold text-gray-900">${p.nombre}</div>
                            ${presentaciones}
                        </td>
                        <td class="px-3 py-0.5 text-center text-sm font-semibold ${stock > 0 ? 'text-gray-700' : 'text-red-500'}">${stockTxt}</td>
                        <td class="px-1 py-0.5 text-center">${btnPrecio(p.producto_id, 'a', prA, 'bg-blue-600 hover:bg-blue-700')}</td>
                        <td class="px-1 py-0.5 text-center">${prB > 0 ? btnPrecio(p.producto_id, 'b', prB, 'bg-green-600 hover:bg-green-700') : sinPrecio}</td>
                        <td class="px-1 py-0.5 text-center">${prC > 0 ? btnPrecio(p.producto_id, 'c', prC, 'bg-amber-500 hover:bg-amber-600') : sinPrecio}</td>
                        <td class="px-3 py-0.5 text-center"><img src="${imgUrl}" onerror="this.onerror=null;this.src='/assets/img/no-image.svg'" class="w-12 h-12 object-cover rounded shadow-sm border border-gray-200 mx-auto"></td>
                    `;
                    tbody.appendChild(tr);
                });
            }
            actualizarPaginacion();
        }

        function seleccionarYAgregar(id, tipo, precio, unidadId = null, presentacion = 'Unid.') {
            const p = resultadosBusqueda.find(item => item.producto_id == id);
            if (p) {
                if (filaEditando !== null) {
                    reemplazarEnCarrito(filaEditando, p, tipo, precio, unidadId, presentacion);
                } else {
                    agregarAlCarrito(p, tipo, precio, unidadId, presentacion);
                }
                volverAlCarrito();
            }
        }

        function volverAlCarrito() {
            elementosDOM.tituloTabla.textContent = 'Carrito de Venta';
            elementosDOM.contenedorCarrito.classList.remove('hidden');
            elementosDOM.contenedorBusqueda.classList.add('hidden');
            elementosDOM.txcodigo.value = '';
            // La casilla del carrito también queda limpia y sin búsquedas en vivo pendientes
            cancelarBusquedaViva();
            filaEditando = null;
            marcarFilaEditando(-1);
            const filaCodigo = document.getElementById('input-nueva-linea');
            if (filaCodigo) { filaCodigo.value = ''; filaCodigo.placeholder = 'Código o descripción...'; }
            document.getElementById('btn-cerrar-busqueda').classList.add('hidden');
            document.getElementById('btn-limpiar-busqueda').classList.add('hidden');
            setTimeout(() => {
                const inp = document.getElementById('input-nueva-linea');
                if (inp) inp.focus();
                else elementosDOM.txcodigo.focus();
            }, 30);
        }

        // Cancela la búsqueda actual: borra el texto, cierra resultados y deja el cursor listo para otro código
        function limpiarBusqueda() {
            clearTimeout(window._posSearchTimer);
            volverAlCarrito();
            setTimeout(() => elementosDOM.txcodigo.focus(), 50);
        }

        function agregarAlCarrito(p, tipo, precio, unidadId = null, presentacion = 'Unid.') {
            const index = carrito.findIndex(item => item.id == p.producto_id && item.tipo == tipo && item.unidad_id == unidadId);
            if (index !== -1) {
                carrito[index].cantidad++;
            } else {
                carrito.push({
                    id: p.producto_id,
                    codigo: p.codigo,
                    nombre: p.nombre,
                    precio: parseFloat(precio),
                    tipo: tipo,
                    cantidad: 1,
                    descuento_pct: 0,
                    unidad_id: unidadId,
                    presentacion: presentacion,
                    aplica_itbms: p.itbms == 1
                });
            }
            actualizarTabla();
        }

        // Doble clic en Código/Descripción: cambia el producto de esa línea sin tocar
        // la cantidad ni el descuento que ya tenía.
        function editarProductoFila(i) {
            if (!carrito[i]) return;
            filaEditando = i;
            const inp = document.getElementById('input-nueva-linea');
            if (!inp) return;
            inp.value = '';
            inp.placeholder = 'Nuevo código para "' + carrito[i].nombre + '"...';
            inp.focus();
            marcarFilaEditando(i);
        }

        function marcarFilaEditando(i) {
            document.querySelectorAll('#posTable tr[data-fila]').forEach(function(tr) {
                tr.classList.toggle('bg-amber-50', Number(tr.dataset.fila) === i);
            });
        }

        function cancelarEdicionFila() {
            if (filaEditando === null) return;
            filaEditando = null;
            marcarFilaEditando(-1);
            const inp = document.getElementById('input-nueva-linea');
            if (inp) { inp.value = ''; inp.placeholder = 'Código o descripción...'; }
        }

        // Reemplaza el producto de la línea "i" conservando cantidad y descuento
        function reemplazarEnCarrito(i, p, tipo, precio, unidadId = null, presentacion = 'Unid.') {
            if (!carrito[i]) return;
            carrito[i] = {
                ...carrito[i],
                id: p.producto_id,
                codigo: p.codigo,
                nombre: p.nombre,
                precio: parseFloat(precio),
                tipo: tipo,
                unidad_id: unidadId,
                presentacion: presentacion,
                aplica_itbms: p.itbms == 1
            };
            filaEditando = null;
            actualizarTabla();
        }

        function actualizarTabla() {
            const tbody = document.getElementById('posTable');
            tbody.innerHTML = '';

            carrito.forEach((item, i) => {
                const tr = document.createElement('tr');
                tr.className = "bg-white hover:bg-sky-50/40 transition receipt-font";
                tr.dataset.fila = i;
                const descPct = item.descuento_pct || 0;
                const descMonto = item.cantidad * item.precio * (descPct / 100);
                const linea = (item.cantidad * item.precio) - descMonto;
                tr.innerHTML = `
                    <td class="px-4 py-3 text-sm text-gray-400">${i + 1}</td>
                    <td class="px-4 py-3 text-sm font-mono text-gray-600 cursor-pointer" ondblclick="editarProductoFila(${i})" title="Doble clic para cambiar el producto">${item.codigo}</td>
                    <td class="px-4 py-3 cursor-pointer" ondblclick="editarProductoFila(${i})" title="Doble clic para cambiar el producto">
                        <div class="text-sm font-medium text-gray-900 line-clamp-1">${item.nombre}</div>
                        <div class="text-xs text-gray-400 mt-0.5">${item.presentacion || 'Unid.'}</div>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <input type="number" min="1" step="1" value="${item.cantidad}" class="w-16 text-center border border-gray-300 rounded text-sm py-1 focus:outline-none focus:border-pos" onchange="cambiarCantidad(${i}, this.value)" onkeyup="if(event.key==='Enter') this.blur();">
                    </td>
                    <td class="px-4 py-3 text-right text-sm">
                        <input type="number" step="0.01" min="0" value="${item.precio.toFixed(2)}" class="w-20 text-right border border-gray-300 rounded text-sm py-1 focus:outline-none focus:border-pos bg-gray-50" onchange="cambiarPrecio(${i}, this.value)" onkeyup="if(event.key==='Enter') this.blur();">
                    </td>
                    <td class="px-4 py-3 text-center">
                        <div class="relative inline-flex items-center">
                            <input type="number" step="1" min="0" max="${MAX_DESCUENTO_PCT}" value="${Math.round(descPct)}" title="Descuento en % — 10 = 10% de descuento" class="w-16 text-center border border-amber-200 rounded text-sm py-1 focus:outline-none focus:border-amber-400 bg-amber-50 text-amber-700 font-semibold pr-4" onchange="cambiarDescuento(${i}, this.value)" onkeyup="if(event.key==='Enter') this.blur();">
                            <span class="absolute right-1.5 text-xs text-amber-500 pointer-events-none font-bold">%</span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-right text-sm font-bold text-gray-800">$${linea.toFixed(2)}</td>
                    <td class="px-4 py-3 text-center">
                        <button onclick="eliminar(${i})" class="w-8 h-8 rounded text-gray-300 hover:text-red-500 transition-colors bg-white hover:bg-red-50"><i class="fas fa-trash"></i></button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            // Fila de entrada de código — siempre visible al final
            const trNueva = document.createElement('tr');
            trNueva.id = 'fila-nueva-linea';
            trNueva.className = 'bg-white';
            trNueva.innerHTML = `
                <td class="px-4 py-2 text-xs text-gray-300">${carrito.length + 1}</td>
                <td colspan="2" class="px-4 py-2">
                    <input type="text" id="input-nueva-linea"
                        placeholder="Código o descripción..."
                        autocomplete="off"
                        class="w-full max-w-md border-b border-dashed border-gray-300 text-sm text-gray-700 focus:outline-none focus:border-pos py-1 bg-transparent placeholder-gray-300">
                </td>
                <td colspan="5" class="px-4 py-2 text-xs text-gray-300 italic">Escriba código o descripción: los resultados aparecen al escribir</td>
            `;
            tbody.appendChild(trNueva);

            const inpLinea = document.getElementById('input-nueva-linea');
            inpLinea.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    cancelarBusquedaViva();
                    agregarPorCodigo(this.value);
                }
            });
            inpLinea.addEventListener('input', function() { buscarEnVivo(this.value); });

            // Si la tabla se redibuja mientras se edita una línea (doble clic), conserva el aviso
            if (filaEditando !== null && carrito[filaEditando]) {
                inpLinea.placeholder = 'Nuevo código para "' + carrito[filaEditando].nombre + '"...';
                marcarFilaEditando(filaEditando);
            }

            elementosDOM.itemCount.textContent = carrito.length;
            actualizarTotales();
            guardarCarritoLocal();
        }

        /* -- Búsqueda en vivo desde la fila del carrito --
           Muestra la tabla de resultados (reemplaza al carrito) y pasa el texto y el cursor
           al buscador superior para poder seguir escribiendo sin perder nada. */
        let busquedaVivaTimer = null;
        let busquedaVivaSeq = 0;

        function cancelarBusquedaViva() {
            clearTimeout(busquedaVivaTimer);
            busquedaVivaSeq++;
        }

        function buscarEnVivo(valor) {
            clearTimeout(busquedaVivaTimer);
            const q = (valor || '').trim();
            const seq = ++busquedaVivaSeq;
            if (q.length < 2) return;
            busquedaVivaTimer = setTimeout(function() {
                fetch('/api/productos/buscar?q=' + encodeURIComponent(q))
                    .then(function(r) { return r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)); })
                    .then(function(data) {
                        const inp = document.getElementById('input-nueva-linea');
                        // Descarta respuestas viejas: el usuario siguió escribiendo o salió de la casilla
                        if (seq !== busquedaVivaSeq || !inp || document.activeElement !== inp) return;
                        const productos = data.productos || [];
                        if (productos.length === 0) return;
                        resultadosBusqueda = productos;
                        paginaActual = 1;
                        mostrarResultados();
                        const top = elementosDOM.txcodigo;
                        top.value = inp.value;
                        document.getElementById('btn-limpiar-busqueda').classList.remove('hidden');
                        top.focus();
                        top.setSelectionRange(top.value.length, top.value.length);
                    })
                    .catch(function(e) { console.error('Búsqueda en vivo:', e); });
            }, 300);
        }

        function agregarPorCodigo(codigo) {
            const q = (codigo || '').trim();
            if (!q) return;
            fetch(`/api/productos/buscar?q=${encodeURIComponent(q)}`)
                .then(r => {
                    if (r.status === 401) throw new Error('Sesión expirada');
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(data => {
                    const productos = data.productos || [];
                    if (productos.length === 1) {
                        const p = productos[0];
                        const pr = parseFloat(p.precio_a) || 0;
                        if (filaEditando !== null) {
                            reemplazarEnCarrito(filaEditando, p, 'a', pr, null, 'Unid.');
                        } else {
                            agregarAlCarrito(p, 'a', pr, null, 'Unid.');
                        }
                        setTimeout(() => {
                            const inp = document.getElementById('input-nueva-linea');
                            if (inp) { inp.value = ''; inp.focus(); }
                        }, 30);
                    } else if (productos.length > 1) {
                        resultadosBusqueda = productos;
                        paginaActual = 1;
                        mostrarResultados();
                        // Deja el término en el buscador superior para poder afinarlo o limpiarlo
                        elementosDOM.txcodigo.value = q;
                        document.getElementById('btn-limpiar-busqueda').classList.remove('hidden');
                        elementosDOM.txcodigo.focus();
                    } else {
                        toast('Producto no encontrado: ' + q, 'info');
                        setTimeout(() => {
                            const inp = document.getElementById('input-nueva-linea');
                            if (inp) inp.select();
                        }, 30);
                    }
                })
                .catch(e => {
                    console.error('Búsqueda de producto:', e);
                    toast('Error al buscar producto: ' + e.message + '. Recargue la página (Ctrl+F5).', 'error');
                });
        }

        function cambiarCantidad(i, v) {
            carrito[i].cantidad = parseFloat(v) || 0;
            if (carrito[i].cantidad <= 0) eliminar(i);
            else actualizarTabla();
        }

        function cambiarPrecio(i, v) {
            carrito[i].precio = parseFloat(v) || 0;
            if (carrito[i].precio < 0) carrito[i].precio = 0;
            actualizarTabla();
        }

        function cambiarDescuento(i, v) {
            // Porcentaje entero: 10 = 10% de descuento (evita confundir 0.1 con 10%)
            const pct = Math.min(MAX_DESCUENTO_PCT, Math.max(0, Math.round(parseFloat(v)) || 0));
            carrito[i].descuento_pct = pct;
            actualizarTabla();
        }

        function eliminar(i) {
            carrito.splice(i, 1);
            actualizarTabla();
        }

        function actualizarTotales() {
            let subtotalBruto = carrito.reduce((s, item) => s + (item.cantidad * item.precio), 0);
            let descuentoTotal = carrito.reduce((s, item) => {
                return s + (item.cantidad * item.precio * ((item.descuento_pct || 0) / 100));
            }, 0);
            let subtotalNeto = subtotalBruto - descuentoTotal;
            let itbms = subtotalNeto * TASA_ITBMS;
            let total = subtotalNeto + itbms;

            elementosDOM.subtotal.textContent = `$${subtotalBruto.toFixed(2)}`;
            elementosDOM.descuento.textContent = `- $${descuentoTotal.toFixed(2)}`;
            elementosDOM.itbms.textContent = `$${itbms.toFixed(2)}`;
            elementosDOM.total.textContent = `$${total.toFixed(2)}`;

            // Highlight descuento row when active
            const filaDesc = document.getElementById('fila-descuento');
            const labelDesc = document.getElementById('label-descuento');
            if (descuentoTotal > 0) {
                filaDesc.classList.add('bg-amber-50');
                labelDesc.classList.add('text-amber-600');
                labelDesc.classList.remove('text-gray-600');
                elementosDOM.descuento.classList.add('text-amber-600');
                elementosDOM.descuento.classList.remove('text-gray-800');
            } else {
                filaDesc.classList.remove('bg-amber-50');
                labelDesc.classList.remove('text-amber-600');
                labelDesc.classList.add('text-gray-600');
                elementosDOM.descuento.classList.remove('text-amber-600');
                elementosDOM.descuento.classList.add('text-gray-800');
            }

            // Sincronizar primer pago si solo hay uno (auto-fill)
            if (pagos.length === 1) {
                pagos[0].monto = Math.round(total * 100) / 100;
            }
            renderPagos();
        }

        function limpiarCarrito() {
            if (carrito.length === 0) return;
            if (confirm('¿Desea limpiar el carrito actual?')) {
                carrito = [];
                pagos = [{ forma_pago: 'EFECTIVO', monto: 0 }];
                actualizarTabla();
                elementosDOM.txreferencia.value = '';
                volverAlCarrito();
                guardarCarritoLocal();
                renderPagos();
            }
        }

        async function procesarDocumento(tipoDoc = 'venta') {
            if (carrito.length === 0) return toast('No hay productos para procesar', 'info');

            if (tipoDoc === 'venta') {
                const total  = calcularTotal();
                const pagado = pagos.reduce(function(s, p) { return s + p.monto; }, 0);
                if (pagado < total - 0.005) {
                    toast('El monto pagado es menor al total de la venta', 'error');
                    return;
                }
            }

            const payload = {
                tipo: tipoDoc,
                cliente_id: elementosDOM.txcliente.value || null,
                pagos: pagos.map(p => ({ forma_pago: p.forma_pago, monto: p.monto })),
                forma_pago: pagos[0].forma_pago,  // backward compat
                notas: elementosDOM.txreferencia.value,
                aplicar_itbms: true,
                items: carrito.map(i => ({
                    producto_id: i.id,
                    cantidad: i.cantidad,
                    precio: i.precio,
                    descuento: i.cantidad * i.precio * ((i.descuento_pct || 0) / 100),
                    unidad_id: i.unidad_id || null
                }))
            };

            try {
                const endpoint = tipoDoc === 'venta' ? '/ventas/procesar' : '/ventas/cotizaciones/guardar';
                const r = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '<?= $_SESSION['csrf_token'] ?? '' ?>' },
                    body: JSON.stringify(payload)
                });
                const res = await r.json();
                if (!r.ok) throw new Error(res.error || 'Error al procesar la operación');

                if (tipoDoc === 'venta' && res.vuelto > 0.005) {
                    toast(`Venta procesada ✓  Vuelto: $${res.vuelto.toFixed(2)}`, 'success', 7000);
                } else {
                    toast(tipoDoc === 'venta' ? 'Venta procesada exitosamente' : 'Cotización guardada', 'success');
                }
                carrito = [];
                pagos = [{ forma_pago: 'EFECTIVO', monto: 0 }];
                guardarCarritoLocal();
                location.reload();
            } catch (e) { toast(e.message, 'error'); }
        }

        function cerrarModalCliente() {
            document.getElementById('modal-nuevo-cliente').classList.add('hidden');
        }

        async function guardarNuevoCliente() {
            const nombre = document.getElementById('nc-nombre').value.trim();
            if (!nombre) { document.getElementById('nc-nombre').focus(); return; }

            const btn = document.getElementById('btn-guardar-cliente');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...';

            try {
                const r = await fetch('/ventas/clientes/rapido', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '<?= $_SESSION['csrf_token'] ?? '' ?>'
                    },
                    body: JSON.stringify({
                        codigo:          document.getElementById('nc-codigo').value.trim(),
                        nombre:          nombre,
                        tipo:            document.getElementById('nc-tipo').value,
                        ruc:             document.getElementById('nc-ruc').value.trim(),
                        dv:              document.getElementById('nc-dv').value.trim(),
                        telefono:        document.getElementById('nc-telefono').value.trim(),
                        email:           document.getElementById('nc-email').value.trim(),
                        itbms:           document.getElementById('nc-itbms').value,
                        estado:          document.getElementById('nc-estado').value,
                        direccion:       document.getElementById('nc-direccion').value.trim(),
                        limite_credito:  document.getElementById('nc-limite-credito').value,
                        dias_credito:    document.getElementById('nc-dias-credito').value
                    })
                });
                const res = await r.json();
                if (!r.ok) throw new Error(res.error || 'Error al guardar');

                // Agregar al Select2 y seleccionarlo
                const opt = new Option(res.nombre, res.cliente_id, true, true);
                $('#txcliente').append(opt).trigger('change');

                cerrarModalCliente();
                toast('Cliente "' + res.nombre + '" creado', 'success');
            } catch (e) {
                toast(e.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save mr-1"></i> Guardar';
            }
        }

        function actualizarPaginacion() {
            const cont = document.getElementById('controles-paginacion');
            if (!cont) return;
            cont.innerHTML = '';
            const totalPaginas = Math.ceil(resultadosBusqueda.length / resultadosPorPagina);
            if (totalPaginas <= 1) return;

            // Simple pagination for now
            for (let i = 1; i <= totalPaginas; i++) {
                const b = document.createElement('button');
                b.className = `px-3 py-1 border rounded text-sm font-medium ${i === paginaActual ? 'bg-pos text-white border-pos' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'}`;
                b.textContent = i;
                b.onclick = () => { paginaActual = i; mostrarResultados(); };
                cont.appendChild(b);
            }
        }

        // Esc: cerrar búsqueda o modal cliente
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (!document.getElementById('modal-nuevo-cliente').classList.contains('hidden')) {
                    cerrarModalCliente();
                } else if (!elementosDOM.contenedorBusqueda.classList.contains('hidden') || elementosDOM.txcodigo.value.length > 0) {
                    limpiarBusqueda();
                } else if (filaEditando !== null) {
                    cancelarEdicionFila();
                }
            }
        });

        // Botón nuevo cliente
        document.getElementById('btn-nuevo-cliente').addEventListener('click', function() {
            document.getElementById('nc-codigo').value          = '';
            document.getElementById('nc-nombre').value          = '';
            document.getElementById('nc-tipo').value             = 'natural';
            document.getElementById('nc-ruc').value              = '';
            document.getElementById('nc-dv').value               = '';
            document.getElementById('nc-telefono').value         = '';
            document.getElementById('nc-email').value            = '';
            document.getElementById('nc-itbms').value            = 'SI';
            document.getElementById('nc-estado').value           = 'activo';
            document.getElementById('nc-direccion').value        = '';
            document.getElementById('nc-limite-credito').value   = '0';
            document.getElementById('nc-dias-credito').value     = '0';
            document.getElementById('modal-nuevo-cliente').classList.remove('hidden');
            setTimeout(() => document.getElementById('nc-nombre').focus(), 80);
        });

        // Enter dentro del modal
        document.getElementById('modal-nuevo-cliente').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') guardarNuevoCliente();
        });

        function salirDelPos() {
            if (carrito.length > 0) {
                return confirm('Hay productos en el carrito. ¿Salir del POS y perder la venta actual?');
            }
            return true;
        }

        /* ── Panel de cotizaciones activas ── */
        var panelCotizacionesAbierto = false;
        var cotizacionesCache = null;
        var buscarCotizacionTimer = null;

        document.getElementById('btn-cotizaciones').addEventListener('click', function(e) {
            e.stopPropagation();
            togglePanelCotizaciones();
        });

        function togglePanelCotizaciones() {
            panelCotizacionesAbierto = !panelCotizacionesAbierto;
            var panel = document.getElementById('panel-cotizaciones');
            if (panelCotizacionesAbierto) {
                panel.classList.remove('hidden');
                if (!cotizacionesCache) cargarCotizacionesPendientes('');
                setTimeout(function() { document.getElementById('buscar-cotizacion').focus(); }, 80);
            } else {
                panel.classList.add('hidden');
            }
        }

        function cerrarPanelCotizaciones() {
            panelCotizacionesAbierto = false;
            document.getElementById('panel-cotizaciones').classList.add('hidden');
        }

        document.addEventListener('click', function(e) {
            if (!document.getElementById('panel-cotizaciones').contains(e.target) &&
                e.target !== document.getElementById('btn-cotizaciones')) {
                cerrarPanelCotizaciones();
            }
        });

        document.getElementById('buscar-cotizacion').addEventListener('input', function() {
            clearTimeout(buscarCotizacionTimer);
            var q = this.value.trim();
            buscarCotizacionTimer = setTimeout(function() {
                cotizacionesCache = null;
                cargarCotizacionesPendientes(q);
            }, 350);
        });

        function cargarCotizacionesPendientes(q) {
            var lista = document.getElementById('lista-cotizaciones');
            lista.innerHTML = '<div class="px-4 py-6 text-center text-xs text-gray-400"><i class="fas fa-spinner fa-spin mr-1"></i> Cargando...</div>';
            fetch('/ventas/cotizaciones/pos/pendientes?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    cotizacionesCache = data.cotizaciones || [];
                    renderizarCotizaciones(cotizacionesCache);
                })
                .catch(function() {
                    lista.innerHTML = '<div class="px-4 py-4 text-center text-xs text-red-400">Error al cargar cotizaciones</div>';
                });
        }

        function renderizarCotizaciones(lista) {
            var cont = document.getElementById('lista-cotizaciones');
            var badge = document.getElementById('badge-cotizaciones');

            badge.textContent = lista.length;
            if (lista.length > 0) badge.classList.remove('hidden');
            else badge.classList.add('hidden');

            if (lista.length === 0) {
                cont.innerHTML = '<div class="px-4 py-8 text-center"><i class="fas fa-file-invoice text-2xl text-gray-200 mb-2 block"></i><p class="text-xs text-gray-400">No hay cotizaciones activas</p></div>';
                return;
            }

            cont.innerHTML = '';
            lista.forEach(function(c) {
                var fechaStr = c.fecha ? c.fecha.substring(0, 10) : '';
                var estadoBadge = c.estado === 'aprobada'
                    ? '<span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-100">Aprobada</span>'
                    : '<span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-100">Pendiente</span>';

                var div = document.createElement('div');
                div.className = 'flex items-center gap-3 px-4 py-2.5 hover:bg-amber-50/60 transition-colors';
                div.innerHTML = `
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-black text-gray-800 font-mono">${c.numero}</span>
                            ${estadoBadge}
                        </div>
                        <p class="text-[11px] text-gray-500 truncate mt-0.5">${c.cliente_nombre}</p>
                        <p class="text-[10px] text-gray-300 mt-0.5">${fechaStr}</p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-sm font-bold text-gray-800">$${parseFloat(c.total).toFixed(2)}</p>
                        <button onclick="cargarCotizacionAlCarrito(${c.cotizacion_id}, '${c.numero}')"
                            class="mt-1 px-3 py-1 bg-amber-500 hover:bg-amber-600 text-white text-[10px] font-bold rounded-lg transition-colors">
                            <i class="fas fa-cart-plus mr-1"></i>Cargar
                        </button>
                    </div>
                `;
                cont.appendChild(div);
            });
        }

        async function cargarCotizacionAlCarrito(id, numero) {
            if (carrito.length > 0) {
                if (!confirm('¿Reemplazar el carrito actual con la cotización ' + numero + '?')) return;
            }
            try {
                const r = await fetch('/ventas/cotizaciones/' + id + '/items-pos');
                const data = await r.json();
                if (!data.items || data.items.length === 0) {
                    toast('La cotización no tiene items', 'info');
                    return;
                }
                carrito = data.items.map(function(item) {
                    return {
                        id:           item.producto_id,
                        codigo:       item.codigo,
                        nombre:       item.nombre,
                        precio:       parseFloat(item.precio),
                        tipo:         'a',
                        cantidad:     parseFloat(item.cantidad),
                        descuento_pct: 0,
                        unidad_id:    null,
                        presentacion: 'Unid.',
                        aplica_itbms: item.aplica_itbms == 1
                    };
                });
                actualizarTabla();
                cerrarPanelCotizaciones();
                toast('Cotización ' + numero + ' cargada en el carrito', 'success');
            } catch(e) {
                toast('Error al cargar la cotización', 'error');
            }
        }
    </script>
</body>
</html>

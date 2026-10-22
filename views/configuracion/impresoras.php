<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<form method="post" action="/configuracion/guardar" class="space-y-6">
    <?= View::csrf() ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Impresora de Tickets -->
        <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <div class="w-9 h-9 rounded-lg bg-blue-500 text-white flex items-center justify-center mr-3 shadow-sm shadow-blue-500/30">
                    <i class="fas fa-receipt"></i>
                </div>
                Impresora de Tickets
            </h3>
            <div class="space-y-4">
                <div class="form-group">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de Impresora</label>
                    <input type="text" name="impresora_ticket" value="POS-58" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="Ej: POS-58">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ancho (caracteres)</label>
                        <input type="number" name="ancho_ticket" value="42" class="w-full px-3 py-2 border rounded-lg text-sm" min="30" max="60">
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Margen</label>
                        <input type="number" name="margen_ticket" value="2" class="w-full px-3 py-2 border rounded-lg text-sm" min="0" max="10">
                    </div>
                </div>

                <div class="form-group">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Texto de Pie de Página</label>
                    <textarea name="footer_ticket" class="w-full px-3 py-2 border rounded-lg text-sm" rows="2" placeholder="¡Gracias por su compra!">¡Gracias por su compra!</textarea>
                </div>

                <div class="flex flex-wrap gap-2 pt-2">
                    <button type="button" class="bg-blue-500 hover:bg-blue-600 text-white text-xs font-bold py-2 px-4 rounded-lg flex items-center transition shadow-sm shadow-blue-500/30">
                        <i class="fas fa-print mr-2"></i> Probar Conexión
                    </button>
                    <button type="button" class="bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold py-2 px-4 rounded-lg flex items-center transition shadow-sm shadow-emerald-500/30">
                        <i class="fas fa-file-invoice mr-2"></i> Ticket de Prueba
                    </button>
                </div>
            </div>
        </div>

        <!-- Impresora A4 -->
        <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <div class="w-9 h-9 rounded-lg bg-emerald-500 text-white flex items-center justify-center mr-3 shadow-sm shadow-emerald-500/30">
                    <i class="fas fa-file-alt"></i>
                </div>
                Impresora A4 / Reportes
            </h3>
            <div class="space-y-4">
                <div class="form-group">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de Impresora</label>
                    <input type="text" name="impresora_a4" value="HP LaserJet 1020" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="Ej: HP-LaserJet">
                </div>

                <div class="form-group">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Orientación</label>
                    <select name="orientacion_a4" class="w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="portrait" selected>Vertical</option>
                        <option value="landscape">Horizontal</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tamaño de Fuente</label>
                    <select name="tamano_fuente" class="w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="small">Pequeña</option>
                        <option value="medium" selected>Mediana</option>
                        <option value="large">Grande</option>
                    </select>
                </div>

                <div class="flex flex-wrap gap-2 pt-2">
                    <button type="button" class="bg-blue-500 hover:bg-blue-600 text-white text-xs font-bold py-2 px-4 rounded-lg flex items-center transition shadow-sm shadow-blue-500/30">
                        <i class="fas fa-print mr-2"></i> Probar Conexión
                    </button>
                    <button type="button" class="bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold py-2 px-4 rounded-lg flex items-center transition shadow-sm shadow-orange-500/30">
                        <i class="fas fa-chart-bar mr-2"></i> Reporte Prueba
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Impresoras Detectadas (Mock) -->
    <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-100">
        <h3 class="text-sm font-bold text-gray-800 mb-4 uppercase tracking-wider">Impresoras Detectadas en este Equipo</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100">
                <thead>
                    <tr>
                        <th class="px-5 py-3.5 bg-gray-50/80 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Nombre</th>
                        <th class="px-5 py-3.5 bg-gray-50/80 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Tipo</th>
                        <th class="px-5 py-3.5 bg-gray-50/80 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Estado</th>
                        <th class="px-5 py-3.5 bg-gray-50/80 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr class="hover:bg-sky-50/40 transition-colors">
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <div class="flex items-center gap-3">
                                <span class="w-8 h-8 rounded-lg bg-blue-500 text-white flex items-center justify-center text-xs shrink-0 shadow-sm shadow-blue-500/30"><i class="fas fa-print"></i></span>
                                <span class="font-bold text-sm text-gray-800">POS-58-Series</span>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <span class="px-2.5 py-1 inline-flex text-xs font-bold rounded-full bg-blue-500 text-white">USB / Ticket</span>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <span class="px-2.5 py-1 inline-flex text-xs font-bold rounded-full bg-emerald-500 text-white">Disponible</span>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap text-right">
                            <button type="button" class="w-8 h-8 rounded-lg bg-sky-500 text-white hover:bg-sky-600 transition-colors inline-flex items-center justify-center shadow-sm shadow-sky-500/30" title="Seleccionar">
                                <i class="fas fa-check text-xs"></i>
                            </button>
                        </td>
                    </tr>
                    <tr class="hover:bg-sky-50/40 transition-colors">
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <div class="flex items-center gap-3">
                                <span class="w-8 h-8 rounded-lg bg-emerald-500 text-white flex items-center justify-center text-xs shrink-0 shadow-sm shadow-emerald-500/30"><i class="fas fa-print"></i></span>
                                <span class="font-bold text-sm text-gray-800">HP LaserJet 1020</span>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <span class="px-2.5 py-1 inline-flex text-xs font-bold rounded-full bg-emerald-500 text-white">RED / A4</span>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <span class="px-2.5 py-1 inline-flex text-xs font-bold rounded-full bg-amber-500 text-white">En espera</span>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap text-right">
                            <button type="button" class="w-8 h-8 rounded-lg bg-sky-500 text-white hover:bg-sky-600 transition-colors inline-flex items-center justify-center shadow-sm shadow-sky-500/30" title="Seleccionar">
                                <i class="fas fa-check text-xs"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Botones de Acción -->
    <div class="flex justify-between items-center bg-white rounded-2xl shadow-sm p-6 border border-gray-100">
        <a href="/configuracion" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-6 rounded-lg text-sm transition">
            <i class="fas fa-arrow-left mr-2"></i> Volver
        </a>
        <button type="submit" class="bg-sky-500 hover:bg-sky-600 text-white font-bold py-2 px-8 rounded-lg text-sm transition shadow-sm shadow-sky-500/30">
            <i class="fas fa-save mr-2"></i> Guardar Cambios
        </button>
    </div>
</form>

<?php View::endSection('content'); ?>

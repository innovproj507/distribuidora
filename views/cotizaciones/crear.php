<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<div>
    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-sm text-gray-400 mb-3">
        <a href="/ventas/cotizaciones" class="hover:text-gray-600 transition-colors">Cotizaciones</a>
        <i class="fas fa-chevron-right text-xs"></i>
        <span class="text-gray-700 font-medium">Nueva Cotización</span>
    </div>

    <form action="/ventas/cotizaciones/nueva" method="POST">
        <?= View::csrf() ?>

        <!-- Action bar -->
        <div class="flex items-center justify-between bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-2.5 mb-4">
            <div class="flex gap-2">
                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
                    <i class="fas fa-save"></i> Guardar
                </button>
                <a href="/ventas/cotizaciones" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                    Cancelar
                </a>
            </div>

            <!-- Pipeline -->
            <div class="flex items-stretch text-xs font-semibold select-none">
                <div class="flex items-center bg-sky-500 text-white pl-4 pr-6 py-2 rounded-l-lg relative">
                    Solicitud
                    <span class="absolute right-0 top-0 h-full w-3 overflow-hidden translate-x-2.5 z-10">
                        <span class="block w-6 h-6 bg-sky-500 rotate-45 origin-top-left mt-0.5 ml-0.5"></span>
                    </span>
                </div>
                <div class="flex items-center bg-gray-100 text-gray-400 pl-7 pr-4 py-2 relative">
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

        <!-- Document card -->
        <div x-data="{ tab: 'notas' }" class="bg-white rounded-2xl border border-gray-100 shadow-sm">
            <div class="p-6 border-b border-gray-100">
                <h2 class="text-3xl font-bold text-gray-900 mb-6">Nueva Cotización</h2>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-16">
                    <div>
                        <div class="flex items-baseline gap-4 py-2">
                            <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Cliente *</label>
                            <select name="cliente_id" required class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                                <option value="">Seleccionar cliente...</option>
                                <?php foreach (($clientes ?? []) as $c): ?>
                                    <option value="<?= $c->cliente_id ?>"><?= View::e($c->nombre) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-baseline gap-4 py-2">
                            <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Fecha de Validez</label>
                            <input type="date" name="fecha_validez" value="<?= date('Y-m-d', strtotime('+30 days')) ?>"
                                   class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="border-b border-gray-100 px-6 flex gap-6">
                <button type="button" @click="tab = 'notas'"
                    :class="tab === 'notas' ? 'border-b-2 border-sky-500 text-sky-600' : 'text-gray-400 hover:text-gray-600'"
                    class="py-3 text-sm font-semibold -mb-px transition-colors">
                    Notas
                </button>
            </div>

            <!-- Tab: Notas -->
            <div x-show="tab === 'notas'" class="p-6">
                <textarea name="notas" rows="4" placeholder="Condiciones, observaciones u otros detalles de la cotización..."
                          class="w-full py-2 px-3 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:border-sky-400 focus:ring-0 outline-none resize-none"></textarea>
            </div>
        </div>
    </form>
</div>

<?php View::endSection('content'); ?>

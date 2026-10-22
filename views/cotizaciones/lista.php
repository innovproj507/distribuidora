<?php use App\Core\View; View::layout('app'); ?>
<?php
View::section('content');
$estado_actual       ??= '';
$cliente_actual      ??= '';
$fecha_inicio_actual ??= '';
$fecha_fin_actual    ??= '';

// JSON para el autocomplete de clientes
$clienteNombreActual = '';
$clientes_json = json_encode(array_map(function($c) use ($cliente_actual, &$clienteNombreActual) {
    $id     = is_object($c) ? $c->cliente_id : ($c['cliente_id'] ?? '');
    $nombre = is_object($c) ? $c->nombre     : ($c['nombre']     ?? '');
    if ((string)$id === (string)$cliente_actual) $clienteNombreActual = $nombre;
    return ['id' => (string)$id, 'nombre' => $nombre];
}, $clientes ?? []));
?>

<!-- Métricas -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-emerald-500 to-emerald-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Total Cotizaciones</p>
                <h3 class="text-xl font-extrabold mt-1 truncate">$<?= number_format((float)($total_cotizaciones ?? 0), 2) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-sack-dollar"></i></span>
        </div>
    </div>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-blue-500 to-blue-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Cantidad</p>
                <h3 class="text-xl font-extrabold mt-1"><?= (int)($cantidad_cotizaciones ?? 0) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-file-invoice"></i></span>
        </div>
    </div>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-red-500 to-red-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Promedio</p>
                <h3 class="text-xl font-extrabold mt-1 truncate">$<?= number_format((float)($promedio_cotizacion ?? 0), 2) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-chart-line"></i></span>
        </div>
    </div>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-orange-500 to-orange-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Hoy</p>
                <h3 class="text-xl font-extrabold mt-1"><?= (int)($cotizaciones_hoy_count ?? 0) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-calendar-day"></i></span>
        </div>
    </div>
</div>

<!-- Filtros + acciones -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-4">
    <form method="get" id="cotizaciones-search-form" class="flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-36">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Fecha Inicio</label>
            <input type="date" name="fecha_inicio" value="<?= View::e($fecha_inicio_actual) ?>"
                   class="w-full py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
        </div>
        <div class="flex-1 min-w-36">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Fecha Fin</label>
            <input type="date" name="fecha_fin" value="<?= View::e($fecha_fin_actual) ?>"
                   class="w-full py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
        </div>
        <div class="flex-1 min-w-28">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Estado</label>
            <select name="estado" class="w-full py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                <option value="">Todos</option>
                <option value="pendiente" <?= $estado_actual == 'pendiente' ? 'selected' : '' ?>>Pendientes</option>
                <option value="aprobada" <?= $estado_actual == 'aprobada' ? 'selected' : '' ?>>Aprobadas</option>
                <option value="rechazada" <?= $estado_actual == 'rechazada' ? 'selected' : '' ?>>Rechazadas</option>
                <option value="convertida" <?= $estado_actual == 'convertida' ? 'selected' : '' ?>>Convertidas</option>
            </select>
        </div>
        <!-- Autocomplete de clientes -->
        <div class="flex-1 min-w-40">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Cliente</label>
            <div x-data="cotizClienteFilter()" @click.outside="open = false" class="relative">
                <input type="hidden" name="cliente" :value="selectedId">
                <div class="relative">
                    <i class="fas fa-user absolute left-0 top-2 text-gray-400 text-xs"></i>
                    <input type="text"
                        x-model="search"
                        @focus="open = true"
                        @input="open = true"
                        placeholder="Buscar cliente..."
                        autocomplete="off"
                        class="w-full pl-5 pr-5 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                    <button type="button" x-show="selectedId" @click="clear()"
                        class="absolute right-0 top-1.5 text-gray-300 hover:text-gray-500 text-xs">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div x-show="open && filtered.length > 0"
                    class="absolute z-20 mt-1 w-56 bg-white rounded-lg border border-gray-100 shadow-lg overflow-hidden">
                    <div class="max-h-52 overflow-y-auto divide-y divide-gray-50">
                        <button type="button" @click="clear()"
                            class="w-full text-left px-3 py-2 text-xs text-gray-400 hover:bg-sky-50 flex items-center gap-2">
                            <i class="fas fa-users text-gray-300 text-xs"></i> Todos los clientes
                        </button>
                        <template x-for="c in filtered" :key="c.id">
                            <button type="button" @click="select(c)"
                                class="w-full text-left px-3 py-2 text-sm hover:bg-sky-50 flex items-center gap-2 transition-colors"
                                :class="c.id === selectedId ? 'bg-sky-50 text-sky-700 font-semibold' : 'text-gray-700'">
                                <i class="fas fa-user text-gray-300 text-xs shrink-0"></i>
                                <span x-text="c.nombre" class="truncate"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
                <i class="fas fa-filter"></i> Filtrar
            </button>
            <a href="/ventas/cotizaciones/nueva" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50 transition">
                <i class="fas fa-plus"></i> Nueva
            </a>
        </div>
    </form>
</div>

<!-- Tabla -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <table class="min-w-full divide-y divide-gray-100">
        <thead>
            <tr>
                <th class="px-5 py-3.5 bg-gray-50/80 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Número</th>
                <th class="px-5 py-3.5 bg-gray-50/80 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Fecha</th>
                <th class="px-5 py-3.5 bg-gray-50/80 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Vence</th>
                <th class="px-5 py-3.5 bg-gray-50/80 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Cliente</th>
                <th class="px-5 py-3.5 bg-gray-50/80 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Total</th>
                <th class="px-5 py-3.5 bg-gray-50/80 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Estado</th>
                <th class="px-5 py-3.5 bg-gray-50/80 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Acciones</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-50">
            <?php foreach (($cotizaciones ?? []) as $cotizacion): ?>
            <tr class="hover:bg-sky-50/40 transition-colors">
                <td class="px-5 py-3.5 whitespace-nowrap">
                    <div class="flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-blue-500 text-white flex items-center justify-center text-xs shrink-0 shadow-sm shadow-blue-500/30"><i class="fas fa-file-invoice"></i></span>
                        <span class="text-sm font-bold text-gray-800"><?= View::e($cotizacion['numero']) ?></span>
                    </div>
                </td>
                <td class="px-5 py-3.5 whitespace-nowrap text-sm text-gray-600"><?= date('d/m/Y H:i', strtotime($cotizacion['fecha'])) ?></td>
                <td class="px-5 py-3.5 whitespace-nowrap text-sm">
                    <?php
                    $fecha_validez = strtotime($cotizacion['fecha_vencimiento'] ?? '');
                    $vencida = $fecha_validez && $fecha_validez < time() && $cotizacion['estado'] === 'pendiente';
                    ?>
                    <span class="<?= $vencida ? 'text-red-600 font-bold' : 'text-gray-600' ?>">
                        <?= $fecha_validez ? date('d/m/Y', $fecha_validez) : '—' ?>
                        <?php if ($vencida): ?><i class="fas fa-exclamation-circle ml-1 text-xs"></i><?php endif; ?>
                    </span>
                </td>
                <td class="px-5 py-3.5 whitespace-nowrap text-sm text-gray-900"><?= View::e($cotizacion['cliente_nombre'] ?? 'Consumidor Final') ?></td>
                <td class="px-5 py-3.5 whitespace-nowrap text-sm font-bold text-gray-900">$<?= number_format((float)$cotizacion['total'], 2) ?></td>
                <td class="px-5 py-3.5 whitespace-nowrap">
                    <?php
                        $badgeClasses = match($cotizacion['estado']) {
                            'aprobada', 'convertida' => 'bg-emerald-500 text-white',
                            'rechazada' => 'bg-red-500 text-white',
                            default     => 'bg-amber-500 text-white',
                        };
                    ?>
                    <span class="px-2.5 py-1 inline-flex text-xs font-bold rounded-full <?= $badgeClasses ?>">
                        <?= ucfirst(View::e($cotizacion['estado'])) ?>
                    </span>
                </td>
                <td class="px-5 py-3.5 whitespace-nowrap text-right text-sm">
                    <div class="flex justify-end gap-1.5">
                        <a href="/ventas/cotizaciones/<?= $cotizacion['cotizacion_id'] ?>" title="Ver"
                           class="w-8 h-8 rounded-lg bg-blue-500 text-white hover:bg-blue-600 transition-colors flex items-center justify-center shadow-sm shadow-blue-500/30"><i class="fas fa-eye text-xs"></i></a>
                        <button onclick="imprimirCotizacion(<?= $cotizacion['cotizacion_id'] ?>)" title="Imprimir"
                           class="w-8 h-8 rounded-lg bg-emerald-500 text-white hover:bg-emerald-600 transition-colors flex items-center justify-center shadow-sm shadow-emerald-500/30"><i class="fas fa-print text-xs"></i></button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($cotizaciones)): ?>
            <tr>
                <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-400">
                    <i class="fas fa-file-invoice text-3xl mb-2 block text-gray-200"></i>
                    No se encontraron cotizaciones
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <div class="px-5 py-3.5 border-t border-gray-100 bg-gray-50/80">
        <?php View::include('partials.pagination', ['pagination' => $pagination ?? []]); ?>
    </div>
</div>

<?php View::endSection('content'); ?>

<?php View::section('extra_js'); ?>
<script>
    function cotizClienteFilter() {
        return {
            open: false,
            search: <?= json_encode($clienteNombreActual) ?>,
            selectedId: <?= json_encode((string)$cliente_actual) ?>,
            clientes: <?= $clientes_json ?>,
            get filtered() {
                if (!this.search) return this.clientes.slice(0, 15);
                var q = this.search.toLowerCase();
                return this.clientes.filter(function(c) {
                    return c.nombre.toLowerCase().indexOf(q) !== -1;
                }).slice(0, 15);
            },
            select: function(c) {
                this.search = c.nombre;
                this.selectedId = c.id;
                this.open = false;
                document.getElementById('cotizaciones-search-form').submit();
            },
            clear: function() {
                this.search = '';
                this.selectedId = '';
                this.open = false;
                document.getElementById('cotizaciones-search-form').submit();
            }
        };
    }

    function imprimirCotizacion(id) { window.open(`/ventas/cotizaciones/${id}/?print=true`, '_blank'); }
</script>
<?php View::endSection('extra_js'); ?>

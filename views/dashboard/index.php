<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<?php
$hora = (int) date('G');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
$nombre = explode(' ', trim($auth['name'] ?? 'Usuario'))[0];
$diasEs = ['Sunday'=>'Domingo','Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado'];
$mesesEs = ['January'=>'enero','February'=>'febrero','March'=>'marzo','April'=>'abril','May'=>'mayo','June'=>'junio','July'=>'julio','August'=>'agosto','September'=>'septiembre','October'=>'octubre','November'=>'noviembre','December'=>'diciembre'];
$fechaHoy = $diasEs[date('l')] . ' ' . date('d') . ' de ' . $mesesEs[date('F')] . ', ' . date('Y');

$okStock = max(0, (int)($totalProductos ?? 0) - (int)($stockBajo ?? 0) - (int)($agotados ?? 0));
$hayProblemas = ((int)($stockBajo ?? 0) + (int)($agotados ?? 0)) > 0;
$pctSaludable = ($totalProductos ?? 0) > 0 ? (int) floor($okStock / $totalProductos * 100) : 100;
if ($hayProblemas && $pctSaludable >= 100) $pctSaludable = 99;

// "Hace X" simple, sin dependencias — solo para la actividad reciente
function haceTiempo(string $fecha): string {
    $diff = time() - strtotime($fecha);
    if ($diff < 60) return 'hace un momento';
    if ($diff < 3600) return 'hace ' . floor($diff / 60) . ' min';
    if ($diff < 86400) return 'hace ' . floor($diff / 3600) . ' h';
    return 'hace ' . floor($diff / 86400) . ' d';
}
?>

<!-- Alertas de Lotes -->
<?php if (($lotesVencidos ?? 0) > 0 || ($lotesVenciendo ?? 0) > 0): ?>
<div class="mb-4 space-y-3">
    <?php if (($lotesVencidos ?? 0) > 0): ?>
    <div class="flex items-center gap-4 bg-red-50 border border-red-200 rounded-2xl px-5 py-4">
        <div class="w-9 h-9 bg-red-100 rounded-xl flex items-center justify-center shrink-0">
            <i class="fas fa-skull-crossbones text-red-600"></i>
        </div>
        <div class="flex-1">
            <p class="text-sm font-bold text-red-800">
                <?= $lotesVencidos ?> lote<?= $lotesVencidos > 1 ? 's' : '' ?> vencido<?= $lotesVencidos > 1 ? 's' : '' ?> aún marcado<?= $lotesVencidos > 1 ? 's' : '' ?> como activo<?= $lotesVencidos > 1 ? 's' : '' ?>.
            </p>
            <p class="text-xs text-red-600 mt-0.5">Estos lotes deben retirarse del inventario disponible.</p>
        </div>
        <a href="/inventario/lotes" class="shrink-0 text-xs font-bold text-red-700 hover:text-red-900 underline underline-offset-2">
            Ver Lotes <i class="fas fa-arrow-right ml-1"></i>
        </a>
    </div>
    <?php endif; ?>
    <?php if (($lotesVenciendo ?? 0) > 0): ?>
    <div class="flex items-center gap-4 bg-amber-50 border border-amber-200 rounded-2xl px-5 py-4">
        <div class="w-9 h-9 bg-amber-100 rounded-xl flex items-center justify-center shrink-0">
            <i class="fas fa-clock text-amber-600"></i>
        </div>
        <div class="flex-1">
            <p class="text-sm font-bold text-amber-800">
                <?= $lotesVenciendo ?> lote<?= $lotesVenciendo > 1 ? 's' : '' ?> vence<?= $lotesVenciendo > 1 ? 'n' : '' ?> en los próximos 30 días.
            </p>
            <p class="text-xs text-amber-600 mt-0.5">Revisa y prioriza la venta de estos productos.</p>
        </div>
        <a href="/inventario/lotes" class="shrink-0 text-xs font-bold text-amber-700 hover:text-amber-900 underline underline-offset-2">
            Ver Lotes <i class="fas fa-arrow-right ml-1"></i>
        </a>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Banner de bienvenida -->
<div class="rounded-2xl px-6 py-5 mb-5 text-white relative overflow-hidden" style="background: linear-gradient(135deg, #0EA5E9 0%, #0369A1 100%);">
    <div class="relative z-10">
        <h1 class="text-2xl font-extrabold"><?= $saludo ?>, <?= View::e($nombre) ?> <i class="fas fa-sun text-amber-300"></i></h1>
        <p class="text-sky-100 text-sm mt-1"><?= $fechaHoy ?></p>
    </div>
</div>

<!-- Métricas -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-emerald-500 to-emerald-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Ventas hoy</p>
                <h3 class="text-xl font-extrabold mt-1 truncate">$<?= number_format((float)($ventasHoy['total'] ?? 0), 2) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-shopping-cart"></i></span>
        </div>
    </div>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-orange-500 to-orange-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Productos</p>
                <h3 class="text-xl font-extrabold mt-1"><?= number_format((int)($totalProductos ?? 0)) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-box"></i></span>
        </div>
    </div>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-pink-500 to-pink-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Clientes</p>
                <h3 class="text-xl font-extrabold mt-1"><?= number_format((int)($totalClientes ?? 0)) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-users"></i></span>
        </div>
    </div>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-red-500 to-red-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Alertas de stock</p>
                <h3 class="text-xl font-extrabold mt-1"><?= (int)($stockBajo ?? 0) + (int)($agotados ?? 0) ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-triangle-exclamation"></i></span>
        </div>
    </div>
</div>

<!-- AI Insights -->
<?php if ($ai_enabled && !empty($insights)): ?>
<div class="mb-5">
    <div class="flex items-center gap-2 mb-3">
        <div class="w-8 h-8 bg-blue-500/10 rounded-lg flex items-center justify-center text-blue-500">
            <i class="fas fa-brain text-sm"></i>
        </div>
        <h3 class="text-xs font-bold text-gray-700 uppercase tracking-widest">Business Intelligence (AI)</h3>
        <span class="px-2 py-0.5 bg-amber-100 text-amber-600 text-[10px] font-bold rounded-full uppercase">Premium</span>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <?php foreach ($insights as $insight): ?>
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex gap-5 hover:shadow-md transition border-l-4 <?= $insight['type'] === 'warning' ? 'border-l-amber-400' : ($insight['type'] === 'success' ? 'border-l-emerald-400' : 'border-l-sky-400') ?>">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 text-white shadow-sm <?= $insight['type'] === 'warning' ? 'bg-amber-500 shadow-amber-500/30' : ($insight['type'] === 'success' ? 'bg-emerald-500 shadow-emerald-500/30' : 'bg-sky-500 shadow-sky-500/30') ?>">
                <i class="<?= $insight['icon'] ?> text-lg"></i>
            </div>
            <div class="flex-1">
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-tighter mb-1"><?= $insight['title'] ?></p>
                <p class="text-sm font-bold text-gray-700 leading-tight mb-2"><?= $insight['message'] ?></p>
                <p class="text-xs text-gray-400 italic mb-3"><?= $insight['detail'] ?></p>
                <div class="flex justify-end">
                    <a href="<?= $insight['action_url'] ?>" class="text-[10px] font-bold text-sky-500 hover:underline uppercase flex items-center gap-1">
                        <?= $insight['action_label'] ?> <i class="fas fa-arrow-right text-[8px]"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Fila principal: gráfico, estado de inventario, actividad -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5 items-start">

    <!-- Ventas de la semana -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <div class="flex items-center justify-between mb-1">
            <h3 class="text-sm font-bold text-gray-700">Ventas de la Semana</h3>
        </div>
        <p class="text-2xl font-extrabold text-gray-900">$<?= number_format(array_sum(array_column($ventasSemanales ?? [], 'total')), 2) ?></p>
        <p class="text-xs text-gray-400 mb-3">Últimos 7 días</p>
        <div class="h-56">
            <canvas id="salesChart"></canvas>
        </div>
    </div>

    <!-- Estado de inventario (dona) -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h3 class="text-sm font-bold text-gray-700 mb-4">Estado de Inventario</h3>
        <div class="relative h-40 flex items-center justify-center">
            <canvas id="inventoryDonut"></canvas>
            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                <span class="text-2xl font-extrabold text-gray-800"><?= $pctSaludable ?>%</span>
                <span class="text-[10px] text-gray-400 uppercase font-bold">Saludable</span>
            </div>
        </div>
        <div class="space-y-2 mt-4 text-xs">
            <div class="flex items-center justify-between">
                <span class="flex items-center gap-2 text-gray-600"><span class="w-2.5 h-2.5 rounded-full bg-sky-500"></span> En stock</span>
                <span class="font-bold text-gray-700"><?= number_format($okStock) ?></span>
            </div>
            <div class="flex items-center justify-between">
                <span class="flex items-center gap-2 text-gray-600"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> Stock bajo</span>
                <span class="font-bold text-gray-700"><?= number_format((int)($stockBajo ?? 0)) ?></span>
            </div>
            <div class="flex items-center justify-between">
                <span class="flex items-center gap-2 text-gray-600"><span class="w-2.5 h-2.5 rounded-full bg-red-500"></span> Agotado</span>
                <span class="font-bold text-gray-700"><?= number_format((int)($agotados ?? 0)) ?></span>
            </div>
        </div>
    </div>

    <!-- Actividad reciente (feed) -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-bold text-gray-700">Actividad Reciente</h3>
            <a href="/ventas" class="text-xs font-bold text-sky-500 hover:underline">Ver todo</a>
        </div>
        <div class="space-y-3 max-h-80 overflow-y-auto overflow-x-hidden">
            <?php foreach (array_slice($ultimasVentas ?? [], 0, 6) as $venta): ?>
            <?php
            $badgeColor = match($venta['estado']) {
                'pagada'    => 'bg-emerald-100 text-emerald-700',
                'anulada'   => 'bg-red-100 text-red-600',
                'pendiente' => 'bg-amber-100 text-amber-700',
                default     => 'bg-gray-100 text-gray-600',
            };
            ?>
            <a href="/ventas/venta/<?= $venta['venta_id'] ?>" class="flex items-center gap-3 hover:bg-gray-50 -mx-2 px-2 py-1.5 rounded-xl transition-colors">
                <div class="w-9 h-9 rounded-full bg-sky-100 text-sky-600 flex items-center justify-center font-bold text-xs shrink-0">
                    <?= strtoupper(substr($venta['cliente_nombre'] ?? 'C', 0, 1)) ?>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold text-gray-800 truncate">
                        Venta a <?= View::e($venta['cliente_nombre'] ?? 'Consumidor Final') ?>
                    </p>
                    <p class="text-[10px] text-gray-400"><?= View::e($venta['numero_factura']) ?> · <?= haceTiempo($venta['fecha']) ?></p>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-xs font-bold text-gray-800">$<?= number_format((float)$venta['total'], 2) ?></p>
                    <span class="px-1.5 py-0.5 rounded-full text-[9px] font-bold <?= $badgeColor ?>"><?= ucfirst($venta['estado']) ?></span>
                </div>
            </a>
            <?php endforeach; ?>
            <?php if (empty($ultimasVentas)): ?>
            <p class="text-center text-xs text-gray-400 py-8">No hay ventas recientes</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Fila secundaria: alertas de stock + acciones rápidas -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    <!-- Alertas de stock -->
    <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-bold text-gray-700">Alertas de Stock</h3>
            <a href="/inventario/alertas" class="text-xs font-bold text-sky-500 hover:underline">Ver todo</a>
        </div>
        <div class="space-y-2">
            <?php foreach (($listaStockBajo ?? []) as $item): ?>
            <?php $esAgotado = (float)$item['existencia'] <= 0; ?>
            <div class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-gray-50 transition-colors">
                <input type="checkbox" disabled class="w-4 h-4 rounded border-gray-300 text-sky-500">
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold text-gray-800 truncate"><?= htmlspecialchars($item['nombre']) ?></p>
                    <p class="text-[10px] text-gray-400"><?= htmlspecialchars($item['deposito_nombre']) ?> · Mín: <?= $item['minimo'] ?></p>
                </div>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0 <?= $esAgotado ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-700' ?>">
                    <?= $esAgotado ? 'Agotado' : 'Existencia: ' . $item['existencia'] ?>
                </span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($listaStockBajo)): ?>
            <div class="text-center py-8">
                <div class="w-12 h-12 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-check text-emerald-500"></i>
                </div>
                <p class="text-gray-400 text-sm italic">Todo el stock está saludable</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Acciones rápidas -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h3 class="text-sm font-bold text-gray-700 mb-4">Acciones Rápidas</h3>
        <div class="grid grid-cols-2 gap-2.5">
            <a href="/ventas/pos" class="flex flex-col items-center justify-center gap-2 p-3.5 rounded-xl bg-white border border-gray-100 hover:border-sky-200 hover:shadow-sm transition-all text-center">
                <span class="w-9 h-9 rounded-lg bg-sky-500 text-white flex items-center justify-center shadow-sm shadow-sky-500/30"><i class="fas fa-cash-register"></i></span>
                <span class="text-[11px] font-bold text-gray-700">Nueva Venta</span>
            </a>
            <a href="/ventas/cotizaciones/nueva" class="flex flex-col items-center justify-center gap-2 p-3.5 rounded-xl bg-white border border-gray-100 hover:border-red-200 hover:shadow-sm transition-all text-center">
                <span class="w-9 h-9 rounded-lg bg-red-500 text-white flex items-center justify-center shadow-sm shadow-red-500/30"><i class="fas fa-file-invoice"></i></span>
                <span class="text-[11px] font-bold text-gray-700">Cotización</span>
            </a>
            <a href="/clientes/nuevo" class="flex flex-col items-center justify-center gap-2 p-3.5 rounded-xl bg-white border border-gray-100 hover:border-pink-200 hover:shadow-sm transition-all text-center">
                <span class="w-9 h-9 rounded-lg bg-pink-500 text-white flex items-center justify-center shadow-sm shadow-pink-500/30"><i class="fas fa-user-plus"></i></span>
                <span class="text-[11px] font-bold text-gray-700">Cliente</span>
            </a>
            <a href="/inventario/nuevo" class="flex flex-col items-center justify-center gap-2 p-3.5 rounded-xl bg-white border border-gray-100 hover:border-blue-200 hover:shadow-sm transition-all text-center">
                <span class="w-9 h-9 rounded-lg bg-blue-500 text-white flex items-center justify-center shadow-sm shadow-blue-500/30"><i class="fas fa-box"></i></span>
                <span class="text-[11px] font-bold text-gray-700">Producto</span>
            </a>
            <a href="/compras/nueva" class="flex flex-col items-center justify-center gap-2 p-3.5 rounded-xl bg-white border border-gray-100 hover:border-orange-200 hover:shadow-sm transition-all text-center">
                <span class="w-9 h-9 rounded-lg bg-orange-500 text-white flex items-center justify-center shadow-sm shadow-orange-500/30"><i class="fas fa-file-invoice-dollar"></i></span>
                <span class="text-[11px] font-bold text-gray-700">Compra</span>
            </a>
            <a href="/reportes" class="flex flex-col items-center justify-center gap-2 p-3.5 rounded-xl bg-white border border-gray-100 hover:border-emerald-200 hover:shadow-sm transition-all text-center">
                <span class="w-9 h-9 rounded-lg bg-emerald-500 text-white flex items-center justify-center shadow-sm shadow-emerald-500/30"><i class="fas fa-chart-bar"></i></span>
                <span class="text-[11px] font-bold text-gray-700">Reportes</span>
            </a>
        </div>
    </div>
</div>

<!-- AI Assistant Floating Button -->
<button id="btn-ai-assistant" class="fixed bottom-6 right-6 w-14 h-14 bg-sky-500 text-white rounded-full shadow-lg hover:scale-110 transition-all flex items-center justify-center z-50 group">
    <i class="fas fa-robot text-xl group-hover:hidden"></i>
    <i class="fas fa-comment-dots text-xl hidden group-hover:block"></i>
    <div class="absolute -top-1 -right-1 w-4 h-4 bg-rose-500 border-2 border-white rounded-full animate-pulse"></div>
</button>

<!-- AI Assistant Sidebar -->
<div id="ai-sidebar" class="fixed top-0 right-0 w-80 h-full bg-white shadow-2xl translate-x-full transition-transform duration-300 z-[60] flex flex-col border-l border-gray-100">
    <div class="p-6 bg-sky-500 text-white flex justify-between items-center">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-brain"></i>
            </div>
            <span class="font-bold">Asistente POS</span>
        </div>
        <button id="close-ai-sidebar" class="hover:rotate-90 transition-all">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="flex-1 p-6 overflow-y-auto space-y-6">
        <div class="bg-sky-50 p-4 rounded-2xl border border-sky-100">
            <p class="text-xs text-sky-700 leading-relaxed font-medium">
                "¡Hola! Soy tu asistente de datos. He analizado hoy y he notado que las ventas de la tarde subieron un 15% respecto a ayer."
            </p>
        </div>
        <div class="space-y-4">
            <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Sugerencias Rápidas</h4>
            <div class="grid grid-cols-1 gap-2">
                <button class="text-left p-3 text-xs bg-gray-50 hover:bg-gray-100 rounded-xl border border-gray-100 transition-all flex items-center gap-2">
                    <i class="fas fa-search text-sky-500"></i> ¿Qué producto subió de precio?
                </button>
                <button class="text-left p-3 text-xs bg-gray-50 hover:bg-gray-100 rounded-xl border border-gray-100 transition-all flex items-center gap-2">
                    <i class="fas fa-chart-pie text-sky-500"></i> Resumen de margen semanal
                </button>
                <button class="text-left p-3 text-xs bg-gray-50 hover:bg-gray-100 rounded-xl border border-gray-100 transition-all flex items-center gap-2">
                    <i class="fas fa-user-tag text-sky-500"></i> ¿Quién es mi mejor cliente hoy?
                </button>
            </div>
        </div>
        <div class="p-4 bg-emerald-50 rounded-2xl border border-emerald-100">
            <div class="flex items-center gap-2 mb-2 text-emerald-700">
                <i class="fas fa-bolt text-xs"></i>
                <span class="text-[10px] font-black uppercase">Dato Curioso</span>
            </div>
            <p class="text-[10px] text-emerald-800 leading-normal">
                El 40% de tus ventas de hoy se han realizado con <strong>YAPPY</strong>. Considera incentivar pagos digitales.
            </p>
        </div>
    </div>
    <div class="p-4 border-t border-gray-100">
        <div class="relative">
            <input type="text" placeholder="Pregúntame algo..." class="w-full pl-4 pr-10 py-2 text-xs bg-gray-50 border border-gray-200 rounded-full focus:outline-none focus:ring-2 focus:ring-sky-400 focus:bg-white transition-all">
            <button class="absolute right-2 top-1.5 text-sky-500 hover:scale-110 transition-all">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const btnOpen = document.getElementById('btn-ai-assistant');
        const btnClose = document.getElementById('close-ai-sidebar');
        const sidebar = document.getElementById('ai-sidebar');

        btnOpen.addEventListener('click', () => {
            sidebar.classList.remove('translate-x-full');
        });

        btnClose.addEventListener('click', () => {
            sidebar.classList.add('translate-x-full');
        });

        document.addEventListener('click', (e) => {
            if (!sidebar.contains(e.target) && !btnOpen.contains(e.target) && !sidebar.classList.contains('translate-x-full')) {
                sidebar.classList.add('translate-x-full');
            }
        });

        const canvas = document.getElementById('salesChart');
        if (canvas) {
            const ctx = canvas.getContext('2d');
            const dataRaw = <?= json_encode($ventasSemanales ?? []) ?>;
            const labels = dataRaw.map(v => v.label);
            const data = dataRaw.map(v => v.total);

            const gradient = ctx.createLinearGradient(0, 0, 0, 300);
            gradient.addColorStop(0, 'rgba(14, 165, 233, 0.4)');
            gradient.addColorStop(1, 'rgba(14, 165, 233, 0)');

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Ventas ($)',
                        data: data,
                        borderColor: '#0EA5E9',
                        backgroundColor: gradient,
                        fill: true,
                        tension: 0.4,
                        borderWidth: 3,
                        pointRadius: 4,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#0EA5E9',
                        pointBorderWidth: 2,
                        pointHoverRadius: 6,
                        pointHoverBackgroundColor: '#0EA5E9',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1E293B',
                            titleFont: { size: 12, weight: 'bold' },
                            bodyFont: { size: 12 },
                            padding: 12,
                            cornerRadius: 12,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return '$' + context.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 11 }, color: '#94A3B8' }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: '#F1F5F9', drawBorder: false },
                            ticks: {
                                font: { size: 11 },
                                color: '#94A3B8',
                                callback: function(value) { return '$' + value.toLocaleString(); }
                            }
                        }
                    },
                    animations: {
                        tension: { duration: 1000, easing: 'linear', from: 1, to: 0.4, loop: false }
                    }
                }
            });
        }

        const donutCanvas = document.getElementById('inventoryDonut');
        if (donutCanvas) {
            new Chart(donutCanvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['En stock', 'Stock bajo', 'Agotado'],
                    datasets: [{
                        data: [<?= $okStock ?>, <?= (int)($stockBajo ?? 0) ?>, <?= (int)($agotados ?? 0) ?>],
                        backgroundColor: ['#0EA5E9', '#F59E0B', '#EF4444'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '75%',
                    plugins: { legend: { display: false } }
                }
            });
        }
    });
</script>

<?php View::endSection('content'); ?>

<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<?php
$est = $cotizacion['estado'] ?? '';
$estadoClass = match($est) {
    'pendiente'  => 'bg-amber-500 text-white',
    'aprobada'   => 'bg-emerald-500 text-white',
    'rechazada'  => 'bg-red-500 text-white',
    'convertida' => 'bg-sky-500 text-white',
    default      => 'bg-gray-400 text-white'
};
$linkPublico = \App\Core\Router::url('/c?t=' . urlencode($cotizacion['token'] ?? ''));
?>

<!-- Breadcrumb -->
<div class="flex items-center gap-2 text-sm text-gray-400 mb-3">
    <a href="/ventas/cotizaciones" class="hover:text-gray-600 transition-colors">Cotizaciones</a>
    <i class="fas fa-chevron-right text-xs"></i>
    <span class="text-gray-700 font-medium">#<?= View::e($cotizacion['numero'] ?? '') ?></span>
</div>

<!-- Action bar -->
<div class="flex items-center justify-between bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-2.5 mb-4">
    <div class="flex gap-2">
        <a href="/ventas/nueva?cotizacion_id=<?= $cotizacion['cotizacion_id'] ?>"
           class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
            <i class="fas fa-file-invoice-dollar"></i> Facturar
        </a>
        <?php if ($est === 'pendiente' || $est === 'aprobada'): ?>
        <form action="/ventas/cotizaciones/<?= $cotizacion['cotizacion_id'] ?>/convertir" method="POST" class="inline">
            <?= View::csrf() ?>
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-emerald-300 text-emerald-700 rounded-lg text-sm font-semibold hover:bg-emerald-50 transition">
                <i class="fas fa-check-double"></i> Auto-Convertir
            </button>
        </form>
        <?php endif; ?>
        <a href="/ventas/cotizaciones/<?= $cotizacion['cotizacion_id'] ?>/imprimir" target="_blank"
           class="inline-flex items-center gap-1.5 px-4 py-2 bg-amber-500 text-white rounded-lg text-sm font-semibold hover:bg-amber-600 transition shadow-sm">
            <i class="fas fa-print"></i> Imprimir
        </a>
        <button type="button" onclick="enviarPorWhatsApp()"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-500 text-white rounded-lg text-sm font-semibold hover:bg-emerald-600 transition shadow-sm">
            <i class="fab fa-whatsapp"></i> Enviar por WhatsApp
        </button>
        <button type="button" onclick="enviarPorCorreo()"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-600 text-white rounded-lg text-sm font-semibold hover:bg-slate-700 transition shadow-sm">
            <i class="fas fa-envelope"></i> Enviar por correo
        </button>
        <a href="/ventas/cotizaciones" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
            Volver
        </a>
    </div>

    <!-- Pipeline -->
    <div class="flex items-stretch text-xs font-semibold select-none">
        <div class="flex items-center <?= $est === 'pendiente' ? 'bg-sky-500 text-white' : 'bg-gray-100 text-gray-400' ?> pl-4 pr-6 py-2 rounded-l-lg relative">
            Solicitud
            <span class="absolute right-0 top-0 h-full w-3 overflow-hidden translate-x-2.5 z-10">
                <span class="block w-6 h-6 <?= $est === 'pendiente' ? 'bg-sky-500' : 'bg-gray-100' ?> rotate-45 origin-top-left mt-0.5 ml-0.5"></span>
            </span>
        </div>
        <div class="flex items-center <?= $est === 'aprobada' ? 'bg-sky-500 text-white' : 'bg-gray-100 text-gray-400' ?> pl-7 pr-6 py-2 relative">
            Aprobada
            <span class="absolute right-0 top-0 h-full w-3 overflow-hidden translate-x-2.5 z-10">
                <span class="block w-6 h-6 <?= $est === 'aprobada' ? 'bg-sky-500' : 'bg-gray-100' ?> rotate-45 origin-top-left mt-0.5 ml-0.5"></span>
            </span>
        </div>
        <div class="flex items-center <?= $est === 'convertida' ? 'bg-sky-500 text-white' : 'bg-gray-100 text-gray-400' ?> pl-7 pr-5 py-2 rounded-r-lg">
            Convertida
        </div>
    </div>
</div>

<!-- Document card -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-4">
    <h2 class="text-3xl font-bold text-gray-900 mb-6">Cotización #<?= View::e($cotizacion['numero'] ?? '') ?></h2>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-16">
        <div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Cliente</label>
                <span class="text-sm text-gray-800"><?= View::e($cotizacion['cliente_nombre'] ?? 'Consumidor Final') ?></span>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Vendedor</label>
                <span class="text-sm text-gray-600"><?= View::e($cotizacion['vendedor_nombre'] ?? 'N/A') ?></span>
            </div>
        </div>
        <div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Fecha</label>
                <span class="text-sm text-gray-600"><?= $cotizacion['fecha'] ?? '' ?></span>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Válida hasta</label>
                <span class="text-sm text-gray-600"><?= $cotizacion['fecha_vencimiento'] ?? 'N/A' ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Items table -->
<?php if (!empty($detalles)): ?>
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-4">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
            <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                <th class="px-6 py-3">Producto</th>
                <th class="px-6 py-3 text-right">Cantidad</th>
                <th class="px-6 py-3 text-right">Precio</th>
                <th class="px-6 py-3 text-right">Total</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            <?php foreach ($detalles as $d): ?>
            <tr class="hover:bg-gray-50/50 transition-colors">
                <td class="px-6 py-3 font-medium text-gray-800"><?= View::e($d['producto_nombre'] ?? '—') ?></td>
                <td class="px-6 py-3 text-right"><?= $d['cantidad'] ?? 0 ?></td>
                <td class="px-6 py-3 text-right text-gray-600">$<?= number_format((float)($d['precio'] ?? 0), 2) ?></td>
                <td class="px-6 py-3 text-right font-semibold text-gray-800">$<?= number_format((float)($d['total_linea'] ?? 0), 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="bg-gray-50 border-t-2 border-gray-100">
            <tr><td colspan="3" class="px-6 py-2 text-right text-xs text-gray-500">Subtotal</td><td class="px-6 py-2 text-right font-medium text-sm">$<?= number_format((float)($cotizacion['subtotal'] ?? 0), 2) ?></td></tr>
            <tr><td colspan="3" class="px-6 py-2 text-right text-xs text-gray-500">ITBMS</td><td class="px-6 py-2 text-right font-medium text-sm">$<?= number_format((float)($cotizacion['itbms'] ?? 0), 2) ?></td></tr>
            <tr><td colspan="3" class="px-6 py-3 text-right font-bold text-gray-800">Total</td><td class="px-6 py-3 text-right font-bold text-xl text-gray-900">$<?= number_format((float)($cotizacion['total'] ?? 0), 2) ?></td></tr>
        </tfoot>
    </table>
</div>
<?php endif; ?>

<!-- Modal: Enviar cotización -->
<div id="modal-enviar" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 id="modal-enviar-titulo" class="text-base font-bold text-gray-800 flex items-center gap-2"></h3>
            <button type="button" onclick="cerrarModalEnviar()" class="text-gray-300 hover:text-gray-500 transition">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <label id="modal-enviar-label" class="block text-xs font-semibold text-gray-500 mb-1"></label>
        <input type="text" id="modal-enviar-valor"
            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-transparent">
        <p class="text-[11px] text-gray-400 mt-1">Se abrirá con un mensaje ya escrito y el enlace para ver la cotización.</p>
        <div class="flex gap-3 justify-end mt-5">
            <button type="button" onclick="cerrarModalEnviar()"
                class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition">
                Cancelar
            </button>
            <button type="button" onclick="confirmarEnvio()" id="btn-confirmar-envio"
                class="px-4 py-2 text-white rounded-lg text-sm font-semibold transition">
                <i class="fas fa-paper-plane mr-1"></i> Enviar
            </button>
        </div>
    </div>
</div>

<?php View::endSection('content'); ?>

<?php View::section('extra_js'); ?>
<script>
    const COT_LINK = <?= json_encode($linkPublico) ?>;
    const COT_IMPRIMIR_URL = <?= json_encode('/ventas/cotizaciones/' . (int) $cotizacion['cotizacion_id'] . '/imprimir') ?>;
    const COT_NUMERO = <?= json_encode($cotizacion['numero'] ?? '') ?>;
    const COT_CLIENTE_TEL = <?= json_encode($cotizacion['cliente_telefono'] ?? '') ?>;
    const COT_CLIENTE_EMAIL = <?= json_encode($cotizacion['cliente_email'] ?? '') ?>;
    const COT_CLIENTE_NOMBRE = <?= json_encode($cotizacion['cliente_nombre'] ?? 'Cliente') ?>;

    function soloDigitos(v) { return (v || '').replace(/\D/g, ''); }

    let modoEnvioActual = null; // 'whatsapp' | 'correo'

    function enviarPorWhatsApp() { abrirModalEnviar('whatsapp'); }
    function enviarPorCorreo() { abrirModalEnviar('correo'); }

    function abrirModalEnviar(modo) {
        modoEnvioActual = modo;
        const input = document.getElementById('modal-enviar-valor');
        const btn = document.getElementById('btn-confirmar-envio');
        if (modo === 'whatsapp') {
            document.getElementById('modal-enviar-titulo').innerHTML = '<i class="fab fa-whatsapp text-emerald-500"></i> Enviar por WhatsApp';
            document.getElementById('modal-enviar-label').textContent = 'Número de WhatsApp del cliente';
            input.type = 'tel';
            input.placeholder = 'Ej: 6123-4567';
            input.value = COT_CLIENTE_TEL || '';
            btn.className = 'px-4 py-2 bg-emerald-500 text-white rounded-lg text-sm font-semibold hover:bg-emerald-600 transition';
        } else {
            document.getElementById('modal-enviar-titulo').innerHTML = '<i class="fas fa-envelope text-slate-500"></i> Enviar por correo';
            document.getElementById('modal-enviar-label').textContent = 'Correo del cliente';
            input.type = 'email';
            input.placeholder = 'cliente@ejemplo.com';
            input.value = COT_CLIENTE_EMAIL || '';
            btn.className = 'px-4 py-2 bg-slate-600 text-white rounded-lg text-sm font-semibold hover:bg-slate-700 transition';
        }
        document.getElementById('modal-enviar').classList.remove('hidden');
        setTimeout(() => { input.focus(); input.select(); }, 80);
    }

    function cerrarModalEnviar() {
        document.getElementById('modal-enviar').classList.add('hidden');
    }

    // WhatsApp y el correo no permiten adjuntar un archivo mediante enlace — es una
    // limitación de esas apps, no del sistema. Por eso la proforma se abre en una pestaña
    // nueva: ahí se guarda el PDF (el usuario elige "Guardar como PDF" al imprimir) y luego
    // se abre el chat o el correo ya listo para adjuntarlo.
    function confirmarEnvio() {
        const valor = document.getElementById('modal-enviar-valor').value.trim();
        if (!valor) { document.getElementById('modal-enviar-valor').focus(); return; }
        if (modoEnvioActual === 'whatsapp' && !soloDigitos(valor)) { document.getElementById('modal-enviar-valor').focus(); return; }

        const url = COT_IMPRIMIR_URL + '?enviar=' + encodeURIComponent(modoEnvioActual) + '&valor=' + encodeURIComponent(valor);
        window.open(url, '_blank');
        cerrarModalEnviar();
    }

    document.getElementById('modal-enviar-valor').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') confirmarEnvio();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !document.getElementById('modal-enviar').classList.contains('hidden')) {
            cerrarModalEnviar();
        }
    });
</script>
<?php View::endSection('extra_js'); ?>

<?php
/**
 * Si la empresa exige caja y el usuario no tiene una abierta, cubre la pantalla con el
 * formulario para abrirla (vuelve a la misma página). Variables: $caja_actual, $exigir_caja, $current_uri.
 */
if (!isset($exigir_caja) || !$exigir_caja || !empty($caja_actual)) {
    return;
}
?>
<div class="fixed inset-0 z-[60] bg-slate-900/70 flex items-center justify-center p-4">
    <form method="POST" action="/caja/abrir" class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
        <?= csrf_field() ?>
        <input type="hidden" name="volver" value="<?= esc($current_uri ?? '/ventas/pos', 'attr') ?>">
        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-500 flex items-center justify-center text-xl mb-3"><i class="fas fa-cash-register"></i></div>
        <h2 class="text-lg font-bold text-gray-800">Abre tu caja para vender</h2>
        <p class="text-sm text-gray-500 mb-4">Escribe el efectivo con el que empiezas (fondo de cambio). Al final del turno cierras la caja y el sistema te dice si cuadra.</p>
        <div class="relative mb-4">
            <span class="absolute left-3 top-2.5 text-gray-400">$</span>
            <input type="number" name="monto_inicial" step="0.01" min="0" required autofocus value="0.00"
                   class="w-full pl-7 pr-3 py-2.5 text-lg font-bold border border-gray-200 rounded-xl focus:outline-none focus:border-sky-400">
        </div>
        <button class="w-full py-2.5 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700"><i class="fas fa-lock-open mr-1"></i> Abrir caja</button>
        <a href="/" class="block text-center text-xs text-gray-400 mt-3 hover:text-gray-600">Volver al inicio</a>
    </form>
</div>

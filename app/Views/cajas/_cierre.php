<?php
/**
 * Formulario de cierre (arqueo). Variables: $r (CajaService::resumen), $formas, $denominaciones.
 * El efectivo se cuenta por billetes/monedas o se escribe directo.
 */
$esperado = $r['esperado'];
$cajaId   = (int) $r['caja']['caja_id'];
$etiqueta = fn (float $d) => $d >= 1 ? '$' . number_format($d, 0) : number_format($d * 100, 0) . '¢';
?>
<form method="POST" action="/caja/cerrar" x-data="cierreCaja()" onsubmit="return confirm('¿Cerrar la caja? Después no se pueden registrar más ventas en ella.')"
      class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
    <?= csrf_field() ?>
    <input type="hidden" name="caja_id" value="<?= $cajaId ?>">
    <h3 class="font-bold text-gray-800 mb-1">Cerrar caja (arqueo)</h3>
    <p class="text-xs text-gray-400 mb-4">Cuenta el efectivo por billetes y monedas, y escribe lo que tienes de cada otra forma de pago (vouchers de tarjeta, comprobantes de Yappy…).</p>

    <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Efectivo</p>
    <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2 mb-3">
        <?php foreach ($denominaciones as $d): $k = number_format($d, 2, '.', ''); ?>
        <label class="flex items-center gap-1.5 bg-gray-50 rounded-lg px-2 py-1.5">
            <span class="text-xs font-semibold text-gray-600 w-9 shrink-0"><?= $etiqueta($d) ?></span>
            <input type="number" min="0" step="1" name="billetes[<?= $k ?>]" x-model.number="billetes['<?= $k ?>']" placeholder="0"
                   class="w-full text-right text-sm bg-white border border-gray-200 rounded px-1.5 py-1 focus:outline-none focus:border-sky-400">
        </label>
        <?php endforeach; ?>
    </div>

    <div class="grid sm:grid-cols-3 gap-3 items-end mb-5 p-3 rounded-xl bg-sky-50/60">
        <div>
            <p class="text-xs text-gray-500">Debería haber</p>
            <p class="text-lg font-bold text-gray-800">$<?= number_format($esperado['EFECTIVO'], 2) ?></p>
        </div>
        <div>
            <label class="text-xs text-gray-500">Contado</label>
            <input type="number" step="0.01" min="0" required name="declarado[EFECTIVO]" x-model.number="efectivo" :readonly="totalBilletes() > 0" placeholder="Cuenta el efectivo"
                   class="w-full text-lg font-bold bg-white border border-gray-200 rounded-lg px-2 py-1 focus:outline-none focus:border-sky-400">
        </div>
        <div>
            <p class="text-xs text-gray-500">Diferencia</p>
            <p class="text-lg font-bold" :class="efectivo === '' ? 'text-gray-300' : (dif('EFECTIVO') === 0 ? 'text-emerald-600' : (dif('EFECTIVO') > 0 ? 'text-sky-600' : 'text-red-600'))"
               x-text="efectivo === '' ? '—' : textoDif(dif('EFECTIVO'))"></p>
        </div>
    </div>

    <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Otras formas de pago</p>
    <div class="space-y-2 mb-5">
        <?php foreach ($formas as $f): if ($f === 'EFECTIVO') continue; ?>
        <div class="grid grid-cols-3 gap-3 items-center">
            <p class="text-sm font-semibold text-gray-700"><?= esc(ucfirst(strtolower($f))) ?> <span class="block text-xs font-normal text-gray-400">debería: $<?= number_format($esperado[$f], 2) ?></span></p>
            <input type="number" step="0.01" min="0" name="declarado[<?= $f ?>]" x-model.number="declarado['<?= $f ?>']"
                   class="text-sm bg-white border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:border-sky-400">
            <p class="text-sm font-semibold" :class="dif('<?= $f ?>') === 0 ? 'text-emerald-600' : (dif('<?= $f ?>') > 0 ? 'text-sky-600' : 'text-red-600')" x-text="textoDif(dif('<?= $f ?>'))"></p>
        </div>
        <?php endforeach; ?>
    </div>

    <label class="block text-xs font-semibold text-gray-500 mb-1">Notas del cierre</label>
    <textarea name="notas" rows="2" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 mb-4 focus:outline-none focus:border-sky-400" placeholder="Ej: faltante por cambio mal dado, se depositó $200 al banco…"></textarea>

    <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-slate-800 text-white rounded-lg text-sm font-bold hover:bg-slate-900">
        <i class="fas fa-lock mr-1"></i> Cerrar caja
    </button>
</form>

<script>
function cierreCaja() {
    const esperado = <?= json_encode($esperado) ?>;
    return {
        billetes: {},
        efectivo: '', // se cuenta, no se precarga con lo esperado
        declarado: Object.fromEntries(Object.keys(esperado).filter(k => k !== 'EFECTIVO').map(k => [k, esperado[k]])),
        init() {
            this.$watch('billetes', () => { const t = this.totalBilletes(); if (t > 0) this.efectivo = t; }, { deep: true });
        },
        totalBilletes() {
            return Math.round(Object.entries(this.billetes).reduce((s, [d, n]) => s + parseFloat(d) * (parseInt(n) || 0), 0) * 100) / 100;
        },
        dif(forma) {
            const dec = forma === 'EFECTIVO' ? (parseFloat(this.efectivo) || 0) : (parseFloat(this.declarado[forma]) || 0);
            return Math.round((dec - esperado[forma]) * 100) / 100;
        },
        textoDif(d) {
            return d === 0 ? 'Cuadra ✓' : (d > 0 ? 'Sobra $' + d.toFixed(2) : 'Falta $' + Math.abs(d).toFixed(2));
        },
    };
}
</script>

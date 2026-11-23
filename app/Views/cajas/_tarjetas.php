<?php
/** Pagos con tarjeta de la caja con su nº de autorización (cuadre con el voucher / estado del banco). Espera $r = CajaService::resumen(). */
$tarjetas = $r['tarjetas'] ?? [];
if (!$tarjetas) {
    return;
}
$totalTarjeta = array_sum(array_map(fn ($t) => (float) $t['monto'], $tarjetas));
?>
<div class="bg-white rounded-xl border border-gray-200 p-5 mb-4">
    <h3 class="font-bold text-gray-800 mb-1"><i class="fas fa-credit-card text-violet-500 mr-1"></i> Pagos con tarjeta (<?= count($tarjetas) ?>)</h3>
    <p class="text-xs text-gray-500 mb-3">Compáralos con el cierre del datáfono: cada autorización debe estar en el voucher.</p>
    <table class="w-full text-sm">
        <thead><tr class="text-xs text-gray-400 border-b"><th class="text-left py-1">Hora</th><th class="text-left">Documento</th><th class="text-left">Autorización</th><th class="text-right">Monto</th></tr></thead>
        <tbody>
        <?php foreach ($tarjetas as $t): ?>
            <tr class="border-b border-gray-50">
                <td class="py-1.5 text-gray-500"><?= esc(date('h:i a', strtotime((string) $t['fecha']))) ?></td>
                <td>
                    <?php if ($t['venta_id']): ?><a href="/ventas/venta/<?= (int) $t['venta_id'] ?>" class="text-sky-600 hover:underline"><?= esc($t['documento']) ?></a>
                    <?php else: ?><?= esc($t['documento']) ?> <span class="text-xs text-gray-400">(abono)</span><?php endif; ?>
                </td>
                <td class="font-mono"><?= $t['referencia'] ? esc(preg_replace('/^Aut\.\s*/', '', (string) $t['referencia'])) : '<span class="text-amber-600 text-xs font-sans">sin anotar</span>' ?></td>
                <td class="text-right">$<?= number_format((float) $t['monto'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="3" class="pt-2 text-right font-semibold text-gray-600">Total tarjeta</td><td class="pt-2 text-right font-bold">$<?= number_format($totalTarjeta, 2) ?></td></tr></tfoot>
    </table>
</div>

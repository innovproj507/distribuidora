<?php $this->extend('layouts/app'); ?>
<?php $this->section('content'); ?>

<?php
$bc = match($venta['estado']) {
    'pagada'  => 'bg-emerald-500 text-white',
    'anulada' => 'bg-red-500 text-white',
    default   => 'bg-amber-500 text-white'
};
?>

<!-- Breadcrumb -->
<div class="flex items-center gap-2 text-sm text-gray-400 mb-3">
    <a href="/ventas" class="hover:text-gray-600 transition-colors">Ventas</a>
    <i class="fas fa-chevron-right text-xs"></i>
    <span class="text-gray-700 font-medium"><?= esc($venta['numero_factura']) ?></span>
</div>

<!-- Action bar -->
<div class="flex items-center justify-between bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-2.5 mb-4">
    <div class="flex gap-2">
        <?php if ($venta['estado'] === 'pendiente' && ($falta_pagar ?? 0) > 0
                  && (\App\Libraries\Auth::can('ventas.crear') || \App\Libraries\Auth::can('pos.usar'))): ?>
        <button onclick="abrirModalPagar()"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-500 text-white rounded-lg text-sm font-semibold hover:bg-emerald-600 transition shadow-sm">
            <i class="fas fa-hand-holding-usd"></i> Abonar / pagar
        </button>
        <?php endif; ?>
        <?php if ($venta['estado'] !== 'anulada'): ?>
        <button onclick="abrirModalAnular(<?= (int) $venta['venta_id'] ?>, <?= esc(json_encode((string) $venta['numero_factura']), 'attr') ?>)"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-red-600 rounded-lg text-sm font-medium hover:bg-red-50 transition">
            <i class="fas fa-ban"></i> Anular
        </button>
        <?php endif; ?>
        <a href="/ventas" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
            Volver
        </a>
    </div>
    <span class="px-3 py-1.5 rounded-full text-xs font-semibold <?= $bc ?>"><?= ucfirst($venta['estado']) ?></span>
</div>

<!-- Document card -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-4">
    <h2 class="text-3xl font-bold text-gray-900 mb-6"><?= esc($venta['numero_factura']) ?></h2>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-16">
        <div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Cliente</label>
                <span class="text-sm text-gray-800"><?= esc($venta['cliente_nombre'] ?? 'Consumidor Final') ?></span>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">RUC</label>
                <span class="text-sm text-gray-600"><?= esc($venta['cliente_ruc'] ?? '—') ?></span>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Vendedor</label>
                <span class="text-sm text-gray-600"><?= esc($venta['vendedor_nombre'] ?? '—') ?></span>
            </div>
            <?php if (!empty($venta['sucursal_nombre'])):
                $mapaVenta = \App\Libraries\SucursalService::enlaceMapa(['direccion' => $venta['sucursal_direccion']] + $venta); ?>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Sucursal</label>
                <span class="text-sm text-gray-600">
                    <?= esc($venta['sucursal_nombre']) ?>
                    <?php if (!empty($venta['sucursal_direccion'])): ?><span class="block text-xs text-gray-400"><?= esc($venta['sucursal_direccion']) ?></span><?php endif; ?>
                    <?php if (!empty($venta['sucursal_telefono'])): ?><span class="block text-xs text-gray-400">Tel. <?= esc($venta['sucursal_telefono']) ?></span><?php endif; ?>
                    <?php if ($mapaVenta): ?><a href="<?= esc($mapaVenta, 'attr') ?>" target="_blank" rel="noopener noreferrer" class="text-xs text-sky-600 hover:underline">Ver en mapa</a><?php endif; ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
        <div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Fecha</label>
                <span class="text-sm text-gray-600"><?= date('d/m/Y H:i', strtotime($venta['fecha'])) ?></span>
            </div>
            <div class="flex items-start gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Forma de Pago</label>
                <?php if (!empty($pagos_venta)): ?>
                    <div class="flex flex-col gap-1">
                        <?php foreach ($pagos_venta as $pg): ?>
                            <span class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-full bg-sky-500 text-white">
                                <?= esc(strtoupper($pg['forma_pago'])) ?>
                                <span>$<?= number_format((float)$pg['monto'], 2) ?></span>
                            </span>
                            <?php if (!empty($pg['referencia'])): ?><span class="text-xs text-gray-500">Ref: <span class="font-mono"><?= esc($pg['referencia']) ?></span></span><?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <span class="text-sm text-gray-600 uppercase"><?= esc($venta['forma_pago'] ?? '—') ?></span>
                <?php endif; ?>
            </div>
            <?php
            // Comprobante de Yappy / transferencia: se ve aquí y se puede adjuntar o cambiar después de cobrar
            $pagoDigital = in_array(strtoupper((string) ($venta['forma_pago'] ?? '')), ['YAPPY', 'TRANSFERENCIA', 'MIXTO'], true)
                || array_filter($pagos_venta ?? [], fn ($pg) => in_array(strtoupper((string) $pg['forma_pago']), ['YAPPY', 'TRANSFERENCIA'], true));
            $puedeComprobante = ($venta['estado'] ?? '') !== 'anulada'
                && ((int) ($venta['vendedor_id'] ?? 0) === (int) \App\Libraries\Auth::id() || \App\Libraries\Auth::can('ventas.anular'));
            $urlComp = '/m/api/venta/' . (int) $venta['venta_id'] . '/comprobante?v=' . md5((string) ($venta['comprobante'] ?? ''));
            if (!empty($venta['comprobante']) || ($pagoDigital && $puedeComprobante)): ?>
            <div class="flex items-start gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Comprobante</label>
                <div>
                    <?php if (!empty($venta['comprobante'])): ?>
                    <a href="<?= esc($urlComp, 'attr') ?>" target="_blank" class="block">
                        <img src="<?= esc($urlComp, 'attr') ?>" alt="Comprobante de pago" class="w-48 max-h-64 object-contain bg-gray-50 rounded-xl border border-gray-200 hover:opacity-90">
                        <span class="text-xs text-sky-600 font-semibold">Ver en grande</span>
                    </a>
                    <?php else: ?>
                    <span class="text-xs text-gray-400 block">Sin foto del comprobante.</span>
                    <?php endif; ?>
                    <?php if ($puedeComprobante): ?>
                    <label class="no-print mt-2 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-sky-50 text-sky-700 hover:bg-sky-100 cursor-pointer">
                        <i class="fas fa-paperclip"></i> <span id="comp-texto"><?= !empty($venta['comprobante']) ? 'Cambiar foto' : 'Adjuntar foto' ?></span>
                        <input type="file" accept="image/*" class="hidden" onchange="subirComprobanteVenta(this, <?= (int) $venta['venta_id'] ?>)">
                    </label>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($venta['notas'])): ?>
            <div class="flex items-start gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Notas</label>
                <span class="text-sm text-gray-600"><?= esc($venta['notas']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Items table -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
            <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                <th class="px-6 py-3">Producto</th>
                <th class="px-6 py-3 text-right">Precio</th>
                <th class="px-6 py-3 text-right">Cant.</th>
                <th class="px-6 py-3 text-right">Desc.</th>
                <th class="px-6 py-3 text-right">ITBMS</th>
                <th class="px-6 py-3 text-right">Total</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            <?php foreach (($detalles ?? []) as $d): ?>
            <tr class="hover:bg-gray-50/50 transition-colors">
                <td class="px-6 py-3">
                    <span class="text-gray-400 text-xs mr-2 font-mono"><?= esc($d['producto_codigo']) ?></span>
                    <span class="font-medium text-gray-800"><?= esc($d['producto_nombre']) ?></span>
                    <?php if (!empty($d['deposito_nombre'])): ?>
                    <span class="text-xs text-gray-400 ml-1">(<?= esc($d['deposito_nombre']) ?>)</span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-3 text-right text-gray-600">$<?= number_format((float)$d['precio'], 2) ?></td>
                <td class="px-6 py-3 text-right font-medium"><?= $d['cantidad'] ?><?= !empty($d['unidad_nombre']) ? ' <span class="text-xs text-gray-500">' . esc($d['unidad_nombre']) . '</span>' : '' ?></td>
                <td class="px-6 py-3 text-right text-gray-500">$<?= number_format((float)$d['descuento'], 2) ?></td>
                <td class="px-6 py-3 text-right text-gray-500">$<?= number_format((float)$d['itbms'], 2) ?></td>
                <td class="px-6 py-3 text-right font-semibold text-gray-800">$<?= number_format((float)$d['total_linea'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="bg-gray-50 border-t-2 border-gray-100">
            <tr><td colspan="5" class="px-6 py-2 text-right text-xs text-gray-500">Subtotal</td><td class="px-6 py-2 text-right font-medium text-sm">$<?= number_format((float)$venta['subtotal'], 2) ?></td></tr>
            <tr><td colspan="5" class="px-6 py-2 text-right text-xs text-gray-500">Descuento</td><td class="px-6 py-2 text-right font-medium text-sm text-red-600">-$<?= number_format((float)$venta['descuento'], 2) ?></td></tr>
            <?php if ((float) $venta["itbms"] > 0): ?><tr><td colspan="5" class="px-6 py-2 text-right text-xs text-gray-500">ITBMS</td><td class="px-6 py-2 text-right font-medium text-sm">$<?= number_format((float)$venta['itbms'], 2) ?></td></tr><?php endif; ?>
            <tr><td colspan="5" class="px-6 py-3 text-right font-bold text-gray-800">Total</td><td class="px-6 py-3 text-right font-bold text-xl text-gray-900">$<?= number_format((float)$venta['total'], 2) ?></td></tr>
            <?php if (!empty($abonos)): ?>
            <tr><td colspan="5" class="px-6 py-2 text-right text-xs text-gray-500">Abonado</td><td class="px-6 py-2 text-right font-medium text-sm text-emerald-700">-$<?= number_format(array_sum(array_column($abonos, 'monto')), 2) ?></td></tr>
            <?php endif; ?>
            <?php if (($falta_pagar ?? 0) > 0): ?>
            <tr><td colspan="5" class="px-6 py-2 text-right font-bold text-amber-700">Falta por pagar</td><td class="px-6 py-2 text-right font-bold text-lg text-amber-700">$<?= number_format((float) $falta_pagar, 2) ?></td></tr>
            <?php endif; ?>
        </tfoot>
    </table>
</div>

<?php if (!empty($abonos)): ?>
<!-- Abonos / pagos recibidos de esta factura a crédito -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mt-4">
    <div class="px-6 py-3 border-b border-gray-100 text-sm font-bold text-gray-700">Abonos recibidos</div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
            <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                <th class="px-6 py-2">Recibo</th><th class="px-6 py-2">Fecha</th><th class="px-6 py-2">Forma de pago</th>
                <th class="px-6 py-2">Referencia / notas</th><th class="px-6 py-2">Cobró</th><th class="px-6 py-2 text-right">Monto</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            <?php foreach ($abonos as $a): ?>
            <tr>
                <td class="px-6 py-2 font-mono text-xs"><?= esc($a['numero']) ?></td>
                <td class="px-6 py-2 text-gray-600"><?= date('d/m/Y H:i', strtotime($a['fecha'])) ?></td>
                <td class="px-6 py-2"><span class="px-2 py-0.5 rounded-md text-xs font-semibold bg-sky-50 text-sky-700"><?= esc($a['forma_pago']) ?></span></td>
                <td class="px-6 py-2 text-xs text-gray-500">
                    <?php if (!empty($a['referencia'])): ?>Ref: <span class="font-mono"><?= esc($a['referencia']) ?></span><?php endif; ?>
                    <?php if (!empty($a['notas'])): ?><span class="block"><?= esc($a['notas']) ?></span><?php endif; ?>
                </td>
                <td class="px-6 py-2 text-gray-600"><?= esc($a['usuario'] ?? '') ?></td>
                <td class="px-6 py-2 text-right font-semibold">$<?= number_format((float) $a['monto'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Modal Registrar Pago -->
<div id="modal-pagar" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-1">Abonar / pagar factura <?= esc($venta['numero_factura']) ?></h3>
        <p class="text-sm text-gray-500 mb-4">Falta por pagar: <strong class="text-amber-700">$<?= number_format((float) ($falta_pagar ?? 0), 2) ?></strong>. Puedes abonar una parte; al llegar a $0 la factura queda <strong>pagada</strong>.</p>
        <form id="form-pagar" method="POST" action="/ventas/venta/<?= (int) $venta['venta_id'] ?>/pagar" onsubmit="return confirmarAbono()">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Monto</label>
                <div class="flex gap-2">
                    <input type="number" name="monto" id="pagar-monto" step="0.01" min="0.01" max="<?= number_format((float) ($falta_pagar ?? 0), 2, '.', '') ?>" required
                           value="<?= number_format((float) ($falta_pagar ?? 0), 2, '.', '') ?>"
                           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm font-semibold text-gray-800">
                    <button type="button" onclick="document.getElementById('pagar-monto').value = '<?= number_format((float) ($falta_pagar ?? 0), 2, '.', '') ?>'"
                            class="px-3 py-2 border border-gray-200 rounded-lg text-xs text-gray-600 hover:bg-gray-50">Todo</button>
                </div>
            </div>
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Forma de pago</label>
                <select name="forma_pago" id="pagar-forma" onchange="cambiarFormaAbono()" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="EFECTIVO">💵 Efectivo</option>
                    <option value="YAPPY">📱 Yappy</option>
                    <option value="TRANSFERENCIA">🏦 Transferencia</option>
                    <option value="TARJETA">💳 Tarjeta</option>
                    <option value="CHEQUE">🧾 Cheque</option>
                </select>
            </div>
            <div class="mb-3 hidden" id="pagar-ref-bloque">
                <label class="block text-sm font-medium text-gray-700 mb-1">Referencia / Nº de comprobante</label>
                <input type="text" name="referencia" maxlength="100" autocomplete="off" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Nº de confirmación">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Notas <span class="text-gray-400 font-normal">(opcional)</span></label>
                <input type="text" name="notas" maxlength="250" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div class="flex gap-3 justify-end">
                <button type="button" onclick="document.getElementById('modal-pagar').classList.add('hidden')" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-emerald-500 text-white rounded-lg text-sm font-semibold hover:bg-emerald-600"><i class="fas fa-check-circle mr-1"></i>Registrar abono</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Anular Venta -->
<div id="modal-anular" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-1">Anular Venta</h3>
        <p class="text-sm text-gray-500 mb-4">Se generará una <strong>nota de crédito</strong> por el monto total.</p>
        <form id="form-anular" method="POST">
            <?= csrf_field() ?>
            <div class="mb-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Factura</label>
                <input type="text" id="modal-factura-num" readonly class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-600">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Motivo de anulación <span class="text-red-500">*</span></label>
                <select name="motivo_tipo" id="motivo_tipo" onchange="toggleMotivoCustom()" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm mb-2">
                    <option value="Producto defectuoso">Producto defectuoso</option>
                    <option value="Error en precio">Error en precio</option>
                    <option value="Error en cantidad">Error en cantidad</option>
                    <option value="Devolución del cliente">Devolución del cliente</option>
                    <option value="Duplicado">Factura duplicada</option>
                    <option value="otro">Otro motivo...</option>
                </select>
                <textarea name="motivo" id="motivo-custom" placeholder="Describe el motivo de la anulación..."
                    class="hidden w-full px-3 py-2 border border-gray-300 rounded-lg text-sm resize-none" rows="3"></textarea>
            </div>
            <div class="flex gap-3 justify-end">
                <button type="button" onclick="cerrarModalAnular()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700"><i class="fas fa-ban mr-1"></i>Confirmar Anulación</button>
            </div>
        </form>
    </div>
</div>

<?php $this->endSection(); ?>

<?php $this->section('extra_js'); ?>
<script src="/assets/js/foto-producto.js?v=1"></script>
<script>
    // Adjunta o cambia la foto del comprobante (Yappy / transferencia) y recarga para verla
    async function subirComprobanteVenta(input, ventaId) {
        const f = input.files && input.files[0];
        input.value = '';
        if (!f) return;
        let archivo = f;
        try { if (window.FotoProducto) archivo = await FotoProducto.reducir(f); } catch (e) { archivo = f; }
        const datos = new FormData();
        datos.append('foto', archivo, 'comprobante.jpg');
        const txt = document.getElementById('comp-texto');
        if (txt) txt.textContent = 'Subiendo…';
        try {
            const r = await fetch('/m/api/venta/' + ventaId + '/comprobante', {
                method: 'POST', credentials: 'same-origin', body: datos,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': <?= json_encode(csrf_hash()) ?> },
            });
            const res = await r.json().catch(() => ({}));
            if (!r.ok) throw new Error(res.error || 'No se pudo subir');
            location.reload();
        } catch (e) {
            alert('No se pudo subir el comprobante: ' + e.message);
            if (txt) txt.textContent = 'Adjuntar foto';
        }
    }

    function abrirModalPagar() {
        cambiarFormaAbono();
        document.getElementById('modal-pagar').classList.remove('hidden');
        document.getElementById('pagar-monto').select();
    }
    function cambiarFormaAbono() {
        const f = document.getElementById('pagar-forma').value;
        document.getElementById('pagar-ref-bloque').classList.toggle('hidden', ['YAPPY', 'TRANSFERENCIA', 'TARJETA', 'CHEQUE'].indexOf(f) === -1);
    }
    let abonoEnviado = false;
    function confirmarAbono() {
        if (abonoEnviado) return false; // evita doble clic = doble recibo
        const m = parseFloat(document.getElementById('pagar-monto').value) || 0;
        const f = document.getElementById('pagar-forma');
        if (!confirm('¿Registrar abono de $' + m.toFixed(2) + ' en ' + f.options[f.selectedIndex].text.replace(/^\S+\s/, '') + '?')) return false;
        abonoEnviado = true;
        return true;
    }

    function abrirModalAnular(ventaId, numeroFactura) {
        document.getElementById('form-anular').action = `/ventas/venta/${ventaId}/anular`;
        document.getElementById('modal-factura-num').value = numeroFactura;
        document.getElementById('motivo_tipo').value = 'Producto defectuoso';
        document.getElementById('motivo-custom').classList.add('hidden');
        document.getElementById('modal-anular').classList.remove('hidden');
    }
    function cerrarModalAnular() {
        document.getElementById('modal-anular').classList.add('hidden');
    }
    function toggleMotivoCustom() {
        const sel = document.getElementById('motivo_tipo');
        const txt = document.getElementById('motivo-custom');
        if (sel.value === 'otro') { txt.classList.remove('hidden'); txt.required = true; }
        else { txt.classList.add('hidden'); txt.required = false; }
    }
    document.getElementById('form-anular').addEventListener('submit', function(e) {
        const sel = document.getElementById('motivo_tipo');
        if (sel.value === 'otro') {
            const txt = document.getElementById('motivo-custom');
            if (!txt.value.trim()) { e.preventDefault(); txt.focus(); return; }
            sel.disabled = true;
        } else {
            document.getElementById('motivo-custom').disabled = true;
        }
    });
</script>
<?php $this->endSection(); ?>

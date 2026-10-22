<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

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
    <span class="text-gray-700 font-medium"><?= View::e($venta['numero_factura']) ?></span>
</div>

<!-- Action bar -->
<div class="flex items-center justify-between bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-2.5 mb-4">
    <div class="flex gap-2">
        <?php if ($venta['estado'] === 'pendiente'): ?>
        <button onclick="abrirModalPagar(<?= $venta['venta_id'] ?>, '<?= addslashes($venta['numero_factura']) ?>', <?= $venta['total'] ?>)"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-500 text-white rounded-lg text-sm font-semibold hover:bg-emerald-600 transition shadow-sm">
            <i class="fas fa-check-circle"></i> Registrar Pago
        </button>
        <?php endif; ?>
        <?php if ($venta['estado'] !== 'anulada'): ?>
        <button onclick="abrirModalAnular(<?= $venta['venta_id'] ?>, '<?= addslashes($venta['numero_factura']) ?>')"
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
    <h2 class="text-3xl font-bold text-gray-900 mb-6"><?= View::e($venta['numero_factura']) ?></h2>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-16">
        <div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Cliente</label>
                <span class="text-sm text-gray-800"><?= View::e($venta['cliente_nombre'] ?? 'Consumidor Final') ?></span>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">RUC</label>
                <span class="text-sm text-gray-600"><?= View::e($venta['cliente_ruc'] ?? '—') ?></span>
            </div>
            <div class="flex items-baseline gap-4 py-2">
                <label class="text-sm font-semibold text-gray-600 w-36 shrink-0">Vendedor</label>
                <span class="text-sm text-gray-600"><?= View::e($venta['vendedor_nombre'] ?? '—') ?></span>
            </div>
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
                                <?= View::e(strtoupper($pg['forma_pago'])) ?>
                                <span>$<?= number_format((float)$pg['monto'], 2) ?></span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <span class="text-sm text-gray-600 uppercase"><?= View::e($venta['forma_pago'] ?? '—') ?></span>
                <?php endif; ?>
            </div>
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
                    <span class="text-gray-400 text-xs mr-2 font-mono"><?= View::e($d['producto_codigo']) ?></span>
                    <span class="font-medium text-gray-800"><?= View::e($d['producto_nombre']) ?></span>
                    <?php if (!empty($d['deposito_nombre'])): ?>
                    <span class="text-xs text-gray-400 ml-1">(<?= View::e($d['deposito_nombre']) ?>)</span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-3 text-right text-gray-600">$<?= number_format((float)$d['precio'], 2) ?></td>
                <td class="px-6 py-3 text-right font-medium"><?= $d['cantidad'] ?></td>
                <td class="px-6 py-3 text-right text-gray-500">$<?= number_format((float)$d['descuento'], 2) ?></td>
                <td class="px-6 py-3 text-right text-gray-500">$<?= number_format((float)$d['itbms'], 2) ?></td>
                <td class="px-6 py-3 text-right font-semibold text-gray-800">$<?= number_format((float)$d['total_linea'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="bg-gray-50 border-t-2 border-gray-100">
            <tr><td colspan="5" class="px-6 py-2 text-right text-xs text-gray-500">Subtotal</td><td class="px-6 py-2 text-right font-medium text-sm">$<?= number_format((float)$venta['subtotal'], 2) ?></td></tr>
            <tr><td colspan="5" class="px-6 py-2 text-right text-xs text-gray-500">Descuento</td><td class="px-6 py-2 text-right font-medium text-sm text-red-600">-$<?= number_format((float)$venta['descuento'], 2) ?></td></tr>
            <tr><td colspan="5" class="px-6 py-2 text-right text-xs text-gray-500">ITBMS (7%)</td><td class="px-6 py-2 text-right font-medium text-sm">$<?= number_format((float)$venta['itbms'], 2) ?></td></tr>
            <tr><td colspan="5" class="px-6 py-3 text-right font-bold text-gray-800">Total</td><td class="px-6 py-3 text-right font-bold text-xl text-gray-900">$<?= number_format((float)$venta['total'], 2) ?></td></tr>
        </tfoot>
    </table>
</div>

<!-- Modal Registrar Pago -->
<div id="modal-pagar" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-1">Registrar Pago</h3>
        <p class="text-sm text-gray-500 mb-4">Se marcará la venta como <strong>pagada</strong> y se actualizará el saldo del cliente.</p>
        <form id="form-pagar" method="POST">
            <?= View::csrf() ?>
            <div class="mb-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Factura</label>
                <input type="text" id="pagar-factura-num" readonly class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-600">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Monto a cobrar</label>
                <input type="text" id="pagar-monto" readonly class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm font-semibold text-gray-800">
            </div>
            <div class="flex gap-3 justify-end">
                <button type="button" onclick="document.getElementById('modal-pagar').classList.add('hidden')" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-emerald-500 text-white rounded-lg text-sm font-semibold hover:bg-emerald-600"><i class="fas fa-check-circle mr-1"></i>Confirmar Pago</button>
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
            <?= View::csrf() ?>
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

<?php View::endSection('content'); ?>

<?php View::section('extra_js'); ?>
<script>
    function abrirModalPagar(ventaId, numeroFactura, total) {
        document.getElementById('form-pagar').action = `/ventas/venta/${ventaId}/pagar`;
        document.getElementById('pagar-factura-num').value = numeroFactura;
        document.getElementById('pagar-monto').value = '$' + parseFloat(total).toFixed(2);
        document.getElementById('modal-pagar').classList.remove('hidden');
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
<?php View::endSection('extra_js'); ?>

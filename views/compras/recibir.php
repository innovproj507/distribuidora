<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>
<div class="max-w-4xl mx-auto">
    <form method="POST" action="/compras/<?= $compra['compra_id'] ?>/procesar-recepcion" class="space-y-6">
        <?= View::csrf() ?>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-semibold">Registrar Recepción</h3>
                    <p class="text-sm text-gray-500">Compra #<?= View::e($compra['numero_factura'] ?? $compra['compra_id']) ?> — <?= View::e($compra['proveedor_nombre'] ?? '') ?></p>
                </div>
                <?php $est = $compra['estado'] ?? ''; ?>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold text-white
                    <?= $est === 'parcialmente_recibida' ? 'bg-blue-500' : 'bg-amber-500' ?>">
                    <?= $est === 'parcialmente_recibida' ? 'Parcialmente Recibida' : 'Pendiente' ?>
                </span>
            </div>

            <?php if (!empty($depositos) && count($depositos) > 1): ?>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Depósito de destino</label>
                <select name="deposito_id" class="border rounded-lg px-3 py-2 text-sm w-64">
                    <option value="0">— Usar depósito de la orden —</option>
                    <?php foreach ($depositos as $d): ?>
                    <option value="<?= $d['deposito_id'] ?>" <?= $d['deposito_id'] == $compra['deposito_id'] ? 'selected' : '' ?>>
                        <?= View::e($d['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
            <input type="hidden" name="deposito_id" value="0">
            <?php endif; ?>

            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left">Producto</th>
                        <th class="px-4 py-2 text-right">Pedido</th>
                        <th class="px-4 py-2 text-right">Ya recibido</th>
                        <th class="px-4 py-2 text-right text-amber-700">Pendiente</th>
                        <th class="px-4 py-2 text-center">Recibir ahora</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item):
                        $pendiente = (float)($item['pendiente'] ?? 0);
                    ?>
                    <tr class="border-t">
                        <td class="px-4 py-3">
                            <div class="font-medium"><?= View::e($item['producto_nombre'] ?? '-') ?></div>
                            <div class="text-xs text-gray-400 font-mono"><?= View::e($item['producto_codigo'] ?? '') ?></div>
                            <?php if (!empty($item['numero_lote'])): ?>
                            <div class="text-xs text-blue-600 mt-0.5">Lote: <?= View::e($item['numero_lote']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right text-gray-600"><?= number_format((float)$item['cantidad'], 2) ?></td>
                        <td class="px-4 py-3 text-right text-green-700 font-medium"><?= number_format((float)$item['cantidad_recibida'], 2) ?></td>
                        <td class="px-4 py-3 text-right text-amber-700 font-bold"><?= number_format($pendiente, 2) ?></td>
                        <td class="px-4 py-3 text-center">
                            <input type="number"
                                   name="cantidad_recibida[<?= $item['detalle_id'] ?>]"
                                   value="<?= $pendiente ?>"
                                   min="0"
                                   max="<?= $pendiente ?>"
                                   step="0.01"
                                   class="w-24 text-center border rounded-lg px-2 py-1 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="flex items-center gap-3">
            <a href="/compras/<?= $compra['compra_id'] ?>" class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                <i class="fas fa-arrow-left mr-1"></i>Cancelar
            </a>
            <button type="submit"
                    class="px-6 py-2 bg-emerald-500 text-white rounded-lg text-sm font-bold hover:bg-emerald-600 transition shadow-sm shadow-emerald-500/30 flex items-center gap-2"
                    onclick="return confirm('¿Confirmar recepción de los artículos indicados?')">
                <i class="fas fa-check mr-1"></i> Confirmar Recepción
            </button>
        </div>
    </form>
</div>
<?php View::endSection('content'); ?>

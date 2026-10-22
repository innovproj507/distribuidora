<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<div class="max-w-5xl mx-auto space-y-6">

    <!-- Métricas del proveedor -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="text-lg font-semibold"><?= View::e($proveedor->nombre) ?></h3>
                <p class="text-sm text-gray-500">Rendimiento histórico</p>
            </div>
            <a href="/inventario/proveedores" class="text-sm text-gray-500 hover:text-gray-700">
                <i class="fas fa-arrow-left mr-1"></i>Volver
            </a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-slate-600 text-white rounded-2xl p-4 text-center shadow-sm shadow-slate-600/30">
                <p class="text-xs text-slate-200 uppercase font-bold mb-1">Evaluaciones</p>
                <p class="text-3xl font-extrabold"><?= (int)($metricas['total_evaluaciones'] ?? 0) ?></p>
            </div>
            <div class="bg-blue-500 text-white rounded-2xl p-4 text-center shadow-sm shadow-blue-500/30">
                <p class="text-xs text-blue-100 uppercase font-bold mb-1">Días entrega (prom.)</p>
                <p class="text-3xl font-extrabold"><?= $metricas['avg_dias_entrega'] ?? '—' ?></p>
            </div>
            <div class="bg-emerald-500 text-white rounded-2xl p-4 text-center shadow-sm shadow-emerald-500/30">
                <p class="text-xs text-emerald-100 uppercase font-bold mb-1">Cumplimiento</p>
                <p class="text-3xl font-extrabold"><?= $metricas['avg_cumplimiento'] !== null ? $metricas['avg_cumplimiento'] . '%' : '—' ?></p>
            </div>
            <div class="bg-amber-500 text-white rounded-2xl p-4 text-center shadow-sm shadow-amber-500/30">
                <p class="text-xs text-amber-100 uppercase font-bold mb-1">Calidad (1–5)</p>
                <p class="text-3xl font-extrabold"><?= $metricas['avg_calidad'] ?? '—' ?></p>
            </div>
        </div>
    </div>

    <!-- Nueva evaluación -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h4 class="font-semibold text-slate-700 mb-4">Registrar evaluación</h4>
        <form method="POST" action="/inventario/proveedores/evaluacion/guardar" class="space-y-4">
            <?= View::csrf() ?>
            <input type="hidden" name="proveedor_id" value="<?= $proveedor->proveedor_id ?>">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Compra vinculada -->
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Compra (opcional)</label>
                    <select name="compra_id" class="w-full border rounded-lg px-3 py-2 text-sm">
                        <option value="">— Evaluación general —</option>
                        <?php foreach ($comprasSinEvaluar as $c): ?>
                        <option value="<?= $c['compra_id'] ?>">
                            #<?= View::e($c['numero_factura'] ?: $c['compra_id']) ?> — <?= $c['fecha_compra'] ?> ($<?= number_format((float)$c['monto_total'], 2) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-400 mt-0.5">El cumplimiento se calcula automático si seleccionas una compra</p>
                </div>

                <!-- Días de entrega -->
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Días de entrega</label>
                    <input type="number" name="dias_entrega" min="0" placeholder="ej. 3"
                           class="w-full border rounded-lg px-3 py-2 text-sm">
                </div>

                <!-- Calidad -->
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Calidad (1–5)</label>
                    <select name="calidad" class="w-full border rounded-lg px-3 py-2 text-sm">
                        <option value="">— Sin calificar —</option>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                        <option value="<?= $i ?>"><?= $i ?> <?= str_repeat('★', $i) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Notas</label>
                <textarea name="notas" rows="2" placeholder="Observaciones sobre este proveedor..."
                          class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>
            </div>

            <button type="submit" class="px-5 py-2 bg-blue-500 text-white rounded-lg text-sm font-semibold hover:bg-blue-600 shadow-sm shadow-blue-500/30">
                <i class="fas fa-save mr-1"></i>Guardar evaluación
            </button>
        </form>
    </div>

    <!-- Historial -->
    <?php if (!empty($evaluaciones)): ?>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h4 class="font-semibold text-slate-700">Historial de evaluaciones</h4>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">Fecha</th>
                    <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">Compra</th>
                    <th class="px-5 py-3 text-center text-xs font-bold text-gray-500 uppercase">Días entrega</th>
                    <th class="px-5 py-3 text-center text-xs font-bold text-gray-500 uppercase">Cumplimiento</th>
                    <th class="px-5 py-3 text-center text-xs font-bold text-gray-500 uppercase">Calidad</th>
                    <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">Notas</th>
                    <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">Usuario</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($evaluaciones as $e): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 text-gray-500"><?= date('d/m/Y', strtotime($e['fecha_evaluacion'])) ?></td>
                    <td class="px-5 py-3">
                        <?php if ($e['compra_id']): ?>
                        <a href="/compras/<?= $e['compra_id'] ?>" class="text-blue-600 hover:underline">
                            #<?= View::e($e['numero_factura'] ?: $e['compra_id']) ?>
                        </a>
                        <?php else: ?>
                        <span class="text-gray-400 italic text-xs">General</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-center"><?= $e['dias_entrega'] ?? '—' ?></td>
                    <td class="px-5 py-3 text-center">
                        <?php if ($e['pct_cumplimiento'] !== null):
                            $pct = (float)$e['pct_cumplimiento'];
                            $cls = $pct >= 95 ? 'text-green-700' : ($pct >= 80 ? 'text-amber-700' : 'text-red-700');
                        ?>
                        <span class="font-bold <?= $cls ?>"><?= number_format($pct, 1) ?>%</span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-center">
                        <?= $e['calidad'] ? str_repeat('★', (int)$e['calidad']) . str_repeat('☆', 5 - (int)$e['calidad']) : '—' ?>
                    </td>
                    <td class="px-5 py-3 text-gray-600 max-w-xs truncate"><?= View::e($e['notas'] ?? '') ?></td>
                    <td class="px-5 py-3 text-gray-400 text-xs"><?= View::e($e['usuario_nombre'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>

<?php View::endSection('content'); ?>

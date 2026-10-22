<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<!-- Filter bar -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-3 mb-4">
    <form method="GET" action="/inventario/kardex" class="flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-48">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Producto</label>
            <select name="producto_id" class="w-full py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                <option value="">Todos los productos</option>
                <?php foreach ($productos as $p): ?>
                <option value="<?= $p->producto_id ?>" <?= ($filtros['producto_id'] ?? '') == $p->producto_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p->nombre) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="min-w-40">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Depósito</label>
            <select name="deposito_id" class="w-full py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                <option value="">Todos los depósitos</option>
                <?php foreach ($depositos as $d): ?>
                <option value="<?= $d['deposito_id'] ?>" <?= ($filtros['deposito_id'] ?? '') == $d['deposito_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($d['nombre']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="min-w-44">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Tipo Movimiento</label>
            <select name="tipo" class="w-full py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                <option value="">Cualquier tipo</option>
                <option value="entrada" <?= ($filtros['tipo'] ?? '') == 'entrada' ? 'selected' : '' ?>>Entrada</option>
                <option value="salida" <?= ($filtros['tipo'] ?? '') == 'salida' ? 'selected' : '' ?>>Salida</option>
                <option value="traslado_en" <?= ($filtros['tipo'] ?? '') == 'traslado_en' ? 'selected' : '' ?>>Traslado Entrante</option>
                <option value="traslado_out" <?= ($filtros['tipo'] ?? '') == 'traslado_out' ? 'selected' : '' ?>>Traslado Saliente</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
                <i class="fas fa-filter"></i> Filtrar
            </button>
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                <i class="fas fa-print"></i> Imprimir
            </button>
        </div>
    </form>
</div>

<!-- Table -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3">Fecha / Hora</th>
                    <th class="px-4 py-3">Producto / Depósito</th>
                    <th class="px-4 py-3 text-center">Tipo</th>
                    <th class="px-4 py-3 text-right">Cantidad</th>
                    <th class="px-4 py-3 text-right">Saldo Nuevo</th>
                    <th class="px-4 py-3">Motivo</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($movimientos as $m): ?>
                <tr class="hover:bg-gray-50/50 transition-colors">
                    <td class="px-4 py-3 whitespace-nowrap">
                        <div class="text-xs font-semibold text-gray-700"><?= date('d/m/Y', strtotime($m['fecha_registro'])) ?></div>
                        <div class="text-[10px] text-gray-400"><?= date('H:i:s', strtotime($m['fecha_registro'])) ?></div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-800"><?= htmlspecialchars($m['producto_nombre']) ?></div>
                        <div class="text-xs text-sky-500 font-medium"><?= htmlspecialchars($m['deposito_nombre']) ?></div>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <?php
                        $badge = match($m['tipo']) {
                            'entrada'      => 'bg-emerald-500 text-white',
                            'salida'       => 'bg-rose-500 text-white',
                            'traslado_en'  => 'bg-sky-500 text-white',
                            'traslado_out' => 'bg-amber-500 text-white',
                            default        => 'bg-gray-400 text-white'
                        };
                        ?>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase <?= $badge ?>">
                            <?= str_replace('_', ' ', $m['tipo']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <span class="font-bold <?= in_array($m['tipo'], ['entrada', 'traslado_en']) ? 'text-emerald-600' : 'text-rose-600' ?>">
                            <?= in_array($m['tipo'], ['entrada', 'traslado_en']) ? '+' : '-' ?><?= number_format((float)$m['cantidad'], 2) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap font-bold text-gray-900">
                        <?= number_format((float)$m['saldo_nuevo'], 2) ?>
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-xs text-gray-600 max-w-xs truncate" title="<?= htmlspecialchars($m['motivo']) ?>">
                            <?= htmlspecialchars($m['motivo']) ?>
                        </p>
                        <span class="text-[10px] text-gray-400">por <?= htmlspecialchars($m['usuario_nombre']) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($movimientos)): ?>
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-gray-400">
                        <i class="fas fa-history text-3xl mb-2 block text-gray-200"></i>
                        No se registran movimientos con los filtros aplicados.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php View::endSection('content'); ?>

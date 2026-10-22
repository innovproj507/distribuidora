<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<div x-data="limpiezaPage()">

    <!-- Cabecera -->
    <div class="flex items-center gap-3 mb-5">
        <div class="w-10 h-10 rounded-xl bg-red-500 text-white flex items-center justify-center shrink-0 shadow-sm shadow-red-500/30">
            <i class="fas fa-trash-alt"></i>
        </div>
        <div>
            <h1 class="text-xl font-bold text-gray-900">Limpieza de Base de Datos</h1>
            <p class="text-xs text-gray-400">Solo disponible para superusuarios · Acción irreversible</p>
        </div>
    </div>

    <!-- Aviso -->
    <div class="bg-red-50 border border-red-200 rounded-xl px-5 py-4 mb-5 flex items-start gap-3">
        <i class="fas fa-exclamation-triangle text-red-500 mt-0.5 shrink-0"></i>
        <div>
            <p class="text-sm font-bold text-red-700 mb-1">Esta operación es irreversible</p>
            <p class="text-xs text-red-500 leading-relaxed">
                Se eliminarán <strong>todos los productos, ventas, compras, cotizaciones, traslados,
                clientes, proveedores e inventario</strong>. Los usuarios, contraseñas, sucursales,
                depósitos, categorías y configuraciones del sistema se conservarán.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-5">

        <!-- Se ELIMINARÁ -->
        <div class="bg-white rounded-2xl border border-red-100 shadow-sm overflow-hidden">
            <div class="px-4 py-3 bg-red-50 border-b border-red-100 flex items-center gap-2">
                <i class="fas fa-trash text-red-400 text-xs"></i>
                <span class="text-xs font-bold text-red-600 uppercase tracking-wide">Se eliminará</span>
                <span class="ml-auto px-2.5 py-1 inline-flex text-xs font-bold rounded-full bg-red-500 text-white">
                    <?= number_format(array_sum(array_filter(array_values($limpiar ?? []), fn($v) => $v !== null))) ?> registros
                </span>
            </div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-50">
                    <?php foreach (($limpiar ?? []) as $tabla => $count): ?>
                    <tr class="<?= $count > 0 ? 'bg-red-50/30' : '' ?>">
                        <td class="px-4 py-2 font-mono text-xs text-gray-600"><?= View::e($tabla) ?></td>
                        <td class="px-4 py-2 text-right">
                            <?php if ($count === null): ?>
                                <span class="text-xs text-gray-300">no existe</span>
                            <?php elseif ($count === 0): ?>
                                <span class="text-xs text-gray-300">vacía</span>
                            <?php else: ?>
                                <span class="text-xs font-bold text-red-600"><?= number_format($count) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Se CONSERVARÁ -->
        <div class="bg-white rounded-2xl border border-emerald-100 shadow-sm overflow-hidden">
            <div class="px-4 py-3 bg-emerald-50 border-b border-emerald-100 flex items-center gap-2">
                <i class="fas fa-shield-alt text-emerald-400 text-xs"></i>
                <span class="text-xs font-bold text-emerald-600 uppercase tracking-wide">Se conservará</span>
            </div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-50">
                    <?php foreach (($conservar ?? []) as $tabla => $count): ?>
                    <tr>
                        <td class="px-4 py-2">
                            <p class="font-mono text-xs text-gray-600"><?= View::e($tabla) ?></p>
                            <p class="text-xs text-gray-400"><?= View::e(($conservar_desc ?? [])[$tabla] ?? '') ?></p>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <?php if ($count === null): ?>
                                <span class="text-xs text-gray-300">no existe</span>
                            <?php else: ?>
                                <span class="text-xs font-semibold text-emerald-600"><?= number_format($count) ?> reg.</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- Formulario de confirmación -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 max-w-lg">
        <h3 class="text-sm font-bold text-gray-800 mb-1">Confirmar limpieza</h3>
        <p class="text-xs text-gray-400 mb-4">
            Escribe <span class="font-mono font-bold text-red-600 bg-red-50 px-1.5 py-0.5 rounded">LIMPIAR</span>
            en el campo y presiona el botón para continuar.
        </p>

        <form method="POST" action="/configuracion/limpieza/ejecutar" @submit.prevent="confirmar($event)">
            <?= View::csrf() ?>

            <div class="mb-4">
                <input type="text" name="confirmar" x-model="palabra"
                    placeholder="Escribe LIMPIAR aquí..."
                    autocomplete="off"
                    class="w-full py-2.5 px-4 text-sm rounded-lg border-2 transition-colors outline-none font-mono
                           focus:ring-0"
                    :class="palabra === 'LIMPIAR'
                        ? 'border-red-400 bg-red-50 text-red-700'
                        : 'border-gray-200 bg-gray-50 text-gray-700 focus:border-gray-300'">
            </div>

            <button type="submit"
                :disabled="palabra !== 'LIMPIAR'"
                class="w-full py-2.5 px-4 rounded-lg text-sm font-bold transition-all
                       flex items-center justify-center gap-2"
                :class="palabra === 'LIMPIAR'
                    ? 'bg-red-500 hover:bg-red-600 text-white shadow-sm cursor-pointer'
                    : 'bg-gray-100 text-gray-300 cursor-not-allowed'">
                <i class="fas fa-trash-alt"></i>
                <span x-text="ejecutando ? 'Limpiando...' : 'Ejecutar limpieza'"></span>
            </button>
        </form>
    </div>

</div>

<?php View::endSection('content'); ?>

<?php View::section('extra_js'); ?>
<script>
function limpiezaPage() {
    return {
        palabra: '',
        ejecutando: false,
        confirmar: function (e) {
            if (this.palabra !== 'LIMPIAR') return;
            if (!confirm('¿Estás seguro? Esta acción no se puede deshacer.')) return;
            this.ejecutando = true;
            e.target.submit();
        }
    };
}
</script>
<?php View::endSection('extra_js'); ?>

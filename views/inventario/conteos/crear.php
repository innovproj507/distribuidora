<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<div class="max-w-xl mx-auto">
    <form action="/inventario/conteos" method="POST"
          class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-5">
        <?= View::csrf() ?>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Depósito *</label>
            <select name="deposito_id" required class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-sky-500">
                <option value="">Seleccione un depósito</option>
                <?php foreach (($depositos ?? []) as $dep): ?>
                    <option value="<?= $dep['deposito_id'] ?>"><?= View::e($dep['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de conteo</label>
            <select name="tipo" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-sky-500">
                <option value="completo">Completo — todos los productos del depósito</option>
                <option value="ciclico">Cíclico — subconjunto por categoría</option>
                <option value="aleatorio">Aleatorio — muestra aleatoria</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Descripción / Referencia</label>
            <input type="text" name="descripcion" placeholder="Ej: Conteo mensual mayo 2026"
                   class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-sky-500">
        </div>

        <p class="text-xs text-gray-400 bg-amber-50 border border-amber-200 rounded-lg p-3">
            <i class="fas fa-info-circle text-amber-500 mr-1"></i>
            Al crear el conteo se cargará automáticamente la lista de productos con su stock actual del sistema.
            Luego podrá ingresar las cantidades físicas contadas.
        </p>

        <div class="flex justify-end gap-3 border-t pt-4">
            <a href="/inventario/conteos" class="px-4 py-2 border rounded-lg text-sm text-gray-600 hover:bg-gray-50">Cancelar</a>
            <button type="submit" class="px-6 py-2 bg-sky-500 text-white rounded-lg text-sm font-medium hover:bg-sky-600 shadow-sm">
                <i class="fas fa-play mr-1"></i> Iniciar Conteo
            </button>
        </div>
    </form>
</div>

<?php View::endSection('content'); ?>

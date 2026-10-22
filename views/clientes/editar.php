<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<div x-data="{ tab: 'credito' }">

    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-sm text-gray-400 mb-3">
        <a href="/clientes" class="hover:text-gray-600 transition-colors">Clientes</a>
        <i class="fas fa-chevron-right text-xs"></i>
        <a href="/clientes/<?= $cliente->cliente_id ?>" class="hover:text-gray-600 transition-colors"><?= View::e($cliente->nombre) ?></a>
        <i class="fas fa-chevron-right text-xs"></i>
        <span class="text-gray-700 font-medium">Editar</span>
    </div>

    <form action="/clientes/<?= $cliente->cliente_id ?>/editar" method="POST">
        <?= View::csrf() ?>

        <!-- Action bar -->
        <div class="flex items-center justify-between bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-3 mb-4">
            <div class="flex gap-2">
                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
                    <i class="fas fa-save"></i> Guardar
                </button>
                <a href="/clientes/<?= $cliente->cliente_id ?>" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                    Cancelar
                </a>
            </div>
            <span class="px-3 py-1.5 rounded-full text-xs font-bold text-white <?= $cliente->estado === 'activo' ? 'bg-emerald-500' : 'bg-slate-400' ?>">
                <?= ucfirst($cliente->estado) ?>
            </span>
        </div>

        <!-- Document card -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
            <div class="p-6 border-b border-gray-100">
                <h2 class="text-3xl font-bold text-gray-900 mb-6"><?= View::e($cliente->nombre) ?></h2>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-16">
                    <!-- Columna izquierda -->
                    <div>
                        <div class="flex items-baseline gap-4 py-2">
                            <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Código *</label>
                            <input type="text" name="codigo" value="<?= View::e($cliente->codigo) ?>" required
                                   class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                        </div>
                        <div class="flex items-baseline gap-4 py-2">
                            <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Nombre *</label>
                            <input type="text" name="nombre" value="<?= View::e($cliente->nombre) ?>" required
                                   class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                        </div>
                        <div class="flex items-baseline gap-4 py-2">
                            <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Tipo</label>
                            <select name="tipo" class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                                <option value="natural" <?= $cliente->tipo === 'natural' ? 'selected' : '' ?>>Natural</option>
                                <option value="juridico" <?= $cliente->tipo === 'juridico' ? 'selected' : '' ?>>Jurídico</option>
                            </select>
                        </div>
                        <div class="flex items-baseline gap-4 py-2">
                            <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">RUC / Cédula</label>
                            <input type="text" name="ruc" value="<?= View::e($cliente->ruc) ?>"
                                   class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                        </div>
                        <div class="flex items-baseline gap-4 py-2">
                            <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">DV</label>
                            <input type="text" name="dv" value="<?= View::e($cliente->dv) ?>"
                                   class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                        </div>
                    </div>

                    <!-- Columna derecha -->
                    <div>
                        <div class="flex items-baseline gap-4 py-2">
                            <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Teléfono</label>
                            <input type="text" name="telefono" value="<?= View::e($cliente->telefono) ?>"
                                   class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                        </div>
                        <div class="flex items-baseline gap-4 py-2">
                            <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Email</label>
                            <input type="email" name="email" value="<?= View::e($cliente->email) ?>"
                                   class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                        </div>
                        <div class="flex items-baseline gap-4 py-2">
                            <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Aplica ITBMS</label>
                            <select name="itbms" class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                                <option value="SI" <?= ($cliente->itbms > 0) ? 'selected' : '' ?>>Sí</option>
                                <option value="NO" <?= ($cliente->itbms <= 0) ? 'selected' : '' ?>>No (Exento)</option>
                            </select>
                        </div>
                        <div class="flex items-baseline gap-4 py-2">
                            <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Estado</label>
                            <select name="estado" class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                                <option value="activo" <?= $cliente->estado === 'activo' ? 'selected' : '' ?>>Activo</option>
                                <option value="inactivo" <?= $cliente->estado === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                            </select>
                        </div>
                    </div>

                    <!-- Dirección — full width -->
                    <div class="lg:col-span-2 flex items-baseline gap-4 py-2">
                        <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Dirección</label>
                        <textarea name="direccion" rows="2"
                                  class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none resize-none"><?= View::e($cliente->direccion) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="border-b border-gray-100 px-6 flex gap-6">
                <button type="button" @click="tab = 'credito'"
                    :class="tab === 'credito' ? 'border-b-2 border-sky-500 text-sky-600' : 'text-gray-400 hover:text-gray-600'"
                    class="py-3 text-sm font-semibold -mb-px transition-colors">
                    Crédito
                </button>
            </div>

            <!-- Tab: Crédito -->
            <div x-show="tab === 'credito'" class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-16">
                    <div>
                        <div class="flex items-baseline gap-4 py-2">
                            <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Límite de Crédito ($)</label>
                            <input type="number" name="limite_credito" value="<?= $cliente->limite_credito ?>" step="0.01"
                                   class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                        </div>
                        <div class="flex items-baseline gap-4 py-2">
                            <label class="text-sm font-semibold text-gray-600 w-40 shrink-0">Días de Crédito</label>
                            <input type="number" name="dias_credito" value="<?= $cliente->dias_credito ?>" min="0"
                                   class="flex-1 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php View::endSection('content'); ?>

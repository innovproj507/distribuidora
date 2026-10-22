<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<!-- Métricas compactas -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-sky-500 to-sky-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Total Productos</p>
                <h3 class="text-xl font-extrabold mt-1"><?= $totalProductos ?? 0 ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-box"></i></span>
        </div>
    </div>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-emerald-500 to-emerald-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Categorías</p>
                <h3 class="text-xl font-extrabold mt-1"><?= $totalCategorias ?? 0 ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-tags"></i></span>
        </div>
    </div>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-amber-500 to-amber-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Stock Bajo</p>
                <h3 class="text-xl font-extrabold mt-1"><?= $productosStockBajo ?? 0 ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-triangle-exclamation"></i></span>
        </div>
    </div>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-red-500 to-red-600 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-white/80">Sin Stock</p>
                <h3 class="text-xl font-extrabold mt-1"><?= $productosSinStock ?? 0 ?></h3>
            </div>
            <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><i class="fas fa-circle-xmark"></i></span>
        </div>
    </div>
</div>

<!-- Filtros + acciones -->
<?php
$currentCat    = $_GET['categoria'] ?? '';
$initCatNombre = '';
foreach (($categorias ?? []) as $_c) {
    $_id = is_array($_c) ? ($_c['categoria_id'] ?? '') : ($_c->categoria_id ?? '');
    if ((string)$_id === (string)$currentCat && $currentCat !== '') {
        $initCatNombre = is_array($_c) ? ($_c['nombre'] ?? '') : ($_c->nombre ?? '');
        break;
    }
}
$catsJson = json_encode(array_values(array_map(fn($c) => [
    'id'     => (string)(is_array($c) ? ($c['categoria_id'] ?? '') : ($c->categoria_id ?? '')),
    'nombre' => is_array($c) ? ($c['nombre'] ?? '') : ($c->nombre ?? ''),
], $categorias ?? [])));
?>

<!-- Alpine data como función para evitar problemas con > en atributos HTML -->
<script>
function posCatFilter() {
    return {
        open: false,
        search: <?= json_encode($initCatNombre) ?>,
        selectedId: <?= json_encode((string)$currentCat) ?>,
        cats: <?= $catsJson ?? '[]' ?>,
        get filtered() {
            var q = this.search.trim().toLowerCase();
            if (!q) return this.cats;
            return this.cats.filter(function(c) {
                return c.nombre.toLowerCase().indexOf(q) !== -1;
            });
        },
        select: function(cat) {
            this.search    = cat.nombre;
            this.selectedId = cat.id;
            this.open      = false;
            this.$nextTick(function() {
                document.getElementById('search-form').submit();
            });
        },
        clear: function() {
            this.search    = '';
            this.selectedId = '';
            this.open      = false;
            this.$nextTick(function() {
                document.getElementById('search-form').submit();
            });
        }
    };
}
</script>

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-3 mb-4">
    <form method="get" id="search-form" class="flex flex-wrap items-end gap-3">

        <!-- Buscador texto -->
        <div class="flex-1 min-w-48">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Buscar</label>
            <div class="relative">
                <i class="fas fa-search absolute left-0 top-2 text-gray-400 text-xs"></i>
                <input type="text" name="buscar" value="<?= View::e($_GET['buscar'] ?? '') ?>"
                    placeholder="Buscar producto..." id="search-input"
                    class="w-full pl-5 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                <div id="search-spinner" class="absolute right-0 top-2 hidden">
                    <i class="fas fa-spinner fa-spin text-sky-500 text-xs"></i>
                </div>
            </div>
        </div>

        <!-- Autocomplete de categoría -->
        <div class="min-w-52 relative" x-data="posCatFilter()" @click.outside="open = false">

            <label class="block text-xs font-semibold text-gray-500 mb-1">Categoría</label>
            <input type="hidden" name="categoria" :value="selectedId">

            <div class="relative">
                <i class="fas fa-tags absolute left-0 top-2 text-gray-400 text-xs"></i>
                <input type="text"
                    x-model="search"
                    @focus="open = true"
                    @input="open = true"
                    @keydown.escape="open = false"
                    placeholder="Todas las categorías"
                    autocomplete="off"
                    class="w-full pl-5 pr-5 py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none">
                <button type="button" x-show="search" @click="clear()"
                    class="absolute right-0 top-1.5 text-gray-300 hover:text-gray-500 text-xs leading-none">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Dropdown -->
            <div x-show="open"
                class="absolute z-50 top-full left-0 mt-1 w-64 bg-white border border-gray-200 rounded-lg shadow-xl max-h-52 overflow-y-auto"
                style="display:none;">

                <button type="button" @click="clear()"
                    class="w-full text-left px-3 py-2 text-xs text-gray-400 hover:bg-gray-50 border-b border-gray-100 transition-colors">
                    <i class="fas fa-times-circle mr-1 text-gray-300"></i> Todas las categorías
                </button>

                <template x-for="cat in filtered" :key="cat.id">
                    <button type="button" @click="select(cat)"
                        :class="cat.id === selectedId ? 'bg-sky-50 text-sky-700 font-semibold' : 'text-gray-700 hover:bg-gray-50'"
                        class="w-full text-left px-3 py-2 text-xs transition-colors">
                        <span x-text="cat.nombre"></span>
                    </button>
                </template>

                <p x-show="filtered.length === 0" class="px-3 py-4 text-xs text-gray-400 text-center">
                    Sin resultados
                </p>
            </div>
        </div>

        <!-- Estado -->
        <div class="min-w-32">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Estado</label>
            <select name="estado" onchange="this.form.submit()"
                class="w-full py-1.5 px-0 text-sm bg-transparent border-0 border-b border-gray-200 focus:border-sky-400 focus:ring-0 outline-none appearance-none">
                <option value="">Todos</option>
                <option value="activo"   <?= (($_GET['estado'] ?? '') === 'activo')   ? 'selected' : '' ?>>Activo</option>
                <option value="inactivo" <?= (($_GET['estado'] ?? '') === 'inactivo') ? 'selected' : '' ?>>Inactivo</option>
            </select>
        </div>

        <div class="flex gap-2">
            <?php if (!empty($_GET['buscar']) || !empty($currentCat) || !empty($_GET['estado'])): ?>
            <a href="/inventario"
                class="inline-flex items-center gap-1 px-3 py-2 border border-gray-200 text-gray-400 rounded-lg text-sm hover:bg-gray-50 transition"
                title="Limpiar filtros">
                <i class="fas fa-times"></i>
            </a>
            <?php endif; ?>
            <a href="/inventario/exportar-excel"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700 transition shadow-sm"
                title="Exportar a Excel (.xlsx)">
                <i class="fas fa-file-excel"></i> .xlsx
            </a>
            <a href="/inventario/nuevo"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-500 text-white rounded-lg text-sm font-semibold hover:bg-sky-600 transition shadow-sm">
                <i class="fas fa-plus"></i> Nuevo
            </a>
        </div>

    </form>
</div>

<!-- Tabla -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <table class="min-w-full divide-y divide-gray-100">
        <thead>
            <tr>
                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Producto</th>
                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-semibold text-sky-500 uppercase tracking-wider">Precio 1</th>
                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-semibold text-emerald-500 uppercase tracking-wider">Precio 2</th>
                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-semibold text-pink-500 uppercase tracking-wider">Precio 3</th>
                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Costo</th>
                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Inventario</th>
                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Disponibilidad</th>
                <th class="px-4 py-3 bg-gray-50 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Acciones</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-100">
            <?php foreach (($productos ?? []) as $producto): ?>
            <?php
            $pNombre = is_array($producto) ? ($producto['nombre'] ?? '') : ($producto->nombre ?? '');
            $pCodigo = is_array($producto) ? ($producto['codigo'] ?? '') : ($producto->codigo ?? '');
            $pId = is_array($producto) ? ($producto['producto_id'] ?? '') : ($producto->producto_id ?? '');
            $stock = is_array($producto) ? ($producto['stock_total'] ?? 0) : ($producto->stock_total ?? 0);
            $stockMin = is_array($producto) ? ($producto['stock_minimo'] ?? 0) : ($producto->stock_minimo ?? 0);
            $costo = is_array($producto) ? ($producto['costo'] ?? $producto['costo_promedio'] ?? 0) : ($producto->costo ?? $producto->costo_promedio ?? 0);
            if ($stock <= 0) {
                $bgClass    = 'bg-red-100';
                $hoverClass = 'hover:bg-red-200/70';
            } elseif ($stockMin > 0 && $stock <= $stockMin) {
                $bgClass    = 'bg-amber-50';
                $hoverClass = 'hover:bg-amber-100';
            } else {
                $bgClass    = '';
                $hoverClass = 'hover:bg-gray-50';
            }
            ?>
            <tr class="<?= $bgClass ?> <?= $hoverClass ?> transition-colors">
                <td class="px-4 py-3 whitespace-nowrap">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-gray-100 rounded-lg flex items-center justify-center overflow-hidden shrink-0">
                            <?php $img = is_array($producto) ? ($producto['imagen_principal'] ?? '') : ($producto->imagen_principal ?? ''); ?>
                            <?php if ($img): ?>
                            <img src="/assets/uploads/<?= View::e($img) ?>" class="h-9 w-9 object-cover" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                            <i class="fas fa-box text-gray-400 text-sm hidden"></i>
                            <?php else: ?>
                            <i class="fas fa-box text-gray-400 text-sm"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <a href="/inventario/<?= $pId ?>" class="text-sm font-semibold text-gray-900 hover:text-sky-500 transition-colors"><?= View::e($pNombre) ?></a>
                            <div class="text-xs text-gray-400">SKU: <?= View::e($pCodigo) ?></div>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-sky-600">
                    <?php $precioA = is_array($producto) ? ($producto['precio_a'] ?? 0) : ($producto->precio_a ?? 0); ?>
                    <?= $precioA ? '$' . number_format((float)$precioA, 2) : '<span class="text-gray-300">—</span>' ?>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-emerald-600">
                    <?php $precioB = is_array($producto) ? ($producto['precio_b'] ?? 0) : ($producto->precio_b ?? 0); ?>
                    <?= $precioB ? '$' . number_format((float)$precioB, 2) : '<span class="text-gray-300">—</span>' ?>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-pink-600">
                    <?php $precioC = is_array($producto) ? ($producto['precio_c'] ?? 0) : ($producto->precio_c ?? 0); ?>
                    <?= $precioC ? '$' . number_format((float)$precioC, 2) : '<span class="text-gray-300">—</span>' ?>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">$<?= number_format((float)$costo, 2) ?></td>
                <td class="px-4 py-3 whitespace-nowrap">
                    <?php
                        $cantDepositos = is_array($producto) ? ($producto['cantidad_depositos'] ?? 1) : ($producto->cantidad_depositos ?? 1);
                        $depNombre     = is_array($producto) ? ($producto['deposito_principal'] ?? 'Principal') : ($producto->deposito_principal ?? 'Principal');
                        $stockColor    = $stock <= 0 ? 'text-red-600 font-black' : ($stockMin > 0 && $stock <= $stockMin ? 'text-amber-600 font-bold' : 'text-gray-900 font-semibold');
                    ?>
                    <div class="text-sm <?= $stockColor ?>"><?= number_format((float)$stock, 0) ?> unid.</div>
                    <div class="text-xs text-gray-400 mt-0.5">
                        <?php if ($cantDepositos == 1): ?>
                            <?= View::e($depNombre) ?>
                        <?php elseif ($cantDepositos > 1): ?>
                            <span class="text-sky-500 font-medium"><?= $cantDepositos ?> depósitos</span>
                        <?php else: ?>
                            Sin inventario
                        <?php endif; ?>
                    </div>
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                    <?php if ($stock <= 0): ?>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold rounded-full bg-red-500 text-white">
                            <i class="fas fa-times-circle" style="font-size:9px"></i> Agotado
                        </span>
                    <?php elseif ($stockMin > 0 && $stock <= $stockMin): ?>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold rounded-full bg-amber-500 text-white">
                            <i class="fas fa-exclamation-triangle" style="font-size:9px"></i> Stock bajo
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold rounded-full bg-emerald-500 text-white">
                            <i class="fas fa-check-circle" style="font-size:9px"></i> En stock
                        </span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-right text-sm">
                    <div class="flex justify-end gap-1.5">
                        <a href="/inventario/<?= $pId ?>" class="w-8 h-8 rounded-lg bg-blue-500 text-white hover:bg-blue-600 transition-colors flex items-center justify-center shadow-sm shadow-blue-500/30" title="Ver"><i class="fas fa-eye text-xs"></i></a>
                        <a href="/inventario/<?= $pId ?>/editar" class="w-8 h-8 rounded-lg bg-amber-500 text-white hover:bg-amber-600 transition-colors flex items-center justify-center shadow-sm shadow-amber-500/30" title="Editar"><i class="fas fa-edit text-xs"></i></a>
                        <a href="/inventario/<?= $pId ?>/precios" class="w-8 h-8 rounded-lg bg-teal-500 text-white hover:bg-teal-600 transition-colors flex items-center justify-center shadow-sm shadow-teal-500/30" title="Precios"><i class="fas fa-tag text-xs"></i></a>
                        <form method="post" action="/inventario/<?= $pId ?>/eliminar" class="inline" onsubmit="return confirm('¿Eliminar este producto?')">
                            <?= View::csrf() ?>
                            <button type="submit" class="w-8 h-8 rounded-lg bg-red-500 text-white hover:bg-red-600 transition-colors flex items-center justify-center shadow-sm shadow-red-500/30" title="Eliminar"><i class="fas fa-trash text-xs"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($productos)): ?>
            <tr>
                <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-400">
                    <i class="fas fa-box-open text-3xl mb-2 block text-gray-200"></i>
                    No se encontraron productos.
                    <a href="/inventario/nuevo" class="block mt-1 text-sky-500 hover:underline font-medium">Crear uno nuevo</a>
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if (!empty($productos)): ?>
<div class="mt-4">
    <?php View::include('partials.pagination', ['pagination' => $pagination ?? []]); ?>
</div>
<?php endif; ?>

<?php View::endSection('content'); ?>

<?php View::section('extra_js'); ?>
<script>
    let searchTimeout;
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            const spinner = document.getElementById('search-spinner');
            if (e.target.value.length >= 2 || e.target.value.length === 0) {
                spinner.classList.remove('hidden');
                searchTimeout = setTimeout(() => { document.getElementById('search-form').submit(); }, 500);
            }
        });
    }
</script>
<?php View::endSection('extra_js'); ?>

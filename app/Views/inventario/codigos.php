<?php $this->extend('layouts/app'); ?>
<?php $this->section('content'); ?>

<?php
$filtros = ['sin' => 'Sin código', 'interno' => 'Con código interno', 'todos' => 'Todos'];
$listaJs = json_encode($productos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<div x-data="codigosBarras()">

<!-- Resumen -->
<div class="grid grid-cols-3 gap-3 mb-4">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-xs text-gray-500">Productos activos</p>
        <p class="text-2xl font-bold text-gray-800"><?= (int) ($resumen['total'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-xs text-gray-500">Sin código de barras</p>
        <p class="text-2xl font-bold text-amber-600"><?= (int) ($resumen['sin_codigo'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-xs text-gray-500">Con código interno</p>
        <p class="text-2xl font-bold text-sky-600"><?= (int) ($resumen['internos'] ?? 0) ?></p>
    </div>
</div>

<!-- Filtros -->
<form method="get" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-3 mb-3 flex flex-wrap items-center gap-2">
    <?php foreach ($filtros as $k => $t): ?>
        <a href="?ver=<?= $k ?>&q=<?= urlencode($q) ?>&categoria=<?= (int) $categoriaId ?>"
           class="px-3 py-1.5 rounded-lg text-sm font-semibold <?= $filtro === $k ? 'bg-sky-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>"><?= $t ?></a>
    <?php endforeach; ?>
    <input type="hidden" name="ver" value="<?= esc($filtro) ?>">
    <input type="text" name="q" value="<?= esc($q) ?>" placeholder="Buscar nombre, código o referencia" class="flex-1 min-w-[180px] px-3 py-1.5 text-sm border border-gray-200 rounded-lg">
    <select name="categoria" class="px-3 py-1.5 text-sm border border-gray-200 rounded-lg bg-white" onchange="this.form.submit()">
        <option value="">Todas las categorías</option>
        <?php foreach ($categorias as $c): ?>
            <option value="<?= (int) $c['categoria_id'] ?>" <?= (int) $categoriaId === (int) $c['categoria_id'] ? 'selected' : '' ?>><?= esc($c['nombre']) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="px-3 py-1.5 bg-gray-800 text-white rounded-lg text-sm font-semibold">Buscar</button>
</form>

<!-- Acciones -->
<div class="bg-sky-50 border border-sky-100 rounded-2xl p-3 mb-3 text-sm text-sky-900 flex flex-wrap items-center gap-3">
    <span class="flex-1 min-w-[260px]"><i class="fas fa-barcode mr-1"></i>
        Haz clic en <b>Código de barras</b> del primer producto y escanea con la pistola: se guarda y salta al siguiente.
        A lo que no trae código (granel, verduras) dale <b>Interno</b>.</span>
    <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="conPres" class="rounded"> incluir presentaciones</label>
    <button type="button" @click="generarTodos()" :disabled="ocupado"
            class="px-3 py-2 bg-sky-600 text-white rounded-lg text-xs font-semibold hover:bg-sky-700 disabled:opacity-50">
        Generar interno a los <span x-text="sinCodigo()"></span> sin código de esta lista
    </button>
    <span class="flex items-center gap-1 text-xs">
        <input type="number" min="1" max="100" x-model.number="copias" class="w-14 px-2 py-1 border border-sky-200 rounded"> copias
    </span>
    <button type="button" @click="imprimir()" :disabled="!seleccionados().length"
            class="px-3 py-2 bg-gray-800 text-white rounded-lg text-xs font-semibold disabled:opacity-40">
        <i class="fas fa-tags mr-1"></i>Etiquetas (<span x-text="seleccionados().length"></span>)
    </button>
</div>

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
            <tr class="text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                <th class="px-3 py-3 w-8"><input type="checkbox" @change="marcarTodos($event.target.checked)" class="rounded"></th>
                <th class="px-3 py-3">Producto</th>
                <th class="px-3 py-3 w-44">Ref. del proveedor</th>
                <th class="px-3 py-3 w-72">Código de barras</th>
                <th class="px-3 py-3">Presentaciones (caja, paca…)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            <template x-for="(p, i) in productos" :key="p.producto_id">
                <tr :class="p.error ? 'bg-red-50' : (p.ok ? 'bg-emerald-50/40' : '')">
                    <td class="px-3 py-2 align-top"><input type="checkbox" x-model="p.sel" class="rounded mt-1"></td>
                    <td class="px-3 py-2 align-top">
                        <a :href="'/inventario/' + p.producto_id" target="_blank" class="font-medium text-gray-800 hover:text-sky-600" x-text="p.nombre"></a>
                        <div class="text-xs text-gray-400"><span class="font-mono" x-text="p.codigo"></span> · <span x-text="p.categoria || 'Sin categoría'"></span></div>
                        <div class="text-xs text-red-600 font-medium" x-show="p.error" x-text="p.error"></div>
                    </td>
                    <td class="px-3 py-2 align-top">
                        <input type="text" x-model="p.supplier_part_no" @change="guardar(p, {supplier_part_no: p.supplier_part_no})" maxlength="100"
                               placeholder="Ej: CLX-473" class="w-full px-2 py-1.5 text-sm border border-gray-200 rounded-lg">
                    </td>
                    <td class="px-3 py-2 align-top">
                        <div class="flex gap-1">
                            <input type="text" x-model="p.codigo_barras" :data-fila="i"
                                   @focus="actual = i" @keydown.enter.prevent="guardarCodigo(p, i, true)" @change="guardarCodigo(p, i, false)"
                                   placeholder="Escanea aquí" autocomplete="off"
                                   class="cb-input flex-1 min-w-0 px-2 py-1.5 text-sm font-mono border rounded-lg"
                                   :class="p.codigo_barras ? (esInterno(p.codigo_barras) ? 'border-sky-200 bg-sky-50' : 'border-emerald-200 bg-emerald-50') : 'border-amber-300 bg-amber-50'">
                            <button type="button" x-show="!p.codigo_barras" @click="guardar(p, {codigo_barras: 'interno'}, i, true)"
                                    class="px-2 py-1 text-xs font-semibold bg-sky-100 text-sky-700 rounded-lg hover:bg-sky-200" title="Generar código interno">Interno</button>
                            <button type="button" @click="imprimir([p])" class="px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200" title="Imprimir etiqueta"><i class="fas fa-tag"></i></button>
                        </div>
                        <div class="text-[11px] mt-0.5" :class="esInterno(p.codigo_barras) ? 'text-sky-600' : 'text-gray-400'"
                             x-text="p.codigo_barras ? (esInterno(p.codigo_barras) ? 'Código interno' : 'Del fabricante') : 'Sin código'"></div>
                    </td>
                    <td class="px-3 py-2 align-top">
                        <template x-for="u in p.unidades" :key="u.unidad_id">
                            <div class="flex items-center gap-1 mb-1">
                                <span class="text-xs text-gray-600 w-28 truncate" x-text="u.nombre + ' ×' + numero(u.factor)"></span>
                                <input type="text" x-model="u.codigo_barras" @keydown.enter.prevent="guardarUnidad(p, u, u.codigo_barras)" @change="guardarUnidad(p, u, u.codigo_barras)"
                                       placeholder="Código de la caja" autocomplete="off"
                                       class="flex-1 min-w-0 px-2 py-1 text-xs font-mono border rounded-lg" :class="u.codigo_barras ? 'border-gray-200' : 'border-amber-200 bg-amber-50/50'">
                                <button type="button" x-show="!u.codigo_barras" @click="guardarUnidad(p, u, 'interno')" class="px-1.5 py-1 text-[11px] font-semibold bg-sky-100 text-sky-700 rounded">Interno</button>
                                <button type="button" x-show="u.codigo_barras" @click="imprimirUnidad(u)" class="px-1.5 py-1 text-[11px] bg-gray-100 text-gray-600 rounded" title="Etiqueta de la caja"><i class="fas fa-tag"></i></button>
                            </div>
                        </template>
                        <span x-show="!p.unidades.length" class="text-xs text-gray-300">—</span>
                    </td>
                </tr>
            </template>
            <tr x-show="!productos.length"><td colspan="5" class="px-3 py-8 text-center text-gray-400">No hay productos en esta lista. 🎉</td></tr>
        </tbody>
    </table>
    </div>
</div>
</div>

<?php $this->endSection(); ?>

<?php $this->section('extra_js'); ?>
<script src="/assets/js/lector-teclado.js?v=1"></script>
<script src="/assets/js/ticket.js?v=6"></script>
<script>
function codigosBarras() {
    const CSRF = <?= json_encode(csrf_hash()) ?>;
    return {
        productos: <?= $listaJs ?>.map(p => Object.assign(p, { sel: false, ok: false, error: '' })),
        actual: 0,
        conPres: true,
        copias: 1,
        ocupado: false,

        init() {
            // Pistola escaneando con el cursor fuera de los campos: va al producto actual (el primero sin código)
            LectorTeclado.escuchar(codigo => {
                let i = this.productos.findIndex((p, k) => k >= this.actual && !p.codigo_barras);
                if (i < 0) i = this.productos.findIndex(p => !p.codigo_barras);
                if (i < 0) return;
                this.productos[i].codigo_barras = codigo;
                this.guardarCodigo(this.productos[i], i, true);
            });
        },
        esInterno(c) { return /^20[01]\d{10}$/.test(c || ''); },
        numero(n) { return Number(n) % 1 === 0 ? String(Number(n)) : Number(n).toFixed(2); },
        sinCodigo() { return this.productos.filter(p => !p.codigo_barras).length; },
        seleccionados() { return this.productos.filter(p => p.sel); },
        marcarTodos(v) { this.productos.forEach(p => p.sel = v); },

        async post(url, cuerpo) {
            const r = await fetch(url, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify(cuerpo),
            });
            const d = await r.json().catch(() => ({ error: 'La sesión expiró: recarga la página.' }));
            if (!r.ok) throw new Error(d.error || 'No se pudo guardar');
            return d;
        },

        async guardar(p, datos, i = null, siguiente = false) {
            p.error = '';
            try {
                const d = await this.post('/api/codigos/' + p.producto_id, datos);
                p.codigo_barras = d.codigo_barras || '';
                p.supplier_part_no = d.supplier_part_no || '';
                p.unidades.forEach(u => { if (d.unidades && d.unidades[u.unidad_id] !== undefined) u.codigo_barras = d.unidades[u.unidad_id]; });
                p.ok = true;
                if (siguiente && i !== null) this.siguiente(i);
            } catch (e) {
                p.error = e.message;
                p.ok = false;
            }
        },

        guardarCodigo(p, i, siguiente) {
            if (p._guardado === p.codigo_barras && !p.error) { if (siguiente) this.siguiente(i); return; }
            p._guardado = p.codigo_barras;
            this.guardar(p, { codigo_barras: (p.codigo_barras || '').trim() }, i, siguiente);
        },

        guardarUnidad(p, u, codigo) {
            const uni = {}; uni[u.unidad_id] = (codigo || '').trim();
            this.guardar(p, { unidades: uni });
        },

        /** Cursor al código del siguiente producto que todavía no tiene. */
        siguiente(i) {
            let k = this.productos.findIndex((x, j) => j > i && !x.codigo_barras);
            if (k < 0) k = Math.min(i + 1, this.productos.length - 1);
            this.actual = k;
            this.$nextTick(() => {
                const el = document.querySelector('.cb-input[data-fila="' + k + '"]');
                if (el) { el.focus(); el.select(); el.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
            });
        },

        async generarTodos() {
            const ids = this.productos.filter(p => !p.codigo_barras || (this.conPres && p.unidades.some(u => !u.codigo_barras))).map(p => p.producto_id);
            if (!ids.length) { alert('Todos los productos de la lista ya tienen código.'); return; }
            if (!confirm('¿Generar código interno a ' + ids.length + ' productos' + (this.conPres ? ' (y sus presentaciones)' : '') + ' que no tienen?\n\nÚsalo solo para lo que NO trae código del fabricante.')) return;
            this.ocupado = true;
            try {
                const d = await this.post('/api/codigos/generar', { ids, presentaciones: this.conPres });
                alert('Listo: ' + d.productos + ' productos y ' + d.presentaciones + ' presentaciones con código interno.');
                location.reload();
            } catch (e) { alert(e.message); }
            this.ocupado = false;
        },

        imprimir(lista) {
            const ps = lista || this.seleccionados();
            if (!ps.length) return;
            const n = Math.max(1, Math.min(100, parseInt(this.copias, 10) || 1));
            TicketPOS.imprimirEtiquetasDe('p=' + ps.map(p => p.producto_id + ':' + n).join(','));
        },
        imprimirUnidad(u) {
            const n = Math.max(1, Math.min(100, parseInt(this.copias, 10) || 1));
            TicketPOS.imprimirEtiquetasDe('u=' + u.unidad_id + ':' + n);
        },
    };
}
</script>
<?php $this->endSection(); ?>

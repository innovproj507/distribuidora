<?php $this->extend('layouts/app'); ?>
<?php $this->section('content'); ?>

<?php
$cobradoHoy = array_sum(array_map(fn ($c) => (float) $c['total'], $cobrosHoy ?? []));
// Datos para buscar y filtrar en la pantalla (sin recargar)
$lista = array_map(fn ($c) => [
    'id'       => (int) $c['cliente_id'],
    'nombre'   => (string) $c['nombre'],
    'ruc'      => (string) ($c['ruc'] ?? ''),
    'telefono' => (string) ($c['telefono'] ?? ''),
    'saldo'    => (float) $c['saldo_pendiente'],
    'limite'   => (float) $c['cupo_credito'],
    'ultima'   => $c['ultima_venta_credito'] ? date('d/m/Y', strtotime($c['ultima_venta_credito'])) : '',
], $cuentas);
?>

<div x-data="cuentasCobrar()">

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-rose-500 to-rose-600 text-white">
        <p class="text-xs font-bold text-white/80 uppercase tracking-widest">Por cobrar</p>
        <h3 class="text-2xl font-black mt-1">$<?= number_format((float) ($stats['total_deuda'] ?? 0), 2) ?></h3>
        <p class="text-xs text-white/80 mt-1"><?= (int) ($stats['total_deudores'] ?? 0) ?> clientes deben</p>
    </div>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-emerald-500 to-emerald-600 text-white">
        <p class="text-xs font-bold text-white/80 uppercase tracking-widest">Cobrado hoy · <?= esc($sucursal_actual['nombre'] ?? '') ?></p>
        <h3 class="text-2xl font-black mt-1">$<?= number_format($cobradoHoy, 2) ?></h3>
        <p class="text-xs text-white/80 mt-1 truncate"><?= $cobrosHoy ? esc(implode(' · ', array_map(fn ($c) => ucfirst(strtolower($c['forma_pago'])) . ' $' . number_format((float) $c['total'], 2), $cobrosHoy))) : 'Sin cobros todavía' ?></p>
    </div>
    <div class="rounded-2xl p-5 shadow-sm bg-gradient-to-br from-amber-500 to-amber-600 text-white">
        <p class="text-xs font-bold text-white/80 uppercase tracking-widest">Deben más de su límite</p>
        <h3 class="text-2xl font-black mt-1" x-text="clientes.filter(c => c.saldo > c.limite).length"></h3>
        <p class="text-xs text-white/80 mt-1">clientes excedidos o sin límite</p>
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="p-4 border-b border-slate-100 flex flex-wrap items-center gap-2">
        <div class="relative flex-1 min-w-[220px]">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-300 text-sm"></i>
            <input type="search" x-model="q" placeholder="Buscar cliente por nombre, teléfono o RUC…" autofocus
                   class="w-full pl-9 pr-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-sky-200 focus:border-sky-400 outline-none">
        </div>
        <template x-for="f in [['todos','Todos'],['excedidos','Excedidos'],['antiguos','Último crédito +30 días']]" :key="f[0]">
            <button type="button" @click="filtro = f[0]" class="px-3 py-1.5 rounded-lg text-sm font-semibold"
                    :class="filtro === f[0] ? 'bg-sky-500 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" x-text="f[1]"></button>
        </template>
        <select x-model="orden" class="px-3 py-1.5 text-sm border border-slate-200 rounded-lg bg-white">
            <option value="saldo">Mayor deuda primero</option>
            <option value="nombre">A → Z</option>
            <option value="antiguo">Crédito más viejo primero</option>
        </select>
        <?php if (\App\Libraries\Auth::can('clientes.editar')): ?>
        <a href="/clientes/saldos-anteriores/importar" class="text-sky-600 hover:text-sky-700 font-semibold text-sm flex items-center gap-1.5 ml-auto">
            <i class="fas fa-file-import"></i> Importar saldos anteriores
        </a>
        <?php endif; ?>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100">
            <thead class="bg-slate-50/50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-bold text-slate-400 uppercase">Cliente</th>
                    <th class="px-5 py-3 text-right text-xs font-bold text-slate-400 uppercase">Debe</th>
                    <th class="px-5 py-3 text-right text-xs font-bold text-slate-400 uppercase">Límite</th>
                    <th class="px-5 py-3 text-center text-xs font-bold text-slate-400 uppercase">Último crédito</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                <template x-for="c in visibles()" :key="c.id">
                    <tr class="hover:bg-slate-50/50">
                        <td class="px-5 py-3">
                            <a :href="'/clientes/' + c.id" class="text-sm font-bold text-slate-700 hover:text-sky-600" x-text="c.nombre"></a>
                            <div class="text-[11px] text-slate-400" x-text="[c.telefono, c.ruc].filter(Boolean).join(' · ')"></div>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <span class="text-sm font-black text-rose-600" x-text="dinero(c.saldo)"></span>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <span class="text-xs" :class="c.saldo > c.limite ? 'text-rose-600 font-bold' : 'text-slate-500'"
                                  x-text="c.limite > 0 ? dinero(c.limite) + (c.saldo > c.limite ? ' · excedido' : '') : 'sin límite'"></span>
                        </td>
                        <td class="px-5 py-3 text-center text-xs text-slate-500" x-text="c.ultima || '—'"></td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <?php if ($puedeCobrar): ?>
                            <button type="button" @click="abrir(c)" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-500 text-white rounded-lg text-xs font-bold hover:bg-emerald-600">
                                <i class="fas fa-hand-holding-usd"></i> Cobrar
                            </button>
                            <?php endif; ?>
                            <a :href="'/clientes/' + c.id" title="Ver cliente" class="inline-flex w-8 h-8 rounded-lg bg-slate-100 text-slate-500 hover:bg-slate-200 items-center justify-center align-middle">
                                <i class="fas fa-chevron-right text-xs"></i>
                            </a>
                        </td>
                    </tr>
                </template>
                <tr x-show="!visibles().length">
                    <td colspan="5" class="px-5 py-12 text-center text-slate-400 text-sm" x-text="clientes.length ? 'Ningún cliente coincide con la búsqueda.' : '¡No hay cuentas pendientes por cobrar!'"></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal de cobro -->
<div x-show="abierto" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @keydown.escape.window="abierto = false">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[92vh] overflow-y-auto" @click.outside="if (!guardando) abierto = false">
        <!-- Paso: cobrar -->
        <div x-show="!recibo" class="p-6">
            <div class="flex items-start justify-between mb-1">
                <h3 class="text-lg font-bold text-slate-800" x-text="cliente ? cliente.nombre : ''"></h3>
                <button type="button" @click="abierto = false" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <p class="text-sm text-slate-500 mb-4">Debe <b class="text-rose-600" x-text="dinero(deuda())"></b></p>

            <p class="text-xs font-bold text-slate-500 uppercase mb-2">1 · ¿Qué paga?</p>
            <div class="flex flex-col gap-1.5 mb-4 max-h-56 overflow-y-auto">
                <label class="flex items-center gap-3 px-3 py-2 rounded-lg border cursor-pointer" :class="factura === null ? 'border-sky-400 bg-sky-50' : 'border-slate-200'">
                    <input type="radio" name="cb-que" :checked="factura === null" @change="elegirFactura(null)">
                    <span class="flex-1 text-sm"><b>Abono a la cuenta</b> <span class="text-slate-500">— se aplica a lo más antiguo primero</span></span>
                </label>
                <template x-for="f in facturas" :key="f.numero_factura">
                    <label class="flex items-center gap-3 px-3 py-2 rounded-lg border" :class="[f.venta_id ? 'cursor-pointer' : 'opacity-70', factura === f ? 'border-sky-400 bg-sky-50' : 'border-slate-200']">
                        <input type="radio" name="cb-que" :disabled="!f.venta_id" :checked="factura === f" @change="elegirFactura(f)">
                        <span class="flex-1 text-sm">
                            <span class="font-mono" x-text="f.numero_factura"></span>
                            <span class="text-xs text-slate-500" x-text="' · ' + fecha(f.fecha) + (Number(f.abonado) > 0 ? ' · abonado ' + dinero(f.abonado) : '')"></span>
                        </span>
                        <span class="text-sm font-bold" x-text="dinero(f.pendiente)"></span>
                    </label>
                </template>
            </div>

            <p class="text-xs font-bold text-slate-500 uppercase mb-2">2 · ¿Cuánto paga?</p>
            <div class="flex gap-2 mb-1">
                <input type="number" step="0.01" min="0" x-model.number="monto" x-ref="monto" placeholder="0.00"
                       class="flex-1 px-3 py-2 text-lg font-bold border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-200 focus:border-sky-400 outline-none">
                <button type="button" @click="monto = tope()" class="px-3 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm font-semibold hover:bg-slate-200">Todo</button>
            </div>
            <p class="text-xs mb-4" :class="monto > tope() + 0.005 ? 'text-rose-600 font-semibold' : 'text-slate-500'"
               x-text="monto > tope() + 0.005 ? 'Es más de lo que debe (' + dinero(tope()) + ')' : (monto > 0 ? 'Quedará debiendo ' + dinero(deuda() - monto) : ' ')"></p>

            <p class="text-xs font-bold text-slate-500 uppercase mb-2">3 · ¿Cómo paga?</p>
            <div class="grid grid-cols-5 gap-1.5 mb-3">
                <template x-for="f in [['EFECTIVO','Efectivo'],['YAPPY','Yappy'],['TRANSFERENCIA','Transf.'],['TARJETA','Tarjeta'],['CHEQUE','Cheque']]" :key="f[0]">
                    <button type="button" @click="forma = f[0]" class="py-2 rounded-lg text-xs font-bold border"
                            :class="forma === f[0] ? 'bg-sky-500 text-white border-sky-500' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'" x-text="f[1]"></button>
                </template>
            </div>
            <input type="text" x-show="forma !== 'EFECTIVO'" x-model="referencia" maxlength="100" placeholder="Referencia / nº de comprobante (opcional)"
                   class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg mb-3">
            <!-- Foto del comprobante: obligatoria con Yappy / transferencia / cheque; opcional con tarjeta -->
            <div x-show="forma !== 'EFECTIVO'" class="mb-3 rounded-lg border p-3" :class="exigeFoto() && !foto ? 'border-amber-300 bg-amber-50' : 'border-slate-200'">
                <p class="text-sm font-semibold text-slate-700 mb-1">
                    Foto del comprobante <span x-show="exigeFoto()" class="text-rose-500">* obligatoria</span><span x-show="!exigeFoto()" class="text-slate-400 font-normal">(voucher, opcional)</span>
                </p>
                <input type="file" accept="image/*" x-ref="foto" @change="elegirFoto($event)"
                       class="w-full text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-sky-50 file:text-sky-700 file:font-semibold">
                <img x-show="fotoUrl" :src="fotoUrl" class="mt-2 max-h-32 rounded-lg border border-slate-200">
            </div>

            <p class="text-sm text-rose-600 mb-2" x-text="error"></p>
            <button type="button" @click="cobrar()" :disabled="guardando || !(monto > 0) || monto > tope() + 0.005 || (exigeFoto() && !foto)"
                    class="w-full py-3 bg-emerald-500 text-white rounded-xl font-bold hover:bg-emerald-600 disabled:opacity-40">
                <i class="fas fa-check mr-1"></i> <span x-text="guardando ? 'Registrando…' : 'Cobrar ' + dinero(monto || 0) + ' en ' + forma.toLowerCase()"></span>
            </button>
        </div>

        <!-- Paso: recibo -->
        <div x-show="recibo" class="p-6 text-center">
            <div class="w-14 h-14 rounded-full bg-emerald-100 text-emerald-600 mx-auto mb-3 flex items-center justify-center text-2xl"><i class="fas fa-check"></i></div>
            <h3 class="text-lg font-bold text-slate-800">Cobro registrado</h3>
            <p class="text-sm text-slate-500 mb-4">Recibo <span class="font-mono" x-text="recibo ? recibo.numero : ''"></span></p>
            <div class="text-left bg-slate-50 rounded-xl p-4 mb-4 text-sm">
                <div class="flex justify-between mb-1"><span>Cobrado</span><b x-text="recibo ? dinero(recibo.monto) : ''"></b></div>
                <div class="flex justify-between mb-2"><span>Queda debiendo</span><b x-text="recibo ? dinero(recibo.saldo) : ''"></b></div>
                <p class="text-xs text-slate-500" x-text="recibo ? recibo.aplicado.map(a => a.numero_factura + ': ' + dinero(a.monto) + (a.liquidada ? ' (pagada)' : ' (abono)')).join(' · ') : ''"></p>
            </div>
            <button type="button" @click="location.reload()" class="w-full py-3 bg-sky-500 text-white rounded-xl font-bold hover:bg-sky-600">Listo</button>
        </div>
    </div>
</div>

</div>

<?php $this->endSection(); ?>

<?php $this->section('extra_js'); ?>
<script src="/assets/js/foto-producto.js?v=1"></script>
<script>
function cuentasCobrar() {
    const CSRF = <?= json_encode(csrf_hash()) ?>;
    const sinAcento = s => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    const hoy = new Date();
    return {
        clientes: <?= json_encode($lista, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        q: '', filtro: <?= json_encode(in_array($ver, ['excedidos', 'antiguos'], true) ? $ver : 'todos') ?>, orden: 'saldo',
        abierto: false, cliente: null, facturas: [], factura: null, monto: '', forma: 'EFECTIVO', referencia: '',
        guardando: false, error: '', recibo: null, foto: null, fotoUrl: '',

        exigeFoto() { return ['YAPPY', 'TRANSFERENCIA', 'CHEQUE'].includes(this.forma); },
        async elegirFoto(ev) {
            const f = ev.target.files && ev.target.files[0];
            if (!f) { this.foto = null; this.fotoUrl = ''; return; }
            try { this.foto = window.FotoProducto ? await FotoProducto.reducir(f) : f; } catch (e) { this.foto = f; }
            this.fotoUrl = URL.createObjectURL(this.foto);
        },

        dinero(n) { return '$' + (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        fecha(f) { const d = String(f || '').slice(0, 10).split('-'); return d.length === 3 ? d[2] + '/' + d[1] : ''; },
        dias(c) { if (!c.ultima) return 0; const [d, m, a] = c.ultima.split('/'); return Math.floor((hoy - new Date(a, m - 1, d)) / 86400000); },
        visibles() {
            const q = sinAcento(this.q.trim());
            let l = this.clientes.filter(c => !q || sinAcento(c.nombre + ' ' + c.telefono + ' ' + c.ruc).includes(q));
            if (this.filtro === 'excedidos') l = l.filter(c => c.saldo > c.limite);
            if (this.filtro === 'antiguos') l = l.filter(c => this.dias(c) > 30);
            const o = this.orden;
            return l.slice().sort((a, b) => o === 'nombre' ? a.nombre.localeCompare(b.nombre) : (o === 'antiguo' ? this.dias(b) - this.dias(a) : b.saldo - a.saldo));
        },
        deuda() { return this.facturas.reduce((s, f) => s + Number(f.pendiente), 0); },
        tope() { return Math.round((this.factura ? Number(this.factura.pendiente) : this.deuda()) * 100) / 100; },
        elegirFactura(f) { this.factura = f; this.monto = f ? Number(f.pendiente) : ''; this.$nextTick(() => this.$refs.monto.focus()); },

        async abrir(c) {
            this.cliente = c; this.facturas = []; this.factura = null; this.monto = ''; this.forma = 'EFECTIVO';
            this.referencia = ''; this.error = ''; this.recibo = null; this.abierto = true;
            this.foto = null; this.fotoUrl = ''; if (this.$refs.foto) this.$refs.foto.value = '';
            try {
                const r = await fetch('/m/api/cliente/' + c.id + '/pendientes', { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const d = await r.json();
                if (!r.ok) throw new Error(d.error || 'No se pudo cargar');
                this.facturas = d.facturas || [];
                this.$nextTick(() => this.$refs.monto.focus());
            } catch (e) { this.error = e.message; }
        },

        async cobrar() {
            if (this.exigeFoto() && !this.foto) { this.error = 'Adjunta la foto del comprobante.'; return; }
            this.guardando = true; this.error = '';
            try {
                // Cobro y foto van juntos: si la foto no llega, el cobro no se registra
                const datos = new FormData();
                datos.append('cliente_id', this.cliente.id);
                datos.append('monto', Number(this.monto));
                datos.append('forma_pago', this.forma);
                datos.append('referencia', this.forma === 'EFECTIVO' ? '' : this.referencia);
                if (this.factura && this.factura.venta_id) datos.append('venta_id', this.factura.venta_id);
                if (this.foto && this.forma !== 'EFECTIVO') datos.append('foto', this.foto, 'comprobante.jpg');
                const r = await fetch('/m/api/cobro', {
                    method: 'POST', credentials: 'same-origin', body: datos,
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF },
                });
                const d = await r.json().catch(() => ({ error: 'La sesión expiró: recarga la página.' }));
                if (!r.ok) throw new Error(d.error || 'No se pudo registrar el cobro');
                this.recibo = d;
            } catch (e) { this.error = e.message; }
            this.guardando = false;
        },
    };
}
</script>
<?php $this->endSection(); ?>

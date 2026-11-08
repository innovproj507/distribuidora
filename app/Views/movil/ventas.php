<?php
$this->extend('layouts/movil');

$estadoVenta = [
    'pagada'    => ['Pagada',  'var(--green-light)',  'var(--green)'],
    'completada'=> ['Pagada',  'var(--green-light)',  'var(--green)'],
    'pendiente' => ['Crédito', 'var(--orange-light)', 'var(--orange)'],
    'anulada'   => ['Anulada', 'var(--red-light)',    'var(--red)'],
];
$totalHoy = 0;
foreach ($ventas as $v) {
    if ($v['estado'] !== 'anulada' && date('Y-m-d', strtotime($v['fecha'])) === date('Y-m-d')) $totalHoy += (float) $v['total'];
}
?>
<?php $this->section('content'); ?>

<div class="stat-card stat-navy mb-4">
  <p class="text-xs text-white/80">Vendido hoy</p>
  <p class="font-mono text-2xl font-semibold"><?= '$' . number_format($totalHoy, 2) ?></p>
</div>

<div class="mb-3 flex items-center gap-2 bg-white rounded-xl border px-3 py-2" style="border-color:var(--line)">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6B7794" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
  <input type="search" id="input-busqueda" class="flex-1 text-sm outline-none bg-transparent" placeholder="Buscar factura o cliente" autocomplete="off">
</div>

<p class="text-sm text-center mt-6" id="msg-vacio" style="color:var(--ink-soft); display:none">Ninguna venta coincide.</p>
<div class="flex flex-col gap-2 pb-4">
  <?php if (empty($ventas)): ?><p class="text-sm" style="color:var(--ink-soft)">Todavía no hay ventas.</p><?php endif; ?>
  <?php foreach ($ventas as $v):
    $e = $estadoVenta[$v['estado']] ?? [ucfirst((string) $v['estado']), 'var(--line)', 'var(--ink-soft)'];
    $busq = mb_strtolower($v['numero_factura'] . ' ' . ($v['cliente_nombre'] ?? ''), 'UTF-8'); ?>
    <button type="button" onclick="verVenta(<?= (int) $v['venta_id'] ?>)" class="card !p-3 flex items-center gap-3 text-left venta-card" data-busqueda="<?= esc($busq) ?>">
      <div class="badge-icon text-lg" style="background:var(--purple-light)">🧾</div>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-medium truncate"><?= esc($v['cliente_nombre'] ?: 'Consumidor final') ?></p>
        <p class="text-xs truncate" style="color:var(--ink-soft)"><?= esc($v['numero_factura']) ?> · <?= date('d/m H:i', strtotime($v['fecha'])) ?> · <?= esc($v['forma_pago']) ?></p>
      </div>
      <div class="text-right flex-shrink-0">
        <p class="font-mono text-sm font-semibold"><?= '$' . number_format((float) $v['total'], 2) ?></p>
        <span class="text-[10px] px-1.5 py-0.5 rounded" style="background:<?= $e[1] ?>; color:<?= $e[2] ?>"><?= esc($e[0]) ?></span>
      </div>
    </button>
  <?php endforeach; ?>
</div>

<div id="sheet-venta" class="sheet" style="transform:translateY(110%)">
  <div class="sheet-handle"></div>
  <div class="flex items-center justify-between mb-3">
    <h2 class="font-head text-lg font-semibold" id="v-factura">Venta</h2>
    <button type="button" onclick="cerrarSheet('sheet-venta')" class="w-8 h-8 rounded-full flex items-center justify-center" style="background:var(--navy-light); color:var(--navy)">✕</button>
  </div>
  <p class="text-xs mb-3" style="color:var(--ink-soft)" id="v-meta"></p>
  <div id="v-items" class="flex flex-col gap-2 mb-3"></div>
  <div class="rounded-xl p-3 mb-3" style="background:var(--teal-light); color:var(--teal-dark)">
    <div class="flex justify-between text-xs mb-1"><span>Subtotal</span><span class="font-mono" id="v-sub"></span></div>
    <div class="flex justify-between text-xs mb-2"><span>ITBMS</span><span class="font-mono" id="v-itbms"></span></div>
    <div class="flex justify-between items-center"><span class="text-sm font-medium">Total</span><span class="font-mono text-xl font-semibold" id="v-total"></span></div>
  </div>
  <p class="text-xs mb-3" id="v-pagos" style="color:var(--ink-soft)"></p>
  <button type="button" onclick="compartirTicket()" class="block w-full py-3 rounded-xl font-medium text-center text-white mb-2" style="background:var(--green)">📤 Compartir ticket (WhatsApp…)</button>
  <?php if (!in_array(\App\Libraries\Auth::rol(), \App\Filters\SoloMovilFilter::ROLES, true)): ?>
  <a id="v-web" href="#" class="block w-full py-3 rounded-xl font-medium text-center" style="background:var(--navy-light); color:var(--navy)">Imprimir / anular (versión web)</a>
  <?php endif; ?>
</div>

<?php $this->endSection(); ?>

<?php $this->section('extra_js'); ?>
<script>
document.getElementById('input-busqueda').addEventListener('input', function () {
  const q = this.value.trim().toLowerCase();
  let visibles = 0;
  document.querySelectorAll('.venta-card').forEach(function (c) {
    const ok = !q || c.dataset.busqueda.indexOf(q) !== -1;
    c.style.display = ok ? '' : 'none';
    if (ok) visibles++;
  });
  document.getElementById('msg-vacio').style.display = visibles ? 'none' : 'block';
});

let ventaActual = null;

/** Ticket en texto para enviarlo por WhatsApp u otra app (sin impresora). */
function compartirTicket() {
  const v = ventaActual;
  if (!v) return;
  const lineas = [
    <?= json_encode($empresa_actual['nombre_comercial'] ?? 'Distribuidora') ?> + ' — ' + <?= json_encode($sucursal_actual['nombre'] ?? '') ?>,
    'Factura ' + v.numero_factura + ' · ' + v.fecha,
    'Cliente: ' + (v.cliente_nombre || 'Consumidor final'),
    '',
  ];
  v.items.forEach(function (i) {
    lineas.push(numero(i.cantidad) + (i.unidad_nombre ? ' ' + i.unidad_nombre.toLowerCase() : '') + ' x ' + i.nombre + '  ' + dinero(i.total_linea));
  });
  lineas.push('', 'Subtotal: ' + dinero(v.subtotal), 'ITBMS: ' + dinero(v.itbms), 'TOTAL: ' + dinero(v.total));
  if (v.pagos && v.pagos.length) lineas.push('Pago: ' + v.pagos.map(function (p) { return p.forma_pago + ' ' + dinero(p.monto); }).join(', '));
  if (v.estado === 'anulada') lineas.push('*** ANULADA ***');
  lineas.push('', '¡Gracias por su compra!');
  const texto = lineas.join('\n');
  if (navigator.share) {
    navigator.share({ title: 'Factura ' + v.numero_factura, text: texto }).catch(function () {});
  } else {
    window.open('https://wa.me/?text=' + encodeURIComponent(texto), '_blank');
  }
}

async function verVenta(id) {
  try {
    const v = await api('/m/api/venta/' + id);
    document.getElementById('v-factura').textContent = 'Factura ' + v.numero_factura;
    document.getElementById('v-meta').textContent = (v.cliente_nombre || 'Consumidor final') + ' · ' + v.fecha + (v.estado === 'anulada' ? ' · ANULADA' : '');
    document.getElementById('v-items').innerHTML = v.items.map(function (i) {
      return '<div class="card !p-3 flex justify-between gap-3"><div class="min-w-0"><p class="text-sm truncate">' + esc(i.nombre) + '</p>' +
        '<p class="text-xs font-mono" style="color:var(--ink-soft)">' + numero(i.cantidad) + (i.unidad_nombre ? ' ' + esc(i.unidad_nombre.toLowerCase()) : '') + ' × ' + dinero(i.precio) + '</p></div>' +
        '<p class="font-mono text-sm font-semibold">' + dinero(i.total_linea) + '</p></div>';
    }).join('');
    document.getElementById('v-sub').textContent = dinero(v.subtotal);
    document.getElementById('v-itbms').textContent = dinero(v.itbms);
    document.getElementById('v-total').textContent = dinero(v.total);
    document.getElementById('v-pagos').textContent = 'Pagos: ' + (v.pagos.length ? v.pagos.map(function (p) { return p.forma_pago + ' ' + dinero(p.monto); }).join(' · ') : v.forma_pago);
    const web = document.getElementById('v-web');
    if (web) web.href = '/ventas/venta/' + id;
    ventaActual = v;
    abrirSheet('sheet-venta');
  } catch (e) { toast(e.message, 'error'); }
}

// /m/ventas?venta=ID abre directamente el detalle (enlaces del inicio y del ticket de venta)
(function () {
  const id = parseInt(new URLSearchParams(location.search).get('venta'), 10);
  if (id > 0) verVenta(id);
})();
</script>
<?php $this->endSection(); ?>

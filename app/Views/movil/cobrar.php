<?php
$this->extend('layouts/movil');
$total = array_sum(array_map(fn ($c) => (float) $c['saldo_pendiente'], $cuentas));
$cobradoHoy = array_sum(array_map(fn ($c) => (float) $c['total'], $cobrosHoy));
?>
<?php $this->section('content'); ?>

<div class="grid grid-cols-2 gap-3 mb-3">
  <div class="stat-card stat-amber">
    <p class="text-xs text-white/80">Por cobrar</p>
    <p class="font-mono text-xl font-semibold"><?= '$' . number_format($total, 2) ?></p>
    <p class="text-[11px] text-white/80 mt-1"><?= count($cuentas) ?> cliente<?= count($cuentas) === 1 ? '' : 's' ?></p>
  </div>
  <div class="stat-card stat-teal">
    <p class="text-xs text-white/80">Cobrado hoy</p>
    <p class="font-mono text-xl font-semibold"><?= '$' . number_format($cobradoHoy, 2) ?></p>
    <p class="text-[11px] text-white/80 mt-1 truncate"><?= $cobrosHoy ? esc(implode(' · ', array_map(fn ($c) => ucfirst(strtolower($c['forma_pago'])) . ' $' . number_format((float) $c['total'], 0), $cobrosHoy))) : 'Sin cobros aún' ?></p>
  </div>
</div>

<!-- Buscar cliente -->
<div class="mb-2 flex items-center gap-2 bg-white rounded-xl border px-3 py-2" style="border-color:var(--line)">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6B7794" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
  <input type="search" id="buscar" class="flex-1 text-sm outline-none bg-transparent" placeholder="Buscar cliente o teléfono" autocomplete="off" oninput="filtrar()">
</div>
<div class="flex gap-2 mb-3 text-xs">
  <button type="button" class="chip-filtro px-3 py-1.5 rounded-full font-semibold" data-f="todos" onclick="ponerFiltro('todos')">Todos</button>
  <button type="button" class="chip-filtro px-3 py-1.5 rounded-full font-semibold" data-f="excedidos" onclick="ponerFiltro('excedidos')">Excedidos</button>
  <button type="button" class="chip-filtro px-3 py-1.5 rounded-full font-semibold" data-f="az" onclick="ponerFiltro('az')">A → Z</button>
</div>

<p class="text-sm text-center mt-6" id="sin-resultados" style="color:var(--ink-soft); display:none">Ningún cliente coincide.</p>
<div class="flex flex-col gap-2 pb-4" id="lista">
  <?php if (empty($cuentas)): ?><p class="text-sm text-center mt-6" style="color:var(--ink-soft)">Sin saldos pendientes 🎉</p><?php endif; ?>
  <?php foreach ($cuentas as $c):
    $saldo = (float) $c['saldo_pendiente']; $limite = (float) $c['limite_credito'];
    $tel = preg_replace('/[^0-9+]/', '', (string) $c['telefono']);
    $buscar = mb_strtolower($c['nombre'] . ' ' . $c['telefono'], 'UTF-8'); ?>
    <div class="card !p-3 cliente-card" data-buscar="<?= esc($buscar) ?>" data-nombre="<?= esc(mb_strtolower($c['nombre'], 'UTF-8')) ?>"
         data-saldo="<?= $saldo ?>" data-excedido="<?= $saldo > $limite ? 1 : 0 ?>">
      <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
          <p class="text-sm font-medium truncate"><?= esc($c['nombre']) ?></p>
          <p class="text-xs" style="color:var(--ink-soft)">
            <?= $c['ultima'] ? 'Último crédito ' . date('d/m', strtotime($c['ultima'])) : 'Sin crédito reciente' ?>
            · <?= $limite > 0 ? 'límite $' . number_format($limite, 0) . ($saldo > $limite ? ' <b style="color:var(--red)">excedido</b>' : '') : '<b style="color:var(--red)">sin límite</b>' ?>
          </p>
        </div>
        <p class="font-mono text-base font-semibold flex-shrink-0" style="color:var(--orange)"><?= '$' . number_format($saldo, 2) ?></p>
      </div>
      <div class="flex gap-2 mt-3">
        <?php if ($puedeCobrar): ?>
          <button type="button" onclick="abrirCobro(<?= (int) $c['cliente_id'] ?>)" class="flex-[2] text-center text-sm py-2 rounded-lg font-semibold text-white" style="background:var(--teal)">💵 Cobrar</button>
        <?php endif; ?>
        <?php if ($tel): ?>
          <a href="tel:<?= esc($tel) ?>" class="flex-1 text-center text-xs py-2 rounded-lg font-medium" style="background:var(--navy-light); color:var(--navy)">📞</a>
          <a href="https://wa.me/<?= esc(ltrim($tel, '+')) ?>?text=<?= rawurlencode('Hola ' . $c['nombre'] . ', le recordamos su saldo pendiente de $' . number_format($saldo, 2) . '.') ?>" class="flex-1 text-center text-xs py-2 rounded-lg font-medium" style="background:var(--green-light); color:var(--green)">WhatsApp</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Sheet: cobro en 3 pasos -->
<div id="sheet-cobro" class="sheet" style="transform:translateY(110%); max-height:94vh; overflow-y:auto">
  <div class="sheet-handle"></div>
  <div class="flex items-center justify-between mb-1">
    <h2 class="font-head text-lg font-semibold" id="cb-nombre">Cobro</h2>
    <button type="button" onclick="cerrarSheet('sheet-cobro')" class="w-8 h-8 rounded-full flex items-center justify-center" style="background:var(--navy-light); color:var(--navy)">✕</button>
  </div>
  <p class="text-sm mb-3" style="color:var(--ink-soft)">Debe <span class="font-mono font-semibold" style="color:var(--orange)" id="cb-saldo"></span></p>

  <p class="field-label">1 · ¿Qué paga?</p>
  <div id="cb-facturas" class="flex flex-col gap-1.5 mb-3" style="max-height:28vh; overflow-y:auto"></div>

  <p class="field-label">2 · ¿Cuánto paga?</p>
  <div class="flex gap-2 mb-1">
    <input type="number" inputmode="decimal" step="0.01" min="0" id="cb-monto" class="field-input text-lg" placeholder="0.00" oninput="resumenCobro()">
    <button type="button" onclick="document.getElementById('cb-monto').value = tope().toFixed(2); resumenCobro()" class="px-4 rounded-xl text-sm font-semibold flex-shrink-0" style="background:var(--navy-light); color:var(--navy)">Todo</button>
  </div>
  <p class="text-xs mb-3" id="cb-queda" style="color:var(--ink-soft)">&nbsp;</p>

  <p class="field-label">3 · ¿Cómo paga?</p>
  <div class="grid grid-cols-3 gap-2 mb-3">
    <button type="button" class="chip-pago chip-cb activo" data-forma="EFECTIVO" onclick="elegirFormaCobro('EFECTIVO')">💵 Efectivo</button>
    <button type="button" class="chip-pago chip-cb" data-forma="YAPPY" onclick="elegirFormaCobro('YAPPY')">📱 Yappy</button>
    <button type="button" class="chip-pago chip-cb" data-forma="TRANSFERENCIA" onclick="elegirFormaCobro('TRANSFERENCIA')">🏦 Transf.</button>
    <button type="button" class="chip-pago chip-cb" data-forma="TARJETA" onclick="elegirFormaCobro('TARJETA')">💳 Tarjeta</button>
    <button type="button" class="chip-pago chip-cb" data-forma="CHEQUE" onclick="elegirFormaCobro('CHEQUE')">🧾 Cheque</button>
  </div>
  <input id="cb-ref" class="field-input mb-4" style="font-family:'Inter'; display:none" placeholder="Referencia / nº de comprobante (opcional)">
  <button type="button" id="btn-cobro" onclick="confirmarCobro()" class="btn-primario" style="background:var(--teal)">Cobrar</button>
</div>

<!-- Sheet: recibo -->
<div id="sheet-recibo" class="sheet text-center" style="transform:translateY(110%)">
  <div class="sheet-handle"></div>
  <div class="w-16 h-16 rounded-full mx-auto mb-3 flex items-center justify-center text-3xl" style="background:var(--green-light)">✓</div>
  <h2 class="font-head text-lg font-semibold">Cobro registrado</h2>
  <p class="text-sm mb-4" style="color:var(--ink-soft)">Recibo <span id="rc-numero" class="font-mono"></span></p>
  <div class="card text-left mb-4">
    <div class="flex justify-between text-sm mb-1"><span>Cobrado</span><span class="font-mono font-semibold" id="rc-monto"></span></div>
    <div class="flex justify-between text-sm mb-2"><span>Queda debiendo</span><span class="font-mono font-semibold" id="rc-saldo"></span></div>
    <p class="text-xs" id="rc-aplicado" style="color:var(--ink-soft)"></p>
  </div>
  <button type="button" onclick="location.href = location.pathname" class="btn-primario">Listo</button>
</div>

<?php $this->endSection(); ?>

<?php $this->section('extra_js'); ?>
<script>
let clienteCobro = null, facturasCobro = [], facturaElegida = null, formaCobro = 'EFECTIVO', filtroActual = 'todos';
const sinAcento = function (s) { return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); };

// ── Lista: buscar y filtrar ──
function ponerFiltro(f) { filtroActual = f; filtrar(); }
function filtrar() {
  const q = sinAcento(document.getElementById('buscar').value.trim());
  const cards = Array.prototype.slice.call(document.querySelectorAll('.cliente-card'));
  let visibles = 0;
  cards.forEach(function (c) {
    const ok = (!q || sinAcento(c.dataset.buscar).indexOf(q) !== -1) && (filtroActual !== 'excedidos' || c.dataset.excedido === '1');
    c.style.display = ok ? '' : 'none';
    if (ok) visibles++;
  });
  cards.sort(function (a, b) { return filtroActual === 'az' ? a.dataset.nombre.localeCompare(b.dataset.nombre) : Number(b.dataset.saldo) - Number(a.dataset.saldo); })
       .forEach(function (c) { document.getElementById('lista').appendChild(c); });
  document.querySelectorAll('.chip-filtro').forEach(function (b) {
    const activo = b.dataset.f === filtroActual;
    b.style.background = activo ? 'var(--navy)' : '#fff';
    b.style.color = activo ? '#fff' : 'var(--ink)';
    b.style.border = '1px solid var(--line)';
  });
  document.getElementById('sin-resultados').style.display = cards.length && !visibles ? 'block' : 'none';
}
filtrar();

// ── Cobro ──
function deuda() { return facturasCobro.reduce(function (s, f) { return s + Number(f.pendiente); }, 0); }
function tope() { return Math.round((facturaElegida ? Number(facturaElegida.pendiente) : deuda()) * 100) / 100; }

function elegirFormaCobro(f) {
  formaCobro = f;
  document.querySelectorAll('.chip-cb').forEach(function (b) { b.classList.toggle('activo', b.dataset.forma === f); });
  document.getElementById('cb-ref').style.display = f === 'EFECTIVO' ? 'none' : '';
  resumenCobro();
}

function pintarFacturas() {
  const fila = function (activa, contenido, accion, deshabilitada) {
    return '<button type="button" ' + (deshabilitada ? 'disabled' : 'onclick="' + accion + '"') + ' class="flex items-center gap-2 text-left text-sm rounded-xl px-3 py-2"' +
      ' style="background:' + (activa ? 'var(--teal-light)' : 'white') + '; border:1.5px solid ' + (activa ? 'var(--teal)' : 'var(--line)') + (deshabilitada ? '; opacity:.6' : '') + '">' +
      '<span style="font-size:16px">' + (activa ? '◉' : '○') + '</span>' + contenido + '</button>';
  };
  let html = fila(facturaElegida === null, '<span class="flex-1"><b>Abono a la cuenta</b><br><span class="text-xs" style="color:var(--ink-soft)">Se aplica a lo más antiguo primero</span></span>', 'elegirFactura(-1)', false);
  html += facturasCobro.map(function (f, i) {
    const parcial = Number(f.abonado) > 0;
    const fecha = String(f.fecha || '').substr(5, 5).split('-').reverse().join('/');
    return fila(facturaElegida === f,
      '<span class="flex-1 min-w-0"><span class="font-mono text-xs">' + esc(f.numero_factura) + '</span><br><span class="text-xs" style="color:var(--ink-soft)">' + esc(fecha) + (parcial ? ' · abonado ' + dinero(f.abonado) : '') + '</span></span>' +
      '<span class="font-mono font-semibold">' + dinero(f.pendiente) + '</span>', 'elegirFactura(' + i + ')', !f.venta_id);
  }).join('');
  document.getElementById('cb-facturas').innerHTML = html;
}

function elegirFactura(i) {
  facturaElegida = i < 0 ? null : facturasCobro[i];
  document.getElementById('cb-monto').value = facturaElegida ? Number(facturaElegida.pendiente).toFixed(2) : '';
  pintarFacturas();
  resumenCobro();
}

function resumenCobro() {
  const m = parseFloat(document.getElementById('cb-monto').value) || 0;
  const q = document.getElementById('cb-queda');
  const boton = document.getElementById('btn-cobro');
  if (m > tope() + 0.005) { q.textContent = 'Es más de lo que debe (' + dinero(tope()) + ')'; q.style.color = 'var(--red)'; }
  else { q.textContent = m > 0 ? 'Quedará debiendo ' + dinero(deuda() - m) : ' '; q.style.color = 'var(--ink-soft)'; }
  const nombres = { EFECTIVO: 'efectivo', YAPPY: 'Yappy', TRANSFERENCIA: 'transferencia', TARJETA: 'tarjeta', CHEQUE: 'cheque' };
  boton.textContent = m > 0 ? 'Cobrar ' + dinero(m) + ' en ' + nombres[formaCobro] : 'Cobrar';
}

async function abrirCobro(id) {
  try {
    const d = await api('/m/api/cliente/' + id + '/pendientes');
    clienteCobro = id;
    facturasCobro = d.facturas || [];
    facturaElegida = null;
    document.getElementById('cb-nombre').textContent = d.cliente.nombre;
    document.getElementById('cb-saldo').textContent = dinero(deuda());
    document.getElementById('cb-monto').value = '';
    document.getElementById('cb-ref').value = '';
    pintarFacturas();
    elegirFormaCobro('EFECTIVO');
    abrirSheet('sheet-cobro');
  } catch (e) { toast(e.message, 'error'); }
}

async function confirmarCobro() {
  const monto = parseFloat(document.getElementById('cb-monto').value) || 0;
  if (monto <= 0) { toast('Escribe cuánto paga.', 'error'); return; }
  if (monto > tope() + 0.005) { toast('Es más de lo que debe (' + dinero(tope()) + ').', 'error'); return; }
  const boton = document.getElementById('btn-cobro');
  boton.disabled = true; boton.textContent = 'Registrando…';
  try {
    const r = await api('/m/api/cobro', { method: 'POST', body: {
      cliente_id: clienteCobro, monto: monto, forma_pago: formaCobro,
      referencia: formaCobro === 'EFECTIVO' ? '' : document.getElementById('cb-ref').value.trim(),
      venta_id: facturaElegida ? facturaElegida.venta_id : null,
    }});
    cerrarSheet('sheet-cobro');
    document.getElementById('rc-numero').textContent = r.numero;
    document.getElementById('rc-monto').textContent = dinero(r.monto);
    document.getElementById('rc-saldo').textContent = dinero(r.saldo);
    document.getElementById('rc-aplicado').textContent = r.aplicado.map(function (a) {
      return a.numero_factura + ': ' + dinero(a.monto) + (a.liquidada ? ' (pagada)' : ' (abono)');
    }).join(' · ');
    abrirSheet('sheet-recibo');
  } catch (e) { toast(e.message, 'error'); }
  finally { boton.disabled = false; resumenCobro(); }
}

// /m/cobrar?cliente=ID abre directamente el cobro de ese cliente (botón "Cobrar abono" de Clientes)
(function () {
  const id = parseInt(new URLSearchParams(location.search).get('cliente'), 10);
  if (id > 0) abrirCobro(id);
})();
</script>
<?php $this->endSection(); ?>

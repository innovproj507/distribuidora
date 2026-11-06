<?php
$this->extend('layouts/movil');
$total = array_sum(array_map(fn ($c) => (float) $c['saldo_pendiente'], $cuentas));
$cobradoHoy = array_sum(array_map(fn ($c) => (float) $c['total'], $cobrosHoy));
?>
<?php $this->section('content'); ?>

<div class="grid grid-cols-2 gap-3 mb-4">
  <div class="stat-card stat-amber">
    <p class="text-xs text-white/80">Por cobrar</p>
    <p class="font-mono text-xl font-semibold"><?= '$' . number_format($total, 2) ?></p>
    <p class="text-[11px] text-white/80 mt-1"><?= count($cuentas) ?> deudor<?= count($cuentas) === 1 ? '' : 'es' ?></p>
  </div>
  <div class="stat-card stat-teal">
    <p class="text-xs text-white/80">Cobrado hoy</p>
    <p class="font-mono text-xl font-semibold"><?= '$' . number_format($cobradoHoy, 2) ?></p>
    <p class="text-[11px] text-white/80 mt-1 truncate"><?= $cobrosHoy ? esc(implode(' · ', array_map(fn ($c) => ucfirst(strtolower($c['forma_pago'])) . ' $' . number_format((float) $c['total'], 0), $cobrosHoy))) : 'Sin cobros aún' ?></p>
  </div>
</div>

<div class="flex flex-col gap-2 pb-4">
  <?php if (empty($cuentas)): ?><p class="text-sm text-center mt-6" style="color:var(--ink-soft)">Sin saldos pendientes 🎉</p><?php endif; ?>
  <?php foreach ($cuentas as $c):
    $saldo = (float) $c['saldo_pendiente']; $limite = (float) $c['limite_credito'];
    $pct = $limite > 0 ? min(100, round($saldo / $limite * 100)) : 100;
    $tel = preg_replace('/[^0-9+]/', '', (string) $c['telefono']); ?>
    <div class="card !p-3">
      <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
          <p class="text-sm font-medium truncate"><?= esc($c['nombre']) ?></p>
          <p class="text-xs" style="color:var(--ink-soft)"><?= $c['ultima'] ? 'Último crédito ' . date('d/m/Y', strtotime($c['ultima'])) : 'Sin crédito reciente' ?></p>
        </div>
        <p class="font-mono text-sm font-semibold flex-shrink-0" style="color:var(--orange)"><?= '$' . number_format($saldo, 2) ?></p>
      </div>
      <?php if ($limite > 0): ?>
        <div class="stockbar mt-2"><div style="width:<?= $pct ?>%; background:<?= $pct >= 90 ? 'var(--red)' : 'var(--orange)' ?>"></div></div>
        <p class="text-[10px] mt-1" style="color:var(--ink-soft)">Usa <?= $pct ?>% de $<?= number_format($limite, 2) ?></p>
      <?php endif; ?>
      <div class="flex gap-2 mt-3">
        <?php if ($puedeCobrar): ?>
          <button type="button" onclick="abrirCobro(<?= (int) $c['cliente_id'] ?>)" class="flex-1 text-center text-xs py-2 rounded-lg font-semibold text-white" style="background:var(--teal)">Cobrar</button>
        <?php endif; ?>
        <?php if ($tel): ?>
          <a href="tel:<?= esc($tel) ?>" class="flex-1 text-center text-xs py-2 rounded-lg font-medium" style="background:var(--navy-light); color:var(--navy)">Llamar</a>
          <a href="https://wa.me/<?= esc(ltrim($tel, '+')) ?>?text=<?= rawurlencode('Hola ' . $c['nombre'] . ', le recordamos su saldo pendiente de $' . number_format($saldo, 2) . '.') ?>" class="flex-1 text-center text-xs py-2 rounded-lg font-medium" style="background:var(--green-light); color:var(--green)">WhatsApp</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Sheet: cobro -->
<div id="sheet-cobro" class="sheet" style="transform:translateY(110%); max-height:94vh">
  <div class="sheet-handle"></div>
  <div class="flex items-center justify-between mb-1">
    <h2 class="font-head text-lg font-semibold" id="cb-nombre">Cobro</h2>
    <button type="button" onclick="cerrarSheet('sheet-cobro')" class="w-8 h-8 rounded-full flex items-center justify-center" style="background:var(--navy-light); color:var(--navy)">✕</button>
  </div>
  <p class="text-xs mb-3" style="color:var(--ink-soft)">Debe <span class="font-mono font-semibold" id="cb-saldo"></span></p>

  <p class="field-label">Facturas pendientes (el abono se aplica a la más antigua primero)</p>
  <div id="cb-facturas" class="flex flex-col gap-1 mb-3" style="max-height:20vh; overflow-y:auto"></div>

  <label class="field-label">Monto a cobrar</label>
  <div class="flex gap-2 mb-3">
    <input type="number" inputmode="decimal" step="0.01" min="0" id="cb-monto" class="field-input" placeholder="0.00">
    <button type="button" onclick="document.getElementById('cb-monto').value = saldoActual.toFixed(2)" class="px-3 rounded-xl text-xs font-semibold flex-shrink-0" style="background:var(--navy-light); color:var(--navy)">Todo</button>
  </div>

  <label class="field-label">Forma de pago</label>
  <div class="grid grid-cols-4 gap-2 mb-3">
    <button type="button" class="chip-pago chip-cb activo" data-forma="EFECTIVO" onclick="elegirFormaCobro('EFECTIVO')">Efectivo</button>
    <button type="button" class="chip-pago chip-cb" data-forma="YAPPY" onclick="elegirFormaCobro('YAPPY')">Yappy</button>
    <button type="button" class="chip-pago chip-cb" data-forma="TRANSFERENCIA" onclick="elegirFormaCobro('TRANSFERENCIA')">Transf.</button>
    <button type="button" class="chip-pago chip-cb" data-forma="CHEQUE" onclick="elegirFormaCobro('CHEQUE')">Cheque</button>
  </div>

  <input id="cb-ref" class="field-input mb-4" style="font-family:'Inter'" placeholder="Referencia (n.º de transferencia, cheque… opcional)">
  <button type="button" id="btn-cobro" onclick="confirmarCobro()" class="btn-primario" style="background:var(--teal)">Registrar cobro</button>
</div>

<!-- Sheet: recibo -->
<div id="sheet-recibo" class="sheet text-center" style="transform:translateY(110%)">
  <div class="sheet-handle"></div>
  <div class="w-16 h-16 rounded-full mx-auto mb-3 flex items-center justify-center text-3xl" style="background:var(--green-light)">✓</div>
  <h2 class="font-head text-lg font-semibold">Cobro registrado</h2>
  <p class="text-sm mb-4" style="color:var(--ink-soft)">Recibo <span id="rc-numero" class="font-mono"></span></p>
  <div class="card text-left mb-4">
    <div class="flex justify-between text-sm mb-1"><span>Cobrado</span><span class="font-mono font-semibold" id="rc-monto"></span></div>
    <div class="flex justify-between text-sm mb-2"><span>Saldo restante</span><span class="font-mono font-semibold" id="rc-saldo"></span></div>
    <p class="text-xs" id="rc-aplicado" style="color:var(--ink-soft)"></p>
  </div>
  <button type="button" onclick="location.reload()" class="btn-primario">Listo</button>
</div>

<?php $this->endSection(); ?>

<?php $this->section('extra_js'); ?>
<script>
let clienteCobro = null, saldoActual = 0, formaCobro = 'EFECTIVO';

function elegirFormaCobro(f) {
  formaCobro = f;
  document.querySelectorAll('.chip-cb').forEach(function (b) { b.classList.toggle('activo', b.dataset.forma === f); });
}

async function abrirCobro(id) {
  try {
    const d = await api('/m/api/cliente/' + id + '/pendientes');
    clienteCobro = id;
    saldoActual = d.facturas.reduce(function (s, f) { return s + Number(f.pendiente); }, 0);
    document.getElementById('cb-nombre').textContent = d.cliente.nombre;
    document.getElementById('cb-saldo').textContent = dinero(saldoActual);
    document.getElementById('cb-facturas').innerHTML = d.facturas.map(function (f) {
      const parcial = Number(f.abonado) > 0;
      return '<div class="flex justify-between text-xs rounded-lg px-3 py-2" style="background:white; border:1px solid var(--line)">' +
        '<span>' + esc(f.numero_factura) + ' <span style="color:var(--ink-soft)">· ' + esc(f.fecha.substr(5, 5).split('-').reverse().join('/')) + (parcial ? ' · abonado ' + dinero(f.abonado) : '') + '</span></span>' +
        '<span class="font-mono font-semibold">' + dinero(f.pendiente) + '</span></div>';
    }).join('') || '<p class="text-xs" style="color:var(--ink-soft)">Sin facturas pendientes.</p>';
    document.getElementById('cb-monto').value = '';
    document.getElementById('cb-ref').value = '';
    elegirFormaCobro('EFECTIVO');
    abrirSheet('sheet-cobro');
  } catch (e) { toast(e.message, 'error'); }
}

async function confirmarCobro() {
  const monto = parseFloat(document.getElementById('cb-monto').value) || 0;
  if (monto <= 0) { toast('Escribe el monto a cobrar.', 'error'); return; }
  if (monto > saldoActual + 0.005) { toast('El monto es mayor que lo que debe (' + dinero(saldoActual) + ').', 'error'); return; }
  const boton = document.getElementById('btn-cobro');
  boton.disabled = true; boton.textContent = 'Registrando…';
  try {
    const r = await api('/m/api/cobro', { method: 'POST', body: {
      cliente_id: clienteCobro, monto: monto, forma_pago: formaCobro, referencia: document.getElementById('cb-ref').value.trim(),
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
  finally { boton.disabled = false; boton.textContent = 'Registrar cobro'; }
}
</script>
<?php $this->endSection(); ?>

<?php
$this->extend('layouts/movil');
$m = fn ($v) => '$' . number_format((float) $v, 2);
?>
<?php $this->section('content'); ?>

<?php if (!$resumen): ?>
  <?php if ($ultima): $d = (float) $ultima['diferencia_efectivo']; ?>
  <div class="card mb-4">
    <p class="text-xs" style="color:var(--ink-soft)">Último cierre · <?= date('d/m H:i', strtotime($ultima['cerrada_en'])) ?></p>
    <p class="text-sm mt-1">Contado <b><?= $m($ultima['efectivo_contado']) ?></b> de <?= $m($ultima['efectivo_esperado']) ?> esperados</p>
    <p class="font-semibold text-sm mt-1" style="color:<?= abs($d) < 0.005 ? 'var(--green)' : ($d > 0 ? 'var(--navy)' : 'var(--red)') ?>">
      <?= abs($d) < 0.005 ? 'Cuadró exacto ✓' : ($d > 0 ? 'Sobraron ' : 'Faltaron ') . $m(abs($d)) ?>
    </p>
  </div>
  <?php endif; ?>

  <form method="POST" action="/caja/abrir" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="volver" value="/m/caja">
    <p class="font-head text-base font-semibold mb-1">Abrir caja</p>
    <p class="text-xs mb-4" style="color:var(--ink-soft)">Escribe el efectivo con el que empiezas (fondo de cambio).</p>
    <label class="field-label">Efectivo inicial</label>
    <input class="field-input mb-3" type="number" inputmode="decimal" step="0.01" min="0" name="monto_inicial" value="0.00" required>
    <label class="field-label">Notas (opcional)</label>
    <input class="field-input mb-4" style="font-family:'Inter'" name="notas" maxlength="300">
    <button class="btn-primario">Abrir caja</button>
  </form>

<?php else: $r = $resumen; $c = $r['caja']; ?>
  <div class="stat-card stat-navy mb-4">
    <p class="text-xs text-white/80">Efectivo en caja (esperado)</p>
    <p class="font-head text-3xl font-semibold"><?= $m($r['esperado']['EFECTIVO']) ?></p>
    <p class="text-xs text-white/80 mt-1">Caja #<?= (int) $c['caja_id'] ?> · desde <?= date('d/m H:i', strtotime($c['abierta_en'])) ?> · fondo <?= $m($c['monto_inicial']) ?></p>
  </div>

  <div class="card mb-4">
    <div class="flex justify-between text-sm mb-2"><span>Ventas (<?= (int) $r['ventas']['cantidad'] ?>)</span><b><?= $m($r['ventas']['total']) ?></b></div>
    <?php foreach ($formas as $f): if (($r['esperado'][$f] ?? 0) == 0 && $f !== 'EFECTIVO') continue; ?>
      <div class="flex justify-between text-xs py-0.5" style="color:var(--ink-soft)"><span><?= esc(ucfirst(strtolower($f))) ?></span><span class="font-mono"><?= $m($f === 'EFECTIVO' ? ($r['por_forma_ventas']['EFECTIVO'] ?? 0) + ($r['cobros']['EFECTIVO']['monto'] ?? 0) : $r['esperado'][$f]) ?></span></div>
    <?php endforeach; ?>
    <?php if ($r['credito'] > 0): ?><div class="flex justify-between text-xs py-0.5" style="color:var(--ink-soft)"><span>A crédito (no es dinero)</span><span class="font-mono"><?= $m($r['credito']) ?></span></div><?php endif; ?>
    <?php if ($r['entradas'] || $r['salidas']): ?><div class="flex justify-between text-xs py-0.5" style="color:var(--ink-soft)"><span>Entradas / salidas</span><span class="font-mono">+<?= $m($r['entradas']) ?> / −<?= $m($r['salidas']) ?></span></div><?php endif; ?>
  </div>

  <form method="POST" action="/caja/movimiento" class="card mb-4">
    <?= csrf_field() ?>
    <input type="hidden" name="volver" value="/m/caja">
    <p class="font-head text-sm font-semibold mb-3">Entrada / salida de efectivo</p>
    <div class="grid grid-cols-2 gap-2 mb-3">
      <label class="chip-pago text-center"><input type="radio" name="tipo" value="entrada" class="mr-1">+ Entrada</label>
      <label class="chip-pago text-center"><input type="radio" name="tipo" value="salida" class="mr-1" checked>− Salida</label>
    </div>
    <input class="field-input mb-2" type="number" inputmode="decimal" step="0.01" min="0.01" name="monto" placeholder="Monto" required>
    <input class="field-input mb-3" style="font-family:'Inter'" name="motivo" maxlength="200" placeholder="Motivo (retiro, pago de flete…)" required>
    <button class="w-full py-2.5 rounded-xl font-medium" style="background:var(--navy-light); color:var(--navy)">Registrar</button>
  </form>

  <form method="POST" action="/caja/cerrar" class="card mb-6" onsubmit="return confirm('¿Cerrar la caja?')">
    <?= csrf_field() ?>
    <input type="hidden" name="volver" value="/m/caja">
    <input type="hidden" name="caja_id" value="<?= (int) $c['caja_id'] ?>">
    <p class="font-head text-sm font-semibold mb-1">Cerrar caja</p>
    <p class="text-xs mb-3" style="color:var(--ink-soft)">Cuenta el efectivo y escribe cuánto hay.</p>
    <label class="field-label">Efectivo contado</label>
    <input class="field-input mb-3" type="number" inputmode="decimal" step="0.01" min="0" name="declarado[EFECTIVO]" placeholder="Cuenta el efectivo" required>
    <?php foreach ($formas as $f): if ($f === 'EFECTIVO' || ($r['esperado'][$f] ?? 0) == 0) continue; ?>
      <label class="field-label"><?= esc(ucfirst(strtolower($f))) ?> (sistema: <?= $m($r['esperado'][$f]) ?>)</label>
      <input class="field-input mb-3" type="number" inputmode="decimal" step="0.01" min="0" name="declarado[<?= $f ?>]" value="<?= number_format($r['esperado'][$f], 2, '.', '') ?>">
    <?php endforeach; ?>
    <label class="field-label">Notas</label>
    <input class="field-input mb-4" style="font-family:'Inter'" name="notas" maxlength="500">
    <button class="w-full py-3 rounded-xl text-white font-medium" style="background:var(--ink)">Cerrar caja</button>
  </form>
<?php endif; ?>

<?php $this->endSection(); ?>

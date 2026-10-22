<?php
use App\Core\Auth;
use App\Core\View;

View::layout('movil');

$totalSalud = max(1, array_sum($salud));
$gradosOk   = (int) round(360 * $salud['ok'] / $totalSalud);
$gradosBajo = (int) round(360 * $salud['bajo'] / $totalSalud);
$anillo = sprintf(
    'conic-gradient(var(--green) 0deg %1$ddeg, var(--orange) %1$ddeg %2$ddeg, var(--red) %2$ddeg 360deg)',
    $gradosOk,
    $gradosOk + $gradosBajo
);
$porReabastecer = $salud['bajo'] + $salud['agotado'];

$estadoVenta = [
    'pagada'    => ['Pagada',    'var(--green-light)',  'var(--green)'],
    'pendiente' => ['Crédito',   'var(--orange-light)', 'var(--orange)'],
    'anulada'   => ['Anulada',   'var(--red-light)',    'var(--red)'],
];
?>
<?php View::section('content'); ?>

<?php if ($porReabastecer > 0 && (Auth::can('productos.ver') || Auth::can('inventario.ver'))): ?>
<a href="/m/inventario?stock_bajo=1" class="card block mb-4">
  <div class="flex items-center justify-between gap-3">
    <div>
      <p class="font-head text-base font-semibold leading-snug">
        <?= $porReabastecer ?> producto<?= $porReabastecer === 1 ? '' : 's' ?><br>necesita<?= $porReabastecer === 1 ? '' : 'n' ?> reabasto
      </p>
      <p class="text-xs mt-2 font-medium" style="color:var(--navy)">Revisar stock bajo →</p>
    </div>
    <div class="w-16 h-16 rounded-2xl flex items-center justify-center text-3xl flex-shrink-0" style="background:var(--orange-light)">📦</div>
  </div>
</a>
<?php endif; ?>

<?php if (Auth::can('pos.usar')): ?>
<a href="/m/vender" class="stat-card stat-teal block mb-4">
  <div class="flex items-center justify-between">
    <div>
      <p class="font-head text-lg font-semibold">Nueva venta</p>
      <p class="text-xs text-white/80 mt-1">Buscar producto y cobrar</p>
    </div>
    <div class="w-12 h-12 rounded-full bg-white/15 flex items-center justify-center text-2xl">🛒</div>
  </div>
</a>
<?php endif; ?>

<div class="grid grid-cols-2 gap-3 mb-4">
  <div class="stat-card stat-navy">
    <div class="flex items-center justify-between mb-4">
      <span class="text-xs text-white/80">Ventas de hoy</span>
      <span class="text-lg">💵</span>
    </div>
    <p class="font-mono text-xl font-semibold"><?= '$' . number_format((float) $ventasHoy['total'], 2) ?></p>
    <p class="text-[11px] text-white/70 mt-1"><?= (int) $ventasHoy['cantidad'] ?> venta<?= (int) $ventasHoy['cantidad'] === 1 ? '' : 's' ?></p>
  </div>
  <div class="stat-card stat-amber">
    <div class="flex items-center justify-between mb-4">
      <span class="text-xs text-white/80">Ventas del mes</span>
      <span class="text-lg">📈</span>
    </div>
    <p class="font-mono text-xl font-semibold"><?= '$' . number_format($ventasMes, 2) ?></p>
    <p class="text-[11px] text-white/70 mt-1"><?= ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'][(int) date('n') - 1] ?></p>
  </div>
</div>

<div class="card flex items-center gap-4 mb-6">
  <div class="ring" style="background:<?= $anillo ?>">
    <div class="ring-inner">
      <span class="font-mono text-sm font-semibold"><?= array_sum($salud) ?></span>
      <span class="text-[9px]" style="color:var(--ink-soft)">productos</span>
    </div>
  </div>
  <div class="flex-1 flex flex-col gap-1.5 text-xs">
    <div class="flex items-center justify-between">
      <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full" style="background:var(--green)"></span>Stock ok</span>
      <span class="font-mono font-medium"><?= $salud['ok'] ?></span>
    </div>
    <div class="flex items-center justify-between">
      <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full" style="background:var(--orange)"></span>Stock bajo</span>
      <span class="font-mono font-medium"><?= $salud['bajo'] ?></span>
    </div>
    <div class="flex items-center justify-between">
      <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full" style="background:var(--red)"></span>Agotado</span>
      <span class="font-mono font-medium"><?= $salud['agotado'] ?></span>
    </div>
    <?php if (Auth::can('productos.editar')): ?>
      <div class="flex items-center justify-between pt-1.5 mt-0.5 border-t" style="border-color:var(--line)">
        <span style="color:var(--ink-soft)">Valor en inventario</span>
        <span class="font-mono font-medium"><?= '$' . number_format($valorInventario, 2) ?></span>
      </div>
    <?php endif; ?>
  </div>
</div>

<h2 class="font-head text-sm font-semibold mb-3">Accesos rápidos</h2>
<div class="grid grid-cols-2 gap-3 mb-6">
  <?php if (Auth::can('productos.ver') || Auth::can('inventario.ver')): ?>
    <a href="/m/inventario" class="card text-left block">
      <div class="icon-tile mb-3" style="background:var(--green-light)"><span class="text-xl">🧰</span></div>
      <p class="font-semibold text-sm">Productos</p>
      <p class="text-xs mt-1" style="color:var(--ink-soft)">Stock y precios</p>
    </a>
  <?php endif; ?>
  <?php if (Auth::can('compras.ver')): ?>
    <a href="/m/compras" class="card text-left block">
      <div class="icon-tile mb-3" style="background:var(--teal-light)"><span class="text-xl">📥</span></div>
      <p class="font-semibold text-sm">Compras</p>
      <p class="text-xs mt-1" style="color:var(--ink-soft)">Factura de proveedor</p>
    </a>
  <?php endif; ?>
  <?php if (Auth::can('clientes.ver')): ?>
    <a href="/m/clientes" class="card text-left block">
      <div class="icon-tile mb-3" style="background:var(--navy-light)"><span class="text-xl">👤</span></div>
      <p class="font-semibold text-sm">Clientes</p>
      <p class="text-xs mt-1" style="color:var(--ink-soft)">Contactos y crédito</p>
    </a>
  <?php endif; ?>
  <?php if (Auth::can('ventas.ver')): ?>
    <a href="/ventas" class="card text-left block">
      <div class="icon-tile mb-3" style="background:var(--purple-light)"><span class="text-xl">🧾</span></div>
      <p class="font-semibold text-sm">Historial</p>
      <p class="text-xs mt-1" style="color:var(--ink-soft)">Ventas (versión web)</p>
    </a>
  <?php endif; ?>
</div>

<h2 class="font-head text-sm font-semibold mb-2">Últimas ventas</h2>
<div class="flex flex-col gap-2 mb-4">
  <?php if (empty($ultimasVentas)): ?>
    <p class="text-sm" style="color:var(--ink-soft)">Todavía no hay ventas registradas.</p>
  <?php endif; ?>
  <?php foreach ($ultimasVentas as $v): $ev = $estadoVenta[$v['estado']] ?? [ucfirst((string) $v['estado']), 'var(--line)', 'var(--ink-soft)']; ?>
    <a href="/ventas/venta/<?= (int) $v['venta_id'] ?>" class="card !p-3 flex items-center gap-3">
      <div class="badge-icon font-semibold" style="background:<?= $ev[1] ?>; color:<?= $ev[2] ?>">$</div>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-medium truncate"><?= View::e($v['cliente_nombre'] ?: 'Consumidor final') ?></p>
        <p class="text-xs" style="color:var(--ink-soft)"><?= View::e($v['numero_factura']) ?> · <?= date('d/m H:i', strtotime($v['fecha'])) ?> · <?= View::e($ev[0]) ?></p>
      </div>
      <p class="font-mono text-sm font-semibold flex-shrink-0"><?= '$' . number_format((float) $v['total'], 2) ?></p>
    </a>
  <?php endforeach; ?>
</div>

<?php View::endSection('content'); ?>

<?php
use App\Libraries\Auth;
$this->extend('layouts/movil');

$etiquetaRol = [
    'superadmin' => 'Administrador',
    'gerente'    => 'Gerente',
    'supervisor' => 'Supervisor',
    'cajero'     => 'Cajero',
    'vendedor'   => 'Vendedor',
    'encargado'  => 'Encargado de tienda',
    'auditor'    => 'Auditor',
][$rol] ?? ucfirst($rol);

$enlace = function (string $href, string $emoji, string $bg, string $titulo, string $detalle): void { ?>
  <a href="<?= esc($href) ?>" class="card text-left flex items-center gap-3 mb-3">
    <div class="icon-tile flex-shrink-0" style="background:<?= $bg ?>"><span class="text-xl"><?= $emoji ?></span></div>
    <div>
      <p class="font-semibold text-sm"><?= esc($titulo) ?></p>
      <p class="text-xs mt-1" style="color:var(--ink-soft)"><?= esc($detalle) ?></p>
    </div>
  </a>
<?php };
?>
<?php $this->section('content'); ?>

<div class="card mb-4">
  <p class="text-sm font-medium"><?= esc($auth['name'] ?? '') ?></p>
  <p class="text-xs mt-1" style="color:var(--ink-soft)"><?= esc($etiquetaRol) ?></p>
  <?php if (!empty($sucursal_actual['nombre'])): ?>
    <p class="text-xs mt-2" style="color:var(--ink-soft)">
      🏪 <?= esc($sucursal_actual['nombre']) ?><?= !empty($deposito_actual['nombre']) ? ' · 📦 ' . esc($deposito_actual['nombre']) : '' ?>
    </p>
  <?php endif; ?>
</div>

<?php $sucursalesSel = $sucursales_disponibles ?? []; $depositosSel = $depositos_disponibles ?? []; ?>
<?php if (count($sucursalesSel) > 1 || count($depositosSel) > 1): ?>
<div class="card mb-4">
  <?php if (count($sucursalesSel) > 1): ?>
  <form method="POST" action="/sucursales/cambiar" class="mb-3">
    <?= csrf_field() ?>
    <label class="text-xs font-semibold" style="color:var(--ink-soft)">Sucursal</label>
    <select name="sucursal_id" onchange="this.form.submit()" class="w-full mt-1 py-2 px-3 rounded-xl border text-sm bg-white">
      <?php foreach ($sucursalesSel as $suc): ?>
      <option value="<?= (int) $suc['sucursal_id'] ?>" <?= (int) $suc['sucursal_id'] === (int) ($sucursal_actual['sucursal_id'] ?? 0) ? 'selected' : '' ?>><?= esc($suc['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php endif; ?>
  <?php if (count($depositosSel) > 1): ?>
  <form method="POST" action="/sucursales/cambiar-deposito">
    <?= csrf_field() ?>
    <label class="text-xs font-semibold" style="color:var(--ink-soft)">Depósito</label>
    <select name="deposito_id" onchange="this.form.submit()" class="w-full mt-1 py-2 px-3 rounded-xl border text-sm bg-white">
      <?php foreach ($depositosSel as $dep): ?>
      <option value="<?= (int) $dep['deposito_id'] ?>" <?= (int) $dep['deposito_id'] === (int) ($deposito_actual['deposito_id'] ?? 0) ? 'selected' : '' ?>><?= esc($dep['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php endif; ?>
  <?php $mapaSuc = !empty($sucursal_actual) ? \App\Libraries\SucursalService::enlaceMapa($sucursal_actual) : null; ?>
  <?php if ($mapaSuc): ?>
  <a href="<?= esc($mapaSuc, 'attr') ?>" target="_blank" rel="noopener noreferrer" class="text-xs mt-3 inline-block" style="color:var(--navy)">📍 Ver sucursal en el mapa</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<button type="button" id="btn-instalar" onclick="instalarApp()" class="stat-card stat-navy w-full text-left mb-4" style="display:none">
  <div class="flex items-center justify-between">
    <div>
      <p class="font-head text-base font-semibold">Instalar la app</p>
      <p class="text-xs text-white/80 mt-1">Ícono en la pantalla de inicio, sin barra del navegador</p>
    </div>
    <span class="text-2xl">📲</span>
  </div>
</button>

<?php if (Auth::can('pos.usar') || Auth::can('ventas.ver')): ?>
<div class="card mb-4">
  <div class="flex items-center gap-3 mb-3">
    <div class="icon-tile flex-shrink-0" style="background:var(--navy-light)"><span class="text-xl">🖨️</span></div>
    <div class="min-w-0">
      <p class="font-semibold text-sm">Impresora de tickets</p>
      <p class="text-xs mt-0.5 truncate" id="imp-estado" style="color:var(--ink-soft)">Térmica 58 mm (Xprinter)</p>
    </div>
  </div>
  <label class="text-xs font-semibold" style="color:var(--ink-soft)">Cómo imprime este celular</label>
  <select id="imp-modo" onchange="cambiarModoImpresora(this.value)" class="w-full mt-1 mb-2 py-2 px-3 rounded-xl border text-sm bg-white">
    <option value="app">App Impresora Distribuidora (recomendado)</option>
    <option value="bluetooth">Bluetooth directo (Chrome)</option>
    <option value="rawbt">App RawBT</option>
    <option value="navegador">Navegador / USB (laptop)</option>
  </select>
  <p class="text-[11px] mb-3" id="imp-ayuda" style="color:var(--ink-soft)"></p>
  <a href="/m/impresora" id="imp-descarga" class="block text-center text-xs font-semibold mb-3" style="color:var(--navy)">⬇️ Descargar / instalar la app de impresión</a>
  <div class="flex gap-2">
    <button type="button" id="imp-conectar" onclick="conectarImpresora()" class="flex-1 py-2.5 rounded-xl text-sm font-medium" style="background:var(--navy-light); color:var(--navy)">🔗 Conectar</button>
    <button type="button" onclick="TicketPOS.prueba()" class="flex-1 py-2.5 rounded-xl text-sm font-medium text-white" style="background:var(--navy)">Imprimir prueba</button>
  </div>
</div>
<?php endif; ?>

<?php if (Auth::can('productos.editar')) $enlace('/m/codigos', '🏷️', 'var(--amber-light)', 'Códigos de barras', 'Escanear el código de cada producto, referencia del proveedor y etiquetas'); ?>
<?php if (Auth::can('compras.crear')) $enlace('/m/pedido', '🛒', 'var(--green-light)', 'Pedido a la ciudad', 'Qué comprar en total y cuánto va a cada tienda'); ?>
<?php if (Auth::can('conteos.ver')) $enlace('/m/conteos', '📋', 'var(--purple-light)', 'Conteo físico', 'Contar estantes, ubicar productos y ajustar faltantes'); ?>
<?php if (Auth::can('traslados.ver')) $enlace('/m/traslados', '🚤', 'var(--navy-light)', 'Traslados entre tiendas', 'Enviar mercancía y recibirla con conteo'); ?>
<?php if (Auth::can('caja.usar')) $enlace('/m/caja', '🧮', 'var(--green-light)', 'Mi caja', !empty($caja_actual) ? 'Abierta · entradas, salidas y cierre' : 'Abrir caja con el fondo inicial'); ?>
<?php if (Auth::can('compras.ver')) $enlace('/m/compras', '📥', 'var(--teal-light)', 'Compras', 'Registrar facturas de proveedor'); ?>
<?php if (Auth::can('ventas.ver')) $enlace('/m/ventas', '🧾', 'var(--purple-light)', 'Historial de ventas', 'Consulta facturas y su detalle'); ?>
<?php if (Auth::can('cotizaciones.ver')) $enlace('/m/cotizaciones', '📝', 'var(--amber-light)', 'Cotizaciones', 'Estado y enlace para compartir'); ?>
<?php if (Auth::can('clientes.ver')) $enlace('/m/cobrar', '💳', 'var(--orange-light)', 'Cuentas por cobrar', 'Saldos de clientes, llamar y WhatsApp'); ?>
<?php if (Auth::can('alertas.ver')) $enlace('/m/alertas', '🔔', 'var(--red-light)', 'Alertas de inventario', 'Stock mínimo, vencimientos y más'); ?>
<?php if (!in_array(Auth::rol(), \App\Filters\SoloMovilFilter::ROLES, true)): ?>
<?php if (Auth::can('reportes.ver')) $enlace('/reportes', '📊', 'var(--green-light)', 'Reportes', 'Versión web'); ?>
<?php $enlace('/', '🖥️', 'var(--navy-light)', 'Versión web completa', 'Todo el sistema, optimizado para computadora'); ?>
<?php endif; ?>

<form method="POST" action="/m/logout">
  <?= csrf_field() ?>
  <button type="submit" class="w-full py-3 rounded-xl text-white font-medium block text-center mt-6 mb-4" style="background:var(--red)">Cerrar sesión</button>
</form>

<?php $this->endSection(); ?>

<?php $this->section('extra_js'); ?>
<script src="/assets/js/ticket.js?v=7"></script>
<script>
// ── Impresora de tickets (la elección queda guardada en este celular) ──
const AYUDA_IMPRESORA = {
  app: 'Nuestra app: usa la impresora emparejada en el Bluetooth del teléfono. Instálala una vez y elige ahí la impresora.',
  bluetooth: 'Enciende la impresora, toca Conectar y elige "XP-P503A" (o "Printer001"). No hace falta emparejarla antes en el Bluetooth del teléfono.',
  rawbt: 'Instala la app RawBT (Play Store), emparéjala con la impresora y elige 58 mm. El ticket se manda a esa app.',
  navegador: 'Abre el ticket con el diálogo de imprimir. Para la laptop con la impresora conectada por USB.',
};
function pintarImpresora() {
  const m = TicketPOS.modo();
  const sel = document.getElementById('imp-modo');
  if (!sel) return;
  sel.value = m;
  document.getElementById('imp-ayuda').textContent = AYUDA_IMPRESORA[m];
  document.getElementById('imp-conectar').style.display = m === 'bluetooth' ? '' : 'none';
  document.getElementById('imp-descarga').style.display = m === 'app' ? '' : 'none';
  const nombre = TicketPOS.nombreImpresora();
  document.getElementById('imp-estado').textContent = m === 'bluetooth'
    ? (TicketPOS.tieneBluetooth ? (nombre ? 'Última impresora: ' + nombre : 'Sin conectar todavía') : 'Este navegador no tiene Bluetooth: usa la app')
    : ({ app: 'Imprime por la app Impresora Distribuidora', rawbt: 'Imprime por la app RawBT' }[m] || 'Imprime desde el navegador');
}
function cambiarModoImpresora(m) { TicketPOS.fijarModo(m); pintarImpresora(); }
async function conectarImpresora() {
  try {
    const d = await TicketPOS.conectar();
    toast('Conectada: ' + (d.name || 'impresora'));
    pintarImpresora();
  } catch (e) {
    if (e && e.name === 'NotFoundError' && /cancel/i.test(e.message || '')) return;
    alert('No se pudo conectar: ' + (e.message || e) +
      '\n\nSi la impresora está emparejada en el Bluetooth del teléfono y no aparece en la lista, elige el modo "App Impresora Distribuidora".');
  }
}
pintarImpresora();

let eventoInstalacion = null;

window.addEventListener('beforeinstallprompt', function (e) {
  e.preventDefault();
  eventoInstalacion = e;
  document.getElementById('btn-instalar').style.display = 'block';
});

async function instalarApp() {
  if (!eventoInstalacion) return;
  eventoInstalacion.prompt();
  await eventoInstalacion.userChoice;
  eventoInstalacion = null;
  document.getElementById('btn-instalar').style.display = 'none';
}
</script>
<?php $this->endSection(); ?>

<?php
use App\Core\Auth;
use App\Core\View;

View::layout('movil');

$etiquetaRol = [
    'superadmin' => 'Administrador',
    'gerente'    => 'Gerente',
    'supervisor' => 'Supervisor',
    'cajero'     => 'Cajero',
    'auditor'    => 'Auditor',
][$rol] ?? ucfirst($rol);

$enlace = function (string $href, string $emoji, string $bg, string $titulo, string $detalle): void { ?>
  <a href="<?= View::e($href) ?>" class="card text-left flex items-center gap-3 mb-3">
    <div class="icon-tile flex-shrink-0" style="background:<?= $bg ?>"><span class="text-xl"><?= $emoji ?></span></div>
    <div>
      <p class="font-semibold text-sm"><?= View::e($titulo) ?></p>
      <p class="text-xs mt-1" style="color:var(--ink-soft)"><?= View::e($detalle) ?></p>
    </div>
  </a>
<?php };
?>
<?php View::section('content'); ?>

<div class="card mb-4">
  <p class="text-sm font-medium"><?= View::e($auth['name'] ?? '') ?></p>
  <p class="text-xs mt-1" style="color:var(--ink-soft)"><?= View::e($etiquetaRol) ?></p>
  <?php if (!empty($sucursal_actual['nombre'])): ?>
    <p class="text-xs mt-2" style="color:var(--ink-soft)">
      🏪 <?= View::e($sucursal_actual['nombre']) ?><?= !empty($deposito_actual['nombre']) ? ' · 📦 ' . View::e($deposito_actual['nombre']) : '' ?>
    </p>
  <?php endif; ?>
</div>

<button type="button" id="btn-instalar" onclick="instalarApp()" class="stat-card stat-navy w-full text-left mb-4" style="display:none">
  <div class="flex items-center justify-between">
    <div>
      <p class="font-head text-base font-semibold">Instalar la app</p>
      <p class="text-xs text-white/80 mt-1">Ícono en la pantalla de inicio, sin barra del navegador</p>
    </div>
    <span class="text-2xl">📲</span>
  </div>
</button>

<?php if (Auth::can('compras.ver')) $enlace('/m/compras', '📥', 'var(--teal-light)', 'Compras', 'Registrar facturas de proveedor'); ?>
<?php if (Auth::can('ventas.ver')) $enlace('/ventas', '🧾', 'var(--purple-light)', 'Historial de ventas', 'Anular, reimprimir (versión web)'); ?>
<?php if (Auth::can('clientes.ver')) $enlace('/clientes/cuentas-por-cobrar', '💳', 'var(--orange-light)', 'Cuentas por cobrar', 'Abonos de clientes (versión web)'); ?>
<?php if (Auth::can('reportes.ver')) $enlace('/reportes', '📊', 'var(--green-light)', 'Reportes', 'Versión web'); ?>
<?php $enlace('/', '🖥️', 'var(--navy-light)', 'Versión web completa', 'Todo el sistema, optimizado para computadora'); ?>

<a href="/m/logout" class="w-full py-3 rounded-xl text-white font-medium block text-center mt-6 mb-4" style="background:var(--red)">Cerrar sesión</a>

<?php View::endSection('content'); ?>

<?php View::section('extra_js'); ?>
<script>
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
<?php View::endSection('extra_js'); ?>

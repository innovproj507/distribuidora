<?php
/**
 * Layout de la PWA móvil (/m) — diseño tomado de la app arango
 * (header con degradado, tarjetas, barra inferior y botón central de vender).
 *
 * Variables: $page_title, $headerTitle, $activeTab ('inicio'|'inventario'|'vender'|'clientes'|'mas')
 */
use App\Core\Auth;
use App\Core\View;

$headerTitle = $headerTitle ?? ($page_title ?? 'Distribuidora');
$activeTab   = $activeTab ?? '';
$navClase    = fn (string $tab) => $tab === $activeTab ? 'navbtn active' : 'navbtn';
$inicial     = mb_strtoupper(mb_substr($auth['name'] ?? 'U', 0, 1, 'UTF-8'), 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#0F2E6B">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="csrf-token" content="<?= View::e($csrf_token) ?>">
<title><?= View::e($page_title ?? 'Distribuidora') ?></title>

<link rel="manifest" href="/m/manifest.webmanifest">
<link rel="icon" href="/movil/icons/icon-192.png">
<link rel="apple-touch-icon" href="/movil/icons/icon-192.png">

<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=IBM+Plex+Mono:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/movil/app.css?v=1">
</head>
<body class="pb-24">

<header class="header-grad pt-8 pb-6 px-5 rounded-b-[28px] safe-top">
  <div class="flex items-center justify-between">
    <div class="min-w-0">
      <h1 class="font-head text-white text-lg font-semibold truncate"><?= View::e($headerTitle) ?></h1>
      <?php if (!empty($deposito_actual['nombre'])): ?>
        <p class="text-xs text-white/70 truncate"><?= View::e($deposito_actual['nombre']) ?></p>
      <?php endif; ?>
    </div>
    <a href="/m/mas" class="w-10 h-10 rounded-full bg-white/15 flex items-center justify-center text-white font-head font-semibold flex-shrink-0"><?= View::e($inicial) ?></a>
  </div>
</header>

<main class="px-5 -mt-4">
  <?php foreach (['danger' => 'toast-error', 'warning' => 'toast-error', 'success' => 'toast-ok', 'info' => 'toast-ok'] as $tipo => $clase): ?>
    <?php foreach ($flash[$tipo] ?? [] as $mensaje): ?>
      <div class="rounded-xl p-3 mb-3 text-sm" style="<?= $clase === 'toast-error' ? 'background:var(--red-light); color:var(--red)' : 'background:var(--green-light); color:var(--green)' ?>"><?= View::e($mensaje) ?></div>
    <?php endforeach; ?>
  <?php endforeach; ?>

  <?= View::yield('content') ?>
</main>

<div class="fixed bottom-0 left-0 right-0 bg-white border-t flex items-center justify-around pt-2.5 pb-3 z-30" style="border-color:var(--line); height:78px; padding-bottom:max(12px, env(safe-area-inset-bottom))">
  <?php if (Auth::can('dashboard.ver')): ?>
    <a href="/m/" class="<?= $navClase('inicio') ?>">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9M5 10v10h14V10"/></svg>
      <span class="text-[10px]">Inicio</span>
      <span class="navline"></span>
    </a>
  <?php endif; ?>
  <?php if (Auth::can('productos.ver') || Auth::can('inventario.ver')): ?>
    <a href="/m/inventario" class="<?= $navClase('inventario') ?>">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7l9-4 9 4-9 4-9-4zM3 7v10l9 4 9-4V7"/></svg>
      <span class="text-[10px]">Inventario</span>
      <span class="navline"></span>
    </a>
  <?php endif; ?>
  <div class="w-14"></div>
  <?php if (Auth::can('clientes.ver')): ?>
    <a href="/m/clientes" class="<?= $navClase('clientes') ?>">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>
      <span class="text-[10px]">Clientes</span>
      <span class="navline"></span>
    </a>
  <?php endif; ?>
  <a href="/m/mas" class="<?= $navClase('mas') ?>">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/></svg>
    <span class="text-[10px]">Más</span>
    <span class="navline"></span>
  </a>
</div>

<?php if (Auth::can('pos.usar')): ?>
  <a href="/m/vender" class="fab-center" title="Vender">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.5 3h2l2.4 12.4a2 2 0 0 0 2 1.6h8.2a2 2 0 0 0 2-1.6L21 8H6"/></svg>
  </a>
<?php endif; ?>

<div id="sheet-backdrop" class="sheet-backdrop" onclick="cerrarSheets()"></div>

<script src="/movil/app.js?v=1"></script>
<?= View::yield('extra_js') ?>
</body>
</html>

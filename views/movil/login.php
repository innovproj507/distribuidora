<?php
use App\Core\View;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#0F2E6B">
<title><?= View::e($page_title ?? 'Iniciar sesión') ?></title>

<link rel="manifest" href="/m/manifest.webmanifest">
<link rel="icon" href="/movil/icons/icon-192.png">
<link rel="apple-touch-icon" href="/movil/icons/icon-192.png">

<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=IBM+Plex+Mono:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/movil/app.css?v=1">
</head>
<body>

<div class="min-h-screen flex flex-col items-center justify-center px-6">
  <div class="w-full max-w-xs">
    <div class="text-center mb-8">
      <img src="/movil/icons/icon-192.png" alt="" class="w-16 h-16 rounded-2xl mx-auto mb-3">
      <h1 class="font-head text-2xl font-semibold" style="color:var(--navy)"><?= View::e($empresa_actual['nombre_comercial'] ?? 'Distribuidora') ?></h1>
      <p class="text-sm mt-1" style="color:var(--ink-soft)">Inicia sesión para continuar</p>
    </div>

    <?php foreach ($flash['danger'] ?? [] as $mensaje): ?>
      <div class="rounded-xl p-3 mb-4 text-sm" style="background:var(--red-light); color:var(--red)"><?= View::e($mensaje) ?></div>
    <?php endforeach; ?>

    <!-- Usa el mismo login del sistema (límite de intentos y 2FA incluidos); _origen=movil vuelve a /m -->
    <form method="post" action="/login" class="card">
      <?= View::csrf() ?>
      <input type="hidden" name="_origen" value="movil">

      <label class="field-label">Usuario</label>
      <input class="field-input mb-3" style="font-family:'Inter'" type="text" name="username" required autofocus autocapitalize="none" autocomplete="username">

      <label class="field-label">Contraseña</label>
      <input class="field-input mb-5" style="font-family:'Inter'" type="password" name="password" required autocomplete="current-password">

      <button type="submit" class="btn-primario">Entrar</button>
    </form>

    <p class="text-xs text-center mt-6"><a href="/login" style="color:var(--ink-soft)">Ir a la versión web</a></p>
  </div>
</div>

<script>
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/m/sw.js', { scope: '/m/' }).catch(console.error);
}
</script>
</body>
</html>

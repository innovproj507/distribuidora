<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Estado de cuenta</title>
<style>
  :root { color-scheme: light; }
  body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f3f4f6; font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: #1f2937; padding: 16px; box-sizing: border-box; }
  .caja { background: #fff; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 28px 22px; max-width: 380px; text-align: center; }
  .icono { font-size: 40px; margin-bottom: 8px; }
  p { color: #6b7280; font-size: 15px; line-height: 1.5; margin: 0; }
</style>
</head>
<body>
  <div class="caja">
    <div class="icono">🔒</div>
    <p><?= esc($mensaje ?? 'Enlace no válido.') ?></p>
  </div>
</body>
</html>

<?php
/** Etiquetas de producto de 58 mm para imprimir desde el navegador (laptop + Xprinter por USB). */
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Etiquetas</title>
<style>
  @page { size: 58mm auto; margin: 0; }
  :root { color-scheme: light; }
  * { box-sizing: border-box; }
  body { margin: 0; background: #e5e7eb; font-family: Arial, Helvetica, sans-serif; color: #000; }
  .barra { max-width: 58mm; margin: 12px auto 0; display: flex; gap: 8px; }
  .barra button { flex: 1; padding: 10px; border: 0; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
  .imp { background: #0F2E6B; color: #fff; }
  .cer { background: #fff; color: #333; }
  .nota { max-width: 58mm; margin: 8px auto; font-size: 11px; color: #555; text-align: center; }
  .etq { width: 58mm; margin: 8px auto; background: #fff; padding: 2mm 2mm 3mm; text-align: center; box-shadow: 0 2px 6px rgba(0,0,0,.15); page-break-after: always; break-after: page; }
  .nom { font-size: 3.4mm; font-weight: 700; line-height: 1.15; max-height: 3em; overflow: hidden; }
  .pre { font-size: 7mm; font-weight: 800; margin: 1mm 0; }
  .ref { font-size: 2.6mm; }
  svg { width: 100%; height: auto; max-height: 20mm; }
  .sin { font-size: 2.8mm; color: #666; margin: 2mm 0; }
  @media print {
    body { background: #fff; }
    .barra, .nota { display: none; }
    .etq { margin: 0; box-shadow: none; }
  }
</style>
</head>
<body>
<div class="barra">
  <button class="imp" onclick="window.print()">🖨️ Imprimir <?= count($etiquetas) ?></button>
  <button class="cer" onclick="window.close()">Cerrar</button>
</div>
<p class="nota">Elige la impresora de tickets (XP-58 / POS-58), papel 58 mm y márgenes "Ninguno".</p>
<?php if (!$etiquetas): ?><p class="nota">No hay etiquetas.</p><?php endif; ?>
<?php foreach ($etiquetas as $i => $e): ?>
<div class="etq">
  <div class="nom"><?= esc($e['nombre']) ?><?= $e['presentacion'] !== '' ? ' (' . esc($e['presentacion']) . ')' : '' ?></div>
  <?php if ((float) $e['precio'] > 0): ?><div class="pre">$<?= number_format((float) $e['precio'], 2) ?></div><?php endif; ?>
  <?php if ($e['barras'] !== ''): ?>
    <svg class="cb" data-codigo="<?= esc($e['barras'], 'attr') ?>"></svg>
  <?php else: ?>
    <div class="sin">(sin código de barras)</div>
  <?php endif; ?>
  <div class="ref">Ref: <?= esc($e['codigo']) ?><?= $e['referencia'] !== '' ? ' · Prov: ' . esc($e['referencia']) : '' ?></div>
</div>
<?php endforeach; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.6/JsBarcode.all.min.js"></script>
<script>
(function () {
  function ean13(c) {
    if (!/^\d{13}$/.test(c)) return false;
    let s = 0;
    for (let i = 0; i < 12; i++) s += Number(c[i]) * (i % 2 ? 3 : 1);
    return (10 - s % 10) % 10 === Number(c[12]);
  }
  document.querySelectorAll('svg.cb').forEach(function (el) {
    const c = el.getAttribute('data-codigo');
    try {
      JsBarcode(el, c, { format: ean13(c) ? 'EAN13' : 'CODE128', height: 50, margin: 0, fontSize: 14, width: ean13(c) ? 2 : 1.6, displayValue: true });
    } catch (e) {
      el.outerHTML = '<div class="sin">' + c.replace(/[<>&]/g, '') + '</div>';
    }
  });
  <?php if ($etiquetas): ?>setTimeout(function () { window.print(); }, 500);<?php endif; ?>
})();
</script>
</body>
</html>

<?php
/** Ticket de 58 mm para imprimir desde el navegador (laptop + Xprinter por USB). Texto armado por ticket.js. */
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticket <?= esc($venta['numero_factura']) ?></title>
<style>
  @page { size: 58mm auto; margin: 0; }
  :root { color-scheme: light; }
  * { box-sizing: border-box; }
  body { margin: 0; background: #e5e7eb; font-family: system-ui, sans-serif; }
  .papel { width: 58mm; margin: 16px auto; background: #fff; padding: 3mm 2mm 6mm; box-shadow: 0 2px 8px rgba(0,0,0,.15); }
  pre { margin: 0; font-family: 'Courier New', Courier, monospace; font-size: 2.95mm; line-height: 1.25; white-space: pre; color: #000; }
  pre b.g { font-size: 5.9mm; line-height: 1.15; }
  .barra { max-width: 58mm; margin: 12px auto 0; display: flex; gap: 8px; }
  .barra button { flex: 1; padding: 10px; border: 0; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
  .imp { background: #0F2E6B; color: #fff; }
  .cer { background: #fff; color: #333; }
  .nota { max-width: 58mm; margin: 8px auto; font-size: 11px; color: #555; text-align: center; }
  @media print {
    body { background: #fff; }
    .papel { margin: 0; box-shadow: none; width: 58mm; padding: 0 1.5mm 4mm; }
    .barra, .nota { display: none; }
  }
</style>
</head>
<body>
<div class="barra">
  <button class="imp" onclick="window.print()">🖨️ Imprimir</button>
  <button class="cer" onclick="window.close()">Cerrar</button>
</div>
<p class="nota">En el diálogo elige la impresora de tickets (XP-58 / POS-58), papel 58 mm y márgenes "Ninguno".</p>
<div class="papel"><pre id="ticket"></pre></div>

<script src="/assets/js/ticket.js?v=4"></script>
<script>
(function () {
  const v = <?= json_encode($venta, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  const esc = function (s) { return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); };
  const ANCHO = TicketPOS.ANCHO;
  document.getElementById('ticket').innerHTML = TicketPOS.lineas(v).map(function (l) {
    const ancho = l.g ? 16 : ANCHO;
    let t = l.t;
    if (l.a === 'c') t = ' '.repeat(Math.max(0, Math.floor((ancho - t.length) / 2))) + t;
    t = esc(t);
    if (l.g) return '<b class="g">' + t + '</b>';
    return l.b ? '<b>' + t + '</b>' : t;
  }).join('\n');
  setTimeout(function () { window.print(); }, 300);
})();
</script>
</body>
</html>

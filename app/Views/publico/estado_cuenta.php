<?php
/**
 * Estado de cuenta público (/ec/{token}) — lo abre el cliente desde el WhatsApp. Sin datos internos
 * (ni costos ni otros clientes): sus facturas pendientes con los productos, todos sus pagos y
 * sus compras a crédito ya pagadas de los últimos 6 meses.
 */
$dinero  = fn ($n) => '$' . number_format((float) $n, 2);
$fecha   = fn ($f) => $f ? date('d/m/Y', strtotime((string) $f)) : '';
$formas  = ['EFECTIVO' => 'Efectivo', 'YAPPY' => 'Yappy', 'TRANSFERENCIA' => 'Transferencia', 'TARJETA' => 'Tarjeta', 'CHEQUE' => 'Cheque'];
$nombreEmpresa = (string) ($empresa['nombre_comercial'] ?? $empresa['razon_social'] ?? 'Inversiones Arango');
$ruc = trim((string) ($empresa['ruc'] ?? '')) . (!empty($empresa['dv']) ? ' DV ' . $empresa['dv'] : '');
$limite = (float) ($cliente['limite_credito'] ?? 0);
$totalPagado = array_sum(array_map(fn ($p) => (float) $p['monto'], $pagos));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Estado de cuenta · <?= esc($nombreEmpresa) ?></title>
<style>
  :root { --azul:#0F2E6B; --azul2:#1d4ed8; --tinta:#1f2937; --suave:#6b7280; --linea:#e5e7eb; --fondo:#f3f4f6; --rojo:#dc2626; --verde:#059669; --ambar:#d97706; color-scheme: light; }
  * { box-sizing: border-box; }
  body { margin: 0; background: var(--fondo); color: var(--tinta); font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; font-size: 15px; }
  .cab { background: linear-gradient(135deg, var(--azul), var(--azul2)); color: #fff; padding: 22px 16px 54px; }
  .cab h1 { margin: 0; font-size: 20px; letter-spacing: .3px; }
  .cab p { margin: 4px 0 0; opacity: .8; font-size: 12px; }
  .cont { max-width: 640px; margin: -38px auto 24px; padding: 0 16px; }
  .tarjeta { background: #fff; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 16px; margin-bottom: 14px; }
  .saldo { text-align: center; }
  .saldo .lbl { font-size: 12px; color: var(--suave); text-transform: uppercase; letter-spacing: 1px; }
  .saldo .num { font-size: 34px; font-weight: 800; margin: 4px 0; color: var(--rojo); }
  .saldo .num.cero { color: var(--verde); }
  .saldo .det { font-size: 13px; color: var(--suave); }
  h2 { font-size: 13px; text-transform: uppercase; letter-spacing: 1px; color: var(--suave); margin: 0 0 10px; }
  .fila { display: flex; justify-content: space-between; gap: 10px; padding: 10px 0; border-top: 1px solid var(--linea); }
  .fila:first-of-type { border-top: 0; }
  .fila .izq { min-width: 0; }
  .fila .t { font-weight: 600; font-size: 14px; }
  .fila .s { font-size: 12px; color: var(--suave); margin-top: 2px; }
  .fila .m { font-weight: 700; white-space: nowrap; }
  .m.rojo { color: var(--rojo); } .m.verde { color: var(--verde); }
  details summary { cursor: pointer; font-size: 12px; color: var(--azul2); margin-top: 4px; list-style: none; }
  details summary::-webkit-details-marker { display: none; }
  .items { margin: 6px 0 0; padding: 8px 10px; background: #f9fafb; border-radius: 10px; font-size: 12px; }
  .items div { display: flex; justify-content: space-between; gap: 8px; padding: 2px 0; }
  .vacio { color: var(--suave); font-size: 13px; text-align: center; padding: 8px 0; }
  .chip { display: inline-block; font-size: 11px; padding: 1px 7px; border-radius: 999px; background: #eef2ff; color: var(--azul2); font-weight: 600; }
  .pie { text-align: center; font-size: 11px; color: var(--suave); padding: 8px 16px 28px; line-height: 1.6; }
</style>
</head>
<body>
<div class="cab">
  <h1><?= esc(mb_strtoupper($nombreEmpresa, 'UTF-8')) ?></h1>
  <p>Estado de cuenta<?= $ruc !== '' ? ' · RUC ' . esc($ruc) : '' ?></p>
</div>

<div class="cont">
  <div class="tarjeta saldo">
    <div class="lbl"><?= esc($cliente['nombre'] ?? '') ?><?= !empty($cliente['codigo']) ? ' · ' . esc($cliente['codigo']) : '' ?></div>
    <div class="num <?= $total <= 0.004 ? 'cero' : '' ?>"><?= $dinero($total) ?></div>
    <div class="det"><?= $total <= 0.004 ? 'Su cuenta está al día. ¡Gracias!' : 'Saldo pendiente al ' . date('d/m/Y') ?></div>
    <?php if ($limite > 0): ?>
      <div class="det" style="margin-top:6px">Límite de crédito <?= $dinero($limite) ?> · Disponible <?= $dinero(max(0, $limite - $total)) ?></div>
    <?php endif; ?>
  </div>

  <div class="tarjeta">
    <h2>Facturas pendientes</h2>
    <?php if (!$pend): ?><div class="vacio">No tiene facturas pendientes.</div><?php endif; ?>
    <?php foreach ($pend as $p): ?>
      <div class="fila">
        <div class="izq">
          <div class="t"><?= esc($p['numero_factura']) ?> <?= $p['venta_id'] === null ? '<span class="chip">saldo anterior</span>' : '' ?></div>
          <div class="s"><?= $fecha($p['fecha'] ?? null) ?><?= (float) ($p['abonado'] ?? 0) > 0 ? ' · abonado ' . $dinero($p['abonado']) : '' ?></div>
          <?php if (!empty($p['items'])): ?>
          <details>
            <summary>Ver productos (<?= count($p['items']) ?>)</summary>
            <div class="items">
              <?php foreach ($p['items'] as $i): ?>
                <div><span><?= rtrim(rtrim(number_format((float) $i['cantidad'], 2, '.', ''), '0'), '.') ?><?= !empty($i['unidad_nombre']) ? ' ' . esc(mb_strtolower($i['unidad_nombre'])) : '' ?> × <?= esc($i['nombre']) ?></span><span><?= $dinero($i['total_linea']) ?></span></div>
              <?php endforeach; ?>
            </div>
          </details>
          <?php endif; ?>
        </div>
        <div class="m rojo"><?= $dinero($p['pendiente']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="tarjeta">
    <h2>Pagos recibidos<?= $pagos ? ' · total ' . $dinero($totalPagado) : '' ?></h2>
    <?php if (!$pagos): ?><div class="vacio">Todavía no hay pagos registrados.</div><?php endif; ?>
    <?php foreach ($pagos as $p): ?>
      <div class="fila">
        <div class="izq">
          <div class="t"><?= esc($p['numero']) ?> · <?= esc($formas[$p['forma_pago']] ?? $p['forma_pago']) ?></div>
          <div class="s"><?= $fecha($p['fecha']) ?><?= !empty($p['aplicado']) ? ' · a ' . esc($p['aplicado']) : '' ?></div>
        </div>
        <div class="m verde">−<?= $dinero($p['monto']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($pagadas): ?>
  <div class="tarjeta">
    <h2>Compras a crédito ya pagadas (6 meses)</h2>
    <?php foreach ($pagadas as $v): ?>
      <div class="fila">
        <div class="izq"><div class="t"><?= esc($v['numero_factura']) ?></div><div class="s"><?= $fecha($v['fecha']) ?> · pagada</div></div>
        <div class="m"><?= $dinero($v['total']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="pie">
  Información al <?= date('d/m/Y H:i') ?>. Enlace personal válido hasta el <?= $fecha($expira) ?>; no lo comparta.<br>
  Si ya realizó un pago que no aparece aquí, envíe el comprobante a la tienda.<br>
  <b><?= esc($nombreEmpresa) ?></b> · ¡Gracias por su preferencia!
</div>
</body>
</html>

<?php
/**
 * Plantilla HTML para Dompdf (proforma de cotización).
 *
 * No usa View::layout ni Tailwind: Dompdf no ejecuta JavaScript ni soporta CSS moderno
 * (flexbox, grid). El diseño se replica con tablas y CSS2.1, pero busca verse igual
 * que la proforma imprimible del navegador (views/cotizaciones/imprimir.php).
 *
 * Variables esperadas: $cotizacion, $detalles, $empresa, $sucursal
 */

use App\Core\View;

$empresaNombre    = $empresa['nombre_comercial'] ?? $empresa['razon_social'] ?? 'Mi Empresa';
$empresaRazon     = $empresa['razon_social'] ?? '';
$empresaDireccion = $sucursal['direccion'] ?? $empresa['direccion'] ?? '';
$empresaTelefono  = $sucursal['telefono'] ?? $empresa['telefono'] ?? '';
$empresaRuc       = trim(($empresa['ruc'] ?? '') . (!empty($empresa['dv']) ? ' DV.' . $empresa['dv'] : ''));

$clienteNombre    = $cotizacion['cliente_nombre'] ?? 'CLIENTE CONTADO';
$clienteRuc       = $cotizacion['cliente_ruc'] ?? '';
$clienteDireccion = $cotizacion['cliente_direccion'] ?? '';
$clienteTelefono  = $cotizacion['cliente_telefono'] ?? '';
$condicion        = ((float)($cotizacion['cliente_dias_credito'] ?? 0) > 0)
    ? 'CRÉDITO (' . (int)$cotizacion['cliente_dias_credito'] . ' días)'
    : 'CONTADO';

$fmtFecha = fn($f) => $f ? date('d/m/Y', strtotime($f)) : '—';
$itbmsPct = (int) round(((float)($_ENV['ITBMS_RATE'] ?? 0.07)) * 100);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { size: letter; margin: 0.5in; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10.5px; color: #111; }
        h1 { font-size: 13px; margin: 0; text-transform: uppercase; }
        h2 { font-size: 15px; margin: 10px 0; letter-spacing: 1px; }
        p { margin: 1px 0; }
        table { width: 100%; border-collapse: collapse; }
        .centro { text-align: center; }
        .derecha { text-align: right; }
        .chica { font-size: 9.5px; }
        .gris { color: #555; }
        .b { font-weight: bold; }
        .encabezado-empresa { margin-bottom: 6px; }
        .meta-tabla td { vertical-align: top; padding: 0; font-size: 9.5px; }
        .meta-tabla .etiqueta { font-weight: bold; width: 65px; }
        .items { border: 1px solid #333; margin: 8px 0; }
        .items th, .items td { padding: 3px 5px; font-size: 9.5px; }
        .items thead tr { background: #eee; border-bottom: 1px solid #333; }
        .items th { text-align: left; }
        .items td.num, .items th.num { text-align: right; }
        .items tbody tr.fila-vacia td { height: 15px; }
        .pie-tabla td { vertical-align: top; padding: 0; }
        .condiciones { font-size: 9px; color: #444; width: 65%; }
        .condiciones p { margin: 1px 0; }
        .totales { width: 35%; font-size: 10.5px; }
        .totales table { width: 100%; }
        .totales td { padding: 2px 0; }
        .totales td.valor { text-align: right; }
        .totales tr.total td { border-top: 2px solid #111; font-weight: bold; font-size: 12px; padding-top: 4px; }
    </style>
</head>
<body>

    <div class="encabezado-empresa centro">
        <h1><?= View::e($empresaNombre) ?></h1>
        <?php if ($empresaRazon && $empresaRazon !== $empresaNombre): ?><p class="chica"><?= View::e($empresaRazon) ?></p><?php endif; ?>
        <?php if ($empresaRuc): ?><p class="chica">RUC: <?= View::e($empresaRuc) ?></p><?php endif; ?>
        <?php if ($empresaDireccion): ?><p class="chica"><?= View::e($empresaDireccion) ?></p><?php endif; ?>
        <?php if ($empresaTelefono): foreach (preg_split('/\r\n|\r|\n/', $empresaTelefono) as $linea): ?>
            <p class="chica"><?= View::e($linea) ?></p>
        <?php endforeach; endif; ?>
    </div>

    <h2 class="centro">COTIZACIÓN</h2>

    <table class="meta-tabla">
        <tr>
            <td style="width: 50%;">
                <p><span class="etiqueta">Cliente:</span> <?= View::e($clienteNombre) ?></p>
                <p><span class="etiqueta">R.U.C.:</span> <?= View::e($clienteRuc ?: '—') ?></p>
                <p><span class="etiqueta">Dirección:</span> <?= View::e($clienteDireccion ?: '—') ?></p>
                <p><span class="etiqueta">Teléfonos:</span> <?= View::e($clienteTelefono ?: '—') ?></p>
            </td>
            <td class="derecha" style="width: 50%;">
                <p>Página: <span class="b">1 de 1</span></p>
                <p>Ref.: <span class="b"><?= View::e($cotizacion['numero'] ?? '') ?></span></p>
                <p>Fecha: <span class="b"><?= $fmtFecha($cotizacion['fecha'] ?? null) ?></span></p>
                <p>Vence: <span class="b"><?= $fmtFecha($cotizacion['fecha_vencimiento'] ?? null) ?></span></p>
                <p>Condición: <span class="b"><?= View::e($condicion) ?></span></p>
                <p>Vendedor: <span class="b"><?= View::e(strtoupper($cotizacion['vendedor_nombre'] ?? 'N/A')) ?></span></p>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 12%;">Código</th>
                <th style="width: 43%;">Descripción</th>
                <th class="num" style="width: 12%;">Cantidad</th>
                <th class="num" style="width: 15%;">Precio unit.</th>
                <th class="num" style="width: 18%;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($detalles as $d): ?>
                <?php $totalLinea = ((float)($d['cantidad'] ?? 0) * (float)($d['precio'] ?? 0)) - (float)($d['descuento'] ?? 0); ?>
                <tr>
                    <td><?= View::e($d['producto_codigo'] ?? '') ?></td>
                    <td><?= View::e($d['producto_nombre'] ?? '') ?></td>
                    <td class="num"><?= number_format((float)($d['cantidad'] ?? 0), 2) ?></td>
                    <td class="num">$<?= number_format((float)($d['precio'] ?? 0), 2) ?></td>
                    <td class="num">$<?= number_format($totalLinea, 2) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($detalles)): ?>
                <tr><td colspan="5" class="centro gris">Sin productos</td></tr>
            <?php endif; ?>
            <?php
            // Renglones en blanco para que la tabla cubra buena parte de la hoja, como el
            // formato preimpreso que reemplaza este sistema, aunque haya pocos ítems.
            $filasMinimas = 16;
            $filasVacias = max(0, $filasMinimas - count($detalles) - (empty($detalles) ? 1 : 0));
            for ($i = 0; $i < $filasVacias; $i++):
            ?>
                <tr class="fila-vacia"><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>
            <?php endfor; ?>
        </tbody>
    </table>

    <table class="pie-tabla">
        <tr>
            <td class="condiciones">
                <p class="b">Condiciones:</p>
                <p>1. No aceptamos cambios ni devoluciones después de 24 horas.</p>
                <p>2. Se entregará la mercancía hasta donde pueda llegar el camión.</p>
                <p>3. Los precios de los materiales son válidos por 30 días.</p>
                <p>4. Para retirar el dinero, se cobrará 10% de recargo.</p>
                <?php if (!empty($cotizacion['notas'])): ?>
                    <p class="b" style="margin-top: 6px;">Notas:</p>
                    <p><?= nl2br(View::e($cotizacion['notas'])) ?></p>
                <?php endif; ?>
            </td>
            <td class="totales">
                <table>
                    <tr><td>Sub-Total =</td><td class="valor">$<?= number_format((float)($cotizacion['subtotal'] ?? 0), 2) ?></td></tr>
                    <tr><td>Descuento -</td><td class="valor">$<?= number_format((float)($cotizacion['descuento'] ?? 0), 2) ?></td></tr>
                    <tr><td>ITBMS <?= $itbmsPct ?>% +</td><td class="valor">$<?= number_format((float)($cotizacion['itbms'] ?? 0), 2) ?></td></tr>
                    <tr><td>Manejo +</td><td class="valor">$0.00</td></tr>
                    <tr class="total"><td>Total =</td><td class="valor">$<?= number_format((float)($cotizacion['total'] ?? 0), 2) ?></td></tr>
                </table>
            </td>
        </tr>
    </table>

</body>
</html>

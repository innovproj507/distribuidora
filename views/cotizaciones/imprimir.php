<?php
use App\Core\View;
use App\Core\Router;

$esPublico = $esPublico ?? false;
$linkPublico = Router::url('/c?t=' . urlencode($cotizacion['token'] ?? ''));

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización <?= View::e($cotizacion['numero'] ?? '') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Tamaño carta (8.5" x 11") tanto en pantalla como al imprimir/exportar a PDF */
        @page {
            size: letter;
            margin: 0.5in;
        }
        .hoja {
            width: 8.5in;
            min-height: 11in;
            padding: 0.5in;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
        }
        /* Los renglones vacíos y el pie (condiciones/totales) se empujan hasta el fondo de la hoja */
        .pie-hoja { margin-top: auto; }
        @media print {
            .no-print { display: none !important; }
            html, body { padding: 0 !important; margin: 0 !important; background: #fff !important; }
            /* @page ya reserva 0.5in de margen; el área imprimible restante es 7.5in x 10in */
            .hoja { width: auto; min-height: 10in; padding: 0; box-shadow: none !important; border: 0 !important; }
        }
        body { font-family: Arial, Helvetica, sans-serif; }
        .tabla-items { border-collapse: collapse; }
    </style>
</head>
<body class="bg-gray-100 py-6 print:bg-white print:py-0">

    <!-- Barra de acciones (no se imprime) -->
    <div class="no-print mx-auto mb-4 flex flex-col items-end gap-1 px-4" style="width: 8.5in; max-width: 100%;">
        <div class="flex flex-wrap justify-end gap-2">
            <?php if (!$esPublico): ?>
            <a href="/ventas/cotizaciones/<?= (int) $cotizacion['cotizacion_id'] ?>"
               class="px-4 py-2 bg-white border border-gray-300 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                <i class="fas fa-arrow-left mr-1"></i> Volver
            </a>
            <?php endif; ?>
            <button onclick="window.print()"
                    class="px-4 py-2 bg-amber-500 text-white rounded-lg text-sm font-semibold hover:bg-amber-600 transition shadow-sm">
                <i class="fas fa-print mr-1"></i> Imprimir
            </button>
            <button onclick="descargarPdfCotizacion()" id="btn-descargar-pdf"
                    class="px-4 py-2 bg-sky-600 text-white rounded-lg text-sm font-semibold hover:bg-sky-700 transition shadow-sm">
                <i class="fas fa-file-pdf mr-1"></i> Descargar PDF
            </button>
            <?php if (!$esPublico): ?>
            <button onclick="enviarPorWhatsApp()"
                    class="px-4 py-2 bg-emerald-500 text-white rounded-lg text-sm font-semibold hover:bg-emerald-600 transition shadow-sm">
                <i class="fab fa-whatsapp mr-1"></i> Enviar por WhatsApp
            </button>
            <button onclick="enviarPorCorreo()"
                    class="px-4 py-2 bg-slate-600 text-white rounded-lg text-sm font-semibold hover:bg-slate-700 transition shadow-sm">
                <i class="fas fa-envelope mr-1"></i> Enviar por correo
            </button>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // El PDF lo genera el servidor (Dompdf). Se pide con fetch() y se guarda como blob
        // — así el navegador SIEMPRE lo descarga, sin abrir un visor ni un diálogo de impresión.
        const COT_PDF_URL = <?= json_encode($esPublico
            ? ('/c/pdf?t=' . urlencode($cotizacion['token'] ?? ''))
            : ('/ventas/cotizaciones/' . (int) $cotizacion['cotizacion_id'] . '/pdf')) ?>;
        const COT_NUMERO_ARCHIVO = <?= json_encode($cotizacion['numero'] ?? 'cotizacion') ?>;

        async function descargarPdfCotizacion() {
            const btn = document.getElementById('btn-descargar-pdf');
            const textoOriginal = btn ? btn.innerHTML : '';
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Generando PDF...'; }
            try {
                const resp = await fetch(COT_PDF_URL, { credentials: 'same-origin' });
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                const blob = await resp.blob();
                const nombreArchivo = 'Cotizacion_' + COT_NUMERO_ARCHIVO.replace(/[^A-Za-z0-9_-]/g, '') + '.pdf';
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = nombreArchivo;
                document.body.appendChild(a);
                a.click();
                a.remove();
                setTimeout(() => URL.revokeObjectURL(url), 4000);
                return true;
            } catch (e) {
                console.error('Descargar PDF:', e);
                alert('No se pudo generar el PDF. Intente de nuevo.');
                return false;
            } finally {
                if (btn) { btn.disabled = false; btn.innerHTML = textoOriginal; }
            }
        }
    </script>

    <?php if (!$esPublico): ?>
    <!-- Modal: Enviar cotización -->
    <div id="modal-enviar" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm mx-4 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 id="modal-enviar-titulo" class="text-base font-bold text-gray-800 flex items-center gap-2"></h3>
                <button type="button" onclick="cerrarModalEnviar()" class="text-gray-300 hover:text-gray-500 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <label id="modal-enviar-label" class="block text-xs font-semibold text-gray-500 mb-1"></label>
            <input type="text" id="modal-enviar-valor"
                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-transparent">
            <p class="text-[11px] text-gray-400 mt-1">Se abrirá con un mensaje ya escrito y el enlace para ver la cotización.</p>
            <div class="flex gap-3 justify-end mt-5">
                <button type="button" onclick="cerrarModalEnviar()"
                    class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition">
                    Cancelar
                </button>
                <button type="button" onclick="confirmarEnvio()" id="btn-confirmar-envio"
                    class="px-4 py-2 text-white rounded-lg text-sm font-semibold transition">
                    <i class="fas fa-paper-plane mr-1"></i> Enviar
                </button>
            </div>
        </div>
    </div>

    <script>
        const COT_LINK = <?= json_encode($linkPublico) ?>;
        const COT_NUMERO = <?= json_encode($cotizacion['numero'] ?? '') ?>;
        const COT_CLIENTE_TEL = <?= json_encode($clienteTelefono) ?>;
        const COT_CLIENTE_EMAIL = <?= json_encode($cotizacion['cliente_email'] ?? '') ?>;
        const COT_CLIENTE_NOMBRE = <?= json_encode($clienteNombre) ?>;

        function soloDigitos(v) { return (v || '').replace(/\D/g, ''); }

        let modoEnvioActual = null; // 'whatsapp' | 'correo'

        function enviarPorWhatsApp() { abrirModalEnviar('whatsapp'); }
        function enviarPorCorreo() { abrirModalEnviar('correo'); }

        function abrirModalEnviar(modo) {
            modoEnvioActual = modo;
            const input = document.getElementById('modal-enviar-valor');
            const btn = document.getElementById('btn-confirmar-envio');
            if (modo === 'whatsapp') {
                document.getElementById('modal-enviar-titulo').innerHTML = '<i class="fab fa-whatsapp text-emerald-500"></i> Enviar por WhatsApp';
                document.getElementById('modal-enviar-label').textContent = 'Número de WhatsApp del cliente';
                input.type = 'tel';
                input.placeholder = 'Ej: 6123-4567';
                input.value = COT_CLIENTE_TEL || '';
                btn.className = 'px-4 py-2 bg-emerald-500 text-white rounded-lg text-sm font-semibold hover:bg-emerald-600 transition';
            } else {
                document.getElementById('modal-enviar-titulo').innerHTML = '<i class="fas fa-envelope text-slate-500"></i> Enviar por correo';
                document.getElementById('modal-enviar-label').textContent = 'Correo del cliente';
                input.type = 'email';
                input.placeholder = 'cliente@ejemplo.com';
                input.value = COT_CLIENTE_EMAIL || '';
                btn.className = 'px-4 py-2 bg-slate-600 text-white rounded-lg text-sm font-semibold hover:bg-slate-700 transition';
            }
            document.getElementById('modal-enviar').classList.remove('hidden');
            setTimeout(() => { input.focus(); input.select(); }, 80);
        }

        function cerrarModalEnviar() {
            document.getElementById('modal-enviar').classList.add('hidden');
        }

        function confirmarEnvio() {
            const valor = document.getElementById('modal-enviar-valor').value.trim();
            if (!valor) { document.getElementById('modal-enviar-valor').focus(); return; }
            if (modoEnvioActual === 'whatsapp' && !soloDigitos(valor)) { document.getElementById('modal-enviar-valor').focus(); return; }
            cerrarModalEnviar();
            procesarEnvioConPdf(modoEnvioActual, valor);
        }

        // WhatsApp y el correo no permiten adjuntar un archivo mediante enlace (whatsapp://,
        // mailto:) — es una limitación de esas apps, no del sistema. Por eso primero se
        // descarga el PDF ya generado por el servidor y luego se abre el chat o el correo
        // listo para que arrastre ese PDF descargado y lo envíe.
        async function procesarEnvioConPdf(modo, valor) {
            const ok = await descargarPdfCotizacion(); // genera el PDF en el servidor y lo descarga
            if (!ok) return; // si falló la descarga, no tiene sentido abrir WhatsApp/correo

            if (modo === 'whatsapp') {
                let tel = soloDigitos(valor);
                if (tel.length <= 8) tel = '507' + tel; // número local de Panamá sin código de país
                const mensaje = `Hola ${COT_CLIENTE_NOMBRE}, le compartimos la cotización ${COT_NUMERO}. Adjunto el PDF que se acaba de descargar — arrástrelo aquí y envíelo.`;
                // whatsapp:// abre la app de escritorio directamente (si está instalada),
                // en vez de pasar por la página web de WhatsApp Web.
                window.location.href = 'whatsapp://send?phone=' + tel + '&text=' + encodeURIComponent(mensaje);
            } else {
                const asunto = `Cotización ${COT_NUMERO}`;
                const cuerpo = `Hola ${COT_CLIENTE_NOMBRE},\n\nLe compartimos la cotización ${COT_NUMERO}. Adjunte el PDF que se acaba de descargar a este correo antes de enviarlo.\n\nSaludos.`;
                window.location.href = 'mailto:' + encodeURIComponent(valor) + '?subject=' + encodeURIComponent(asunto) + '&body=' + encodeURIComponent(cuerpo);
            }
        }

        // Si se llega desde el detalle de la cotización con ?enviar=whatsapp|correo&valor=...,
        // se dispara el mismo flujo automáticamente sin pedir el dato de nuevo.
        (function () {
            const params = new URLSearchParams(window.location.search);
            const modo = params.get('enviar');
            const valor = params.get('valor');
            if ((modo === 'whatsapp' || modo === 'correo') && valor) {
                window.addEventListener('DOMContentLoaded', function () {
                    setTimeout(function () { procesarEnvioConPdf(modo, valor); }, 300);
                });
            }
        })();

        document.getElementById('modal-enviar-valor').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') confirmarEnvio();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !document.getElementById('modal-enviar').classList.contains('hidden')) {
                cerrarModalEnviar();
            }
        });
    </script>
    <?php endif; ?>

    <!-- Proforma -->
    <div class="hoja mx-auto bg-white shadow-sm print:shadow-none border border-gray-200 print:border-0 text-[13px] text-gray-900">

        <!-- Encabezado de la empresa, centrado -->
        <div class="text-center mb-2">
            <h1 class="text-base font-bold uppercase"><?= View::e($empresaNombre) ?></h1>
            <?php if ($empresaRazon && $empresaRazon !== $empresaNombre): ?>
                <p class="text-xs"><?= View::e($empresaRazon) ?></p>
            <?php endif; ?>
            <?php if ($empresaRuc): ?><p class="text-xs"><?= View::e($empresaRuc) ?></p><?php endif; ?>
            <?php if ($empresaDireccion): ?><p class="text-xs"><?= View::e($empresaDireccion) ?></p><?php endif; ?>
            <?php if ($empresaTelefono): ?><p class="text-xs whitespace-pre-line"><?= View::e($empresaTelefono) ?></p><?php endif; ?>
        </div>

        <h2 class="text-center text-lg font-bold tracking-wide mb-3">COTIZACIÓN</h2>

        <!-- Cliente (izquierda) y datos del documento (derecha) -->
        <div class="grid grid-cols-2 gap-x-8 mb-4 text-xs">
            <div class="space-y-1">
                <div class="flex gap-2"><span class="font-semibold w-20 shrink-0">Cliente:</span><span><?= View::e($clienteNombre) ?></span></div>
                <div class="flex gap-2"><span class="font-semibold w-20 shrink-0">R.U.C.:</span><span><?= View::e($clienteRuc ?: '—') ?></span></div>
                <div class="flex gap-2"><span class="font-semibold w-20 shrink-0">Dirección:</span><span><?= View::e($clienteDireccion ?: '—') ?></span></div>
                <div class="flex gap-2"><span class="font-semibold w-20 shrink-0">Teléfonos:</span><span><?= View::e($clienteTelefono ?: '—') ?></span></div>
            </div>
            <div class="space-y-1 text-right">
                <div>Página: <span class="font-semibold">1 de 1</span></div>
                <div>Ref.: <span class="font-semibold"><?= View::e($cotizacion['numero'] ?? '') ?></span></div>
                <div>Fecha: <span class="font-semibold"><?= $fmtFecha($cotizacion['fecha'] ?? null) ?></span></div>
                <div>Vence: <span class="font-semibold"><?= $fmtFecha($cotizacion['fecha_vencimiento'] ?? null) ?></span></div>
                <div>Condición: <span class="font-semibold"><?= View::e($condicion) ?></span></div>
                <div>Vendedor: <span class="font-semibold"><?= View::e(strtoupper($cotizacion['vendedor_nombre'] ?? 'N/A')) ?></span></div>
            </div>
        </div>

        <!-- Items -->
        <table class="tabla-items w-full text-xs mb-4 border border-gray-800">
            <thead>
                <tr class="bg-gray-100 border-b border-gray-800">
                    <th class="text-left py-1.5 px-2">Código</th>
                    <th class="text-left py-1.5 px-2">Descripción</th>
                    <th class="text-right py-1.5 px-2">Cantidad</th>
                    <th class="text-right py-1.5 px-2">Precio unit.</th>
                    <th class="text-right py-1.5 px-2">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $d): ?>
                    <?php $totalLinea = ((float)($d['cantidad'] ?? 0) * (float)($d['precio'] ?? 0)) - (float)($d['descuento'] ?? 0); ?>
                    <tr style="height: 26px;">
                        <td class="py-1 px-2 font-mono"><?= View::e($d['producto_codigo'] ?? '') ?></td>
                        <td class="py-1 px-2"><?= View::e($d['producto_nombre'] ?? '') ?></td>
                        <td class="py-1 px-2 text-right"><?= number_format((float)($d['cantidad'] ?? 0), 2) ?></td>
                        <td class="py-1 px-2 text-right">$<?= number_format((float)($d['precio'] ?? 0), 2) ?></td>
                        <td class="py-1 px-2 text-right">$<?= number_format($totalLinea, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($detalles)): ?>
                    <tr style="height: 26px;"><td colspan="5" class="py-1 px-2 text-center text-gray-400">Sin productos</td></tr>
                <?php endif; ?>
                <?php
                // Renglones en blanco (sin bordes) para que la tabla siga cubriendo casi toda la hoja
                // aunque la cotización tenga pocos ítems.
                $filasMinimas = 18;
                $filasVacias = max(0, $filasMinimas - count($detalles) - (empty($detalles) ? 1 : 0));
                for ($i = 0; $i < $filasVacias; $i++):
                ?>
                    <tr style="height: 26px;">
                        <td class="py-1 px-2">&nbsp;</td>
                        <td class="py-1 px-2">&nbsp;</td>
                        <td class="py-1 px-2">&nbsp;</td>
                        <td class="py-1 px-2">&nbsp;</td>
                        <td class="py-1 px-2">&nbsp;</td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <!-- Condiciones y totales -->
        <div class="pie-hoja flex justify-between items-start gap-8">
            <div class="flex-1 text-[11px] text-gray-700 space-y-0.5">
                <p class="font-semibold mb-1">Condiciones:</p>
                <p>1. No aceptamos cambios ni devoluciones después de 24 horas.</p>
                <p>2. Se entregará la mercancía hasta donde pueda llegar el camión.</p>
                <p>3. Los precios de los materiales son válidos por 30 días.</p>
                <p>4. Para retirar el dinero, se cobrará 10% de recargo.</p>
                <?php if (!empty($cotizacion['notas'])): ?>
                    <p class="font-semibold mt-2 mb-1">Notas:</p>
                    <p class="whitespace-pre-line"><?= View::e($cotizacion['notas']) ?></p>
                <?php endif; ?>
            </div>
            <div class="w-56 shrink-0 text-sm">
                <div class="flex justify-between py-1"><span class="text-gray-600">Sub-Total =</span><span>$<?= number_format((float)($cotizacion['subtotal'] ?? 0), 2) ?></span></div>
                <div class="flex justify-between py-1"><span class="text-gray-600">Descuento -</span><span>$<?= number_format((float)($cotizacion['descuento'] ?? 0), 2) ?></span></div>
                <div class="flex justify-between py-1"><span class="text-gray-600">ITBMS <?= $itbmsPct ?>% +</span><span>$<?= number_format((float)($cotizacion['itbms'] ?? 0), 2) ?></span></div>
                <div class="flex justify-between py-1"><span class="text-gray-600">Manejo +</span><span>$0.00</span></div>
                <div class="flex justify-between py-2 border-t-2 border-gray-800 mt-1 font-bold text-base"><span>Total =</span><span>$<?= number_format((float)($cotizacion['total'] ?? 0), 2) ?></span></div>
            </div>
        </div>
    </div>

</body>
</html>

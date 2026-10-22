<?php
declare(strict_types=1);

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Genera la proforma de una cotización como PDF real (servidor), usando Dompdf.
 * Reemplaza la dependencia de "imprimir desde el navegador" para descargar el PDF.
 */
class CotizacionPdfService
{
    /**
     * Genera el PDF y lo envía al navegador como descarga, terminando la ejecución.
     */
    public static function descargar(array $cotizacion, array $detalles, array $empresa, array $sucursal): void
    {
        $pdf = self::generar($cotizacion, $detalles, $empresa, $sucursal);

        $numero = preg_replace('/[^A-Za-z0-9_-]/', '', $cotizacion['numero'] ?? 'cotizacion') ?: 'cotizacion';
        $filename = 'Cotizacion_' . $numero . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    /**
     * Genera el PDF y devuelve los bytes crudos (sin enviar headers ni terminar la ejecución).
     */
    public static function generar(array $cotizacion, array $detalles, array $empresa, array $sucursal): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('letter', 'portrait');

        ob_start();
        (function () use ($cotizacion, $detalles, $empresa, $sucursal) {
            require BASE_PATH . '/views/cotizaciones/pdf_template.php';
        })();
        $html = ob_get_clean();

        $dompdf->loadHtml($html);
        $dompdf->render();

        return $dompdf->output();
    }
}

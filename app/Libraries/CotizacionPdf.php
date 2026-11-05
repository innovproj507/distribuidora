<?php

declare(strict_types=1);

namespace App\Libraries;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Proforma de cotización en PDF real (servidor), con Dompdf.
 */
final class CotizacionPdf
{
    public static function generar(array $cotizacion, array $detalles, array $empresa, array $sucursal): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->loadHtml(view('cotizaciones/pdf_template', compact('cotizacion', 'detalles', 'empresa', 'sucursal')));
        $dompdf->render();

        return $dompdf->output();
    }

    public static function nombreArchivo(array $cotizacion): string
    {
        $numero = preg_replace('/[^A-Za-z0-9_-]/', '', $cotizacion['numero'] ?? 'cotizacion') ?: 'cotizacion';
        return 'Cotizacion_' . $numero . '.pdf';
    }
}

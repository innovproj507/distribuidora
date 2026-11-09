<?php
declare(strict_types=1);

namespace App\Libraries;

/**
 * Lee la primera hoja de un .xlsx (o un .csv) como filas de texto, sin dependencias externas.
 * Pareja de SimpleXlsxWriter. Requiere las extensiones zip y SimpleXML.
 */
final class SimpleXlsxReader
{
    /** Máximo de filas que se leen (protege la memoria). */
    private const MAX_FILAS = 20000;

    /**
     * @return array<int, array<int, string>> filas con columnas 0..n (texto recortado)
     * @throws \DomainException si el archivo no se puede leer
     */
    public static function leer(string $ruta, string $nombreOriginal = ''): array
    {
        $ext = strtolower(pathinfo($nombreOriginal ?: $ruta, PATHINFO_EXTENSION));
        return $ext === 'csv' || $ext === 'txt' ? self::leerCsv($ruta) : self::leerXlsx($ruta);
    }

    private static function leerXlsx(string $ruta): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($ruta) !== true) {
            throw new \DomainException('El archivo no es un Excel (.xlsx) válido. Si es .xls antiguo, guárdalo como .xlsx.');
        }

        // Textos compartidos (las celdas de texto guardan un índice a esta tabla)
        $textos = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $sst = self::xml($xml);
            foreach ($sst->si as $si) {
                if (isset($si->t)) {
                    $textos[] = (string) $si->t;
                } else { // texto con formato: varias corridas <r><t>
                    $t = '';
                    foreach ($si->r as $r) {
                        $t .= (string) $r->t;
                    }
                    $textos[] = $t;
                }
            }
        }

        $hoja = $zip->getFromName(self::rutaPrimeraHoja($zip));
        $zip->close();
        if ($hoja === false) {
            throw new \DomainException('No se encontró ninguna hoja en el archivo.');
        }

        $filas = [];
        foreach (self::xml($hoja)->sheetData->row as $row) {
            $fila = [];
            foreach ($row->c as $c) {
                $col  = self::columna((string) $c['r']);
                $tipo = (string) $c['t'];
                $v    = (string) $c->v;
                $fila[$col] = match ($tipo) {
                    's'         => $textos[(int) $v] ?? '',
                    'inlineStr' => (string) ($c->is->t ?? ''),
                    'b'         => $v === '1' ? 'VERDADERO' : 'FALSO',
                    default     => self::numero($v),
                };
            }
            if ($fila) {
                $max  = max(array_keys($fila));
                $filas[] = array_map(fn ($i) => trim((string) ($fila[$i] ?? '')), range(0, $max));
            }
            if (count($filas) >= self::MAX_FILAS) break;
        }
        return $filas;
    }

    /** Ruta de la primera hoja según workbook.xml (no siempre es sheet1.xml). */
    private static function rutaPrimeraHoja(\ZipArchive $zip): string
    {
        $wb   = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wb !== false && $rels !== false) {
            $libro = self::xml($wb);
            $hojas = $libro->sheets->sheet ?? null;
            if ($hojas && isset($hojas[0])) {
                $rid = (string) $hojas[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                foreach (self::xml($rels)->Relationship as $r) {
                    if ((string) $r['Id'] === $rid) {
                        $destino = ltrim((string) $r['Target'], '/');
                        return str_starts_with($destino, 'xl/') ? $destino : 'xl/' . $destino;
                    }
                }
            }
        }
        return 'xl/worksheets/sheet1.xml';
    }

    private static function leerCsv(string $ruta): array
    {
        $contenido = (string) file_get_contents($ruta);
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido); // BOM
        if (!mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252'); // Excel en español guarda CSV en ANSI
        }
        $lineas = preg_split('/\r\n|\r|\n/', $contenido);
        $sep    = substr_count($lineas[0] ?? '', ';') > substr_count($lineas[0] ?? '', ',') ? ';' : ',';
        $filas  = [];
        foreach ($lineas as $l) {
            if (trim($l) === '') continue;
            $filas[] = array_map('trim', str_getcsv($l, $sep, '"', ''));
            if (count($filas) >= self::MAX_FILAS) break;
        }
        return $filas;
    }

    private static function xml(string $xml): \SimpleXMLElement
    {
        $prev = libxml_use_internal_errors(true);
        $doc  = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET);
        libxml_use_internal_errors($prev);
        if ($doc === false) {
            throw new \DomainException('El archivo está dañado o no es un Excel válido.');
        }
        return $doc;
    }

    /** "C12" → 2 (índice de columna desde 0). */
    private static function columna(string $ref): int
    {
        $letras = preg_replace('/\d+/', '', strtoupper($ref));
        $n = 0;
        foreach (str_split($letras) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return max(0, $n - 1);
    }

    /** Excel guarda 9 como "9" y 2.5 como "2.5" o "2.4999999999999996": se redondea. */
    private static function numero(string $v): string
    {
        if ($v !== '' && is_numeric($v) && str_contains($v, '.')) {
            return rtrim(rtrim(number_format((float) $v, 6, '.', ''), '0'), '.');
        }
        return $v;
    }
}

<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Genera archivos .xlsx (OpenXML) sin dependencias externas.
 * Requiere la extensión zip de PHP (habilitada por defecto en Laragon).
 */
class SimpleXlsxWriter
{
    /**
     * Envía el archivo xlsx directamente al navegador y termina la ejecución.
     *
     * @param string   $filename  Nombre del archivo descargado (ej. "reporte.xlsx")
     * @param string[] $headers   Encabezados de columna (se muestran en negrita)
     * @param array[]  $rows      Filas de datos; valores numéricos se guardan como número
     */
    public static function output(string $filename, array $headers, array $rows): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        self::write($tmp, $headers, $rows);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Content-Length: ' . filesize($tmp));

        readfile($tmp);
        unlink($tmp);
        exit;
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private static function write(string $path, array $headers, array $rows): void
    {
        // Shared strings table
        $strings = [];
        $strIdx  = [];

        $addStr = static function (string $s) use (&$strings, &$strIdx): int {
            if (!isset($strIdx[$s])) {
                $strIdx[$s] = count($strings);
                $strings[]  = $s;
            }
            return $strIdx[$s];
        };

        // Pre-collect all strings so the shared strings table is complete
        foreach ($headers as $h) {
            $addStr((string) $h);
        }
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if (!is_numeric($cell) || $cell === '') {
                    $addStr((string) $cell);
                }
            }
        }

        // ── Sheet XML ───────────────────────────────────────────────────────
        $sheet  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $sheet .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $sheet .= '<sheetData>';

        // Header row — style index 1 (bold)
        $sheet .= '<row r="1">';
        foreach (array_values($headers) as $col => $h) {
            $ref    = self::ref($col, 1);
            $si     = $strIdx[(string) $h];
            $sheet .= '<c r="' . $ref . '" t="s" s="1"><v>' . $si . '</v></c>';
        }
        $sheet .= '</row>';

        // Data rows — style index 0 (normal)
        foreach (array_values($rows) as $ri => $row) {
            $rowNum = $ri + 2;
            $sheet .= '<row r="' . $rowNum . '">';
            foreach (array_values($row) as $col => $cell) {
                $ref = self::ref($col, $rowNum);
                if (is_numeric($cell) && $cell !== '') {
                    $sheet .= '<c r="' . $ref . '"><v>' . $cell . '</v></c>';
                } else {
                    $si     = $strIdx[(string) $cell] ?? 0;
                    $sheet .= '<c r="' . $ref . '" t="s"><v>' . $si . '</v></c>';
                }
            }
            $sheet .= '</row>';
        }

        $sheet .= '</sheetData></worksheet>';

        // ── Shared strings XML ──────────────────────────────────────────────
        $cnt = count($strings);
        $ss  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $ss .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
             . ' count="' . $cnt . '" uniqueCount="' . $cnt . '">';
        foreach ($strings as $s) {
            $ss .= '<si><t xml:space="preserve">'
                 . htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8')
                 . '</t></si>';
        }
        $ss .= '</sst>';

        // ── ZIP ─────────────────────────────────────────────────────────────
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml',          self::contentTypes());
        $zip->addFromString('_rels/.rels',                  self::rels());
        $zip->addFromString('xl/workbook.xml',              self::workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels',   self::workbookRels());
        $zip->addFromString('xl/styles.xml',                self::styles());
        $zip->addFromString('xl/sharedStrings.xml',         $ss);
        $zip->addFromString('xl/worksheets/sheet1.xml',     $sheet);

        $zip->close();
    }

    private static function ref(int $col, int $row): string
    {
        // Convert 0-based column index to letter (A, B, …, Z, AA, AB, …)
        $letter = '';
        for ($c = $col; $c >= 0; $c = intdiv($c, 26) - 1) {
            $letter = chr(65 + ($c % 26)) . $letter;
        }
        return $letter . $row;
    }

    // ── Static XML fragments ──────────────────────────────────────────────────

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
             . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
             . '<Default Extension="xml"  ContentType="application/xml"/>'
             . '<Override PartName="/xl/workbook.xml"            ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
             . '<Override PartName="/xl/worksheets/sheet1.xml"   ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
             . '<Override PartName="/xl/sharedStrings.xml"       ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
             . '<Override PartName="/xl/styles.xml"              ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
             . '</Types>';
    }

    private static function rels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
             . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
             . '</Relationships>';
    }

    private static function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
             . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
             . '<sheets><sheet name="Inventario" sheetId="1" r:id="rId1"/></sheets>'
             . '</workbook>';
    }

    private static function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
             . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"     Target="worksheets/sheet1.xml"/>'
             . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
             . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"        Target="styles.xml"/>'
             . '</Relationships>';
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
             . '<fonts count="2">'
             . '<font><sz val="11"/><name val="Calibri"/></font>'               // 0: normal
             . '<font><b/><sz val="11"/><name val="Calibri"/></font>'           // 1: bold
             . '</fonts>'
             . '<fills count="2">'
             . '<fill><patternFill patternType="none"/></fill>'
             . '<fill><patternFill patternType="gray125"/></fill>'
             . '</fills>'
             . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
             . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
             . '<cellXfs count="2">'
             . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'  // 0: normal
             . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/>'  // 1: bold header
             . '</cellXfs>'
             . '</styleSheet>';
    }
}

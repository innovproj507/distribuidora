<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Fotos de comprobantes de pago (ventas y abonos): se guardan fuera de public/, en
 * writable/uploads/comprobantes/Y/m/, y se sirven con permiso desde MovilApiController.
 */
final class Comprobantes
{
    /** Formas de pago de un abono que exigen foto del comprobante (tarjeta: opcional). */
    public const FORMAS_CON_FOTO = ['YAPPY', 'TRANSFERENCIA', 'CHEQUE'];

    public static function exigeFoto(string $forma): bool
    {
        return in_array(strtoupper(trim($forma)), self::FORMAS_CON_FOTO, true);
    }

    /** Valida sin mover (para rechazar antes de registrar nada). Lanza RuntimeException. */
    public static function validar(?UploadedFile $file): void
    {
        if ($file === null || !$file->isValid() || $file->hasMoved()) {
            throw new \RuntimeException('Adjunta la foto del comprobante (Yappy, transferencia o cheque).');
        }
        if (!isset(self::tipos()[$file->getMimeType()]) || $file->getSize() > 8 * 1024 * 1024) {
            throw new \RuntimeException('La foto debe ser JPG, PNG o WEBP de menos de 8 MB.');
        }
    }

    /** Mueve la foto a su carpeta y devuelve la ruta relativa (Y/m/archivo). */
    public static function guardar(UploadedFile $file, string $prefijo): string
    {
        self::validar($file);
        $sub    = date('Y/m');
        $nombre = $prefijo . '_' . bin2hex(random_bytes(8)) . '.' . self::tipos()[$file->getMimeType()];
        $file->move(WRITEPATH . 'uploads/comprobantes/' . $sub, $nombre);
        return $sub . '/' . $nombre;
    }

    public static function borrar(?string $ruta): void
    {
        if ($ruta) @unlink(WRITEPATH . 'uploads/comprobantes/' . $ruta);
    }

    private static function tipos(): array
    {
        return ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    }
}

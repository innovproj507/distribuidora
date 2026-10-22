<?php
declare(strict_types=1);

namespace App\Services;

/**
 * TOTP implementation (RFC 6238) — pure PHP, no external dependencies.
 * Compatible with Google Authenticator, Authy, and any RFC 6238 app.
 */
final class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const STEP     = 30;
    private const DIGITS   = 6;

    /** Generates a random 16-character base32 secret. */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(10));
    }

    /**
     * Returns the otpauth:// URI for QR code generation.
     * Format: otpauth://totp/{account}?secret={secret}&issuer={issuer}
     */
    public static function getUri(string $secret, string $account, string $issuer): string
    {
        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            rawurlencode($issuer . ':' . $account),
            $secret,
            rawurlencode($issuer)
        );
    }

    /**
     * Verifies a 6-digit OTP code against the secret.
     * Allows ±1 time step (90-second window) to handle clock drift.
     */
    public static function verify(string $secret, string $code): bool
    {
        if (strlen($code) !== self::DIGITS || !ctype_digit($code)) {
            return false;
        }
        $step = (int) floor(time() / self::STEP);
        for ($i = -1; $i <= 1; $i++) {
            if (hash_equals(self::generate($secret, $step + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    /** Generates the OTP for a given time step (defaults to now). */
    public static function generate(string $secret, ?int $step = null): string
    {
        $step ??= (int) floor(time() / self::STEP);
        $key  = self::base32Decode($secret);
        $msg  = pack('N*', 0) . pack('N*', $step);
        $hash = hash_hmac('sha1', $msg, $key, true);

        $offset = ord($hash[19]) & 0x0F;
        $code   = (
            ((ord($hash[$offset])     & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) <<  8) |
             (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);

        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $bytes): string
    {
        $buffer   = 0;
        $bitsLeft = 0;
        $result   = '';
        foreach (str_split($bytes) as $char) {
            $buffer    = ($buffer << 8) | ord($char);
            $bitsLeft += 8;
            while ($bitsLeft >= 5) {
                $bitsLeft -= 5;
                $result   .= self::ALPHABET[($buffer >> $bitsLeft) & 0x1F];
            }
        }
        if ($bitsLeft > 0) {
            $result .= self::ALPHABET[($buffer << (5 - $bitsLeft)) & 0x1F];
        }
        return $result;
    }

    private static function base32Decode(string $s): string
    {
        $s        = strtoupper($s);
        $buffer   = 0;
        $bitsLeft = 0;
        $result   = '';
        foreach (str_split($s) as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos === false) continue;
            $buffer    = ($buffer << 5) | $pos;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $result   .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        return $result;
    }
}

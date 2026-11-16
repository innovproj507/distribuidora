<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Estado de cuenta de un cliente en texto para WhatsApp (negritas con *…*): facturas y saldos
 * anteriores pendientes, últimos pagos, total y crédito disponible. Lo usan la web y el celular
 * (GET /m/api/cliente/{id}/estado-cuenta) para que el mensaje sea siempre el mismo.
 */
final class EstadoCuenta
{
    /** @return array{texto: string, telefono: string, url: string, saldo: float} */
    public static function whatsapp(int $clienteId, int $empresaId, int $sucursalId): array
    {
        $c = Db::row("SELECT cliente_id, codigo, nombre, telefono, saldo_pendiente, limite_credito FROM clientes WHERE cliente_id = ? AND empresa_id = ?", [$clienteId, $empresaId]);
        if (!$c) throw new \RuntimeException('Cliente no encontrado.');
        $empresa  = Db::row("SELECT nombre_comercial, razon_social FROM companies WHERE empresa_id = ?", [$empresaId]) ?? [];
        $tienda   = Db::row("SELECT nombre, telefono FROM branches WHERE sucursal_id = ?", [$sucursalId]) ?? [];
        $pend     = CobroService::pendientes($clienteId, $empresaId);
        $pagos    = Db::all("SELECT numero, fecha, forma_pago, monto FROM cobros WHERE cliente_id = ? AND empresa_id = ? ORDER BY fecha DESC, cobro_id DESC LIMIT 3", [$clienteId, $empresaId]);
        $total    = round(array_sum(array_map(fn ($p) => (float) $p['pendiente'], $pend)), 2);
        $limite   = (float) $c['limite_credito'];

        $dinero = fn (float $n): string => '$' . number_format($n, 2);
        $fecha  = fn (?string $f): string => $f ? date('d/m/Y', strtotime($f)) : '';
        $formas = ['EFECTIVO' => 'Efectivo', 'YAPPY' => 'Yappy', 'TRANSFERENCIA' => 'Transferencia', 'TARJETA' => 'Tarjeta', 'CHEQUE' => 'Cheque'];
        $linea  = '━━━━━━━━━━━━━━━━';

        $l   = [];
        $l[] = '*' . mb_strtoupper((string) ($empresa['nombre_comercial'] ?? $empresa['razon_social'] ?? 'Inversiones Arango'), 'UTF-8') . '*';
        // Solo el primer teléfono de la tienda (algunas tienen varios en el mismo campo)
        $telTienda = trim((string) (preg_split('/[\/,;\n]| y |\s{2,}/u', trim((string) ($tienda['telefono'] ?? '')))[0] ?? ''));
        $l[] = trim(($tienda['nombre'] ?? '') . ($telTienda !== '' ? ' · Tel. ' . $telTienda : ''));
        $l[] = $linea;
        $l[] = '*ESTADO DE CUENTA*';
        $l[] = 'Cliente: ' . $c['nombre'] . ($c['codigo'] ? ' (' . $c['codigo'] . ')' : '');
        $l[] = 'Fecha: ' . date('d/m/Y');
        $l[] = '';

        if ($pend) {
            $l[] = '*Facturas pendientes*';
            foreach ($pend as $p) {
                $abonado = (float) ($p['abonado'] ?? 0);
                $l[] = '• ' . $fecha($p['fecha'] ?? null) . ' · ' . $p['numero_factura']
                    . ($p['venta_id'] === null ? ' (saldo anterior)' : '')
                    . ' · *' . $dinero((float) $p['pendiente']) . '*'
                    . ($abonado > 0 ? ' _(abonado ' . $dinero($abonado) . ')_' : '');
            }
            $l[] = '';
        }
        if ($pagos) {
            $l[] = '*Últimos pagos recibidos*';
            foreach ($pagos as $p) {
                $l[] = '• ' . $fecha($p['fecha']) . ' · ' . $p['numero'] . ' · ' . ($formas[$p['forma_pago']] ?? $p['forma_pago']) . ' · ' . $dinero((float) $p['monto']);
            }
            $l[] = '';
        }

        $l[] = $linea;
        if ($total > 0.004) {
            $l[] = '*TOTAL PENDIENTE: ' . $dinero($total) . '*';
            if ($limite > 0) {
                $l[] = 'Límite de crédito: ' . $dinero($limite) . ' · Disponible: ' . $dinero(max(0, $limite - $total));
            }
            $l[] = '';
            $l[] = 'Puede realizar su abono en nuestra tienda en efectivo, por Yappy o transferencia. Si ya realizó el pago, por favor envíenos el comprobante y disculpe la molestia.';
        } else {
            $l[] = '*Su cuenta está al día.* No tiene saldo pendiente.';
        }
        $l[] = '';
        $l[] = '¡Gracias por su preferencia!';

        $texto    = implode("\n", $l);
        $telefono = self::telefonoWhatsapp((string) $c['telefono']);
        return [
            'texto'    => $texto,
            'telefono' => $telefono,
            // Sin teléfono válido WhatsApp abre para elegir el contacto
            'url'      => 'https://wa.me/' . $telefono . '?text=' . rawurlencode($texto),
            'saldo'    => $total,
        ];
    }

    /**
     * Número para wa.me: solo dígitos, con código de país. Panamá: 8 dígitos (celular 6xxx-xxxx)
     * o 7 (fijo) → se antepone 507. '' si no se puede (WhatsApp deja elegir el contacto).
     */
    public static function telefonoWhatsapp(string $tel): string
    {
        // Si hay varios números ("6589-8898 / 6836-9298") se usa el primero
        $primero = preg_split('/[\/,;]| y |\s{2,}/u', trim($tel))[0] ?? '';
        $d = preg_replace('/\D/', '', $primero) ?? '';
        if ($d === '') return '';
        if (str_starts_with($d, '00')) $d = substr($d, 2);
        if (strlen($d) === 8 || strlen($d) === 7) return '507' . $d;
        if (strlen($d) >= 10) return $d;
        return '';
    }
}

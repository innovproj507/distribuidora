<?php
declare(strict_types=1);

namespace App\Libraries;

/**
 * Saldos anteriores: créditos que los clientes ya debían antes de usar el sistema.
 * No son ventas (no tocan inventario, caja ni reportes de ventas): suben clientes.saldo_pendiente
 * y se cobran con CobroService igual que una factura a crédito (numero SA-000001).
 * Se cargan uno por uno (detalle del cliente) o desde Excel (plantilla → revisar → aplicar).
 */
final class SaldoAnteriorService
{
    public const ENCABEZADOS = ['ID', 'Código', 'Cliente', 'RUC', 'Teléfono', 'Debe hoy en el sistema (referencia)', 'Saldo anterior', 'Fecha', 'Referencia', 'Límite de crédito'];

    /**
     * Registra un saldo anterior. El llamador abre la transacción.
     * @param array{empresa_id:int, sucursal_id:?int} $ctx
     */
    public static function registrar(int $clienteId, float $monto, string $fecha, ?string $referencia, ?string $notas, array $ctx): array
    {
        $monto = round($monto, 2);
        if ($monto <= 0) {
            throw new \DomainException('El monto debe ser mayor que cero.');
        }
        $fecha = self::fecha($fecha) ?? throw new \DomainException('Fecha no válida.');
        if ($fecha > date('Y-m-d')) {
            throw new \DomainException('La fecha no puede ser futura.');
        }
        $cliente = Db::row("SELECT cliente_id, nombre FROM clientes WHERE cliente_id = ? AND empresa_id = ? FOR UPDATE", [$clienteId, $ctx['empresa_id']]);
        if (!$cliente) {
            throw new \DomainException('Cliente no encontrado.');
        }

        $id = Db::insert('saldos_anteriores', [
            'empresa_id'  => $ctx['empresa_id'],
            'sucursal_id' => $ctx['sucursal_id'] ?? null,
            'cliente_id'  => $clienteId,
            'referencia'  => self::texto($referencia, 100),
            'fecha'       => $fecha,
            'monto'       => $monto,
            'estado'      => 'pendiente',
            'notas'       => self::texto($notas, 2000),
            'usuario_id'  => Auth::id(),
        ]);
        $numero = 'SA-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
        Db::update('saldos_anteriores', ['numero' => $numero], ['saldo_id' => $id]);
        Db::query("UPDATE clientes SET saldo_pendiente = saldo_pendiente + ? WHERE cliente_id = ?", [$monto, $clienteId]);

        return ['saldo_id' => $id, 'numero' => $numero, 'monto' => $monto, 'cliente' => $cliente['nombre']];
    }

    /** Anula un saldo anterior cargado por error (solo si no tiene abonos). El llamador abre la transacción. */
    public static function anular(int $saldoId, int $empresaId): array
    {
        $s = Db::row("SELECT * FROM saldos_anteriores WHERE saldo_id = ? AND empresa_id = ? FOR UPDATE", [$saldoId, $empresaId]);
        if (!$s || $s['estado'] === 'anulada') {
            throw new \DomainException('Saldo anterior no encontrado.');
        }
        $abonado = (float) Db::value("SELECT COALESCE(SUM(monto), 0) FROM cobros_ventas WHERE saldo_id = ?", [$saldoId]);
        if ($abonado > 0.004) {
            throw new \DomainException('Ya tiene abonos ($' . number_format($abonado, 2) . '); no se puede anular.');
        }
        Db::update('saldos_anteriores', ['estado' => 'anulada'], ['saldo_id' => $saldoId]);
        Db::query("UPDATE clientes SET saldo_pendiente = GREATEST(0, saldo_pendiente - ?) WHERE cliente_id = ?", [$s['monto'], $s['cliente_id']]);
        return $s;
    }

    /** Saldos anteriores del cliente con lo abonado y lo que falta. */
    public static function delCliente(int $clienteId, int $empresaId): array
    {
        return Db::all(
            "SELECT s.*, COALESCE((SELECT SUM(monto) FROM cobros_ventas WHERE saldo_id = s.saldo_id), 0) AS abonado
             FROM saldos_anteriores s WHERE s.cliente_id = ? AND s.empresa_id = ? ORDER BY s.fecha DESC, s.saldo_id DESC",
            [$clienteId, $empresaId]
        );
    }

    // ── Importar desde Excel ─────────────────────────────────────────────────

    /** Una fila por cliente activo, con lo que debe hoy como referencia y las columnas a llenar vacías. */
    public static function plantilla(int $empresaId): array
    {
        return array_map(fn (array $c): array => [
            (int) $c['cliente_id'], $c['codigo'] ?? '', $c['nombre'], $c['ruc'] ?? '', $c['telefono'] ?? '',
            (float) $c['saldo_pendiente'], '', '', '', '',
        ], Db::all(
            "SELECT cliente_id, codigo, nombre, ruc, telefono, saldo_pendiente FROM clientes
             WHERE empresa_id = ? AND estado = 'activo' ORDER BY nombre",
            [$empresaId]
        ));
    }

    /**
     * Valida las filas del archivo sin guardar nada. 'accion': nuevo (saldo a cliente existente),
     * cliente_nuevo (se crea el cliente), repetido (ese saldo ya estaba cargado), error.
     *
     * @param array<int, array<int, string>> $filas
     */
    public static function analizar(int $empresaId, array $filas): array
    {
        if (!$filas) {
            throw new \DomainException('El archivo está vacío.');
        }
        $cols = self::columnas($filas[0]);

        $porId = $porCodigo = $porNombre = [];
        foreach (Db::all("SELECT cliente_id, codigo, nombre, limite_credito FROM clientes WHERE empresa_id = ?", [$empresaId]) as $c) {
            $porId[(int) $c['cliente_id']] = $c;
            if (trim((string) $c['codigo']) !== '') $porCodigo[mb_strtolower(trim((string) $c['codigo']))] = $c;
            $porNombre[self::normalizar((string) $c['nombre'])][] = $c;
        }
        $cargados = [];
        foreach (Db::all("SELECT cliente_id, monto, fecha FROM saldos_anteriores WHERE empresa_id = ? AND estado <> 'anulada'", [$empresaId]) as $s) {
            $cargados[$s['cliente_id'] . '|' . number_format((float) $s['monto'], 2, '.', '') . '|' . $s['fecha']] = true;
        }

        $val = fn (array $f, string $k): string => isset($cols[$k]) ? trim((string) ($f[$cols[$k]] ?? '')) : '';
        $lineas = [];
        $hoy = date('Y-m-d');
        foreach (array_slice($filas, 1, null, true) as $i => $f) {
            $montoTxt = $val($f, 'monto');
            if ($montoTxt === '' || self::decimal($montoTxt) === 0.0) {
                continue; // cliente sin saldo anterior: se ignora
            }
            $l = [
                'fila' => $i + 1, 'cliente_id' => 0, 'cliente' => $val($f, 'nombre'), 'codigo' => $val($f, 'codigo'),
                'ruc' => $val($f, 'ruc'), 'telefono' => $val($f, 'telefono'),
                'monto' => 0.0, 'fecha' => '', 'referencia' => mb_substr($val($f, 'referencia'), 0, 100),
                'limite' => null, 'limite_antes' => null, 'accion' => 'error', 'error' => '',
            ];

            // Cliente: por ID, luego código, luego nombre exacto
            $c = null;
            $idTxt = $val($f, 'id');
            if ($idTxt !== '' && ctype_digit($idTxt)) {
                $c = $porId[(int) $idTxt] ?? null;
            }
            if (!$c && $l['codigo'] !== '') {
                $c = $porCodigo[mb_strtolower($l['codigo'])] ?? null;
            }
            if (!$c && $l['cliente'] !== '') {
                $mismos = $porNombre[self::normalizar($l['cliente'])] ?? [];
                if (count($mismos) > 1) {
                    $l['error'] = 'Hay ' . count($mismos) . ' clientes con ese nombre: usa la columna ID de la plantilla.';
                }
                $c = count($mismos) === 1 ? $mismos[0] : null;
            }

            $monto  = self::decimal($montoTxt);
            $fechaT = $val($f, 'fecha');
            $fecha  = $fechaT === '' ? $hoy : self::fecha($fechaT);
            $limT   = $val($f, 'limite');
            $limite = $limT === '' ? null : self::decimal($limT);

            if ($l['error'] !== '') {
                // ya tiene error (nombre ambiguo)
            } elseif (!$c && $idTxt !== '' && ctype_digit($idTxt)) {
                $l['error'] = "No existe un cliente con ID {$idTxt}.";
            } elseif (!$c && $l['cliente'] === '') {
                $l['error'] = 'Falta el nombre del cliente.';
            } elseif ($monto === null || $monto < 0) {
                $l['error'] = 'El saldo anterior no es un número válido.';
            } elseif ($fecha === null) {
                $l['error'] = 'Fecha no válida (usa día/mes/año).';
            } elseif ($fecha > $hoy) {
                $l['error'] = 'La fecha no puede ser futura.';
            } elseif ($limite === null && $limT !== '' || $limite !== null && $limite < 0) {
                $l['error'] = 'El límite de crédito no es un número válido.';
            }

            if ($c) {
                $l['cliente_id'] = (int) $c['cliente_id'];
                $l['cliente']    = $c['nombre'];
                $l['codigo']     = (string) $c['codigo'];
            }
            if ($l['error'] === '') {
                $l['monto']  = round($monto, 2);
                $l['fecha']  = $fecha;
                $l['limite'] = $limite !== null ? round($limite, 2) : null;
                if ($c) {
                    $l['limite_antes'] = (float) $c['limite_credito'];
                    $clave = $c['cliente_id'] . '|' . number_format($l['monto'], 2, '.', '') . '|' . $fecha;
                    $l['accion'] = isset($cargados[$clave]) ? 'repetido' : 'nuevo';
                } else {
                    $l['accion'] = 'cliente_nuevo';
                }
            }
            $lineas[] = $l;
        }
        return $lineas;
    }

    /** Guarda las líneas nuevo/cliente_nuevo. Devuelve [saldos, clientes creados, total]. El llamador abre la transacción. */
    public static function aplicar(array $lineas, array $ctx): array
    {
        $saldos = $creados = 0;
        $total = 0.0;
        $nuevos = []; // mismo cliente nuevo en varias filas → se crea una vez
        foreach ($lineas as $l) {
            if (!in_array($l['accion'], ['nuevo', 'cliente_nuevo'], true)) continue;

            $clienteId = (int) $l['cliente_id'];
            if ($l['accion'] === 'cliente_nuevo') {
                $clave = self::normalizar($l['cliente']);
                if (!isset($nuevos[$clave])) {
                    $nuevos[$clave] = Db::insert('clientes', [
                        'empresa_id'     => $ctx['empresa_id'],
                        'codigo'         => $l['codigo'] !== '' ? $l['codigo'] : NumeracionService::siguiente('cliente', $ctx['empresa_id']),
                        'nombre'         => mb_substr($l['cliente'], 0, 200),
                        'tipo'           => 'natural',
                        'ruc'            => $l['ruc'],
                        'telefono'       => $l['telefono'],
                        'limite_credito' => $l['limite'] ?? 0,
                        'estado'         => 'activo',
                        'saldo'          => 0,
                        'vendedor_id'    => Auth::id(),
                        'fecha_registro' => date('Y-m-d H:i:s'),
                    ]);
                    $creados++;
                }
                $clienteId = $nuevos[$clave];
            } elseif ($l['limite'] !== null) {
                Db::query("UPDATE clientes SET limite_credito = ? WHERE cliente_id = ? AND empresa_id = ?", [$l['limite'], $clienteId, $ctx['empresa_id']]);
            }

            self::registrar($clienteId, (float) $l['monto'], $l['fecha'], $l['referencia'] ?: 'Saldo anterior (importado)', null, $ctx);
            $saldos++;
            $total += (float) $l['monto'];
        }
        return [$saldos, $creados, round($total, 2)];
    }

    // ── Utilidades ───────────────────────────────────────────────────────────

    private static function columnas(array $encabezado): array
    {
        $cols = [];
        foreach ($encabezado as $i => $h) {
            $h = self::normalizar((string) $h);
            if (!isset($cols['id']) && $h === 'id') $cols['id'] = $i;
            elseif (!isset($cols['codigo']) && str_starts_with($h, 'codigo')) $cols['codigo'] = $i;
            elseif (!isset($cols['nombre']) && (str_starts_with($h, 'cliente') || $h === 'nombre')) $cols['nombre'] = $i;
            elseif (!isset($cols['ruc']) && (str_starts_with($h, 'ruc') || $h === 'cedula')) $cols['ruc'] = $i;
            elseif (!isset($cols['telefono']) && str_starts_with($h, 'telefono')) $cols['telefono'] = $i;
            elseif (!isset($cols['monto']) && (str_starts_with($h, 'saldo anterior') || $h === 'monto' || $h === 'debe' || $h === 'saldo')) $cols['monto'] = $i;
            elseif (!isset($cols['fecha']) && str_starts_with($h, 'fecha')) $cols['fecha'] = $i;
            elseif (!isset($cols['referencia']) && (str_starts_with($h, 'referencia') || str_starts_with($h, 'factura') || str_starts_with($h, 'nota'))) $cols['referencia'] = $i;
            elseif (!isset($cols['limite']) && str_starts_with($h, 'limite')) $cols['limite'] = $i;
        }
        $faltan = [];
        if (!isset($cols['monto'])) $faltan[] = 'Saldo anterior';
        if (!isset($cols['id']) && !isset($cols['codigo']) && !isset($cols['nombre'])) $faltan[] = 'ID, Código o Cliente';
        if ($faltan) {
            throw new \DomainException('Faltan columnas en la primera fila: ' . implode(', ', $faltan) . '. Usa la plantilla descargada.');
        }
        return $cols;
    }

    /** "2025-03-15", "15/03/2025", "15-03-25" o número de fecha de Excel → Y-m-d; null si no es fecha. */
    public static function fecha(string $v): ?string
    {
        $v = trim($v);
        if (preg_match('/^\d{4,5}(\.\d+)?$/', $v) && (float) $v > 20000 && (float) $v < 80000) {
            return date('Y-m-d', (int) round(((float) $v - 25569) * 86400)); // serial de Excel
        }
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $v, $m)) {
            [$a, $mes, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{2}|\d{4})$#', $v, $m)) {
            [$d, $mes, $a] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            if ($a < 100) $a += 2000;
        } else {
            return null;
        }
        return checkdate($mes, $d, $a) ? sprintf('%04d-%02d-%02d', $a, $mes, $d) : null;
    }

    private static function texto(?string $v, int $max): ?string
    {
        $v = trim((string) $v);
        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    private static function normalizar(string $s): string
    {
        $s = preg_replace('/\s+/', ' ', mb_strtolower(trim($s)));
        return strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
    }

    /** "150", "150.50", "1,250.00", "$150,50" → float; null si no es número. */
    private static function decimal(string $v): ?float
    {
        $v = str_replace(['$', ' ', "\u{00A0}"], '', $v);
        if (preg_match('/^\d+,\d{1,2}$/', $v)) $v = str_replace(',', '.', $v);
        $v = str_replace(',', '', $v);
        return is_numeric($v) ? (float) $v : null;
    }
}

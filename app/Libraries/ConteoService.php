<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Inventario físico por sesiones: foto del stock → conteo → ajuste de diferencias.
 * Usado por la web (ConteoController) y la PWA (/m/conteos). El llamador abre y cierra la transacción
 * donde se indica.
 *
 * El ajuste se aplica como diferencia relativa (contado − foto) sobre el stock ACTUAL, así que las ventas
 * hechas mientras se cuenta no se pisan.
 */
final class ConteoService
{
    /** Ubicaciones usadas en un depósito, con cuántos productos tiene cada una (para elegir qué estante contar). */
    public static function ubicaciones(int $depositoId): array
    {
        return Db::all(
            "SELECT COALESCE(NULLIF(TRIM(i.ubicacion), ''), '') AS ubicacion, COUNT(*) AS productos
             FROM inventario i JOIN productos p ON p.producto_id = i.producto_id AND p.estado = 'activo'
             WHERE i.deposito_id = ? GROUP BY 1 ORDER BY (1 = ''), 1",
            [$depositoId]
        );
    }

    /**
     * Abre una sesión con la foto del stock de los productos que cumplan el filtro.
     *
     * @param array $filtro {ubicacion?: string ('' = sin ubicación), categoria_id?: int}
     * @return int conteo_id
     */
    public static function crear(int $empresaId, int $sucursalId, int $depositoId, string $tipo, string $descripcion, array $filtro = []): int
    {
        $tipo   = in_array($tipo, ['completo', 'ciclico', 'aleatorio'], true) ? $tipo : 'completo';
        $where  = ["i.deposito_id = ?", "p.empresa_id = ?", "p.estado = 'activo'"];
        $params = [$depositoId, $empresaId];

        if (array_key_exists('ubicacion', $filtro) && $filtro['ubicacion'] !== null) {
            $ub = trim((string) $filtro['ubicacion']);
            if ($ub === '') {
                $where[] = "(i.ubicacion IS NULL OR TRIM(i.ubicacion) = '')";
            } else {
                $where[]  = 'i.ubicacion = ?';
                $params[] = $ub;
            }
        }
        if (!empty($filtro['categoria_id'])) {
            $where[]  = 'p.categoria_id = ?';
            $params[] = (int) $filtro['categoria_id'];
        }

        $conteoId = Db::insert('inventario_conteos', [
            'empresa_id'   => $empresaId,
            'sucursal_id'  => $sucursalId,
            'deposito_id'  => $depositoId,
            'descripcion'  => mb_substr($descripcion, 0, 200),
            'tipo'         => $tipo,
            'estado'       => 'en_proceso',
            'usuario_id'   => Auth::id(),
            'fecha_inicio' => date('Y-m-d H:i:s'),
        ]);

        Db::query(
            "INSERT INTO conteo_detalle (conteo_id, producto_id, cantidad_sistema, costo_unitario)
             SELECT ?, p.producto_id, i.existencia, i.costo_promedio
             FROM inventario i JOIN productos p ON p.producto_id = i.producto_id
             WHERE " . implode(' AND ', $where),
            array_merge([$conteoId], $params)
        );
        $total = (int) Db::value("SELECT COUNT(*) FROM conteo_detalle WHERE conteo_id = ?", [$conteoId]);
        if ($total === 0) {
            throw new \RuntimeException('Ese filtro no tiene productos para contar.');
        }
        Db::update('inventario_conteos', ['total_productos' => $total], ['conteo_id' => $conteoId]);

        return $conteoId;
    }

    /** Conteo de la sucursal (null si no existe o es de otra). */
    public static function buscar(int $conteoId, int $empresaId, int $sucursalId): ?array
    {
        return Db::row(
            "SELECT c.*, d.nombre AS deposito_nombre FROM inventario_conteos c
             JOIN depositos d ON c.deposito_id = d.deposito_id
             WHERE c.conteo_id = ? AND c.empresa_id = ? AND c.sucursal_id = ?",
            [$conteoId, $empresaId, $sucursalId]
        );
    }

    /**
     * Anota lo contado de un producto (null = vuelve a pendiente). Si viene $ubicacion, la deja en el
     * producto de ese depósito (se cuenta recorriendo el estante: contar y ubicar en un solo paso).
     *
     * @return array{cantidad_sistema:float, cantidad_contada:?float, diferencia:?float, estado:string}
     */
    public static function anotar(int $conteoId, int $productoId, ?float $cantidad, ?string $ubicacion = null): array
    {
        $conteo = Db::row("SELECT deposito_id, estado FROM inventario_conteos WHERE conteo_id = ? FOR UPDATE", [$conteoId]);
        if (!$conteo || $conteo['estado'] !== 'en_proceso') {
            throw new \RuntimeException('El conteo no está abierto.');
        }
        if ($cantidad !== null && $cantidad < 0) {
            throw new \RuntimeException('La cantidad contada no puede ser negativa.');
        }
        if (!Db::value("SELECT 1 FROM conteo_detalle WHERE conteo_id = ? AND producto_id = ?", [$conteoId, $productoId])) {
            throw new \RuntimeException('Ese producto no está en este conteo.');
        }

        $previa = Db::row("SELECT cantidad_contada, estado FROM conteo_detalle WHERE conteo_id = ? AND producto_id = ?", [$conteoId, $productoId]);
        $cambio = $previa['estado'] === 'pendiente' || $previa['cantidad_contada'] === null || abs((float) $previa['cantidad_contada'] - (float) $cantidad) > 0.0001;
        if ($cantidad !== null && $cambio) {
            // El "sistema" que se compara es el stock AL MOMENTO DE CONTAR este producto, no el de cuando se abrió
            // el conteo: si entre la foto y el conteo hubo compras, ventas o altas, esas ya están reflejadas en lo
            // que se cuenta. (Con la foto vieja, una entrada hecha después de abrir el conteo se sumaba dos veces
            // al aplicar los ajustes.) Los movimientos posteriores a contar sí se respetan al aplicar.
            $inv = Db::row(
                "SELECT existencia, costo_promedio FROM inventario WHERE producto_id = ? AND deposito_id = ?",
                [$productoId, (int) $conteo['deposito_id']]
            );
            if ($inv) {
                Db::query(
                    "UPDATE conteo_detalle SET cantidad_sistema = ?, costo_unitario = ? WHERE conteo_id = ? AND producto_id = ?",
                    [$inv['existencia'], $inv['costo_promedio'], $conteoId, $productoId]
                );
            }
        }
        Db::query(
            "UPDATE conteo_detalle SET cantidad_contada = ?, estado = ? WHERE conteo_id = ? AND producto_id = ?",
            [$cantidad, $cantidad === null ? 'pendiente' : 'contado', $conteoId, $productoId]
        );
        if ($ubicacion !== null) {
            self::fijarUbicacion((int) $conteo['deposito_id'], $productoId, $ubicacion);
        }
        Db::query(
            "UPDATE inventario_conteos SET total_diferencias =
                (SELECT COUNT(*) FROM conteo_detalle WHERE conteo_id = ? AND diferencia <> 0 AND estado = 'contado') WHERE conteo_id = ?",
            [$conteoId, $conteoId]
        );

        $fila = Db::row("SELECT cantidad_sistema, cantidad_contada, diferencia, estado FROM conteo_detalle WHERE conteo_id = ? AND producto_id = ?", [$conteoId, $productoId]);
        return [
            'cantidad_sistema' => (float) $fila['cantidad_sistema'],
            'cantidad_contada' => $fila['cantidad_contada'] !== null ? (float) $fila['cantidad_contada'] : null,
            'diferencia'       => $fila['diferencia'] !== null ? (float) $fila['diferencia'] : null,
            'estado'           => $fila['estado'],
        ];
    }

    public static function fijarUbicacion(int $depositoId, int $productoId, string $ubicacion): void
    {
        $ubicacion = mb_substr(trim($ubicacion), 0, 100);
        Db::query("UPDATE inventario SET ubicacion = ? WHERE deposito_id = ? AND producto_id = ?", [$ubicacion !== '' ? $ubicacion : null, $depositoId, $productoId]);
    }

    /** Totales del conteo: contados, pendientes, diferencias (sobrantes/faltantes) y su impacto en costo. */
    public static function resumen(int $conteoId): array
    {
        $r = Db::row(
            "SELECT COUNT(*) AS total,
                    SUM(estado IN ('contado','ajustado')) AS contados,
                    SUM(estado = 'pendiente') AS pendientes,
                    SUM(estado = 'contado' AND diferencia > 0) AS sobrantes,
                    SUM(estado = 'contado' AND diferencia < 0) AS faltantes,
                    COALESCE(SUM(CASE WHEN estado = 'contado' THEN diferencia * costo_unitario END), 0) AS impacto,
                    COALESCE(SUM(CASE WHEN estado = 'contado' AND diferencia < 0 THEN diferencia * costo_unitario END), 0) AS impacto_faltante
             FROM conteo_detalle WHERE conteo_id = ?",
            [$conteoId]
        );
        return [
            'total' => (int) $r['total'], 'contados' => (int) $r['contados'], 'pendientes' => (int) $r['pendientes'],
            'sobrantes' => (int) $r['sobrantes'], 'faltantes' => (int) $r['faltantes'],
            'impacto' => round((float) $r['impacto'], 2), 'impacto_faltante' => round((float) $r['impacto_faltante'], 2),
        ];
    }

    /**
     * Lleva el stock a lo contado (diferencias de las líneas contadas) y cierra la sesión.
     * El llamador abre y cierra la transacción.
     *
     * @return int líneas ajustadas
     */
    public static function aplicarAjustes(int $conteoId, int $empresaId, int $sucursalId): int
    {
        $conteo = self::buscar($conteoId, $empresaId, $sucursalId);
        if (!$conteo || $conteo['estado'] !== 'en_proceso') {
            throw new \RuntimeException('Conteo ya procesado.');
        }

        $items = Db::all("SELECT * FROM conteo_detalle WHERE conteo_id = ? AND estado = 'contado' AND diferencia <> 0", [$conteoId]);
        foreach ($items as $item) {
            $inv = Db::row(
                "SELECT inventario_id, existencia FROM inventario WHERE producto_id = ? AND deposito_id = ? FOR UPDATE",
                [$item['producto_id'], $conteo['deposito_id']]
            );
            if (!$inv) continue;

            $dif           = (float) $item['diferencia'];
            $saldoAnterior = (float) $inv['existencia'];
            $saldoNuevo    = $saldoAnterior + $dif;

            // tipo 'ajuste' con saldo explícito: InventarioService::registrar() trata 'ajuste' como salida
            Db::query("UPDATE inventario SET existencia = ? WHERE inventario_id = ?", [$saldoNuevo, $inv['inventario_id']]);
            Db::insert('inventario_movimientos', [
                'inventario_id'   => $inv['inventario_id'],
                'tipo'            => 'ajuste',
                'cantidad'        => abs($dif),
                'saldo_anterior'  => $saldoAnterior,
                'saldo_nuevo'     => $saldoNuevo,
                'usuario_id'      => Auth::id(),
                'referencia_id'   => $conteoId,
                'referencia_tipo' => 'conteo',
                'motivo'          => 'Ajuste por conteo físico (' . ($dif > 0 ? 'sobrante' : 'faltante') . ')',
            ]);
            Db::update('conteo_detalle', ['estado' => 'ajustado'], ['detalle_id' => $item['detalle_id']]);
        }
        Db::update('inventario_conteos', ['estado' => 'completado', 'fecha_cierre' => date('Y-m-d H:i:s')], ['conteo_id' => $conteoId]);

        return count($items);
    }

    public static function cancelar(int $conteoId, int $empresaId, int $sucursalId): void
    {
        $conteo = self::buscar($conteoId, $empresaId, $sucursalId);
        if (!$conteo) {
            throw new \RuntimeException('Conteo no encontrado.');
        }
        if ($conteo['estado'] === 'completado') {
            throw new \RuntimeException('Un conteo ya aplicado no se puede cancelar.');
        }
        Db::update('inventario_conteos', ['estado' => 'cancelado'], ['conteo_id' => $conteoId]);
    }
}

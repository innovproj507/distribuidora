<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Traslados entre depósitos: enviar() saca del origen (traslado_salida) y deja el traslado
 * en tránsito; recibir() lo ingresa al destino (traslado_entrada). Lo usan la web y la API.
 * El llamador abre y cierra la transacción.
 */
final class TrasladoService
{
    /**
     * @param array $items [{producto_id, cantidad}]
     * @param array $ctx   empresa_id, sucursal_id (el origen debe ser de esta sucursal)
     */
    public static function enviar(int $origenId, int $destinoId, array $items, string $notas, array $ctx): int
    {
        if (!$origenId || !$destinoId || $origenId === $destinoId) {
            throw new \RuntimeException('Origen y destino deben ser depósitos distintos.');
        }
        if (empty($items)) {
            throw new \RuntimeException('Agregue al menos un producto.');
        }
        if (!Db::value("SELECT 1 FROM depositos WHERE deposito_id = ? AND sucursal_id = ? AND estado = 'activo'", [$origenId, $ctx['sucursal_id']])) {
            throw new \RuntimeException('El depósito de origen no pertenece a esta sucursal.');
        }
        if (!Db::value(
            "SELECT 1 FROM depositos d JOIN branches b ON d.sucursal_id = b.sucursal_id WHERE d.deposito_id = ? AND b.empresa_id = ? AND d.estado = 'activo'",
            [$destinoId, $ctx['empresa_id']]
        )) {
            throw new \RuntimeException('El depósito de destino no es válido.');
        }

        $trasladoId = Db::insert('traslados', [
            'empresa_id'          => $ctx['empresa_id'],
            'deposito_origen_id'  => $origenId,
            'deposito_destino_id' => $destinoId,
            'usuario_envia_id'    => Auth::id(),
            'estado'              => 'en_transito',
            'fecha_envio'         => date('Y-m-d H:i:s'),
            'notas'               => $notas,
        ]);
        Db::update('traslados', ['numero' => 'TR-' . str_pad((string) $trasladoId, 6, '0', STR_PAD_LEFT)], ['traslado_id' => $trasladoId]);

        foreach ($items as $item) {
            $productoId = (int) $item['producto_id'];
            $cantidad   = (float) $item['cantidad'];
            if ($cantidad <= 0) continue;

            $inv = Db::row(
                "SELECT i.inventario_id, i.existencia, p.nombre FROM inventario i JOIN productos p ON p.producto_id = i.producto_id
                 WHERE i.producto_id = ? AND i.deposito_id = ? FOR UPDATE",
                [$productoId, $origenId]
            );
            if (!$inv) {
                throw new \RuntimeException("El producto #{$productoId} no existe en el depósito de origen.");
            }
            if ($cantidad > (float) $inv['existencia']) {
                throw new \RuntimeException("Stock insuficiente de \"{$inv['nombre']}\" en origen (hay " . (float) $inv['existencia'] . ').');
            }

            Db::insert('traslados_detalle', ['traslado_id' => $trasladoId, 'producto_id' => $productoId, 'cantidad' => $cantidad]);
            InventarioService::registrar((int) $inv['inventario_id'], 'traslado_salida', $cantidad, $trasladoId, 'traslados', "Traslado #{$trasladoId} hacia destino");
        }

        return $trasladoId;
    }

    /**
     * Recibe en el destino un traslado en tránsito de la sucursal (como origen o destino).
     *
     * @param array<int,float>|null $recibidas producto_id → cantidad realmente recibida. Null = todo lo enviado.
     *                                         Una línea ausente del mapa se toma completa; la diferencia queda en
     *                                         traslados_detalle.cantidad_recibida (no vuelve al origen).
     */
    public static function recibir(int $trasladoId, array $ctx, ?array $recibidas = null): void
    {
        $traslado = Db::row(
            "SELECT t.* FROM traslados t
             JOIN depositos do ON t.deposito_origen_id = do.deposito_id
             JOIN depositos dd ON t.deposito_destino_id = dd.deposito_id
             WHERE t.traslado_id = ? AND (do.sucursal_id = ? OR dd.sucursal_id = ?) FOR UPDATE",
            [$trasladoId, $ctx['sucursal_id'], $ctx['sucursal_id']]
        );
        if (!$traslado || $traslado['estado'] !== 'en_transito') {
            throw new \RuntimeException('Este traslado no está en tránsito o ya fue recibido.');
        }

        Db::update('traslados', ['estado' => 'recibido', 'usuario_recibe_id' => Auth::id(), 'fecha_recepcion' => date('Y-m-d H:i:s')], ['traslado_id' => $trasladoId]);

        foreach (Db::all("SELECT * FROM traslados_detalle WHERE traslado_id = ?", [$trasladoId]) as $detalle) {
            $enviada  = (float) $detalle['cantidad'];
            $recibida = isset($recibidas[(int) $detalle['producto_id']]) ? (float) $recibidas[(int) $detalle['producto_id']] : $enviada;
            if ($recibida < 0 || $recibida > $enviada + 0.001) {
                throw new \RuntimeException('La cantidad recibida no puede ser negativa ni mayor a la enviada.');
            }
            Db::update('traslados_detalle', ['cantidad_recibida' => $recibida], ['detalle_id' => $detalle['detalle_id']]);
            if ($recibida <= 0) continue;
            $detalle['cantidad'] = $recibida;

            $inventarioId = InventarioService::inventarioId((int) $detalle['producto_id'], (int) $traslado['deposito_destino_id']);

            if (!$inventarioId) {
                // Nuevo en el destino: hereda el costo del origen para no quedar en 0
                $costos = Db::row(
                    "SELECT costo_promedio, ultimo_costo FROM inventario WHERE producto_id = ? AND deposito_id = ?",
                    [$detalle['producto_id'], $traslado['deposito_origen_id']]
                );
                $inventarioId = Db::insert('inventario', [
                    'producto_id'    => $detalle['producto_id'],
                    'deposito_id'    => $traslado['deposito_destino_id'],
                    'existencia'     => 0,
                    'costo_promedio' => $costos['costo_promedio'] ?? 0,
                    'ultimo_costo'   => $costos['ultimo_costo'] ?? 0,
                ]);
            }

            InventarioService::registrar($inventarioId, 'traslado_entrada', (float) $detalle['cantidad'], $trasladoId, 'traslados', "Traslado #{$trasladoId} recibido");

            // Vencimiento: si el destino no tiene fecha, toma la del origen (la mercancía es la misma)
            Db::query(
                "UPDATE inventario d JOIN inventario o ON o.producto_id = d.producto_id AND o.deposito_id = ?
                 SET d.fecha_vencimiento = o.fecha_vencimiento
                 WHERE d.inventario_id = ? AND d.fecha_vencimiento IS NULL AND o.fecha_vencimiento IS NOT NULL",
                [$traslado['deposito_origen_id'], $inventarioId]
            );
        }
    }
}

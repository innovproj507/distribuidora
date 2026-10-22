<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Genera sugerencias de órdenes de compra por dos criterios:
 *   1. stock_minimo: existencia <= minimo (punto de reorden)
 *   2. demanda_historica: cobertura < 30 días según promedio de salidas de 90 días
 */
final class OrdenCompraAutoService
{
    public function __construct(private int $empresaId) {}

    /**
     * Elimina sugerencias pendientes y regenera para la empresa.
     */
    public function regenerar(): int
    {
        Database::query(
            "DELETE FROM ordenes_compra_sugeridas WHERE empresa_id = ? AND estado = 'pendiente'",
            [$this->empresaId]
        );

        $total  = $this->generarPorStockMinimo();
        $total += $this->generarPorDemandaHistorica();

        return $total;
    }

    // ── Por punto de reorden ──────────────────────────────────────────────────

    private function generarPorStockMinimo(): int
    {
        $rows = Database::query(
            "SELECT i.inventario_id, i.producto_id, i.deposito_id, i.existencia,
                    i.minimo, i.maximo, i.costo_promedio,
                    p.nombre AS producto_nombre, p.proveedor_id, p.costo
             FROM inventario i
             JOIN productos p ON i.producto_id = p.producto_id
             WHERE p.empresa_id = ?
               AND p.estado = 'activo'
               AND i.minimo > 0
               AND i.existencia <= i.minimo",
            [$this->empresaId]
        )->fetchAll();

        $count = 0;
        foreach ($rows as $r) {
            $existencia = (float) $r['existencia'];
            $maximo     = (float) $r['maximo'];
            $minimo     = (float) $r['minimo'];

            // Cantidad a pedir: llenar hasta el máximo, o al menos 2× el mínimo
            $cantSugerida = $maximo > 0 ? max(0, $maximo - $existencia) : $minimo * 2;
            if ($cantSugerida <= 0) $cantSugerida = $minimo;

            $costo = (float) ($r['costo_promedio'] ?: $r['costo']);

            $this->insertar(
                productoId:       (int) $r['producto_id'],
                depositoId:       (int) $r['deposito_id'],
                proveedorId:      $r['proveedor_id'] ? (int) $r['proveedor_id'] : null,
                cantidadSugerida: $cantSugerida,
                costoEstimado:    $costo,
                motivo:           'stock_minimo',
            );
            $count++;
        }

        return $count;
    }

    // ── Por demanda histórica (90 días) ───────────────────────────────────────

    private function generarPorDemandaHistorica(): int
    {
        $rows = Database::query(
            "SELECT i.producto_id, i.deposito_id, i.existencia,
                    i.costo_promedio, p.proveedor_id, p.costo,
                    COALESCE(SUM(m.cantidad), 0)          AS salidas_90d,
                    COALESCE(SUM(m.cantidad) / 90, 0)     AS demanda_diaria
             FROM inventario i
             JOIN productos p ON i.producto_id = p.producto_id
             LEFT JOIN inventario_movimientos m
                    ON m.inventario_id = i.inventario_id
                   AND m.tipo IN ('salida','traslado_salida')
                   AND m.fecha_registro >= DATE_SUB(NOW(), INTERVAL 90 DAY)
             WHERE p.empresa_id = ? AND p.estado = 'activo'
             GROUP BY i.inventario_id
             HAVING demanda_diaria > 0
                AND i.existencia < (demanda_diaria * 30)",
            [$this->empresaId]
        )->fetchAll();

        $count = 0;
        foreach ($rows as $r) {
            // Omitir si ya generamos sugerencia por stock_minimo para el mismo producto-depósito
            $existe = Database::query(
                "SELECT 1 FROM ordenes_compra_sugeridas
                 WHERE empresa_id = ? AND producto_id = ? AND deposito_id = ? AND estado = 'pendiente'",
                [$this->empresaId, $r['producto_id'], $r['deposito_id']]
            )->fetch();
            if ($existe) continue;

            // Sugerir 45 días de stock (buffer extra sobre los 30 de cobertura mínima)
            $cantSugerida = round((float) $r['demanda_diaria'] * 45, 2);
            if ($cantSugerida <= 0) continue;

            $costo = (float) ($r['costo_promedio'] ?: $r['costo']);

            $this->insertar(
                productoId:       (int) $r['producto_id'],
                depositoId:       (int) $r['deposito_id'],
                proveedorId:      $r['proveedor_id'] ? (int) $r['proveedor_id'] : null,
                cantidadSugerida: $cantSugerida,
                costoEstimado:    $costo,
                motivo:           'demanda_historica',
            );
            $count++;
        }

        return $count;
    }

    private function insertar(
        int    $productoId,
        int    $depositoId,
        ?int   $proveedorId,
        float  $cantidadSugerida,
        float  $costoEstimado,
        string $motivo,
    ): void {
        Database::query(
            "INSERT INTO ordenes_compra_sugeridas
                (empresa_id, producto_id, deposito_id, proveedor_id, cantidad_sugerida, costo_estimado, motivo)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$this->empresaId, $productoId, $depositoId, $proveedorId,
             $cantidadSugerida, $costoEstimado, $motivo]
        );
    }
}

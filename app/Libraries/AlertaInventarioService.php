<?php
declare(strict_types=1);

namespace App\Libraries;


/**
 * Genera y persiste alertas de inventario consultando el estado actual de la BD.
 * Se ejecuta on-demand (al visitar la vista de alertas).
 */
final class AlertaInventarioService
{
    private int $empresaId;

    public function __construct(int $empresaId)
    {
        $this->empresaId = $empresaId;
    }

    /**
     * Regenera todas las alertas activas para la empresa.
     * Elimina las obsoletas y crea las nuevas.
     */
    public function regenerar(): void
    {
        // Limpia alertas resueltas/obsoletas antes de regenerar
        Db::query(
            "DELETE FROM alertas_inventario WHERE empresa_id = ? AND estado = 'activa'",
            [$this->empresaId]
        );

        $this->generarStockMinimo();
        $this->generarStockExceso();
        $this->generarVencimientoProximo();
        $this->generarLotesVencidos();
        $this->generarVencimientoProducto();
        $this->generarRotacionLenta();
    }

    /**
     * Vencimiento por producto y depósito (inventario.fecha_vencimiento), con stock > 0:
     * por vencer (≤ 30 días) o ya vencido.
     */
    private function generarVencimientoProducto(): void
    {
        $rows = Db::query(
            "SELECT p.producto_id, p.nombre, d.deposito_id, d.nombre AS deposito, i.existencia,
                    i.fecha_vencimiento, DATEDIFF(i.fecha_vencimiento, CURDATE()) AS dias
             FROM inventario i
             JOIN productos p ON i.producto_id = p.producto_id
             JOIN depositos  d ON i.deposito_id  = d.deposito_id
             WHERE p.empresa_id = ? AND p.estado = 'activo' AND i.existencia > 0
               AND i.fecha_vencimiento IS NOT NULL
               AND DATEDIFF(i.fecha_vencimiento, CURDATE()) <= ?",
            [$this->empresaId, InventarioService::DIAS_AVISO_VENCIMIENTO]
        )->getResultArray();

        foreach ($rows as $r) {
            $dias  = (int) $r['dias'];
            $fecha = date('d/m/Y', strtotime($r['fecha_vencimiento']));
            $cant  = rtrim(rtrim(number_format((float) $r['existencia'], 2, '.', ''), '0'), '.');
            if ($dias < 0) {
                $this->insertar(
                    tipo: 'lote_vencido',
                    mensaje: "Vencido: {$r['nombre']} ({$cant} unds) — venció el {$fecha} en {$r['deposito']}",
                    prioridad: 'critica',
                    productoId: (int) $r['producto_id'],
                    depositoId: (int) $r['deposito_id'],
                );
            } else {
                $this->insertar(
                    tipo: 'vencimiento_proximo',
                    mensaje: "Vence en {$dias} días ({$fecha}): {$r['nombre']} ({$cant} unds) en {$r['deposito']}",
                    prioridad: $dias <= 7 ? 'critica' : ($dias <= 15 ? 'alta' : 'media'),
                    productoId: (int) $r['producto_id'],
                    depositoId: (int) $r['deposito_id'],
                    diasRestantes: $dias,
                );
            }
        }
    }

    /** Productos con existencia <= stock_minimo (y minimo > 0) */
    private function generarStockMinimo(): void
    {
        $rows = Db::query(
            "SELECT p.producto_id, p.nombre, d.deposito_id, d.nombre AS deposito,
                    i.existencia, i.minimo,
                    CASE WHEN i.existencia <= 0 THEN 'critica' ELSE 'alta' END AS prioridad
             FROM inventario i
             JOIN productos p ON i.producto_id = p.producto_id
             JOIN depositos  d ON i.deposito_id  = d.deposito_id
             WHERE p.empresa_id = ? AND i.minimo > 0 AND i.existencia <= i.minimo",
            [$this->empresaId]
        )->getResultArray();

        foreach ($rows as $r) {
            $tipo = $r['existencia'] <= 0 ? 'Sin stock' : 'Stock bajo';
            $this->insertar(
                tipo: 'stock_minimo',
                mensaje: "{$tipo}: {$r['nombre']} en {$r['deposito']} — existencia {$r['existencia']} (mínimo {$r['minimo']})",
                prioridad: $r['prioridad'],
                productoId: (int) $r['producto_id'],
                depositoId: (int) $r['deposito_id'],
            );
        }
    }

    /** Productos con existencia > maximo (y maximo > 0) */
    private function generarStockExceso(): void
    {
        $rows = Db::query(
            "SELECT p.producto_id, p.nombre, d.deposito_id, d.nombre AS deposito,
                    i.existencia, i.maximo
             FROM inventario i
             JOIN productos p ON i.producto_id = p.producto_id
             JOIN depositos  d ON i.deposito_id  = d.deposito_id
             WHERE p.empresa_id = ? AND i.maximo > 0 AND i.existencia > i.maximo",
            [$this->empresaId]
        )->getResultArray();

        foreach ($rows as $r) {
            $exceso = $r['existencia'] - $r['maximo'];
            $this->insertar(
                tipo: 'stock_exceso',
                mensaje: "Exceso de stock: {$r['nombre']} en {$r['deposito']} — {$exceso} unidades sobre el máximo ({$r['maximo']})",
                prioridad: 'baja',
                productoId: (int) $r['producto_id'],
                depositoId: (int) $r['deposito_id'],
            );
        }
    }

    /** Lotes que vencen en los próximos 30 días */
    private function generarVencimientoProximo(): void
    {
        $rows = Db::query(
            "SELECT l.lote_id, l.numero_lote, l.cantidad_actual,
                    DATEDIFF(l.fecha_vencimiento, CURDATE()) AS dias,
                    p.producto_id, p.nombre AS producto,
                    d.deposito_id, d.nombre AS deposito
             FROM lotes l
             JOIN productos p ON l.producto_id = p.producto_id
             JOIN depositos  d ON l.deposito_id  = d.deposito_id
             WHERE p.empresa_id = ?
               AND l.estado = 'activo'
               AND l.fecha_vencimiento IS NOT NULL
               AND l.fecha_vencimiento >= CURDATE()
               AND DATEDIFF(l.fecha_vencimiento, CURDATE()) <= 30",
            [$this->empresaId]
        )->getResultArray();

        foreach ($rows as $r) {
            $dias = (int) $r['dias'];
            $prioridad = $dias <= 7 ? 'critica' : ($dias <= 15 ? 'alta' : 'media');
            $this->insertar(
                tipo: 'vencimiento_proximo',
                mensaje: "Vence en {$dias} días: {$r['producto']} lote {$r['numero_lote']} ({$r['cantidad_actual']} unds) en {$r['deposito']}",
                prioridad: $prioridad,
                productoId: (int) $r['producto_id'],
                depositoId: (int) $r['deposito_id'],
                loteId: (int) $r['lote_id'],
                diasRestantes: $dias,
            );
        }
    }

    /** Lotes ya vencidos con stock > 0 */
    private function generarLotesVencidos(): void
    {
        $rows = Db::query(
            "SELECT l.lote_id, l.numero_lote, l.cantidad_actual,
                    DATEDIFF(CURDATE(), l.fecha_vencimiento) AS dias_vencido,
                    p.producto_id, p.nombre AS producto,
                    d.deposito_id, d.nombre AS deposito
             FROM lotes l
             JOIN productos p ON l.producto_id = p.producto_id
             JOIN depositos  d ON l.deposito_id  = d.deposito_id
             WHERE p.empresa_id = ?
               AND l.estado IN ('activo','vencido')
               AND l.cantidad_actual > 0
               AND l.fecha_vencimiento IS NOT NULL
               AND l.fecha_vencimiento < CURDATE()",
            [$this->empresaId]
        )->getResultArray();

        foreach ($rows as $r) {
            $this->insertar(
                tipo: 'lote_vencido',
                mensaje: "Lote vencido: {$r['producto']} lote {$r['numero_lote']} ({$r['cantidad_actual']} unds) — vencido hace {$r['dias_vencido']} días en {$r['deposito']}",
                prioridad: 'critica',
                productoId: (int) $r['producto_id'],
                depositoId: (int) $r['deposito_id'],
                loteId: (int) $r['lote_id'],
            );
        }
    }

    /**
     * Productos sin movimientos de salida en los últimos 90 días
     * con existencia > 0 (rotación lenta).
     */
    private function generarRotacionLenta(): void
    {
        $rows = Db::query(
            "SELECT p.producto_id, p.nombre, d.deposito_id, d.nombre AS deposito,
                    i.existencia,
                    DATEDIFF(CURDATE(), COALESCE(
                        (SELECT MAX(m2.fecha_registro)
                         FROM inventario_movimientos m2
                         WHERE m2.inventario_id = i.inventario_id
                           AND m2.tipo IN ('salida','traslado_salida')
                        ),
                        i.fecha_actualizacion
                    )) AS dias_sin_movimiento
             FROM inventario i
             JOIN productos p ON i.producto_id = p.producto_id
             JOIN depositos  d ON i.deposito_id  = d.deposito_id
             WHERE p.empresa_id = ? AND i.existencia > 0
             HAVING dias_sin_movimiento >= 90",
            [$this->empresaId]
        )->getResultArray();

        foreach ($rows as $r) {
            $dias = (int) $r['dias_sin_movimiento'];
            $this->insertar(
                tipo: 'rotacion_lenta',
                mensaje: "Sin salidas en {$dias} días: {$r['nombre']} en {$r['deposito']} ({$r['existencia']} unds en stock)",
                prioridad: $dias > 180 ? 'alta' : 'baja',
                productoId: (int) $r['producto_id'],
                depositoId: (int) $r['deposito_id'],
            );
        }
    }

    private function insertar(
        string $tipo,
        string $mensaje,
        string $prioridad,
        ?int $productoId = null,
        ?int $depositoId = null,
        ?int $loteId = null,
        ?int $diasRestantes = null,
    ): void {
        Db::query(
            "INSERT INTO alertas_inventario
                (empresa_id, deposito_id, producto_id, lote_id, tipo, mensaje, prioridad, dias_restantes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$this->empresaId, $depositoId, $productoId, $loteId, $tipo, $mensaje, $prioridad, $diasRestantes]
        );
    }
}

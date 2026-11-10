<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Costos unitarios con 10 decimales (antes 2 en compras/ventas y 4 en productos/inventario):
 * un tanque de $21 que rinde 90 porciones cuesta 0.2333333333 por porción. Los totales en dinero
 * (ventas.costo, compras.monto_*, total_linea) siguen con 2 decimales.
 *   php spark migrate
 */
class CostosDiezDecimales extends Migration
{
    private const COLUMNAS = [
        ['productos', 'costo', 'NULL DEFAULT 0'],
        ['inventario', 'costo_promedio', 'NULL DEFAULT 0'],
        ['inventario', 'ultimo_costo', 'NULL DEFAULT 0'],
        ['compras_detalle', 'costo', 'NOT NULL'],
        ['ventas_detalle', 'costo', 'NULL DEFAULT 0'],
        ['conteo_detalle', 'costo_unitario', 'NULL DEFAULT 0'],
        ['ordenes_compra_sugeridas', 'costo_estimado', 'NULL DEFAULT 0'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNAS as [$tabla, $col, $resto]) {
            $this->db->query("ALTER TABLE {$tabla} MODIFY {$col} DECIMAL(22,10) {$resto}");
        }
    }

    public function down(): void
    {
        $antes = ['productos.costo' => '12,4', 'inventario.costo_promedio' => '12,4', 'inventario.ultimo_costo' => '12,4',
                  'compras_detalle.costo' => '12,2', 'ventas_detalle.costo' => '12,2', 'conteo_detalle.costo_unitario' => '12,4',
                  'ordenes_compra_sugeridas.costo_estimado' => '12,4'];
        foreach (self::COLUMNAS as [$tabla, $col, $resto]) {
            $this->db->query("ALTER TABLE {$tabla} MODIFY {$col} DECIMAL({$antes[$tabla . '.' . $col]}) {$resto}");
        }
    }
}

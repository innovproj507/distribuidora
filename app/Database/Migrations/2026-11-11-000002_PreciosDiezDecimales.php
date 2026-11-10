<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Precios unitarios con 10 decimales (antes 2), igual que los costos (2026-11-11-000001).
 * Los totales en dinero (total_linea, ventas.total…) siguen con 2 decimales.
 *   php spark migrate
 */
class PreciosDiezDecimales extends Migration
{
    private const COLUMNAS = [
        ['precios_productos', 'precio', 'NOT NULL'],
        ['productos_unidades', 'precio_a', 'NULL DEFAULT 0'],
        ['productos_unidades', 'precio_b', 'NULL DEFAULT 0'],
        ['productos_unidades', 'precio_c', 'NULL DEFAULT 0'],
        ['ventas_detalle', 'precio', 'NOT NULL'],
        ['cotizaciones_detalle', 'precio', 'NOT NULL'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNAS as [$tabla, $col, $resto]) {
            $this->db->query("ALTER TABLE {$tabla} MODIFY {$col} DECIMAL(22,10) {$resto}");
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNAS as [$tabla, $col, $resto]) {
            $this->db->query("ALTER TABLE {$tabla} MODIFY {$col} DECIMAL(12,2) {$resto}");
        }
    }
}

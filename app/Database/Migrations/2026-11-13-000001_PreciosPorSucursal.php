<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Precios por tienda (decisión 2026-11-13: el arroz no vale lo mismo en Rio Tigre que en Rio Sidra).
 *  - precios_productos pasa a tener sucursal_id (PK producto + sucursal + tipo).
 *  - productos_unidades_precios: precio A/B/C de cada presentación (caja, tira…) por tienda.
 * Cada tienda arranca con el precio que había (se copia a todas las sucursales).
 * Los costos ya eran por depósito (inventario.costo_promedio); no cambia el esquema.
 *   php spark migrate
 */
class PreciosPorSucursal extends Migration
{
    public function up(): void
    {
        $db = $this->db;

        // Primero la llave nueva (las filas actuales quedan con sucursal 0), después la copia a cada tienda
        $db->query(
            "ALTER TABLE precios_productos ADD COLUMN sucursal_id INT NOT NULL DEFAULT 0 AFTER producto_id,
             DROP PRIMARY KEY, ADD PRIMARY KEY (producto_id, sucursal_id, tipo_precio), ADD KEY idx_precios_sucursal (sucursal_id, tipo_precio)"
        );
        $db->query(
            "INSERT INTO precios_productos (producto_id, sucursal_id, tipo_precio, precio, fecha_inicio)
             SELECT pp.producto_id, b.sucursal_id, pp.tipo_precio, pp.precio, pp.fecha_inicio
             FROM precios_productos pp CROSS JOIN branches b WHERE pp.sucursal_id = 0"
        );
        $db->query("DELETE FROM precios_productos WHERE sucursal_id = 0");
        $db->query("ALTER TABLE precios_productos ALTER sucursal_id DROP DEFAULT");

        $db->query(
            "CREATE TABLE productos_unidades_precios (
                unidad_id   INT NOT NULL,
                sucursal_id INT NOT NULL,
                precio_a    DECIMAL(22,10) NOT NULL DEFAULT 0,
                precio_b    DECIMAL(22,10) NOT NULL DEFAULT 0,
                precio_c    DECIMAL(22,10) NOT NULL DEFAULT 0,
                PRIMARY KEY (unidad_id, sucursal_id),
                KEY idx_upp_sucursal (sucursal_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $db->query(
            "INSERT INTO productos_unidades_precios (unidad_id, sucursal_id, precio_a, precio_b, precio_c)
             SELECT u.unidad_id, b.sucursal_id, COALESCE(u.precio_a, 0), COALESCE(u.precio_b, 0), COALESCE(u.precio_c, 0)
             FROM productos_unidades u CROSS JOIN branches b"
        );
    }

    public function down(): void
    {
        // Vuelve a un precio único: el de la sucursal principal (o la de menor id)
        $db = $this->db;
        $principal = (int) ($db->query("SELECT sucursal_id FROM branches ORDER BY es_principal DESC, sucursal_id LIMIT 1")->getRow()->sucursal_id ?? 0);
        $db->query("DELETE FROM precios_productos WHERE sucursal_id <> ?", [$principal]);
        $db->query("ALTER TABLE precios_productos DROP KEY idx_precios_sucursal, DROP PRIMARY KEY, DROP COLUMN sucursal_id, ADD PRIMARY KEY (producto_id, tipo_precio)");
        $db->query(
            "UPDATE productos_unidades u JOIN productos_unidades_precios p ON p.unidad_id = u.unidad_id AND p.sucursal_id = ?
             SET u.precio_a = p.precio_a, u.precio_b = p.precio_b, u.precio_c = p.precio_c",
            [$principal]
        );
        $db->query("DROP TABLE productos_unidades_precios");
    }
}

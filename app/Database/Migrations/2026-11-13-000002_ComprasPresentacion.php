<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Presentación en que se escribió cada línea de compra (Caja de 6, Bulto de 54…), para que al editar
 * la orden se vea igual que en la factura. cantidad/costo siguen en unidades base.
 *   php spark migrate
 */
class ComprasPresentacion extends Migration
{
    public function up(): void
    {
        $this->db->query(
            "ALTER TABLE compras_detalle
             ADD COLUMN presentacion VARCHAR(50) NULL AFTER costo,
             ADD COLUMN factor_presentacion DECIMAL(12,2) NOT NULL DEFAULT 1 AFTER presentacion"
        );
    }

    public function down(): void
    {
        $this->db->query("ALTER TABLE compras_detalle DROP COLUMN presentacion, DROP COLUMN factor_presentacion");
    }
}

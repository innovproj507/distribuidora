<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * numeraciones.con_sucursal: si el número de factura/cotización lleva delante el prefijo de la
 * sucursal (TIG-FAC-000001). Por defecto 1 (como hasta ahora); se apaga en Configuración →
 * Nomenclatura para una serie única de la empresa (FAC-2026-00001).
 *   php spark migrate
 */
class NumeracionPrefijoSucursal extends Migration
{
    public function up(): void
    {
        $this->db->query("ALTER TABLE numeraciones ADD COLUMN con_sucursal TINYINT(1) NOT NULL DEFAULT 1 AFTER reinicio");
    }

    public function down(): void
    {
        $this->db->query("ALTER TABLE numeraciones DROP COLUMN con_sucursal");
    }
}

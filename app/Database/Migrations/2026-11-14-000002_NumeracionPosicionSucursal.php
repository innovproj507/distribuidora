<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * numeraciones.posicion_sucursal: dónde va el prefijo de la sucursal en facturas/cotizaciones.
 *   antes   → TIG-FAC-2026-000001 (como hasta ahora)
 *   despues → FAC-T-2026-000001
 *   php spark migrate
 */
class NumeracionPosicionSucursal extends Migration
{
    public function up(): void
    {
        $this->db->query("ALTER TABLE numeraciones ADD COLUMN posicion_sucursal VARCHAR(10) NOT NULL DEFAULT 'antes' AFTER con_sucursal");
    }

    public function down(): void
    {
        $this->db->query("ALTER TABLE numeraciones DROP COLUMN posicion_sucursal");
    }
}

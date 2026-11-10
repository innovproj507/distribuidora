<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * ventas.comprobante: foto del comprobante de Yappy / transferencia. El archivo se guarda en
 * writable/uploads/comprobantes (no es público) y se sirve con permiso desde MovilApiController.
 *   php spark migrate
 */
class VentasComprobante extends Migration
{
    public function up(): void
    {
        $this->db->query("ALTER TABLE ventas ADD COLUMN comprobante VARCHAR(255) NULL AFTER notas");
    }

    public function down(): void
    {
        $this->db->query("ALTER TABLE ventas DROP COLUMN comprobante");
    }
}

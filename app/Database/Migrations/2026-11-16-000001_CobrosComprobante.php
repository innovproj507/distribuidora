<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * cobros.comprobante: foto del comprobante de un abono por Yappy / transferencia / cheque (y voucher
 * de tarjeta, opcional). El archivo va en writable/uploads/comprobantes (no público), igual que ventas.
 *   php spark migrate
 */
class CobrosComprobante extends Migration
{
    public function up(): void
    {
        $this->db->query("ALTER TABLE cobros ADD COLUMN comprobante VARCHAR(255) NULL AFTER referencia");
    }

    public function down(): void
    {
        $this->db->query("ALTER TABLE cobros DROP COLUMN comprobante");
    }
}

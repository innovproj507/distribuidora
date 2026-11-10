<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * ventas_pagos.referencia: nº de comprobante de Yappy / transferencia (opcional).
 *   php spark migrate
 */
class VentasPagosReferencia extends Migration
{
    public function up(): void
    {
        $this->db->query("ALTER TABLE ventas_pagos ADD COLUMN referencia VARCHAR(100) NULL AFTER monto");
    }

    public function down(): void
    {
        $this->db->query("ALTER TABLE ventas_pagos DROP COLUMN referencia");
    }
}

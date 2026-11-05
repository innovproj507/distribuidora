<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Lo ya enviado a otras tiendas por línea de compra ({"deposito_id": cantidad}).   php spark migrate */
class ComprasDetalleRepartoEnviado extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('compras_detalle', [
            'reparto_enviado' => ['type' => 'TEXT', 'null' => true, 'after' => 'reparto'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('compras_detalle', 'reparto_enviado');
    }
}

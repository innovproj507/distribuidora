<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pedido consolidado a la ciudad: la compra llega al depósito principal y aquí queda anotado
 * cuánto de cada línea va luego a otras tiendas ({"deposito_id": cantidad}).   php spark migrate
 */
class ComprasDetalleReparto extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('compras_detalle', [
            'reparto' => ['type' => 'TEXT', 'null' => true, 'after' => 'cantidad_recibida'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('compras_detalle', 'reparto');
    }
}

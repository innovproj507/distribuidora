<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pedido de la tienda a la persona que compra en la ciudad (lista por WhatsApp).
 * El pedido queda como compra pendiente (compras.origen = 'pedido') con el texto enviado,
 * y la empresa guarda a quién se le manda (nombre y WhatsApp del comprador).
 *   php spark migrate
 */
class PedidosTienda extends Migration
{
    public function up(): void
    {
        $this->db->query("ALTER TABLE companies
            ADD COLUMN pedido_contacto VARCHAR(80) NULL,
            ADD COLUMN pedido_telefono VARCHAR(30) NULL");
        $this->db->query("ALTER TABLE compras
            ADD COLUMN origen VARCHAR(20) NULL,
            ADD COLUMN pedido_texto TEXT NULL");
    }

    public function down(): void
    {
        $this->db->query("ALTER TABLE compras DROP COLUMN origen, DROP COLUMN pedido_texto");
        $this->db->query("ALTER TABLE companies DROP COLUMN pedido_contacto, DROP COLUMN pedido_telefono");
    }
}

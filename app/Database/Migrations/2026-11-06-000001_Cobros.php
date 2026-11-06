<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Abonos de clientes a ventas a crédito. cobros = recibo (monto, forma de pago, tienda que lo recibió);
 * cobros_ventas = a qué facturas se aplicó. Reemplaza el "marcar como pagada" todo o nada.
 *   php spark migrate
 */
class Cobros extends Migration
{
    public function up(): void
    {
        $this->db->query("CREATE TABLE cobros (
            cobro_id    INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id  INT NOT NULL,
            sucursal_id INT NOT NULL,
            cliente_id  INT NOT NULL,
            numero      VARCHAR(30) NULL UNIQUE,
            monto       DECIMAL(12,2) NOT NULL,
            forma_pago  VARCHAR(20) NOT NULL DEFAULT 'EFECTIVO',
            referencia  VARCHAR(100) NULL,
            notas       TEXT NULL,
            usuario_id  INT NULL,
            fecha       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_cobros_cliente (cliente_id),
            KEY idx_cobros_sucursal_fecha (sucursal_id, fecha)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->query("CREATE TABLE cobros_ventas (
            id       INT AUTO_INCREMENT PRIMARY KEY,
            cobro_id INT NOT NULL,
            venta_id INT NOT NULL,
            monto    DECIMAL(12,2) NOT NULL,
            KEY idx_cv_cobro (cobro_id),
            KEY idx_cv_venta (venta_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down(): void
    {
        $this->forge->dropTable('cobros_ventas', true);
        $this->forge->dropTable('cobros', true);
    }
}

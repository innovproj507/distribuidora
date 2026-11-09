<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Créditos viejos de clientes (de antes del sistema): se cobran con los mismos abonos que las
 * facturas a crédito, pero no son ventas (no tocan inventario, caja ni reportes de ventas).
 * cobros_ventas pasa a aplicar un abono a una venta O a un saldo anterior.
 *   php spark migrate
 */
class SaldosAnteriores extends Migration
{
    public function up(): void
    {
        $this->db->query("CREATE TABLE saldos_anteriores (
            saldo_id    INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id  INT NOT NULL,
            sucursal_id INT NULL,
            cliente_id  INT NOT NULL,
            numero      VARCHAR(30) NULL UNIQUE,
            referencia  VARCHAR(100) NULL,
            fecha       DATE NOT NULL,
            monto       DECIMAL(12,2) NOT NULL,
            estado      VARCHAR(20) NOT NULL DEFAULT 'pendiente',
            notas       TEXT NULL,
            usuario_id  INT NULL,
            creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_sa_cliente (cliente_id, estado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->query("ALTER TABLE cobros_ventas
            MODIFY venta_id INT NULL,
            ADD COLUMN saldo_id INT NULL AFTER venta_id,
            ADD KEY idx_cv_saldo (saldo_id)");
    }

    public function down(): void
    {
        $this->db->query("DELETE FROM cobros_ventas WHERE venta_id IS NULL");
        $this->db->query("ALTER TABLE cobros_ventas DROP KEY idx_cv_saldo, DROP COLUMN saldo_id, MODIFY venta_id INT NOT NULL");
        $this->forge->dropTable('saldos_anteriores', true);
    }
}

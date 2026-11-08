<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Caja con arqueo: apertura con monto inicial, entradas/salidas de efectivo y cierre con lo
 * declarado por forma de pago. Ventas y abonos guardan la caja donde entró el dinero.
 *   php spark migrate
 */
class Cajas extends Migration
{
    public function up(): void
    {
        $this->db->query("CREATE TABLE cajas (
            caja_id             INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id          INT NOT NULL,
            sucursal_id         INT NOT NULL,
            usuario_id          INT NOT NULL,
            estado              ENUM('abierta','cerrada') NOT NULL DEFAULT 'abierta',
            monto_inicial       DECIMAL(12,2) NOT NULL DEFAULT 0,
            notas_apertura      TEXT NULL,
            abierta_en          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            cerrada_en          DATETIME NULL,
            cerrada_por         INT NULL,
            notas_cierre        TEXT NULL,
            efectivo_esperado   DECIMAL(12,2) NULL,
            efectivo_contado    DECIMAL(12,2) NULL,
            diferencia_efectivo DECIMAL(12,2) NULL,
            total_ventas        DECIMAL(12,2) NULL,
            arqueo              JSON NULL COMMENT 'Por forma de pago: esperado, declarado, diferencia; y conteo de billetes',
            KEY idx_cajas_usuario (usuario_id, estado),
            KEY idx_cajas_sucursal (sucursal_id, abierta_en)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->query("CREATE TABLE caja_movimientos (
            movimiento_id INT AUTO_INCREMENT PRIMARY KEY,
            caja_id       INT NOT NULL,
            tipo          ENUM('entrada','salida') NOT NULL,
            monto         DECIMAL(12,2) NOT NULL,
            motivo        VARCHAR(200) NOT NULL,
            usuario_id    INT NULL,
            fecha         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_cm_caja (caja_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->forge->addColumn('ventas', ['caja_id' => ['type' => 'INT', 'null' => true, 'after' => 'vendedor_id']]);
        $this->forge->addColumn('cobros', ['caja_id' => ['type' => 'INT', 'null' => true, 'after' => 'usuario_id']]);
        $this->db->query('ALTER TABLE ventas ADD KEY idx_ventas_caja (caja_id)');
        $this->db->query('ALTER TABLE cobros ADD KEY idx_cobros_caja (caja_id)');

        // Exigir caja abierta para vender (se puede apagar en Configuración)
        $this->forge->addColumn('companies', ['exigir_caja' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 1]]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('companies', 'exigir_caja');
        $this->db->query('ALTER TABLE cobros DROP KEY idx_cobros_caja');
        $this->db->query('ALTER TABLE ventas DROP KEY idx_ventas_caja');
        $this->forge->dropColumn('cobros', 'caja_id');
        $this->forge->dropColumn('ventas', 'caja_id');
        $this->forge->dropTable('caja_movimientos', true);
        $this->forge->dropTable('cajas', true);
    }
}

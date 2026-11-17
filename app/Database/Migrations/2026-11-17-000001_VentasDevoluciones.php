<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Devoluciones parciales: un producto (o parte) de una factura vuelve. Cada devolución genera su
 * nota de crédito (notas_credito) y queda en ventas_devoluciones; ventas_detalle.cantidad_devuelta
 * acumula lo devuelto de cada línea (no se puede devolver más de lo vendido).
 *   php spark migrate
 */
class VentasDevoluciones extends Migration
{
    public function up(): void
    {
        $this->db->query("ALTER TABLE ventas_detalle ADD COLUMN cantidad_devuelta DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER cantidad");
        $this->db->query(
            "CREATE TABLE ventas_devoluciones (
                devolucion_id INT AUTO_INCREMENT PRIMARY KEY,
                venta_id      INT NOT NULL,
                detalle_id    INT NOT NULL,
                nota_id       INT NULL,
                cantidad      DECIMAL(12,2) NOT NULL,
                monto         DECIMAL(12,2) NOT NULL,
                reingresa     TINYINT(1) NOT NULL DEFAULT 0,
                reembolso     VARCHAR(20) NOT NULL,
                motivo        VARCHAR(255) NOT NULL,
                usuario_id    INT NULL,
                fecha         DATETIME NOT NULL,
                KEY idx_dev_venta (venta_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(): void
    {
        $this->db->query("DROP TABLE ventas_devoluciones");
        $this->db->query("ALTER TABLE ventas_detalle DROP COLUMN cantidad_devuelta");
    }
}

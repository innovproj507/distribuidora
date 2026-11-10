<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * productos.controla_vencimiento: solo los productos marcados piden fecha de vencimiento
 * (al recibir compras, contar, crear/editar). Se marcan de entrada los que ya tienen una
 * fecha cargada o manejan lotes.
 *   php spark migrate
 */
class ProductoControlaVencimiento extends Migration
{
    public function up(): void
    {
        $this->db->query("ALTER TABLE productos ADD COLUMN controla_vencimiento TINYINT(1) NOT NULL DEFAULT 0 AFTER maneja_lotes");
        $this->db->query(
            "UPDATE productos SET controla_vencimiento = 1
             WHERE maneja_lotes = 1
                OR producto_id IN (SELECT producto_id FROM (SELECT DISTINCT producto_id FROM inventario WHERE fecha_vencimiento IS NOT NULL) x)"
        );
    }

    public function down(): void
    {
        $this->db->query("ALTER TABLE productos DROP COLUMN controla_vencimiento");
    }
}

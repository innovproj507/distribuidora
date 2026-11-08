<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Fecha de vencimiento por producto y depósito (el "próximo vencimiento" de lo que hay en la
 * tienda). Independiente de los lotes, que siguen existiendo para productos con maneja_lotes.
 */
class InventarioVencimiento extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('inventario', [
            'fecha_vencimiento' => ['type' => 'DATE', 'null' => true, 'after' => 'ubicacion'],
        ]);
        $this->db->query('ALTER TABLE inventario ADD KEY idx_inventario_vence (fecha_vencimiento)');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE inventario DROP KEY idx_inventario_vence');
        $this->forge->dropColumn('inventario', 'fecha_vencimiento');
    }
}

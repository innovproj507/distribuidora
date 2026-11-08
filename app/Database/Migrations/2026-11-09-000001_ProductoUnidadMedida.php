<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Unidad en que se vende y se cuenta cada producto (la unidad base del inventario):
 * 'und' (por defecto), 'lb', 'kg', 'oz', 'gal', 'lt', 'm', 'pie', 'yd'. Ver ProductoService::UNIDADES_MEDIDA.
 * Las presentaciones (caja, saco…) siguen siendo múltiplos de esta unidad.
 */
class ProductoUnidadMedida extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('productos', [
            'unidad_medida' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => false, 'default' => 'und', 'after' => 'nombre'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('productos', 'unidad_medida');
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Guarda en cada línea de venta la presentación vendida (caja, docena…) y su factor, para mostrarla
 * y para que la anulación devuelva las unidades base correctas.   php spark migrate
 */
class VentasDetallePresentacion extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('ventas_detalle', [
            'unidad_id'     => ['type' => 'INT', 'null' => true, 'after' => 'deposito_id'],
            'unidad_nombre' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'after' => 'unidad_id'],
            'factor'        => ['type' => 'DECIMAL', 'constraint' => '12,4', 'default' => 1, 'after' => 'unidad_nombre'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('ventas_detalle', ['unidad_id', 'unidad_nombre', 'factor']);
    }
}

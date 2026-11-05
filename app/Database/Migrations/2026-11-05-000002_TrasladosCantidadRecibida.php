<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Traslados entre tiendas: cantidad realmente recibida por línea, para detectar
 * faltantes/roturas en el viaje en bote.   php spark migrate
 */
class TrasladosCantidadRecibida extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('traslados_detalle', [
            'cantidad_recibida' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true, 'after' => 'cantidad'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('traslados_detalle', 'cantidad_recibida');
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Multi-sucursal: serie de numeración propia (prefijo) y ubicación en mapa por sucursal.
 *   php spark migrate        /   php spark migrate:rollback
 */
class SucursalesUbicacionYSerie extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('branches', [
            'prefijo'  => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true, 'after' => 'codigo'],
            'mapa_url' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'email'],
            'latitud'  => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true, 'after' => 'mapa_url'],
            'longitud' => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true, 'after' => 'latitud'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('branches', ['prefijo', 'mapa_url', 'latitud', 'longitud']);
    }
}

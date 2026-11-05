<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tokens de la API: users.api_token pasa a guardar el SHA-256 del token (no el token) y
 * vence en users.api_token_expira. Los tokens existentes estaban en texto plano: se anulan
 * (las apps deben volver a iniciar sesión).
 */
class ApiTokenHashYVencimiento extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'api_token_expira' => ['type' => 'DATETIME', 'null' => true, 'after' => 'api_token'],
        ]);
        $this->db->query('UPDATE users SET api_token = NULL');
    }

    public function down(): void
    {
        $this->db->query('UPDATE users SET api_token = NULL');
        $this->forge->dropColumn('users', 'api_token_expira');
    }
}

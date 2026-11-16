<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Enlaces públicos del estado de cuenta (/ec/{token}) que se mandan por WhatsApp.
 * Solo se guarda el SHA-256 del token (43 caracteres al azar); vencen a los 30 días y cada
 * envío nuevo revoca el anterior del mismo cliente.
 *   php spark migrate
 */
class ClientesEnlaces extends Migration
{
    public function up(): void
    {
        $this->db->query(
            "CREATE TABLE clientes_enlaces (
                enlace_id     INT AUTO_INCREMENT PRIMARY KEY,
                empresa_id    INT NOT NULL,
                cliente_id    INT NOT NULL,
                token_hash    CHAR(64) NOT NULL,
                creado_por    INT NULL,
                creado_en     DATETIME NOT NULL,
                expira_en     DATETIME NOT NULL,
                revocado      TINYINT(1) NOT NULL DEFAULT 0,
                accesos       INT NOT NULL DEFAULT 0,
                ultimo_acceso DATETIME NULL,
                UNIQUE KEY uq_enlace_token (token_hash),
                KEY idx_enlace_cliente (cliente_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(): void
    {
        $this->db->query("DROP TABLE clientes_enlaces");
    }
}

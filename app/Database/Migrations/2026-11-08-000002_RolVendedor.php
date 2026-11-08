<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Rol "vendedor": trabaja solo desde la PWA (/m), fijo en su sucursal (ver Config\Permisos y
 * App\Filters\SoloMovilFilter).
 */
class RolVendedor extends Migration
{
    public function up(): void
    {
        $this->db->query("ALTER TABLE users MODIFY rol ENUM('vendedor','cajero','supervisor','gerente','auditor','superadmin') NOT NULL DEFAULT 'cajero'");
    }

    public function down(): void
    {
        $this->db->query("UPDATE users SET rol = 'cajero' WHERE rol = 'vendedor'");
        $this->db->query("ALTER TABLE users MODIFY rol ENUM('cajero','supervisor','gerente','auditor','superadmin') NOT NULL DEFAULT 'cajero'");
    }
}

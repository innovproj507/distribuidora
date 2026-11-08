<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Rol "encargado" (encargado de tienda): administra su sucursal desde la web y la PWA, sin
 * eliminar ni tocar usuarios/configuración (ver Config\Permisos).
 */
class RolEncargado extends Migration
{
    public function up(): void
    {
        $this->db->query("ALTER TABLE users MODIFY rol ENUM('vendedor','encargado','cajero','supervisor','gerente','auditor','superadmin') NOT NULL DEFAULT 'cajero'");
    }

    public function down(): void
    {
        $this->db->query("UPDATE users SET rol = 'supervisor' WHERE rol = 'encargado'");
        $this->db->query("ALTER TABLE users MODIFY rol ENUM('vendedor','cajero','supervisor','gerente','auditor','superadmin') NOT NULL DEFAULT 'cajero'");
    }
}

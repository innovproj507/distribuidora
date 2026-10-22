<?php
/**
 * Instala el módulo Móvil (PWA en /m).
 *
 * Uso:
 *   php migrations/install_movil.php
 *   php migrations/install_movil.php --rollback
 *
 * Idempotente: no crea tablas, solo registra el módulo en `modules`.
 */

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->load();

use App\Core\Database;

if (in_array('--rollback', $argv ?? [], true)) {
    Database::query("DELETE FROM modules WHERE name = 'movil'");
    echo "Módulo 'movil' eliminado del registro.\n";
    exit(0);
}

$faltantes = [];
foreach (['core', 'inventario', 'ventas', 'clientes'] as $dep) {
    $row = Database::query(
        "SELECT id FROM modules WHERE name = ? AND estado = 'instalado' LIMIT 1",
        [$dep]
    )->fetch();
    if (!$row) $faltantes[] = $dep;
}

if ($faltantes) {
    echo "Faltan módulos requeridos: " . implode(', ', $faltantes) . "\n";
    exit(1);
}

$existe = Database::query("SELECT id, estado FROM modules WHERE name = 'movil' LIMIT 1")->fetch();

if ($existe && $existe['estado'] === 'instalado') {
    echo "Módulo 'movil' ya estaba instalado (id={$existe['id']}).\n";
} elseif ($existe) {
    Database::query("UPDATE modules SET estado = 'instalado', instalado_en = NOW() WHERE id = ?", [$existe['id']]);
    echo "Módulo 'movil' reactivado.\n";
} else {
    Database::query(
        "INSERT INTO modules (name, label, version, estado, instalado_en)
         VALUES ('movil', 'Móvil (PWA)', '1.0.0', 'instalado', NOW())"
    );
    echo "Módulo 'movil' instalado. Abre /m en el celular.\n";
}

exit(0);

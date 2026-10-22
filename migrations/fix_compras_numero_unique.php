<?php
/**
 * Fix: compras.numero UNIQUE conflicto al dejar N° factura vacío
 *
 * Problema: la columna 'numero' tiene UNIQUE y DEFAULT '' (cadena vacía).
 *           Al insertar sin número, MySQL usa '' y choca con el índice.
 * Solución: cambiar DEFAULT a NULL para que múltiples filas sin número
 *           convivan sin conflicto (UNIQUE permite múltiples NULL).
 *
 * Uso: php migrations/fix_compras_numero_unique.php
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->load();

use App\Core\Database;

$isCli = PHP_SAPI === 'cli';
function out(string $msg, string $type = 'info'): void {
    global $isCli;
    $prefix = match($type) {
        'ok'   => $isCli ? "\033[32m  ✓\033[0m" : '  ✓',
        'skip' => $isCli ? "\033[33m  ─\033[0m" : '  ─',
        'fail' => $isCli ? "\033[31m  ✗\033[0m" : '  ✗',
        default=> '   ',
    };
    echo $prefix . ' ' . $msg . "\n";
}

echo "\nFix: compras.numero UNIQUE\n" . str_repeat('─', 50) . "\n";

$db    = $_ENV['DB_DATABASE'] ?? 'pos_empresa';
$pdo   = Database::getInstance();

// ── 1. Verificar que la tabla y columna existen ───────────────────────────────

$col = Database::query(
    "SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_TYPE
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'compras' AND COLUMN_NAME = 'numero'",
    [$db]
)->fetch();

if (!$col) {
    out("Columna 'numero' no existe en 'compras' — nada que hacer.", 'skip');
    exit(0);
}

out("Columna encontrada: {$col['COLUMN_TYPE']} | nullable={$col['IS_NULLABLE']} | default=" . var_export($col['COLUMN_DEFAULT'], true));

// ── 2. Verificar si ya es nullable ───────────────────────────────────────────

if ($col['IS_NULLABLE'] === 'YES') {
    out("Ya es nullable — no se requiere cambio.", 'skip');
    exit(0);
}

// ── 3. Aplicar el ALTER ───────────────────────────────────────────────────────

try {
    // Cambiar a nullable para que el default sea NULL en lugar de ''
    $pdo->exec("ALTER TABLE compras MODIFY COLUMN numero VARCHAR(50) DEFAULT NULL");
    out("ALTER TABLE compras MODIFY COLUMN numero VARCHAR(50) DEFAULT NULL — OK", 'ok');
} catch (\Throwable $e) {
    out("Error al modificar columna: " . $e->getMessage(), 'fail');
    exit(1);
}

// ── 4. Limpiar filas con numero = '' que pueden quedar de antes ───────────────

try {
    $afectadas = $pdo->exec("UPDATE compras SET numero = NULL WHERE numero = ''");
    out("Filas con numero='' corregidas a NULL: {$afectadas}", 'ok');
} catch (\Throwable $e) {
    out("Advertencia al limpiar vacíos: " . $e->getMessage(), 'skip');
}

echo "\n" . ($isCli ? "\033[32m" : '') . "  Fix aplicado correctamente." . ($isCli ? "\033[0m" : '') . "\n\n";
exit(0);

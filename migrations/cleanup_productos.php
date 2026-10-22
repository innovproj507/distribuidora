<?php
/**
 * Script de limpieza: elimina todos los productos, ventas, compras,
 * cotizaciones, traslados, clientes, proveedores e inventario.
 *
 * CONSERVA: usuarios, roles, contraseñas, sucursales, depósitos,
 *           categorías de productos, configuración de empresa y módulos.
 *
 * Uso:
 *   php migrations/cleanup_productos.php            (pide confirmación)
 *   php migrations/cleanup_productos.php --force    (sin confirmación)
 */

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->load();

use App\Core\Database;

// ── Helpers de output ─────────────────────────────────────────────────────────

$isCli = PHP_SAPI === 'cli';

function line(string $msg = ''): void { echo $msg . "\n"; }

function ok(string $msg): void
{
    global $isCli;
    echo ($isCli ? "\033[32m  ✓\033[0m" : '  ✓') . ' ' . $msg . "\n";
}

function skip(string $msg): void
{
    global $isCli;
    echo ($isCli ? "\033[33m  ─\033[0m" : '  ─') . ' ' . $msg . "\n";
}

function fail(string $msg): void
{
    global $isCli;
    echo ($isCli ? "\033[31m  ✗\033[0m" : '  ✗') . ' ' . $msg . "\n";
}

function title(string $msg): void
{
    global $isCli;
    $text = $isCli ? "\033[1;36m{$msg}\033[0m" : $msg;
    echo "\n{$text}\n" . str_repeat('─', 60) . "\n";
}

function warn(string $msg): void
{
    global $isCli;
    echo ($isCli ? "\033[33m  ⚠  {$msg}\033[0m" : "  ⚠  {$msg}") . "\n";
}

// ── Helpers de tabla ──────────────────────────────────────────────────────────

function tableExists(string $table): bool
{
    $db  = $_ENV['DB_DATABASE'] ?? 'pos_empresa';
    $row = Database::query(
        "SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
        [$db, $table]
    )->fetch();
    return (int)($row['n'] ?? 0) > 0;
}

function countRows(string $table): int
{
    if (!tableExists($table)) return -1;
    return (int) Database::query("SELECT COUNT(*) AS n FROM `{$table}`")->fetch()['n'];
}

function truncate(string $table): int
{
    if (!tableExists($table)) {
        skip("'{$table}' no existe, omitida.");
        return 0;
    }
    $antes = countRows($table);
    Database::getInstance()->exec("TRUNCATE TABLE `{$table}`");
    ok("'{$table}' — {$antes} registro(s) eliminado(s).");
    return $antes;
}

// ── Definición de tablas ──────────────────────────────────────────────────────

// Orden: primero las que tienen FK apuntando a otras (hijas antes que padres)
$tablas = [
    // Mantenimiento
    'mant_ejecuciones',
    'mant_tareas',
    'mant_software',

    // Lotes
    'lote_movimientos',
    'lotes',

    // Inventario
    'inventario_movimientos',
    'inventario_conteos_detalle',
    'inventario_conteos',
    'alertas_inventario',

    // Órdenes automáticas
    'ordenes_compra_sugeridas',

    // Bitácora
    'audit_log',

    // Transacciones
    'ventas_detalle',
    'ventas',
    'cotizaciones_detalle',
    'cotizaciones',
    'compras_detalle',
    'compras',
    'traslados_detalle',
    'traslados',

    // Clientes
    'direcciones_cliente',
    'clientes',

    // Proveedores
    'proveedores',

    // Inventario base
    'inventario',
    'precios_productos',
    'productos_unidades',

    // Productos (al final porque todo referencia a esta)
    'marcas',
    'productos',
];

// ── TABLAS QUE SE CONSERVAN (solo para mostrar al usuario) ────────────────────
$conservar = [
    'users'                  => 'Usuarios y contraseñas',
    'user_profiles'          => 'Perfiles de usuario',
    'user_profiles_sucursales' => 'Sucursales por usuario',
    'companies'              => 'Configuración de empresa',
    'branches'               => 'Sucursales',
    'depositos'              => 'Depósitos / almacenes',
    'categorias_productos'   => 'Categorías de productos',
    'modules'                => 'Módulos instalados',
];

// ── Confirmación ──────────────────────────────────────────────────────────────

title('LIMPIEZA DE BASE DE DATOS — Sistema POS');

warn('Esta operación es IRREVERSIBLE. Se eliminarán:');
line();

$totalRegistros = 0;
foreach ($tablas as $t) {
    $n = countRows($t);
    if ($n < 0) continue;
    $totalRegistros += $n;
    printf("     %-35s %s registros\n", $t, number_format($n));
}

line();
line("  Total a eliminar: " . ($isCli ? "\033[31m" : '') . number_format($totalRegistros) . " registro(s)" . ($isCli ? "\033[0m" : ''));
line();

title('SE CONSERVARÁN');
foreach ($conservar as $tabla => $desc) {
    $n = countRows($tabla);
    printf("  %-35s %s (%s registros)\n", $tabla, $desc, $n >= 0 ? number_format($n) : 'N/A');
}

line();

$force = in_array('--force', $argv ?? [], true);

if (!$force) {
    if ($isCli) {
        echo "\033[1;33m  ¿Confirma la limpieza? Escriba 'LIMPIAR' para continuar: \033[0m";
        $input = trim(fgets(STDIN));
        if ($input !== 'LIMPIAR') {
            line();
            line($isCli ? "\033[33m  Operación cancelada.\033[0m" : 'Operación cancelada.');
            exit(0);
        }
    } else {
        line('  Ejecute con --force para confirmar desde un contexto no-CLI.');
        exit(0);
    }
}

// ── Limpieza ──────────────────────────────────────────────────────────────────

title('EJECUTANDO LIMPIEZA');

$pdo = Database::getInstance();
$pdo->exec('SET foreign_key_checks = 0');

$eliminados = 0;
$errores    = 0;

foreach ($tablas as $tabla) {
    try {
        $eliminados += truncate($tabla);
    } catch (Throwable $e) {
        fail("'{$tabla}': " . $e->getMessage());
        $errores++;
    }
}

$pdo->exec('SET foreign_key_checks = 1');

// ── Resumen ───────────────────────────────────────────────────────────────────

title('RESUMEN');

line("  Registros eliminados : " . number_format($eliminados));
line("  Errores              : {$errores}");
line();

if ($errores === 0) {
    $msg = "Limpieza completada. La base de datos está lista para datos nuevos.";
    line($isCli ? "\033[32m{$msg}\033[0m" : $msg);
} else {
    $msg = "Limpieza terminó con {$errores} error(es). Revise los mensajes anteriores.";
    line($isCli ? "\033[31m{$msg}\033[0m" : $msg);
}

line();
exit($errores > 0 ? 1 : 0);

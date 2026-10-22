<?php
/**
 * Runner de migraciones — ejecutar desde la raíz del proyecto:
 *   php migrations/migrate.php
 *
 * Solo aplica archivos con formato: YYYY_MM_DD_descripcion.sql
 * Otros archivos (database.sql, dumps, seeds) son ignorados.
 *
 * Errores tolerados (idempotencia):
 *   1060 — columna ya existe (ADD COLUMN duplicada)
 *   1050 — tabla ya existe  (CREATE TABLE duplicada)
 *   1061 — índice ya existe (CREATE INDEX duplicada)
 */

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$dsn = 'mysql:host=' . $_ENV['DB_HOST'] . ';port=' . ($_ENV['DB_PORT'] ?? 3306)
     . ';dbname=' . $_ENV['DB_DATABASE'] . ';charset=utf8mb4';
$pdo = new PDO($dsn, $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
]);

// Errores que se tratan como "ya estaba aplicado" — idempotentes
const WARN_CODES = [1060, 1050, 1061, 1091];

$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
    migration  VARCHAR(255) PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$applied = array_flip(
    $pdo->query("SELECT migration FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN)
);

// Solo archivos con prefijo de fecha: YYYY_MM_DD_*.sql
$files = glob(__DIR__ . '/[0-9][0-9][0-9][0-9]_[0-9][0-9]_[0-9][0-9]_*.sql');
sort($files);

if (empty($files)) {
    echo "No hay archivos de migración (formato esperado: YYYY_MM_DD_descripcion.sql)\n";
    exit(0);
}

$pendientes = 0;
foreach ($files as $file) {
    $name = basename($file);

    if (isset($applied[$name])) {
        echo "  [ya aplicada]  $name\n";
        continue;
    }

    $sql        = file_get_contents($file);
    $stmts      = array_filter(array_map('trim', explode(';', $sql)));
    $warnings   = [];
    $fatalError = null;

    echo "  [aplicando]    $name ... ";

    foreach ($stmts as $stmt) {
        if ($stmt === '' || str_starts_with($stmt, '--')) continue;
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            $code = (int) $e->errorInfo[1];
            if (in_array($code, WARN_CODES)) {
                $warnings[] = $e->getMessage();
            } else {
                $fatalError = $e->getMessage();
                break;
            }
        }
    }

    if ($fatalError) {
        echo "ERROR: $fatalError\n";
        exit(1);
    }

    $pdo->prepare("INSERT INTO schema_migrations (migration) VALUES (?)")->execute([$name]);

    if ($warnings) {
        echo "OK (advertencias: " . count($warnings) . " sentencia(s) ya aplicadas)\n";
    } else {
        echo "OK\n";
    }
    $pendientes++;
}

echo $pendientes === 0
    ? "\nNo hay migraciones pendientes.\n"
    : "\n$pendientes migración(es) aplicada(s).\n";

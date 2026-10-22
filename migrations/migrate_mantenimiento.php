<?php
/**
 * Migración de producción: Módulo Mantenimiento v1.0.0
 *
 * Uso:
 *   php migrations/migrate_mantenimiento.php
 *   php migrations/migrate_mantenimiento.php --rollback
 *
 * Segura: idempotente — puede ejecutarse múltiples veces sin error.
 * DDL no es transaccional en MySQL; cada paso se reporta por separado.
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
    $prefix = $isCli ? "\033[32m  ✓\033[0m" : '  ✓';
    echo $prefix . ' ' . $msg . "\n";
}

function skip(string $msg): void
{
    global $isCli;
    $prefix = $isCli ? "\033[33m  ─\033[0m" : '  ─';
    echo $prefix . ' ' . $msg . "\n";
}

function fail(string $msg): void
{
    global $isCli;
    $prefix = $isCli ? "\033[31m  ✗\033[0m" : '  ✗';
    echo $prefix . ' ' . $msg . "\n";
}

function title(string $msg): void
{
    global $isCli;
    $text = $isCli ? "\033[1;36m{$msg}\033[0m" : $msg;
    echo "\n" . $text . "\n" . str_repeat('─', 60) . "\n";
}

// ── Helpers de migración ──────────────────────────────────────────────────────

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

function columnExists(string $table, string $column): bool
{
    $db  = $_ENV['DB_DATABASE'] ?? 'pos_empresa';
    $row = Database::query(
        "SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?",
        [$db, $table, $column]
    )->fetch();
    return (int)($row['n'] ?? 0) > 0;
}

function ddl(string $sql): void
{
    Database::getInstance()->exec($sql);
}

// ── Rollback ──────────────────────────────────────────────────────────────────

function rollback(): void
{
    title('ROLLBACK — Módulo Mantenimiento');

    $pdo = Database::getInstance();
    $pdo->exec('SET foreign_key_checks = 0');

    foreach (['mant_ejecuciones', 'mant_tareas', 'mant_software'] as $table) {
        if (tableExists($table)) {
            $pdo->exec("DROP TABLE {$table}");
            ok("Tabla {$table} eliminada.");
        } else {
            skip("Tabla {$table} no existe, nada que eliminar.");
        }
    }

    $pdo->exec('SET foreign_key_checks = 1');

    $row = Database::query("SELECT id FROM modules WHERE name = 'mantenimiento' LIMIT 1")->fetch();
    if ($row) {
        Database::query("DELETE FROM modules WHERE name = 'mantenimiento'");
        ok("Módulo 'mantenimiento' eliminado del registro.");
    } else {
        skip("Módulo 'mantenimiento' no estaba registrado.");
    }

    line();
    line($GLOBALS['isCli'] ? "\033[33mRollback completado.\033[0m" : 'Rollback completado.');
}

// ── Main ──────────────────────────────────────────────────────────────────────

if (in_array('--rollback', $argv ?? [], true)) {
    rollback();
    exit(0);
}

$errors  = 0;
$created = 0;
$skipped = 0;

title('MIGRACIÓN — Módulo Mantenimiento v1.0.0');

// ── Paso 1: mant_software ─────────────────────────────────────────────────────

line("\n[1/4] Tabla mant_software");

if (tableExists('mant_software')) {
    skip('Ya existe, omitida.');
    $skipped++;
} else {
    try {
        ddl("SET foreign_key_checks = 0");
        ddl("
            CREATE TABLE mant_software (
                software_id                INT          AUTO_INCREMENT PRIMARY KEY,
                empresa_id                 INT          NOT NULL,
                nombre                     VARCHAR(200) NOT NULL,
                version                    VARCHAR(50)  DEFAULT NULL,
                proveedor                  VARCHAR(200) DEFAULT NULL,
                tipo                       ENUM('aplicacion','base_datos','sistema_operativo','servicio','otro')
                                                        NOT NULL DEFAULT 'aplicacion',
                servidor                   VARCHAR(100) DEFAULT NULL,
                fecha_instalacion          DATE         DEFAULT NULL,
                fecha_vencimiento_licencia DATE         DEFAULT NULL,
                contacto_soporte           VARCHAR(200) DEFAULT NULL,
                notas                      TEXT         DEFAULT NULL,
                estado                     ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
                fecha_registro             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

                INDEX  idx_msw_empresa (empresa_id),
                INDEX  idx_msw_estado  (estado),
                CONSTRAINT fk_msw_empresa
                    FOREIGN KEY (empresa_id) REFERENCES companies(empresa_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        ddl("SET foreign_key_checks = 1");
        ok('Tabla mant_software creada.');
        $created++;
    } catch (Throwable $e) {
        fail('mant_software: ' . $e->getMessage());
        $errors++;
    }
}

// ── Paso 2: mant_tareas ───────────────────────────────────────────────────────

line("\n[2/4] Tabla mant_tareas");

if (tableExists('mant_tareas')) {
    skip('Ya existe, omitida.');
    $skipped++;
} else {
    try {
        ddl("SET foreign_key_checks = 0");
        ddl("
            CREATE TABLE mant_tareas (
                tarea_id           INT          AUTO_INCREMENT PRIMARY KEY,
                software_id        INT          NOT NULL,
                empresa_id         INT          NOT NULL,
                nombre             VARCHAR(200) NOT NULL,
                descripcion        TEXT         DEFAULT NULL,
                frecuencia         ENUM('diaria','semanal','mensual','trimestral','semestral','anual')
                                               NOT NULL DEFAULT 'mensual',
                prioridad          ENUM('alta','media','baja') NOT NULL DEFAULT 'media',
                responsable        VARCHAR(200) DEFAULT NULL,
                duracion_estimada  INT          DEFAULT 30,
                activa             TINYINT(1)   NOT NULL DEFAULT 1,
                proxima_ejecucion  DATE         DEFAULT NULL,
                ultima_ejecucion   DATE         DEFAULT NULL,
                fecha_registro     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

                INDEX  idx_mta_empresa  (empresa_id),
                INDEX  idx_mta_software (software_id),
                INDEX  idx_mta_proxima  (proxima_ejecucion),
                INDEX  idx_mta_activa   (activa),
                CONSTRAINT fk_mta_software
                    FOREIGN KEY (software_id) REFERENCES mant_software(software_id) ON DELETE CASCADE,
                CONSTRAINT fk_mta_empresa
                    FOREIGN KEY (empresa_id)  REFERENCES companies(empresa_id)      ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        ddl("SET foreign_key_checks = 1");
        ok('Tabla mant_tareas creada.');
        $created++;
    } catch (Throwable $e) {
        fail('mant_tareas: ' . $e->getMessage());
        $errors++;
    }
}

// ── Paso 3: mant_ejecuciones ──────────────────────────────────────────────────

line("\n[3/4] Tabla mant_ejecuciones");

if (tableExists('mant_ejecuciones')) {
    skip('Ya existe, omitida.');
    $skipped++;
} else {
    try {
        ddl("SET foreign_key_checks = 0");
        ddl("
            CREATE TABLE mant_ejecuciones (
                ejecucion_id    INT       AUTO_INCREMENT PRIMARY KEY,
                tarea_id        INT       NOT NULL,
                empresa_id      INT       NOT NULL,
                usuario_id      INT       DEFAULT NULL,
                fecha_ejecucion DATETIME  NOT NULL,
                duracion_real   INT       DEFAULT NULL,
                estado          ENUM('completado','fallido','omitido') NOT NULL DEFAULT 'completado',
                notas           TEXT      DEFAULT NULL,
                fecha_registro  DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,

                INDEX  idx_mej_tarea   (tarea_id),
                INDEX  idx_mej_empresa (empresa_id),
                INDEX  idx_mej_fecha   (fecha_ejecucion),
                INDEX  idx_mej_estado  (estado),
                CONSTRAINT fk_mej_tarea
                    FOREIGN KEY (tarea_id)   REFERENCES mant_tareas(tarea_id)  ON DELETE CASCADE,
                CONSTRAINT fk_mej_empresa
                    FOREIGN KEY (empresa_id) REFERENCES companies(empresa_id)  ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        ddl("SET foreign_key_checks = 1");
        ok('Tabla mant_ejecuciones creada.');
        $created++;
    } catch (Throwable $e) {
        fail('mant_ejecuciones: ' . $e->getMessage());
        $errors++;
    }
}

// ── Paso 4: registro del módulo ───────────────────────────────────────────────

line("\n[4/4] Registro en tabla modules");

try {
    $exists = Database::query(
        "SELECT id FROM modules WHERE name = 'mantenimiento' LIMIT 1"
    )->fetch();

    if ($exists) {
        skip("Módulo 'mantenimiento' ya estaba registrado (id={$exists['id']}).");
        $skipped++;
    } else {
        Database::query(
            "INSERT INTO modules (name, label, version, estado, instalado_en)
             VALUES ('mantenimiento', 'Mantenimiento', '1.0.0', 'instalado', NOW())"
        );
        ok("Módulo 'mantenimiento' registrado con estado 'instalado'.");
        $created++;
    }
} catch (Throwable $e) {
    fail('modules: ' . $e->getMessage());
    $errors++;
}

// ── Resumen ───────────────────────────────────────────────────────────────────

title('RESUMEN');

line("  Creados : {$created}");
line("  Omitidos: {$skipped}  (ya existían)");
line("  Errores : {$errors}");
line();

if ($errors === 0) {
    $msg = "Migración completada exitosamente.";
    line($isCli ? "\033[32m{$msg}\033[0m" : $msg);
} else {
    $msg = "Migración terminó con {$errors} error(es). Revisar los mensajes anteriores.";
    line($isCli ? "\033[31m{$msg}\033[0m" : $msg);
}

line();
exit($errors > 0 ? 1 : 0);

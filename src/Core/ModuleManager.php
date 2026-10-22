<?php
declare(strict_types=1);

namespace App\Core;

class ModuleManager
{
    private static ?array $installed = null;

    // ── Query instalados ────────────────────────────────────────────────────────

    public static function getInstalled(): array
    {
        if (self::$installed !== null) {
            return self::$installed;
        }
        try {
            $rows = Database::query(
                "SELECT name FROM modules WHERE estado = 'instalado' ORDER BY id",
                []
            )->fetchAll();
            self::$installed = array_column($rows, 'name');
        } catch (\Throwable) {
            // Tabla aún no existe (primera ejecución): todos los módulos activos
            self::$installed = ['core', 'inventario', 'ventas', 'clientes', 'reportes'];
        }
        return self::$installed;
    }

    public static function isInstalled(string $name): bool
    {
        return in_array($name, self::getInstalled(), true);
    }

    // ── Manifests ───────────────────────────────────────────────────────────────

    public static function getManifest(string $name): array
    {
        $file = BASE_PATH . "/modules/{$name}/manifest.php";
        if (!file_exists($file)) {
            return [];
        }
        $manifest = require $file;
        return is_array($manifest) ? array_merge(['name' => $name], $manifest) : [];
    }

    public static function getAllModules(): array
    {
        $installed = self::getInstalled();
        $dirs      = glob(BASE_PATH . '/modules/*', GLOB_ONLYDIR) ?: [];
        $modules   = [];

        foreach ($dirs as $dir) {
            $name     = basename($dir);
            $manifest = self::getManifest($name);
            if (empty($manifest)) {
                continue;
            }
            $manifest['instalado'] = in_array($name, $installed, true);
            $modules[]             = $manifest;
        }

        usort($modules, fn($a, $b) => ($a['menu_order'] ?? 99) <=> ($b['menu_order'] ?? 99));

        return $modules;
    }

    // ── Install / Uninstall ─────────────────────────────────────────────────────

    public static function install(string $name): array
    {
        $manifest = self::getManifest($name);
        if (empty($manifest)) {
            return ['success' => false, 'error' => "Módulo '{$name}' no encontrado"];
        }

        foreach (($manifest['depends'] ?? []) as $dep) {
            if (!self::isInstalled($dep)) {
                $depLabel = self::getManifest($dep)['label'] ?? $dep;
                return ['success' => false, 'error' => "Requiere instalar primero: {$depLabel}"];
            }
        }

        try {
            Database::query(
                "INSERT INTO modules (name, label, version, estado, instalado_en)
                 VALUES (?, ?, ?, 'instalado', NOW())
                 ON DUPLICATE KEY UPDATE estado='instalado', instalado_en=NOW(), version=VALUES(version)",
                [$name, $manifest['label'] ?? $name, $manifest['version'] ?? '1.0.0']
            );
            self::$installed = null;
            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public static function uninstall(string $name): array
    {
        if ($name === 'core') {
            return ['success' => false, 'error' => 'El módulo Core no puede desinstalarse'];
        }

        foreach (self::getInstalled() as $installed) {
            if ($installed === $name) {
                continue;
            }
            $manifest = self::getManifest($installed);
            if (in_array($name, $manifest['depends'] ?? [], true)) {
                $label = $manifest['label'] ?? $installed;
                return ['success' => false, 'error' => "El módulo '{$label}' depende de '{$name}'"];
            }
        }

        try {
            Database::query(
                "UPDATE modules SET estado='desinstalado' WHERE name = ?",
                [$name]
            );
            self::$installed = null;
            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ── Route loading ───────────────────────────────────────────────────────────

    public static function loadRoutes(Router $router): void
    {
        foreach (self::getInstalled() as $name) {
            $file = BASE_PATH . "/modules/{$name}/routes.php";
            if (file_exists($file)) {
                (static function (Router $router, string $file): void {
                    require $file;
                })($router, $file);
            }
        }
    }

    // ── Sidebar menu ────────────────────────────────────────────────────────────

    public static function getMenu(): array
    {
        $core   = self::getManifest('core');
        $top    = $core['menu_top']    ?? [];
        $bottom = $core['menu_bottom'] ?? [];
        $middle = [];

        $names = array_filter(self::getInstalled(), fn($n) => $n !== 'core');
        $manifests = array_map(fn($n) => self::getManifest($n), $names);
        usort($manifests, fn($a, $b) => ($a['menu_order'] ?? 99) <=> ($b['menu_order'] ?? 99));

        foreach ($manifests as $manifest) {
            if (!empty($manifest['menu'])) {
                foreach ($manifest['menu'] as $item) {
                    $middle[] = $item;
                }
            }
        }

        return array_merge($top, $middle, $bottom);
    }
}

<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\Router\RouteCollection;

/**
 * Sistema de módulos: cada carpeta app/Modules/{nombre} tiene
 *   - manifest.php → label, depends, menu_order, menu (sidebar)
 *   - routes.php   → rutas del módulo (recibe $routes)
 *
 * Los módulos instalados se leen de la tabla `modules` (estado = 'instalado').
 * Un módulo instalado sin routes.php todavía no fue migrado a CI4: sus enlaces del
 * menú caen en el 404 personalizado, que ofrece abrirlo en el sistema anterior.
 */
final class ModuleManager
{
    private static ?array $installed = null;

    public static function getInstalled(): array
    {
        if (self::$installed !== null) {
            return self::$installed;
        }
        try {
            $rows = Db::all("SELECT name FROM modules WHERE estado = 'instalado' ORDER BY id");
            self::$installed = array_column($rows, 'name');
        } catch (\Throwable) {
            // Tabla aún no existe (primera ejecución)
            self::$installed = ['core', 'inventario', 'ventas', 'clientes', 'reportes'];
        }
        return self::$installed;
    }

    public static function isInstalled(string $name): bool
    {
        return in_array($name, self::getInstalled(), true);
    }

    public static function getManifest(string $name): array
    {
        $file = APPPATH . "Modules/{$name}/manifest.php";
        if (!is_file($file)) {
            return [];
        }
        $manifest = require $file;
        return is_array($manifest) ? array_merge(['name' => $name], $manifest) : [];
    }

    /** Todos los módulos de app/Modules con su manifest y si están instalados, por menu_order. */
    public static function getAllModules(): array
    {
        $installed = self::getInstalled();
        $modules   = [];

        foreach (glob(APPPATH . 'Modules/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $manifest = self::getManifest(basename($dir));
            if ($manifest) {
                $manifest['instalado'] = in_array($manifest['name'], $installed, true);
                $modules[] = $manifest;
            }
        }

        usort($modules, fn ($a, $b) => ($a['menu_order'] ?? 99) <=> ($b['menu_order'] ?? 99));
        return $modules;
    }

    /** @return array{success: bool, error?: string} */
    public static function install(string $name): array
    {
        $manifest = self::getManifest($name);
        if (!$manifest) {
            return ['success' => false, 'error' => "Módulo '{$name}' no encontrado"];
        }
        foreach ($manifest['depends'] ?? [] as $dep) {
            if (!self::isInstalled($dep)) {
                return ['success' => false, 'error' => 'Requiere instalar primero: ' . (self::getManifest($dep)['label'] ?? $dep)];
            }
        }

        if (Db::value("SELECT id FROM modules WHERE name = ?", [$name])) {
            Db::query("UPDATE modules SET estado = 'instalado', instalado_en = NOW(), version = ? WHERE name = ?", [$manifest['version'] ?? '1.0.0', $name]);
        } else {
            Db::insert('modules', [
                'name' => $name, 'label' => $manifest['label'] ?? $name, 'version' => $manifest['version'] ?? '1.0.0',
                'estado' => 'instalado', 'instalado_en' => date('Y-m-d H:i:s'),
            ]);
        }
        self::$installed = null;
        return ['success' => true];
    }

    /** @return array{success: bool, error?: string} */
    public static function uninstall(string $name): array
    {
        if ($name === 'core') {
            return ['success' => false, 'error' => 'El módulo Core no puede desinstalarse'];
        }
        foreach (self::getInstalled() as $installed) {
            $manifest = self::getManifest($installed);
            if ($installed !== $name && in_array($name, $manifest['depends'] ?? [], true)) {
                return ['success' => false, 'error' => "El módulo '" . ($manifest['label'] ?? $installed) . "' depende de '{$name}'"];
            }
        }

        Db::query("UPDATE modules SET estado = 'desinstalado' WHERE name = ?", [$name]);
        self::$installed = null;
        return ['success' => true];
    }

    /** Registra las rutas de cada módulo instalado que ya tiene routes.php. */
    public static function loadRoutes(RouteCollection $routes): void
    {
        foreach (self::getInstalled() as $name) {
            $file = APPPATH . "Modules/{$name}/routes.php";
            if (is_file($file)) {
                (static function (RouteCollection $routes, string $file): void {
                    require $file;
                })($routes, $file);
            }
        }
    }

    /** Sidebar: menu_top de core + menús de los módulos (por menu_order) + menu_bottom de core. */
    public static function getMenu(): array
    {
        $core   = self::getManifest('core');
        $middle = [];

        $names     = array_filter(self::getInstalled(), fn ($n) => $n !== 'core');
        $manifests = array_filter(array_map(fn ($n) => self::getManifest($n), $names));
        usort($manifests, fn ($a, $b) => ($a['menu_order'] ?? 99) <=> ($b['menu_order'] ?? 99));

        foreach ($manifests as $manifest) {
            foreach ($manifest['menu'] ?? [] as $item) {
                $middle[] = $item;
            }
        }

        return array_merge($core['menu_top'] ?? [], $middle, $core['menu_bottom'] ?? []);
    }
}

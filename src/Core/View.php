<?php
declare(strict_types=1);

namespace App\Core;

/**
 * View - Motor de templates PHP con layouts y secciones
 */
class View
{
    private static array $sections = [];
    private static ?string $layout = null;
    private static array $sharedData = [];

    /**
     * Compartir datos con todas las vistas
     */
    public static function share(string $key, mixed $value): void
    {
        self::$sharedData[$key] = $value;
    }

    /**
     * Renderizar una vista
     */
    public static function render(string $viewName, array $data = []): void
    {
        $viewPath = BASE_PATH . '/views/' . str_replace('.', '/', $viewName) . '.php';

        if (!file_exists($viewPath)) {
            throw new \RuntimeException("Vista no encontrada: {$viewName} ({$viewPath})");
        }

        // Inyectar datos compartidos globales
        $app = Application::getInstance();

        // Auth data - safe even without Application
        if (!isset($data['auth'])) {
            try {
                $data['auth'] = [
                    'check' => Auth::check(),
                    'user' => Auth::check() ? Auth::user() : null,
                    'name' => Auth::name(),
                    'isSuperuser' => Auth::isSuperuser(),
                    'isStaff' => Auth::isStaff(),
                ];
            } catch (\Throwable $e) {
                $data['auth'] = ['check' => false, 'user' => null, 'name' => 'Usuario', 'isSuperuser' => false, 'isStaff' => false];
            }
        }

        // Datos de sesión/sucursal - only when Application is available
        if ($app) {
            try {
                $session = $app->getSession();
                $data['flash'] = $data['flash'] ?? $session->getFlash();
                $data['csrf_token'] = $data['csrf_token'] ?? $session->csrfToken();
                $data['sucursal_actual'] = $data['sucursal_actual'] ?? $session->get('sucursal_actual');
                $data['empresa_actual'] = $data['empresa_actual'] ?? $session->get('empresa_actual');
                $data['sucursales_disponibles'] = $data['sucursales_disponibles'] ?? $session->get('sucursales_disponibles', []);
                $data['deposito_actual'] = $data['deposito_actual'] ?? $session->get('deposito_actual');
                $data['depositos_disponibles'] = $data['depositos_disponibles'] ?? $session->get('depositos_disponibles', []);
            } catch (\Throwable $e) {
                // Session not available
            }
        }

        // Defaults for missing data
        $data['flash'] = $data['flash'] ?? [];
        $data['csrf_token'] = $data['csrf_token'] ?? '';
        $data['sucursal_actual'] = $data['sucursal_actual'] ?? null;
        $data['empresa_actual'] = $data['empresa_actual'] ?? null;
        $data['sucursales_disponibles'] = $data['sucursales_disponibles'] ?? [];
        $data['deposito_actual'] = $data['deposito_actual'] ?? null;
        $data['depositos_disponibles'] = $data['depositos_disponibles'] ?? [];

        // Merge shared data
        $data = array_merge(self::$sharedData, $data);

        // Extraer datos como variables
        extract($data);

        // Capturar el contenido de la vista
        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        // Si hay layout, renderizarlo con el contenido
        if (self::$layout !== null) {
            $layoutPath = BASE_PATH . '/views/layouts/' . self::$layout . '.php';
            self::$layout = null;

            if (file_exists($layoutPath)) {
                // Solo usar el capture externo como contenido si la vista
                // NO definió explícitamente un section('content')
                if (!isset(self::$sections['content']) || self::$sections['content'] === '') {
                    self::$sections['content'] = $content;
                }
                extract($data);
                require $layoutPath;
                self::$sections = [];
                return;
            }
        }

        echo $content;
    }

    /**
     * Establecer layout para la vista
     */
    public static function layout(string $name): void
    {
        self::$layout = $name;
    }

    /**
     * Iniciar una sección
     */
    public static function section(string $name): void
    {
        ob_start();
    }

    /**
     * Finalizar sección
     */
    public static function endSection(string $name): void
    {
        self::$sections[$name] = ob_get_clean();
    }

    /**
     * Obtener contenido de una sección
     */
    public static function yield(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }

    /**
     * Incluir una vista parcial
     */
    public static function include(string $viewName, array $data = []): void
    {
        $viewPath = BASE_PATH . '/views/' . str_replace('.', '/', $viewName) . '.php';
        if (file_exists($viewPath)) {
            extract($data);
            require $viewPath;
        }
    }

    /**
     * Escape HTML
     */
    public static function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Generar campo CSRF hidden
     */
    public static function csrf(): string
    {
        $app = Application::getInstance();
        $token = $app->getSession()->csrfToken();
        return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
    }

    /**
     * Obtener URL activa para comparación
     */
    public static function currentUrl(): string
    {
        $app = Application::getInstance();
        return $app ? $app->getRequest()->uri() : '/';
    }

    /**
     * Verificar si la URL actual coincide con un patrón
     */
    public static function isActive(string $pattern): bool
    {
        $current = self::currentUrl();
        if ($pattern === '/') {
            return $current === '/';
        }
        return str_starts_with($current, $pattern);
    }
}

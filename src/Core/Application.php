<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Application - Bootstrap principal del sistema
 */
class Application
{
    private static ?Application $instance = null;
    private Router $router;
    private Request $request;
    private Session $session;
    private ?array $user = null;

    public function __construct()
    {
        self::$instance = $this;

        // Configurar timezone
        date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Panama');

        // Configurar errores según entorno
        if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(0);
            ini_set('display_errors', '0');
        }

        $this->session = new Session();
        $this->request = new Request();
        $this->router = new Router();
    }

    public static function getInstance(): ?Application
    {
        return self::$instance;
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getSession(): Session
    {
        return $this->session;
    }

    public function getUser(): ?array
    {
        if ($this->user === null && $this->session->has('user_id')) {
            $this->user = Auth::user();
        }
        return $this->user;
    }

    public function setUser(?array $user): void
    {
        $this->user = $user;
    }

    /**
     * Ejecutar la aplicación
     */
    public function run(): void
    {
        // 1. Security headers
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        if (!empty($_ENV['APP_URL']) && str_starts_with($_ENV['APP_URL'], 'https')) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        // 2. CORS — solo orígenes permitidos explícitamente
        $allowedOrigins = array_filter(array_map(
            'trim',
            explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? $_ENV['APP_URL'] ?? '')
        ));
        $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($requestOrigin && in_array($requestOrigin, $allowedOrigins, true)) {
            header("Access-Control-Allow-Origin: $requestOrigin");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        // 2. Identificar Tenant (Si no se hizo por Middleware)
        // \App\Core\Tenant::identify($this->request);

        // 3. Cargar rutas - $app disponible en routes.php
        $app = $this;
        require_once BASE_PATH . '/config/routes.php';

        // Resolver y ejecutar la ruta
        $this->router->resolve($this->request);
    }
}

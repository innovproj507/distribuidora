<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Router - Enrutador de URLs con soporte para grupos y middleware
 */
class Router
{
    private array $routes = [];
    private array $groupStack = [];

    /**
     * Registrar ruta GET
     */
    public function get(string $path, array|callable $action, array $middleware = []): self
    {
        return $this->addRoute('GET', $path, $action, $middleware);
    }

    /**
     * Registrar ruta POST
     */
    public function post(string $path, array|callable $action, array $middleware = []): self
    {
        return $this->addRoute('POST', $path, $action, $middleware);
    }

    /**
     * Registrar ruta PUT
     */
    public function put(string $path, array|callable $action, array $middleware = []): self
    {
        return $this->addRoute('PUT', $path, $action, $middleware);
    }

    /**
     * Registrar ruta DELETE
     */
    public function delete(string $path, array|callable $action, array $middleware = []): self
    {
        return $this->addRoute('DELETE', $path, $action, $middleware);
    }

    /**
     * Grupo de rutas con prefijo y middleware compartidos
     */
    public function group(array $options, callable $callback): void
    {
        $this->groupStack[] = $options;
        $callback($this);
        array_pop($this->groupStack);
    }

    /**
     * Agregar ruta al registro
     */
    private function addRoute(string $method, string $path, array|callable $action, array $middleware = []): self
    {
        $prefix = '';
        $groupMiddleware = [];

        foreach ($this->groupStack as $group) {
            $prefix .= $group['prefix'] ?? '';
            if (isset($group['middleware'])) {
                $groupMiddleware = array_merge($groupMiddleware, (array) $group['middleware']);
            }
        }

        $fullPath = $prefix . $path;
        $allMiddleware = array_merge($groupMiddleware, $middleware);

        $this->routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'action' => $action,
            'middleware' => $allMiddleware,
            'pattern' => $this->buildPattern($fullPath),
        ];

        return $this;
    }

    /**
     * Construir patrón regex para la ruta
     */
    private function buildPattern(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[0-9]+)', $path);
        
        // Hacer la barra final opcional
        if ($pattern === '/') {
            return '#^/?$#';
        }
        
        return '#^' . rtrim($pattern, '/') . '/?$#';
    }

    /**
     * Resolver la ruta actual
     */
    public function resolve(Request $request): void
    {
        $method = $request->method();
        $uri = $request->uri();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            if (preg_match($route['pattern'], $uri, $matches)) {
                // Extraer parámetros nombrados
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $params = array_map('intval', $params);

                // Ejecutar middleware
                foreach ($route['middleware'] as $middlewareClass) {
                    $middleware = new $middlewareClass();
                    $result = $middleware->handle($request);
                    if ($result === false) return;
                }

                // Ejecutar acción
                if (is_callable($route['action'])) {
                    call_user_func_array($route['action'], $params);
                } elseif (is_array($route['action'])) {
                    [$controllerClass, $methodName] = $route['action'];
                    $controller = new $controllerClass();
                    call_user_func_array([$controller, $methodName], $params);
                }
                return;
            }
        }

        // 404 - Ruta no encontrada
        http_response_code(404);
        echo '<h1>404 - Página no encontrada</h1>';
        echo '<p>La ruta <code>' . htmlspecialchars($uri) . '</code> no existe.</p>';
        echo '<a href="/">Volver al inicio</a>';
    }

    /**
     * Generar URL para una ruta nombrada (helper)
     */
    public static function url(string $path): string
    {
        $baseUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
        return $baseUrl . $path;
    }
}

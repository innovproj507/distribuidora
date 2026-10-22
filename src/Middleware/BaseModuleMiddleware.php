<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Tenant;
use App\Core\Request;
use App\Core\Response;

/**
 * BaseModuleMiddleware - Clase base para protejer rutas por módulos
 */
abstract class BaseModuleMiddleware
{
    protected string $moduleSlug = '';

    public function handle(Request $request): bool
    {
        if (!Tenant::isActive()) {
            return true; // No hay tenant, no aplica restricción de módulos aún
        }

        if (!Tenant::hasModule($this->moduleSlug)) {
            // Si es una petición API
            if (str_starts_with($request->uri(), '/api')) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error',
                    'message' => "Módulo '{$this->moduleSlug}' no habilitado para su plan."
                ]);
                return false;
            }

            // Si es petición Web
            Response::redirect('/dashboard?error=module_not_enabled&module=' . $this->moduleSlug);
            return false;
        }

        return true;
    }
}

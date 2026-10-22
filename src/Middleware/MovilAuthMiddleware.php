<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/**
 * MovilAuthMiddleware - Igual que AuthMiddleware, pero redirige al login de la PWA (/m/login)
 */
class MovilAuthMiddleware
{
    public function handle(Request $request): bool
    {
        if (!Auth::check()) {
            if (str_starts_with($request->uri(), '/m/api/')) {
                Response::json(['error' => 'Sesión expirada. Vuelve a iniciar sesión.'], 401);
                return false;
            }
            Response::redirect('/m/login');
            return false;
        }

        return true;
    }
}

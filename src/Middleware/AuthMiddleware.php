<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/**
 * AuthMiddleware - Verifica que el usuario esté autenticado
 */
class AuthMiddleware
{
    public function handle(Request $request): bool
    {
        if (!Auth::check()) {
            if (str_contains($request->uri(), '/api/')) {
                Response::json([
                    'status' => 'error',
                    'message' => 'No autorizado. Sesión expirada o token inválido.'
                ], 401);
                return false;
            }
            Response::redirect('/login');
            return false;
        }

        return true;
    }
}

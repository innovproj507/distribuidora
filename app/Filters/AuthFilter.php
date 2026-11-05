<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Auth;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Exige sesión iniciada. Las rutas JSON (/api/...) reciben 401 en vez de redirección.
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (Auth::check()) {
            return null;
        }

        if (str_starts_with(ltrim($request->getUri()->getPath(), '/'), 'api/') ||str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return service('response')->setStatusCode(401)->setJSON([
                'status'  => 'error',
                'message' => 'No autorizado. Sesión expirada.',
            ]);
        }

        return redirect()->to('/login');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}

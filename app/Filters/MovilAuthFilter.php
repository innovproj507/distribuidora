<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Auth;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Igual que AuthFilter, pero redirige al login de la PWA (/m/login).
 */
class MovilAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (Auth::check()) {
            return null;
        }

        if (str_starts_with(ltrim($request->getUri()->getPath(), '/'), 'm/api/')) {
            return service('response')->setStatusCode(401)
                ->setJSON(['error' => 'Sesión expirada. Vuelve a iniciar sesión.']);
        }

        return redirect()->to('/m/login');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}

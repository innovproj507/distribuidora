<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Auth;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Exige un permiso de config/Permisos.php. Uso en rutas: ['filter' => 'permiso:ventas.anular']
 * (varios argumentos = basta con tener uno de ellos).
 */
class PermisoFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        foreach ((array) $arguments as $permiso) {
            if (Auth::can($permiso)) {
                return null;
            }
        }

        if (Auth::esApi()) {
            return service('response')->setStatusCode(403)
                ->setJSON(['status' => 'error', 'message' => 'Tu rol no tiene permiso para esta operación.', 'errors' => null]);
        }

        if ($request->isAJAX() || str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return service('response')->setStatusCode(403)
                ->setJSON(['error' => 'No tienes permiso para realizar esta acción.']);
        }

        flash('danger', 'No tienes permiso para realizar esta acción.');
        return redirect()->to('/');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}

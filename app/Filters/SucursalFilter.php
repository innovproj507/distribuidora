<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Auth;
use App\Libraries\SucursalService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Deja en sesión el contexto multi-sucursal: empresa_actual, sucursal_actual,
 * sucursales_disponibles, deposito_actual, depositos_disponibles.
 *
 * Se recalcula en cada petición (son consultas pequeñas) para que una sucursal o depósito
 * desactivado, o una asignación retirada al usuario, se aplique de inmediato. Se conserva
 * la sucursal/depósito elegidos mientras sigan permitidos.
 */
class SucursalFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (!Auth::check()) {
            return null;
        }

        $session  = session();
        $sucursal = $session->get('sucursal_actual');
        $deposito = $session->get('deposito_actual');

        $ctx = SucursalService::contexto(
            $sucursal ? (int) $sucursal['sucursal_id'] : null,
            $deposito ? (int) $deposito['deposito_id'] : null
        );

        if ($ctx === null) {
            $session->remove(['empresa_actual', 'sucursal_actual', 'deposito_actual', 'depositos_disponibles', 'sucursales_disponibles']);
            return null;
        }

        $session->set($ctx);
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}

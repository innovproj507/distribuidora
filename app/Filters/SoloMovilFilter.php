<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Auth;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Los roles de ROLES solo trabajan desde la PWA (/m). Cualquier página web los devuelve a /m;
 * se permiten las rutas que la PWA usa por debajo (vender, caja, búsquedas JSON, PDF de
 * cotización...). Filtro global: no aplica a la API v1 (token) ni a visitantes sin sesión.
 */
class SoloMovilFilter implements FilterInterface
{
    public const ROLES = ['vendedor'];

    /** Prefijos de ruta permitidos (sin barra inicial). */
    private const PERMITIDAS = [
        'm', 'login', 'logout', 'auth/2fa', 'c/',
        'ventas/procesar', 'ventas/clientes/rapido',
        'caja/abrir', 'caja/movimiento', 'caja/cerrar',
        'sucursales/cambiar', 'sucursales/cambiar-deposito',
        'api/',              // mini-API JSON con sesión (buscar productos, clientes, crédito)
        'ventas/cotizaciones/', // solo el PDF, ver abajo
    ];

    public function before(RequestInterface $request, $arguments = null)
    {
        $ruta = trim($request->getUri()->getPath(), '/');
        if (str_starts_with($ruta, 'api/v1') || !Auth::check() || Auth::esApi() || !in_array(Auth::rol(), self::ROLES, true)) {
            return null;
        }

        foreach (self::PERMITIDAS as $p) {
            if ($ruta === rtrim($p, '/') || str_starts_with($ruta, rtrim($p, '/') . '/') || ($p === 'c/' && str_starts_with($ruta, 'c/'))) {
                // De cotizaciones solo el PDF (lo abre la lista móvil)
                if ($p === 'ventas/cotizaciones/' && !preg_match('#^ventas/cotizaciones/\d+/pdf$#', $ruta)) {
                    break;
                }
                return null;
            }
        }

        if ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest' || str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return service('response')->setStatusCode(403)->setJSON(['error' => 'Tu usuario trabaja desde la app del celular.']);
        }
        return redirect()->to('/m');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}

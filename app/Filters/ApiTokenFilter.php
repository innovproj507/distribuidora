<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Auth;
use App\Libraries\Db;
use App\Libraries\SucursalService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Autenticación de la API REST: header "Authorization: Bearer <token>" (users.api_token,
 * emitido por POST /api/v1/login). No usa sesión PHP: deja el usuario en Auth::usarUsuarioApi()
 * para que Auth::can(), la auditoría y los servicios funcionen igual que en la web.
 */
class ApiTokenFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $token = self::token($request);
        if ($token === null) {
            return self::noAutorizado('Token de acceso no proporcionado.');
        }

        $user = Db::row(
            "SELECT u.id, u.username, u.first_name, u.last_name, u.rol, u.is_superuser, u.is_staff,
                    p.empresa_id, p.sucursal_actual_id AS sucursal_id
             FROM users u LEFT JOIN user_profiles p ON u.id = p.user_id
             WHERE u.api_token = ? AND u.is_active = 1
             LIMIT 1",
            [$token]
        );
        if (!$user) {
            return self::noAutorizado('Token de acceso inválido o expirado.');
        }

        $usuario = [
            'id'            => (int) $user['id'],
            'username'      => $user['username'],
            'user_fullname' => trim($user['first_name'] . ' ' . $user['last_name']) ?: $user['username'],
            'rol'           => $user['rol'] ?? 'cajero',
            'is_superuser'  => (bool) $user['is_superuser'],
            'is_staff'      => (bool) $user['is_staff'],
            'empresa_id'    => (int) $user['empresa_id'],
            'sucursal_id'   => 0,
            'deposito_id'   => 0,
        ];
        // Primero el usuario (para que rol y asignaciones se lean en modo API), luego el contexto
        Auth::usarUsuarioApi($usuario);

        // Sucursal: la de la cabecera X-Sucursal-Id si el usuario tiene acceso; si no, la de su
        // perfil; si ya no está disponible (inactiva o retirada), la primera permitida.
        $pedida = (int) $request->getHeaderLine('X-Sucursal-Id');
        $ctx    = SucursalService::contexto($pedida ?: ((int) $user['sucursal_id'] ?: null));
        if ($ctx === null) {
            return service('response')->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'El usuario no tiene una sucursal activa asignada.', 'errors' => null]);
        }
        if ($pedida && (int) $ctx['sucursal_actual']['sucursal_id'] !== $pedida) {
            return service('response')->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'No tienes acceso a esa sucursal.', 'errors' => null]);
        }

        Auth::usarUsuarioApi([
            'empresa_id'  => (int) $ctx['empresa_actual']['empresa_id'],
            'sucursal_id' => (int) $ctx['sucursal_actual']['sucursal_id'],
            'deposito_id' => (int) ($ctx['deposito_actual']['deposito_id'] ?? 0),
        ] + $usuario);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    public static function token(RequestInterface $request): ?string
    {
        if (preg_match('/Bearer\s+(\S+)/i', $request->getHeaderLine('Authorization'), $m)) {
            return $m[1];
        }
        return null;
    }

    private static function noAutorizado(string $mensaje): ResponseInterface
    {
        return service('response')->setStatusCode(401)->setJSON(['status' => 'error', 'message' => $mensaje, 'errors' => null]);
    }
}

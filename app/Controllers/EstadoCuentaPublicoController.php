<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\EstadoCuenta;
use App\Libraries\LimiteIntentos;
use CodeIgniter\Controller;

/**
 * GET /ec/{token} — estado de cuenta del cliente sin iniciar sesión (enlace enviado por WhatsApp).
 * El token es de 43 caracteres al azar, vence a los 30 días y cada envío nuevo revoca el anterior.
 * Tras 20 enlaces inválidos desde la misma IP en 15 minutos, se bloquea (evita probar al azar).
 */
class EstadoCuentaPublicoController extends Controller
{
    public function ver(string $token)
    {
        $this->response->setHeader('Cache-Control', 'no-store, private')->setHeader('X-Robots-Tag', 'noindex, nofollow');
        $clave = LimiteIntentos::clave('estado_cuenta', $this->request->getIPAddress());
        if (LimiteIntentos::bloqueado($clave, 20)) {
            return $this->response->setStatusCode(429)->setBody(view('publico/estado_cuenta_error', ['mensaje' => 'Demasiados intentos. Espere unos minutos.']));
        }
        $enlace = EstadoCuenta::porToken($token);
        if (!$enlace) {
            LimiteIntentos::fallo($clave);
            return $this->response->setStatusCode(404)->setBody(view('publico/estado_cuenta_error', [
                'mensaje' => 'Este enlace no es válido o ya venció. Pida a la tienda que le envíe su estado de cuenta actualizado.',
            ]));
        }
        return view('publico/estado_cuenta', EstadoCuenta::datosPublicos((int) $enlace['cliente_id'], (int) $enlace['empresa_id']) + [
            'expira' => $enlace['expira_en'],
        ]);
    }
}

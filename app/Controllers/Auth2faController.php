<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\AuditService;
use App\Libraries\Auth;
use App\Libraries\Db;
use App\Libraries\LimiteIntentos;
use App\Libraries\TotpService;

class Auth2faController extends BaseController
{
    private const MAX_INTENTOS_2FA = 5;

    // ── Verificación tras login ──────────────────────────────────────────────

    public function show()
    {
        if (!Auth::needs2fa()) {
            return redirect()->to(Auth::check() ? '/' : '/login');
        }
        return $this->render('auth.2fa', ['page_title' => 'Verificación en dos pasos']);
    }

    public function verificar()
    {
        if (!Auth::needs2fa()) {
            return redirect()->to('/login');
        }

        $pending = Auth::pending2faData();
        $code    = trim((string) $this->request->getPost('code'));

        // Un código de 6 dígitos se adivina por fuerza bruta si no hay límite: 5 fallos por
        // usuario cada 15 minutos (en caché, no en la sesión). Al llegar al límite se anula el
        // login pendiente y hay que volver a poner la contraseña.
        $clave = LimiteIntentos::clave('2fa_usuario', (string) $pending['user_id']);
        if (LimiteIntentos::bloqueado($clave, self::MAX_INTENTOS_2FA)) {
            $this->session->remove(['2fa_pending', '2fa_destino']);
            $this->error('Demasiados códigos incorrectos. Espera 15 minutos e inicia sesión de nuevo.');
            return redirect()->to('/login');
        }

        if (!TotpService::verify($pending['totp_secret'], $code)) {
            $fallos = LimiteIntentos::fallo($clave);
            AuditService::log('auth.2fa_fallido', "Código 2FA incorrecto para '{$pending['username']}' (intento {$fallos})", (int) $pending['user_id'], 'usuario');
            if ($fallos >= self::MAX_INTENTOS_2FA) {
                $this->session->remove(['2fa_pending', '2fa_destino']);
                $this->error('Demasiados códigos incorrectos. Espera 15 minutos e inicia sesión de nuevo.');
                return redirect()->to('/login');
            }
            $this->error('Código incorrecto. Intenta de nuevo.');
            return redirect()->to('/auth/2fa');
        }

        LimiteIntentos::limpiar($clave);

        // El login (web o móvil) indicó a dónde volver
        $destino = $this->session->get('2fa_destino') ?? '/';
        Auth::completeLogin($pending);
        $this->session->remove('2fa_destino');

        AuditService::log('auth.2fa_ok', "2FA verificado: {$pending['username']}", (int) $pending['user_id'], 'usuario');

        $this->success('Bienvenido, ' . Auth::name());
        return redirect()->to($destino);
    }

    // ── Configuración ────────────────────────────────────────────────────────

    public function setup()
    {
        $user    = Auth::user();
        $empresa = $this->session->get('empresa_actual');
        $issuer  = $empresa['nombre_comercial'] ?? env('APP_NAME', 'Sistema POS');

        if (!$this->session->has('totp_setup_secret')) {
            $this->session->set('totp_setup_secret', TotpService::generateSecret());
        }

        $secret = $this->session->get('totp_setup_secret');
        $uri    = TotpService::getUri($secret, $user['username'], $issuer);

        return $this->render('auth.2fa_setup', [
            'page_title' => 'Activar verificación en dos pasos',
            'secret'     => $secret,
            'qr_image'   => 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=10&data=' . rawurlencode($uri),
            'ya_activo'  => (bool) $user['totp_habilitado'],
        ]);
    }

    public function guardarSetup()
    {
        $secret = $this->session->get('totp_setup_secret');
        if (!$secret) {
            $this->error('Sesión de configuración expirada. Vuelve a intentarlo.');
            return redirect()->to('/auth/2fa/setup');
        }

        if (!TotpService::verify($secret, trim((string) $this->request->getPost('code')))) {
            $this->error('Código incorrecto. Escanea el QR e intenta de nuevo.');
            return redirect()->to('/auth/2fa/setup');
        }

        Db::query("UPDATE users SET totp_secret = ?, totp_habilitado = 1 WHERE id = ?", [$secret, Auth::id()]);
        $this->session->remove(['totp_setup_secret', '2fa_setup_required']);

        AuditService::log('auth.2fa_activado', '2FA activado para: ' . Auth::name(), Auth::id(), 'usuario', [], $this->empresaId());

        $this->success('Verificación en dos pasos activada correctamente.');
        return redirect()->to('/');
    }

    public function desactivar()
    {
        if ($r = $this->exigir('usuarios.gestionar')) return $r;

        $userId = (int) ($this->request->getPost('user_id') ?? Auth::id());
        $target = Db::row("SELECT username FROM users WHERE id = ?", [$userId]);

        if (!$target) {
            $this->error('Usuario no encontrado.');
            return redirect()->to('/usuarios');
        }

        Db::query("UPDATE users SET totp_habilitado = 0, totp_secret = NULL WHERE id = ?", [$userId]);

        AuditService::log('auth.2fa_desactivado', "2FA desactivado para '{$target['username']}' por " . Auth::name(), $userId, 'usuario', [], $this->empresaId());

        $this->warning("2FA desactivado para '{$target['username']}'.");
        return redirect()->to($userId === Auth::id() ? '/auth/2fa/setup' : '/usuarios');
    }
}

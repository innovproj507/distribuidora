<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\AuditService;
use App\Services\TotpService;

class Auth2faController extends Controller
{
    // ── Verificación tras login ────────────────────────────────────────────────

    public function show(): void
    {
        if (!Auth::needs2fa()) {
            $this->redirect(Auth::check() ? '/' : '/login');
            return;
        }
        $this->view('auth.2fa', [
            'page_title' => 'Verificación en dos pasos',
        ]);
    }

    public function verificar(): void
    {
        if (!$this->verifyCsrf()) return;

        if (!Auth::needs2fa()) {
            $this->redirect('/login');
            return;
        }

        $pending = Auth::pending2faData();
        $code    = trim($this->request->post('code', ''));

        if (!TotpService::verify($pending['totp_secret'], $code)) {
            AuditService::log(
                'auth.2fa_fallido',
                "Código 2FA incorrecto para '{$pending['username']}'",
                $pending['user_id'], 'usuario'
            );
            $this->error('Código incorrecto. Intenta de nuevo.');
            $this->redirect('/auth/2fa');
            return;
        }

        Auth::completeLogin($pending);

        AuditService::log('auth.2fa_ok', "2FA verificado: {$pending['username']}", $pending['user_id'], 'usuario');

        $this->success('Bienvenido, ' . Auth::name());
        $this->redirect('/');
    }

    // ── Configuración inicial ──────────────────────────────────────────────────

    public function setup(): void
    {
        if (!Auth::check()) {
            $this->redirect('/login');
            return;
        }

        $user = Auth::user();
        $empresa = $this->session->get('empresa_actual');
        $issuer  = $empresa['nombre_comercial'] ?? ($_ENV['APP_NAME'] ?? 'Sistema POS');

        // Generar secret temporal en sesión si no existe
        if (!$this->session->has('totp_setup_secret')) {
            $this->session->set('totp_setup_secret', TotpService::generateSecret());
        }

        $secret = $this->session->get('totp_setup_secret');
        $uri    = TotpService::getUri($secret, $user['username'], $issuer);

        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=10&data=' . rawurlencode($uri);

        $this->view('auth.2fa_setup', [
            'page_title' => 'Activar verificación en dos pasos',
            'secret'     => $secret,
            'qr_image'   => $qrUrl,
            'ya_activo'  => (bool) $user['totp_habilitado'],
        ]);
    }

    public function guardarSetup(): void
    {
        if (!$this->verifyCsrf()) return;
        if (!Auth::check()) {
            $this->redirect('/login');
            return;
        }

        $secret = $this->session->get('totp_setup_secret');
        if (!$secret) {
            $this->error('Sesión de configuración expirada. Vuelve a intentarlo.');
            $this->redirect('/auth/2fa/setup');
            return;
        }

        $code = trim($this->request->post('code', ''));
        if (!TotpService::verify($secret, $code)) {
            $this->error('Código incorrecto. Escanea el QR e intenta de nuevo.');
            $this->redirect('/auth/2fa/setup');
            return;
        }

        Database::query(
            "UPDATE users SET totp_secret = ?, totp_habilitado = 1 WHERE id = ?",
            [$secret, Auth::id()]
        );

        $this->session->remove('totp_setup_secret');
        $this->session->remove('2fa_setup_required');

        AuditService::log(
            'auth.2fa_activado',
            '2FA activado para: ' . Auth::name(),
            Auth::id(), 'usuario', [], $this->empresaId()
        );

        $this->success('Verificación en dos pasos activada correctamente.');
        $this->redirect('/');
    }

    public function desactivar(): void
    {
        if (!$this->verifyCsrf()) return;
        if (!Auth::check()) {
            $this->redirect('/login');
            return;
        }
        if (!$this->requirePermission('usuarios.gestionar')) return;

        $userId = (int) $this->request->post('user_id', Auth::id());

        $target = Database::query("SELECT username FROM users WHERE id = ?", [$userId])->fetch();
        if (!$target) {
            $this->error('Usuario no encontrado.');
            $this->redirect('/usuarios');
            return;
        }

        Database::query(
            "UPDATE users SET totp_habilitado = 0, totp_secret = NULL WHERE id = ?",
            [$userId]
        );

        AuditService::log(
            'auth.2fa_desactivado',
            "2FA desactivado para '{$target['username']}' por " . Auth::name(),
            $userId, 'usuario', [], $this->empresaId()
        );

        $this->warning("2FA desactivado para '{$target['username']}'.");
        $this->redirect($userId === Auth::id() ? '/auth/2fa/setup' : '/usuarios');
    }
}

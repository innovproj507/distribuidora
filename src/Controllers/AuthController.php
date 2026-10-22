<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;

class AuthController extends Controller
{
    /**
     * Mostrar formulario de login
     */
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect('/');
            return;
        }
        $this->view('auth.login');
    }

    /**
     * Procesar login
     */
    public function login(): void
    {
        if (!$this->verifyCsrf()) return;

        // El login de la PWA móvil (/m/login) envía _origen=movil para volver a /m
        $esMovil  = $this->request->post('_origen', '') === 'movil';
        $loginUrl = $esMovil ? '/m/login' : '/login';
        $homeUrl  = $esMovil ? '/m/' : '/';

        // Rate limiting por IP: máx 10 intentos fallidos en 15 minutos
        $ip  = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $key = 'login_fail_' . md5($ip);
        $intentos = (int) ($_SESSION[$key . '_count'] ?? 0);
        $desde    = (int) ($_SESSION[$key . '_since'] ?? 0);

        if ($intentos >= 10 && (time() - $desde) < 900) {
            $this->error('Demasiados intentos fallidos. Espera 15 minutos.');
            $this->redirect($loginUrl);
            return;
        }
        if ((time() - $desde) >= 900) {
            $_SESSION[$key . '_count'] = 0;
            $_SESSION[$key . '_since'] = time();
        }

        $username = $this->request->post('username', '');
        $password = $this->request->post('password', '');

        if (empty($username) || empty($password)) {
            $this->error('Por favor ingrese usuario y contraseña.');
            $this->redirect($loginUrl);
            return;
        }

        if (!Auth::attempt($username, $password)) {
            $_SESSION[$key . '_count'] = ($intentos + 1);
            if ($intentos === 0) $_SESSION[$key . '_since'] = time();
            $this->error('Usuario o contraseña incorrectos.');
            $this->redirect($loginUrl);
            return;
        }
        // Login exitoso — limpiar contador
        unset($_SESSION[$key . '_count'], $_SESSION[$key . '_since']);

        if (Auth::needs2fa()) {
            $this->redirect('/auth/2fa');
            return;
        }

        $this->success('Bienvenido, ' . Auth::name());
        $this->redirect($homeUrl);
    }

    /**
     * Cerrar sesión
     */
    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/login');
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Auth;
use App\Libraries\LimiteIntentos;

class AuthController extends BaseController
{
    /** Máximo de intentos fallidos en la ventana de tiempo, por IP y por nombre de usuario. */
    private const MAX_INTENTOS    = 10;
    private const MAX_POR_USUARIO = 20;
    private const VENTANA_SEG     = 900;

    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->to('/');
        }
        return $this->render('auth.login');
    }

    public function login()
    {
        // La PWA (/m/login) envía _origen=movil para volver a /m
        $esMovil  = $this->request->getPost('_origen') === 'movil';
        $loginUrl = $esMovil ? '/m/login' : '/login';
        $homeUrl  = $esMovil ? '/m' : '/';

        $username = trim((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');

        // Contadores en caché del servidor: por IP y por usuario (frena también ataques
        // repartidos entre varias IP contra una misma cuenta)
        $claveIp      = LimiteIntentos::clave('login_ip', $this->request->getIPAddress());
        $claveUsuario = LimiteIntentos::clave('login_usuario', $username);

        if (LimiteIntentos::bloqueado($claveIp, self::MAX_INTENTOS)
            || ($username !== '' && LimiteIntentos::bloqueado($claveUsuario, self::MAX_POR_USUARIO))) {
            $this->error('Demasiados intentos fallidos. Espera 15 minutos.');
            return redirect()->to($loginUrl);
        }

        if ($username === '' || $password === '') {
            $this->error('Por favor ingrese usuario y contraseña.');
            return redirect()->to($loginUrl);
        }

        if (!Auth::attempt($username, $password)) {
            LimiteIntentos::fallo($claveIp, self::VENTANA_SEG);
            LimiteIntentos::fallo($claveUsuario, self::VENTANA_SEG);
            $this->error('Usuario o contraseña incorrectos.');
            return redirect()->to($loginUrl);
        }

        LimiteIntentos::limpiar($claveIp, $claveUsuario);

        if (Auth::needs2fa()) {
            $this->session->set('2fa_destino', $homeUrl);
            return redirect()->to('/auth/2fa');
        }

        $this->success('Bienvenido, ' . Auth::name());
        return redirect()->to($homeUrl);
    }

    public function logout()
    {
        Auth::logout();
        return redirect()->to('/login');
    }
}

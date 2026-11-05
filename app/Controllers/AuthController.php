<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Auth;

class AuthController extends BaseController
{
    /** Máximo de intentos fallidos por IP en la ventana de tiempo. */
    private const MAX_INTENTOS = 10;
    private const VENTANA_SEG  = 900;

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

        $key      = 'login_fail_' . md5($this->request->getIPAddress());
        $intentos = (int) ($this->session->get($key . '_count') ?? 0);
        $desde    = (int) ($this->session->get($key . '_since') ?? 0);

        if ($intentos >= self::MAX_INTENTOS && (time() - $desde) < self::VENTANA_SEG) {
            $this->error('Demasiados intentos fallidos. Espera 15 minutos.');
            return redirect()->to($loginUrl);
        }
        if ((time() - $desde) >= self::VENTANA_SEG) {
            $intentos = 0;
            $this->session->set([$key . '_count' => 0, $key . '_since' => time()]);
        }

        $username = trim((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');

        if ($username === '' || $password === '') {
            $this->error('Por favor ingrese usuario y contraseña.');
            return redirect()->to($loginUrl);
        }

        if (!Auth::attempt($username, $password)) {
            $this->session->set($key . '_count', $intentos + 1);
            if ($intentos === 0) {
                $this->session->set($key . '_since', time());
            }
            $this->error('Usuario o contraseña incorrectos.');
            return redirect()->to($loginUrl);
        }

        $this->session->remove([$key . '_count', $key . '_since']);

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

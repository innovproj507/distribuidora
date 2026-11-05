<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Auth;

/**
 * 404 personalizado: JSON para la API, login si no hay sesión, y página dentro del layout.
 */
class Errores extends BaseController
{
    public function noEncontrado()
    {
        if (str_starts_with(trim($this->request->getUri()->getPath(), '/'), 'api/')) {
            return $this->response->setStatusCode(404)->setJSON(['status' => 'error', 'message' => 'Endpoint no encontrado.', 'errors' => null]);
        }

        if (!Auth::check()) {
            return redirect()->to('/login');
        }

        $this->response->setStatusCode(404);

        return $this->render('errors.no_encontrado', [
            'page_title'    => 'Página no encontrada',
            'page_subtitle' => 'Error 404',
            'ruta'          => '/' . trim($this->request->getUri()->getPath(), '/'),
        ]);
    }
}

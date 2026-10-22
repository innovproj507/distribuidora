<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\ModuleManager;
use App\Core\Auth;

class AppController extends Controller
{
    public function index(): void
    {
        if (!Auth::isSuperuser()) {
            $this->error('Acceso denegado.');
            $this->redirect('/');
            return;
        }

        $modules = ModuleManager::getAllModules();

        // Agrupar por categoría
        $categorias = [];
        foreach ($modules as $mod) {
            $cat = $mod['category'] ?? 'General';
            $categorias[$cat][] = $mod;
        }

        $this->view('apps.index', [
            'page_title'    => 'Aplicaciones',
            'page_subtitle' => 'Instala y gestiona módulos del sistema',
            'modules'       => $modules,
            'categorias'    => $categorias,
        ]);
    }

    public function instalar(): void
    {
        if (!Auth::isSuperuser()) {
            $this->jsonError('Acceso denegado', 403);
            return;
        }

        $name = trim($_POST['module'] ?? '');
        if ($name === '') {
            $this->error('Módulo no especificado.');
            $this->redirect('/apps');
            return;
        }

        $result = ModuleManager::install($name);

        if ($result['success']) {
            $manifest = ModuleManager::getManifest($name);
            $this->success("Módulo '{$manifest['label']}' instalado correctamente.");
        } else {
            $this->error($result['error']);
        }

        $this->redirect('/apps');
    }

    public function desinstalar(): void
    {
        if (!Auth::isSuperuser()) {
            $this->jsonError('Acceso denegado', 403);
            return;
        }

        $name = trim($_POST['module'] ?? '');
        if ($name === '') {
            $this->error('Módulo no especificado.');
            $this->redirect('/apps');
            return;
        }

        $result = ModuleManager::uninstall($name);

        if ($result['success']) {
            $manifest = ModuleManager::getManifest($name);
            $this->success("Módulo '{$manifest['label']}' desinstalado.");
        } else {
            $this->error($result['error']);
        }

        $this->redirect('/apps');
    }

    private function jsonError(string $msg, int $code): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $msg]);
    }
}

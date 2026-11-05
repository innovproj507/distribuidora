<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\AuditService;
use App\Libraries\Auth;
use App\Libraries\ModuleManager;

/** "Tienda" de módulos: instalar/desinstalar (solo superusuarios). */
class AppController extends BaseController
{
    public function index()
    {
        if ($r = $this->soloSuperusuario()) return $r;

        $modules    = ModuleManager::getAllModules();
        $categorias = [];
        foreach ($modules as $mod) {
            $categorias[$mod['category'] ?? 'General'][] = $mod;
        }

        return $this->render('apps.index', [
            'page_title'    => 'Aplicaciones',
            'page_subtitle' => 'Instala y gestiona módulos del sistema',
            'modules'       => $modules,
            'categorias'    => $categorias,
        ]);
    }

    public function instalar()
    {
        return $this->cambiar('install', 'instalado correctamente');
    }

    public function desinstalar()
    {
        return $this->cambiar('uninstall', 'desinstalado');
    }

    private function cambiar(string $accion, string $verbo)
    {
        if ($r = $this->soloSuperusuario()) return $r;

        $name = trim((string) ($this->request->getPost('module') ?? ''));
        if ($name === '') {
            $this->error('Módulo no especificado.');
            return redirect()->to('/apps');
        }

        $result = ModuleManager::$accion($name);
        if ($result['success']) {
            $label = ModuleManager::getManifest($name)['label'] ?? $name;
            AuditService::log('core.modulos', "Módulo '{$label}' {$verbo}", null, 'modulo', ['modulo' => $name], $this->empresaId());
            $this->success("Módulo '{$label}' {$verbo}.");
        } else {
            $this->error($result['error']);
        }

        return redirect()->to('/apps');
    }

    private function soloSuperusuario()
    {
        if (Auth::isSuperuser()) {
            return null;
        }
        $this->error('Acceso denegado.');
        return redirect()->to('/');
    }
}

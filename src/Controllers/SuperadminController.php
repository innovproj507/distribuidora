<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

/**
 * SuperadminController - Deshabilitado en sistema de empresa única.
 * Este sistema no es SaaS; no existe panel maestro multi-tenant.
 */
class SuperadminController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->error('El panel maestro no está disponible en la versión de empresa única.');
        $this->redirect('/dashboard');
        exit;
    }

    // Stubs para evitar errores de método no encontrado
    public function index(): void {}
    public function empresas(): void {}
    public function crearEmpresa(): void {}
    public function editarEmpresa(int $id): void {}
    public function eliminarEmpresa(int $id): void {}
    public function usuarios(): void {}
    public function editarUsuario(int $id): void {}
    public function cambiarEstadoUsuario(int $id): void {}
    public function facturacion(): void {}
    public function planes(): void {}
    public function crearPlan(): void {}
    public function editarPlan(int $id): void {}
    public function eliminarPlan(int $id): void {}
    public function alertas(): void {}
    public function database(): void {}
    public function logs(): void {}
    public function backup(): void {}
    public function clearCache(): void {}
    public function optimizarTablas(): void {}
}

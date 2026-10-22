<?php
declare(strict_types=1);

namespace App\Controllers\Api;

/**
 * ApiMasterController - Deshabilitado. Sistema de empresa única, no SaaS.
 */
class ApiMasterController extends ApiController
{
    public function __construct()
    {
        parent::__construct();
        $this->errorResponse('Este endpoint no está disponible en la versión de empresa única.', 404);
        exit;
    }

    public function stats(): void {}
    public function tenants(): void {}
    public function saveTenant(): void {}
    public function planes(): void {}
    public function savePlan(): void {}
    public function optimizeDB(): void {}
}

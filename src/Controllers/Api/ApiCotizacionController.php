<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Core\Database;

/**
 * ApiCotizacionController - Endpoints para cotizaciones
 */
class ApiCotizacionController extends ApiController
{
    /**
     * Listar cotizaciones
     */
    public function index(): void
    {
        $cotizaciones = Database::query(
            "SELECT c.*, cl.nombre as cliente_nombre 
             FROM cotizaciones c
             LEFT JOIN customers cl ON c.cliente_id = cl.cliente_id
             ORDER BY c.fecha DESC"
        )->fetchAll();

        $this->successResponse(['cotizaciones' => $cotizaciones]);
    }

    /**
     * Obtener detalle de una cotización
     */
    public function detalle(int $id): void
    {
        $cotizacion = Cotizacion::find($id);
        if (!$cotizacion) {
            $this->errorResponse('Cotización no encontrada', 404);
            return;
        }

        $detalles = Database::query(
            "SELECT d.*, p.nombre as producto_nombre 
             FROM cotizaciones_detalle d
             JOIN productos p ON d.producto_id = p.producto_id
             WHERE d.cotizacion_id = ?",
            [$id]
        )->fetchAll();

        $this->successResponse([
            'cotizacion' => $cotizacion,
            'detalles' => $detalles
        ]);
    }
}

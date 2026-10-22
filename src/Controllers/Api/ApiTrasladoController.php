<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;

/**
 * ApiTrasladoController - Gestión de traslados de inventario entre depósitos
 */
class ApiTrasladoController extends ApiController
{
    /**
     * Listar traslados realizados
     */
    public function index(): void
    {
        $empresaId = $this->empresaId();
        $limit = (int) ($this->request->get('limit', '20'));

        $traslados = Database::query(
            "SELECT t.*, d1.nombre as origen_nombre, d2.nombre as destino_nombre, u.username as usuario_nombre
             FROM traslados t
             LEFT JOIN depositos d1 ON t.deposito_origen_id = d1.deposito_id
             LEFT JOIN depositos d2 ON t.deposito_destino_id = d2.deposito_id
             LEFT JOIN users u ON t.usuario_id = u.id
             WHERE t.empresa_id = ?
             ORDER BY t.fecha_envio DESC
             LIMIT ?",
            [$empresaId, $limit]
        )->fetchAll();

        $this->successResponse(['traslados' => $traslados]);
    }

    /**
     * Registrar un nuevo traslado de stock
     */
    public function guardar(): void
    {
        $data = $this->getInputData();
        $empresaId = $this->empresaId();
        $items = $data['items'] ?? [];

        if (empty($data['deposito_origen_id']) || empty($data['deposito_destino_id']) || empty($items)) {
            $this->errorResponse('Depósitos e ítems son obligatorios');
            return;
        }

        try {
            Database::beginTransaction();

            // Crear cabecera de traslado
            Database::query(
                "INSERT INTO traslados (empresa_id, deposito_origen_id, deposito_destino_id, usuario_id, numero, notas, fecha_envio, estado) 
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), 'completado')",
                [
                    $empresaId,
                    $data['deposito_origen_id'],
                    $data['deposito_destino_id'],
                    $this->user_id,
                    $data['numero'] ?? 'TRAS-' . time(),
                    $data['notas'] ?? ''
                ]
            );

            $trasladoId = (int) Database::lastInsertId();

            // Registrar detalles y mover stock
            foreach ($items as $item) {
                $productoId = $item['producto_id'];
                $cantidad = $item['cantidad'];

                Database::query(
                    "INSERT INTO traslados_detalle (traslado_id, producto_id, cantidad) 
                     VALUES (?, ?, ?)",
                    [$trasladoId, $productoId, $cantidad]
                );

                // Descontar del origen (Actualizar tabla inventario)
                Database::query(
                    "UPDATE inventario SET existencia = existencia - ? 
                     WHERE producto_id = ? AND deposito_id = ?",
                    [$cantidad, $productoId, $data['deposito_origen_id']]
                );

                // Aumentar en el destino
                // Verificar si existe el registro en el destino (Actualizar tabla inventario)
                $existeDestino = Database::query(
                    "SELECT 1 FROM inventario WHERE producto_id = ? AND deposito_id = ?",
                    [$productoId, $data['deposito_destino_id']]
                )->fetch();

                if ($existeDestino) {
                    Database::query(
                        "UPDATE inventario SET existencia = existencia + ? 
                         WHERE producto_id = ? AND deposito_id = ?",
                        [$cantidad, $productoId, $data['deposito_destino_id']]
                    );
                } else {
                    Database::query(
                        "INSERT INTO inventario (producto_id, deposito_id, existencia) 
                         VALUES (?, ?, ?)",
                        [$productoId, $data['deposito_destino_id'], $cantidad]
                    );
                }
            }

            Database::commit();
            $this->successResponse(['traslado_id' => $trasladoId], 'Traslado registrado exitosamente', 201);

        } catch (\Exception $e) {
            Database::rollback();
            $this->errorResponse($e->getMessage(), 500);
        }
    }
}

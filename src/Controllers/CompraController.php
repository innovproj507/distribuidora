<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Auth;
use App\Services\AuditService;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Proveedor;
use App\Models\Deposito;
use App\Models\Movimiento;
use App\Models\Lote;

class CompraController extends Controller
{
    public function index(): void
    {
        $sucursalId = $this->sucursalId();
        $buscar     = trim($this->request->get('buscar', ''));
        $perPage    = \in_array((int) $this->request->get('por_pagina', '25'), [10, 25, 50, 100], true)
                        ? (int) $this->request->get('por_pagina', '25') : 25;
        $page       = max(1, (int) $this->request->get('page', '1'));
        $offset     = ($page - 1) * $perPage;

        $where  = 'c.sucursal_id = ?';
        $params = [$sucursalId];
        if ($buscar !== '') {
            $where   .= ' AND (c.numero_factura LIKE ? OR p.nombre LIKE ?)';
            $params[] = "%{$buscar}%";
            $params[] = "%{$buscar}%";
        }

        $total = (int) Database::query(
            "SELECT COUNT(*) AS total
             FROM compras c
             JOIN proveedores p ON c.proveedor_id = p.proveedor_id
             WHERE {$where}",
            $params
        )->fetch()['total'];

        $compras = Database::query(
            "SELECT c.*, p.nombre AS proveedor_nombre
             FROM compras c
             JOIN proveedores p ON c.proveedor_id = p.proveedor_id
             WHERE {$where}
             ORDER BY c.fecha_compra DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        )->fetchAll();

        $totalPages = max(1, (int) ceil($total / $perPage));

        $stats = Database::query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN estado IN ('pendiente','parcialmente_recibida') THEN 1 ELSE 0 END) AS pendientes,
                SUM(CASE WHEN estado = 'recibida' THEN 1 ELSE 0 END)                             AS recibidas,
                COALESCE(SUM(CASE WHEN estado = 'recibida' THEN monto_total ELSE 0 END), 0)       AS monto_recibido
             FROM compras WHERE sucursal_id = ?",
            [$sucursalId]
        )->fetch();

        $this->view('compras.index', [
            'page_title'    => 'Gestión de Compras',
            'page_subtitle' => 'Entradas de mercancía y facturas de proveedores',
            'compras'       => $compras,
            'stats'         => $stats,
            'buscar'        => $buscar,
            'pagination'    => [
                'total'         => $total,
                'per_page'      => $perPage,
                'current_page'  => $page,
                'total_pages'   => $totalPages,
                'has_previous'  => $page > 1,
                'has_next'      => $page < $totalPages,
                'previous_page' => max(1, $page - 1),
                'next_page'     => min($totalPages, $page + 1),
            ],
        ]);
    }

    public function crear(): void
    {
        $proveedores = Proveedor::all('nombre ASC');
        $depositos   = Deposito::where('sucursal_id', $this->sucursalId());
        $categorias  = Database::query(
            "SELECT categoria_id, nombre FROM categorias_productos
             WHERE empresa_id = ? ORDER BY nombre ASC",
            [$this->empresaId()]
        )->fetchAll();
        $empresa = \App\Models\Empresa::find($this->empresaId());

        $this->view('compras.crear', [
            'page_title'    => 'Nueva Compra',
            'page_subtitle' => 'Registrar entrada de mercancía',
            'proveedores'   => $proveedores,
            'depositos'     => $depositos,
            'categorias'    => $categorias,
            'margen_precio_a' => (float) ($empresa->margen_precio_a ?? 35),
            'margen_precio_b' => (float) ($empresa->margen_precio_b ?? 20),
            'margen_precio_c' => (float) ($empresa->margen_precio_c ?? 10),
        ]);
    }

    public function detalle(int $id): void
    {
        $compra = $this->findCompra($id);
        if (!$compra) return;

        $detalles = Database::query(
            "SELECT cd.*, pr.nombre as producto_nombre, pr.codigo as producto_codigo,
                    (cd.cantidad - cd.cantidad_recibida) AS cantidad_pendiente
             FROM compras_detalle cd
             JOIN productos pr ON cd.producto_id = pr.producto_id
             WHERE cd.compra_id = ?",
            [$id]
        )->fetchAll();

        $this->view('compras.detalle', [
            'page_title' => 'Compra #' . ($compra['numero_factura'] ?? $id),
            'page_subtitle' => 'Detalle de compra',
            'compra' => $compra,
            'detalles' => $detalles,
        ]);
    }

    // ── Recepción parcial / completa ─────────────────────────────────────────

    public function recibir(int $id): void
    {
        $compra = $this->findCompra($id);
        if (!$compra) return;

        if ($compra['estado'] === 'recibida') {
            $this->info('Esta orden ya fue recibida completamente.');
            $this->redirect("/compras/{$id}");
            return;
        }
        if ($compra['estado'] === 'cancelada') {
            $this->error('La orden está cancelada.');
            $this->redirect("/compras/{$id}");
            return;
        }

        $items = Database::query(
            "SELECT cd.*, pr.nombre as producto_nombre, pr.codigo as producto_codigo,
                    (cd.cantidad - cd.cantidad_recibida) AS pendiente
             FROM compras_detalle cd
             JOIN productos pr ON cd.producto_id = pr.producto_id
             WHERE cd.compra_id = ? AND cd.cantidad > cd.cantidad_recibida
             ORDER BY pr.nombre",
            [$id]
        )->fetchAll();

        $depositos = Deposito::where('sucursal_id', $this->sucursalId());

        $this->view('compras.recibir', [
            'page_title' => 'Registrar Recepción',
            'page_subtitle' => 'Compra #' . ($compra['numero_factura'] ?? $id),
            'compra' => $compra,
            'items' => $items,
            'depositos' => $depositos,
        ]);
    }

    public function procesarRecepcion(int $id): void
    {
        if (!$this->verifyCsrf()) return;

        $compra = $this->findCompra($id);
        if (!$compra) return;

        if (in_array($compra['estado'], ['recibida', 'cancelada'])) {
            $this->error('Estado de la orden no permite recepción.');
            $this->redirect("/compras/{$id}");
            return;
        }

        $cantidadesRecibidas = $this->request->post('cantidad_recibida', []);
        $depositoOverride    = (int) $this->request->post('deposito_id', 0);

        try {
            Database::beginTransaction();

            $pendientes = Database::query(
                "SELECT cd.*, pr.nombre as producto_nombre
                 FROM compras_detalle cd
                 JOIN productos pr ON cd.producto_id = pr.producto_id
                 WHERE cd.compra_id = ? AND cd.cantidad > cd.cantidad_recibida",
                [$id]
            )->fetchAll();

            foreach ($pendientes as $linea) {
                $detalleId  = (int) $linea['detalle_id'];
                $cantPedida = (float) $linea['cantidad'];
                $cantYaRec  = (float) $linea['cantidad_recibida'];
                $pendiente  = $cantPedida - $cantYaRec;

                $cantNueva = isset($cantidadesRecibidas[$detalleId])
                    ? min((float) $cantidadesRecibidas[$detalleId], $pendiente)
                    : 0.0;

                if ($cantNueva <= 0) continue;

                $productoId = (int) $linea['producto_id'];
                $costoUnit  = (float) $linea['costo'];
                $depositoId = $depositoOverride ?: (int) $compra['deposito_id'];

                $this->recibirLinea(
                    productoId:   $productoId,
                    depositoId:   $depositoId,
                    cantidadBase: $cantNueva,
                    costoUnit:    $costoUnit,
                    compraId:     $id,
                    factura:      $compra['numero_factura'] ?? '',
                    numeroLote:   $linea['numero_lote'] ?? null,
                    fechaVence:   $linea['fecha_vencimiento'] ?? null,
                );

                Database::query(
                    "UPDATE compras_detalle SET cantidad_recibida = cantidad_recibida + ? WHERE detalle_id = ?",
                    [$cantNueva, $detalleId]
                );
            }

            // Determinar nuevo estado de la orden
            $sinRecibir = (int) Database::query(
                "SELECT COUNT(*) as t FROM compras_detalle WHERE compra_id = ? AND cantidad > cantidad_recibida",
                [$id]
            )->fetch()['t'];

            $nuevoEstado = $sinRecibir === 0 ? 'recibida' : 'parcialmente_recibida';
            Database::query(
                "UPDATE compras SET estado = ?" . ($nuevoEstado === 'recibida' ? ", fecha_recepcion = NOW()" : "") . " WHERE compra_id = ?",
                [$nuevoEstado, $id]
            );

            Database::commit();

            AuditService::log(
                'compras.recibir',
                "Recepción " . ($nuevoEstado === 'recibida' ? 'completa' : 'parcial') . " — Compra #{$id}",
                $id, 'compra', ['estado' => $nuevoEstado], $this->empresaId()
            );

            $msg = $nuevoEstado === 'recibida'
                ? 'Recepción completa registrada. Inventario actualizado.'
                : 'Recepción parcial registrada. Quedan artículos pendientes.';
            $this->success($msg);
            $this->redirect("/compras/{$id}");

        } catch (\Exception $e) {
            Database::rollback();
            $this->error('Error al procesar recepción: ' . $e->getMessage());
            $this->redirect("/compras/{$id}/recibir");
        }
    }

    // ── Crear orden de compra ─────────────────────────────────────────────────

    public function guardar(): void
    {
        if (!$this->request->isPost()) {
            $this->json(['error' => 'Método no permitido'], 405);
            return;
        }

        $data = $this->request->json();
        $esPendiente = !empty($data['guardar_como_pendiente']);

        try {
            Database::beginTransaction();

            $mainDepositoId = !empty($data['items']) ? (int) $data['items'][0]['deposito_id'] : 0;

            $compra = Compra::create([
                'proveedor_id'            => $data['proveedor_id'],
                'sucursal_id'             => $this->sucursalId(),
                'empresa_id'              => $this->empresaId(),
                'deposito_id'             => $mainDepositoId,
                'numero_factura'          => trim($data['numero_factura'] ?? '') ?: ('PEND-' . date('YmdHis') . '-' . rand(100, 999)),
                'numero_factura_proveedor'=> trim($data['numero_factura'] ?? '') ?: null,
                'monto_subtotal'          => $data['subtotal'] ?? 0,
                'monto_itbms'             => $data['itbms'] ?? 0,
                'monto_total'             => $data['total'] ?? 0,
                'estado'                  => $esPendiente ? 'pendiente' : 'recibida',
                'fecha_compra'            => $data['fecha_compra'] ?? date('Y-m-d'),
                'notas'                   => $data['notas'] ?? '',
                'usuario_id'              => Auth::id(),
            ]);

            foreach ($data['items'] as $item) {
                $productoId     = (int)   $item['producto_id'];
                $cantidadOrig   = (float) $item['cantidad'];
                $costoOriginal  = (float) $item['costo'];
                $depositoId     = (int)  ($item['deposito_id'] ?? $mainDepositoId);
                $unidadId       = !empty($item['unidad_id']) ? (int) $item['unidad_id'] : null;

                $factor = (float)($item['factor'] ?? 1);
                if ($factor < 1) $factor = 1.0;
                if ($unidadId) {
                    $uRow = Database::query(
                        "SELECT factor_conversion FROM productos_unidades WHERE unidad_id = ? AND producto_id = ?",
                        [$unidadId, $productoId]
                    )->fetch();
                    if ($uRow) $factor = (float) $uRow['factor_conversion'];
                }

                $cantidadBase    = $cantidadOrig * $factor;
                $costoUnitBase   = $factor > 0 ? $costoOriginal / $factor : $costoOriginal;
                $cantidadRecibida = $esPendiente ? 0.0 : $cantidadBase;

                CompraDetalle::create([
                    'compra_id'         => $compra->compra_id,
                    'producto_id'       => $productoId,
                    'cantidad'          => $cantidadBase,
                    'cantidad_recibida' => $cantidadRecibida,
                    'costo'             => $costoUnitBase,
                    'itbms'             => $item['itbms'] ?? 0,
                    'total_linea'       => $cantidadOrig * $costoOriginal,
                    'numero_lote'       => !empty($item['numero_lote']) ? trim($item['numero_lote']) : null,
                    'fecha_vencimiento' => !empty($item['fecha_vencimiento']) ? $item['fecha_vencimiento'] : null,
                    'fecha_fabricacion' => !empty($item['fecha_fabricacion']) ? $item['fecha_fabricacion'] : null,
                ]);

                // Actualizar precios de venta A, B, C si vienen en el item
                foreach (['a' => 'precio_a', 'b' => 'precio_b', 'c' => 'precio_c'] as $tipo => $campo) {
                    $precio = (float)($item[$campo] ?? 0);
                    if ($precio > 0) {
                        Database::query(
                            "INSERT INTO precios_productos (producto_id, tipo_precio, precio, fecha_inicio)
                             VALUES (?, ?, ?, CURDATE())
                             ON DUPLICATE KEY UPDATE precio = VALUES(precio), fecha_inicio = VALUES(fecha_inicio)",
                            [$productoId, $tipo, $precio]
                        );
                    }
                }

                if (!$esPendiente) {
                    $this->recibirLinea(
                        productoId:   $productoId,
                        depositoId:   $depositoId,
                        cantidadBase: $cantidadBase,
                        costoUnit:    $costoUnitBase,
                        compraId:     (int) $compra->compra_id,
                        factura:      $data['numero_factura'] ?? '',
                        numeroLote:   !empty($item['numero_lote']) ? trim($item['numero_lote']) : null,
                        fechaVence:   !empty($item['fecha_vencimiento']) ? $item['fecha_vencimiento'] : null,
                    );
                }
            }

            Database::commit();
            AuditService::log(
                'compras.crear',
                "Compra #{$compra->compra_id} creada" . ($esPendiente ? ' (orden pendiente)' : ' (recibida)'),
                (int) $compra->compra_id, 'compra',
                ['estado' => $esPendiente ? 'pendiente' : 'recibida', 'total' => $data['total'] ?? 0],
                $this->empresaId()
            );
            $this->json(['success' => true, 'compra_id' => $compra->compra_id]);

        } catch (\Exception $e) {
            Database::rollback();
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function recibirLinea(
        int     $productoId,
        int     $depositoId,
        float   $cantidadBase,
        float   $costoUnit,
        int     $compraId,
        string  $factura,
        ?string $numeroLote,
        ?string $fechaVence,
    ): void {
        $invRow = Database::query(
            "SELECT inventario_id, existencia, costo_promedio FROM inventario WHERE producto_id = ? AND deposito_id = ? LIMIT 1",
            [$productoId, $depositoId]
        )->fetch();

        if (!$invRow) {
            Database::query(
                "INSERT INTO inventario (producto_id, deposito_id, existencia, ultimo_costo, costo_promedio, fecha_actualizacion_costo)
                 VALUES (?, ?, 0, ?, ?, NOW())",
                [$productoId, $depositoId, $costoUnit, $costoUnit]
            );
            $inventarioId = (int) Database::lastInsertId();
            $oldQty       = 0.0;
            $oldAvgCost   = $costoUnit;
        } else {
            $inventarioId = (int) $invRow['inventario_id'];
            $oldQty       = (float) $invRow['existencia'];
            $oldAvgCost   = (float) $invRow['costo_promedio'];
        }

        // Costo promedio ponderado
        $newQty = $oldQty + $cantidadBase;
        if ($oldQty <= 0) {
            $newAvgCost = $costoUnit;
        } elseif ($newQty > 0) {
            $newAvgCost = (($oldQty * $oldAvgCost) + ($cantidadBase * $costoUnit)) / $newQty;
        } else {
            $newAvgCost = $costoUnit;
        }

        Movimiento::registrar(
            $inventarioId, 'entrada', $cantidadBase, $compraId, 'compras',
            "Compra Factura #{$factura}"
        );

        Database::query(
            "UPDATE inventario SET ultimo_costo = ?, costo_promedio = ?, fecha_actualizacion_costo = NOW() WHERE inventario_id = ?",
            [$costoUnit, $newAvgCost, $inventarioId]
        );

        Database::query(
            "UPDATE productos SET costo = ? WHERE producto_id = ?",
            [$costoUnit, $productoId]
        );

        if ($numeroLote) {
            $loteExist = Database::query(
                "SELECT lote_id, cantidad_actual FROM lotes WHERE producto_id=? AND deposito_id=? AND numero_lote=? LIMIT 1",
                [$productoId, $depositoId, $numeroLote]
            )->fetch();
            if ($loteExist) {
                $nuevoSaldo = (float) $loteExist['cantidad_actual'] + $cantidadBase;
                Database::query("UPDATE lotes SET cantidad_actual=?,estado='activo' WHERE lote_id=?", [$nuevoSaldo, $loteExist['lote_id']]);
                Database::query(
                    "INSERT INTO lote_movimientos(lote_id,tipo,cantidad,saldo_anterior,saldo_nuevo,referencia_id,referencia_tipo) VALUES(?,'entrada',?,?,?,?,'compras')",
                    [$loteExist['lote_id'], $cantidadBase, $loteExist['cantidad_actual'], $nuevoSaldo, $compraId]
                );
            } else {
                $lote = Lote::create([
                    'producto_id'     => $productoId,
                    'deposito_id'     => $depositoId,
                    'numero_lote'     => $numeroLote,
                    'fecha_vencimiento' => $fechaVence,
                    'cantidad_inicial' => $cantidadBase,
                    'cantidad_actual' => $cantidadBase,
                    'estado'          => 'activo',
                ]);
                Database::query(
                    "INSERT INTO lote_movimientos(lote_id,tipo,cantidad,saldo_anterior,saldo_nuevo,referencia_id,referencia_tipo) VALUES(?,'entrada',?,0,?,?,'compras')",
                    [$lote->lote_id, $cantidadBase, $cantidadBase, $compraId]
                );
            }
        }
    }

    // ── Crear proveedor rápido desde compras ─────────────────────────────────────

    public function crearProveedorRapido(): void
    {
        $data   = $this->request->json() ?: [];
        $nombre = trim($data['nombre'] ?? '');

        if ($nombre === '') {
            $this->json(['error' => 'El nombre es requerido'], 422);
            return;
        }

        $empresaId = $this->empresaId();

        try {
            // Auto-generar código tipo PROV01, PROV02...
            $ultimo = Database::query(
                "SELECT codigo FROM proveedores
                 WHERE empresa_id = ? AND codigo REGEXP '^PROV[0-9]+$'
                 ORDER BY CAST(SUBSTRING(codigo, 5) AS UNSIGNED) DESC LIMIT 1",
                [$empresaId]
            )->fetch();
            $siguiente = $ultimo ? ((int) substr($ultimo['codigo'], 4) + 1) : 1;
            $codigo    = 'PROV' . str_pad((string)$siguiente, 2, '0', STR_PAD_LEFT);

            Database::query(
                "INSERT INTO proveedores (empresa_id, codigo, nombre, telefono, ruc, estado, fecha_registro)
                 VALUES (?, ?, ?, ?, ?, 'activo', NOW())",
                [
                    $empresaId,
                    $codigo,
                    $nombre,
                    trim($data['telefono'] ?? '') ?: null,
                    trim($data['ruc']      ?? '') ?: null,
                ]
            );
            $proveedorId = (int) Database::lastInsertId();

            AuditService::log(
                'inventario.proveedor.crear_rapido',
                "Proveedor creado rápido desde compras: {$nombre}",
                $proveedorId, 'proveedor', [], $empresaId
            );

            $this->json([
                'success'   => true,
                'proveedor' => ['id' => $proveedorId, 'nombre' => $nombre],
            ]);
        } catch (\Throwable $e) {
            $this->json(['error' => 'No se pudo crear el proveedor: ' . $e->getMessage()], 500);
        }
    }

    // ── Siguiente código de producto ──────────────────────────────────────────────

    public function siguienteCodigoProducto(): void
    {
        $this->json(['codigo' => $this->generarCodigoProducto()]);
    }

    private function generarCodigoProducto(): string
    {
        $ultimo = Database::query(
            "SELECT codigo FROM productos
             WHERE empresa_id = ? AND codigo REGEXP '^PROD-[0-9]+$'
             ORDER BY CAST(SUBSTRING(codigo, 6) AS UNSIGNED) DESC LIMIT 1",
            [$this->empresaId()]
        )->fetch();

        $siguiente = $ultimo ? ((int) substr($ultimo['codigo'], 5) + 1) : 1;
        return 'PROD-' . str_pad((string) $siguiente, 5, '0', STR_PAD_LEFT);
    }

    // ── Crear producto rápido desde compras ──────────────────────────────────────

    public function crearProductoRapido(): void
    {
        $data   = $this->request->json() ?: [];
        $nombre = trim($data['nombre'] ?? '');
        $costo  = (float) ($data['costo'] ?? 0);

        if ($nombre === '') {
            $this->json(['error' => 'El nombre es requerido'], 422);
            return;
        }

        $empresaId = $this->empresaId();

        // Usar código enviado o generar consecutivo
        $codigo = trim($data['codigo'] ?? '');
        if ($codigo === '') {
            $codigo = $this->generarCodigoProducto();
        }

        // Verificar código único
        $existe = Database::query(
            "SELECT producto_id FROM productos WHERE codigo = ? AND empresa_id = ? LIMIT 1",
            [$codigo, $empresaId]
        )->fetch();

        if ($existe) {
            $this->json(['error' => "El código '{$codigo}' ya está en uso."], 409);
            return;
        }

        try {
            $categoriaId = !empty($data['categoria_id']) ? (int)$data['categoria_id'] : null;

            Database::query(
                "INSERT INTO productos (empresa_id, nombre, codigo, costo, categoria_id, itbms, estado, fecha_creacion)
                 VALUES (?, ?, ?, ?, ?, 7, 'activo', NOW())",
                [$empresaId, $nombre, $codigo, $costo, $categoriaId]
            );
            $productoId = (int) Database::lastInsertId();

            // Crear registro de inventario en el depósito principal de la sucursal
            // para que el producto aparezca en el listado de inventario
            $deposito = Database::query(
                "SELECT deposito_id FROM depositos
                 WHERE sucursal_id = ? AND estado = 'activo'
                 ORDER BY es_principal DESC LIMIT 1",
                [$this->sucursalId()]
            )->fetch();

            if ($deposito) {
                Database::query(
                    "INSERT INTO inventario
                        (producto_id, deposito_id, existencia, ultimo_costo, costo_promedio, fecha_actualizacion_costo)
                     VALUES (?, ?, 0, ?, ?, NOW())",
                    [$productoId, $deposito['deposito_id'], $costo, $costo]
                );
            }

            AuditService::log(
                'inventario.producto.crear_rapido',
                "Producto creado rápido desde compras: {$nombre} ({$codigo})",
                $productoId, 'producto', [], $empresaId
            );

            $this->json([
                'success'  => true,
                'producto' => [
                    'producto_id' => $productoId,
                    'nombre'      => $nombre,
                    'codigo'      => $codigo,
                    'costo'       => $costo,
                    'precio_a'    => 0,
                    'precio_b'    => 0,
                    'precio_c'    => 0,
                ],
            ]);
        } catch (\Throwable $e) {
            $this->json(['error' => 'No se pudo crear el producto: ' . $e->getMessage()], 500);
        }
    }

    private function findCompra(int $id): ?array
    {
        $compra = Database::query(
            "SELECT c.*, p.nombre as proveedor_nombre, u.username as usuario_nombre, d.nombre as deposito_nombre
             FROM compras c
             LEFT JOIN proveedores p ON c.proveedor_id = p.proveedor_id
             LEFT JOIN users u ON c.usuario_id = u.id
             LEFT JOIN depositos d ON c.deposito_id = d.deposito_id
             WHERE c.compra_id = ? AND c.sucursal_id = ?",
            [$id, $this->sucursalId()]
        )->fetch();

        if (!$compra) {
            $this->error('Compra no encontrada.');
            $this->redirect('/compras');
            return null;
        }

        return $compra;
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Libraries\AuditService;
use App\Libraries\Auth;
use App\Libraries\CompraService;
use App\Libraries\Db;
use App\Libraries\TrasladoService;

/** Depósitos, existencias, ajuste de stock, compras y traslados. */
class ApiInventarioController extends ApiController
{
    // ── Depósitos (/api/v1/depositos) ────────────────────────────────────────

    /** GET ?sucursal_id= */
    public function depositos()
    {
        $sql    = "SELECT d.*, b.nombre AS sucursal_nombre FROM depositos d JOIN branches b ON d.sucursal_id = b.sucursal_id
                   WHERE b.empresa_id = ? AND d.estado = 'activo'";
        $params = [$this->empresaId()];
        if ($sucursalId = (int) ($this->request->getGet('sucursal_id') ?? 0)) {
            $sql .= ' AND d.sucursal_id = ?';
            $params[] = $sucursalId;
        }
        return $this->ok(['depositos' => Db::all($sql . ' ORDER BY b.nombre, d.nombre', $params)]);
    }

    public function deposito(int $id)
    {
        $deposito = $this->buscarDeposito($id);
        if (!$deposito) {
            return $this->noEncontrado('Depósito no encontrado');
        }
        $deposito['stock'] = Db::row("SELECT COUNT(*) AS total_productos, COALESCE(SUM(existencia), 0) AS total_unidades FROM inventario WHERE deposito_id = ?", [$id]);
        return $this->ok($deposito);
    }

    public function crearDeposito()
    {
        $data       = $this->entrada();
        $sucursalId = (int) ($data['sucursal_id'] ?? $this->sucursalId());
        if (trim((string) ($data['nombre'] ?? '')) === '') {
            return $this->fallo('El nombre es obligatorio.', 422);
        }
        if (!Db::value("SELECT 1 FROM branches WHERE sucursal_id = ? AND empresa_id = ? AND activa = 1", [$sucursalId, $this->empresaId()])) {
            return $this->fallo('Sucursal no válida.', 422);
        }

        Db::begin();
        if (!empty($data['es_principal'])) {
            Db::query("UPDATE depositos SET es_principal = 0 WHERE sucursal_id = ?", [$sucursalId]);
        }
        $id = Db::insert('depositos', [
            'sucursal_id'  => $sucursalId,
            'codigo'       => $data['codigo'] ?? null,
            'nombre'       => trim((string) $data['nombre']),
            'descripcion'  => $data['descripcion'] ?? null,
            'es_principal' => !empty($data['es_principal']) ? 1 : 0,
            'estado'       => 'activo',
        ]);
        \App\Libraries\SucursalService::poblarInventario($id);
        Db::commit();

        AuditService::log('inventario.deposito.crear', "Depósito creado por API: {$data['nombre']}", $id, 'deposito');
        return $this->ok(['deposito_id' => $id], 'Depósito creado exitosamente', 201);
    }

    public function actualizarDeposito(int $id)
    {
        $deposito = $this->buscarDeposito($id);
        if (!$deposito) {
            return $this->noEncontrado('Depósito no encontrado');
        }
        $data  = $this->entrada();
        $datos = array_intersect_key($data, array_flip(['codigo', 'nombre', 'descripcion']));
        if (array_key_exists('es_principal', $data)) {
            $datos['es_principal'] = !empty($data['es_principal']) ? 1 : 0;
        }

        Db::begin();
        if (!empty($datos['es_principal'])) {
            Db::query("UPDATE depositos SET es_principal = 0 WHERE sucursal_id = ? AND deposito_id <> ?", [$deposito['sucursal_id'], $id]);
        }
        if ($datos) {
            Db::update('depositos', $datos, ['deposito_id' => $id]);
        }
        Db::commit();
        return $this->ok([], 'Depósito actualizado correctamente');
    }

    /** DELETE — desactiva; se rechaza si aún tiene existencias. */
    public function eliminarDeposito(int $id)
    {
        if (!$this->buscarDeposito($id)) {
            return $this->noEncontrado('Depósito no encontrado');
        }
        if ((int) Db::value("SELECT COUNT(*) FROM inventario WHERE deposito_id = ? AND existencia > 0", [$id]) > 0) {
            return $this->fallo('El depósito tiene productos con existencias. Trasládalos antes de desactivarlo.', 422);
        }
        Db::update('depositos', ['estado' => 'inactivo'], ['deposito_id' => $id]);
        return $this->ok([], 'Depósito desactivado correctamente');
    }

    private function buscarDeposito(int $id): ?array
    {
        return Db::row(
            "SELECT d.*, b.nombre AS sucursal_nombre FROM depositos d JOIN branches b ON d.sucursal_id = b.sucursal_id
             WHERE d.deposito_id = ? AND b.empresa_id = ?",
            [$id, $this->empresaId()]
        );
    }

    // ── Existencias (/api/v1/inventario) ─────────────────────────────────────

    public function stockProducto(int $productoId)
    {
        return $this->ok(Db::all(
            "SELECT i.*, d.nombre AS deposito_nombre FROM inventario i
             JOIN depositos d ON i.deposito_id = d.deposito_id
             JOIN productos p ON p.producto_id = i.producto_id
             WHERE i.producto_id = ? AND p.empresa_id = ? ORDER BY d.nombre",
            [$productoId, $this->empresaId()]
        ));
    }

    public function stockDeposito(int $depositoId)
    {
        if (!$this->buscarDeposito($depositoId)) {
            return $this->noEncontrado('Depósito no encontrado');
        }
        return $this->ok(Db::all(
            "SELECT i.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo
             FROM inventario i JOIN productos p ON i.producto_id = p.producto_id
             WHERE i.deposito_id = ? AND p.estado = 'activo' ORDER BY p.nombre",
            [$depositoId]
        ));
    }

    /**
     * POST {producto_id, deposito_id?, cantidad, motivo?} — fija la existencia en "cantidad"
     * (conteo) y deja el movimiento 'ajuste' en el kardex con su saldo anterior y nuevo.
     */
    public function ajustar()
    {
        $data       = $this->entrada();
        $productoId = (int) ($data['producto_id'] ?? 0);
        $depositoId = $this->depositoId($data['deposito_id'] ?? null);
        $cantidad   = (float) ($data['cantidad'] ?? -1);

        if (!$productoId || !$depositoId || $cantidad < 0) {
            return $this->fallo('producto_id, deposito_id válido y cantidad (>= 0) son requeridos.', 422);
        }
        $producto = Db::row("SELECT nombre, maneja_lotes FROM productos WHERE producto_id = ? AND empresa_id = ?", [$productoId, $this->empresaId()]);
        if (!$producto) {
            return $this->noEncontrado('Producto no encontrado');
        }
        if ($producto['maneja_lotes']) {
            return $this->fallo('El producto maneja lotes: su stock se ajusta desde la gestión de lotes.', 422);
        }

        try {
            Db::begin();
            $inv = Db::row("SELECT inventario_id, existencia FROM inventario WHERE producto_id = ? AND deposito_id = ? FOR UPDATE", [$productoId, $depositoId]);
            $inventarioId = $inv ? (int) $inv['inventario_id'] : Db::insert('inventario', ['producto_id' => $productoId, 'deposito_id' => $depositoId, 'existencia' => 0]);
            $anterior     = $inv ? (float) $inv['existencia'] : 0.0;

            Db::query("UPDATE inventario SET existencia = ? WHERE inventario_id = ?", [$cantidad, $inventarioId]);
            Db::insert('inventario_movimientos', [
                'inventario_id'   => $inventarioId,
                'tipo'            => 'ajuste',
                'cantidad'        => abs($cantidad - $anterior),
                'saldo_anterior'  => $anterior,
                'saldo_nuevo'     => $cantidad,
                'referencia_tipo' => 'api',
                'motivo'          => trim((string) ($data['motivo'] ?? '')) ?: 'Ajuste manual (API)',
                'usuario_id'      => Auth::id(),
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->fallo($this->mensajeError($e, 'Error al ajustar el stock.'), 500);
        }

        AuditService::log('inventario.ajustar', "Ajuste API: {$producto['nombre']} de {$anterior} a {$cantidad}", $productoId, 'producto', ['deposito_id' => $depositoId]);
        return $this->ok(['saldo_anterior' => $anterior, 'saldo_nuevo' => $cantidad], 'Ajuste de stock realizado correctamente');
    }

    // ── Compras (/api/v1/compras) ────────────────────────────────────────────

    public function compras()
    {
        [$pagina, $porPagina, $offset] = $this->paginacion();
        return $this->ok([
            'compras' => Db::all(
                "SELECT c.*, p.nombre AS proveedor_nombre FROM compras c
                 LEFT JOIN proveedores p ON c.proveedor_id = p.proveedor_id
                 WHERE c.empresa_id = ? ORDER BY c.fecha_compra DESC, c.compra_id DESC
                 LIMIT {$porPagina} OFFSET {$offset}",
                [$this->empresaId()]
            ),
            'page'     => $pagina,
            'per_page' => $porPagina,
        ]);
    }

    public function compra(int $id)
    {
        $compra = Db::row(
            "SELECT c.*, p.nombre AS proveedor_nombre, d.nombre AS deposito_nombre FROM compras c
             LEFT JOIN proveedores p ON c.proveedor_id = p.proveedor_id
             LEFT JOIN depositos d ON c.deposito_id = d.deposito_id
             WHERE c.compra_id = ? AND c.empresa_id = ?",
            [$id, $this->empresaId()]
        );
        if (!$compra) {
            return $this->noEncontrado('Compra no encontrada');
        }
        $compra['detalles'] = Db::all(
            "SELECT cd.*, pr.nombre AS producto_nombre, pr.codigo AS producto_codigo
             FROM compras_detalle cd JOIN productos pr ON cd.producto_id = pr.producto_id WHERE cd.compra_id = ?",
            [$id]
        );
        return $this->ok($compra);
    }

    /** POST — mismo formato que CompraService::registrar(); items[].deposito_id es opcional. */
    public function crearCompra()
    {
        $data       = $this->entrada();
        $depositoId = $this->depositoId($data['deposito_id'] ?? null);
        if (!$depositoId) {
            return $this->fallo('Depósito no válido.', 422);
        }
        if (!empty($data['proveedor_id']) && !Db::value("SELECT 1 FROM proveedores WHERE proveedor_id = ? AND empresa_id = ?", [(int) $data['proveedor_id'], $this->empresaId()])) {
            return $this->fallo('Proveedor no válido.', 422);
        }
        foreach ($data['items'] ?? [] as $i => $item) {
            $data['items'][$i]['deposito_id'] = $depositoId; // la API compra a un solo depósito
        }

        try {
            Db::begin();
            $compraId = CompraService::registrar($data, ['empresa_id' => $this->empresaId(), 'sucursal_id' => $this->sucursalId(), 'deposito_id' => $depositoId]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->fallo($this->mensajeError($e, 'Error al registrar la compra.'), 422);
        }

        AuditService::log('compras.crear', "Compra #{$compraId} registrada por API", $compraId, 'compra');
        return $this->ok(['compra_id' => $compraId], 'Compra registrada exitosamente', 201);
    }

    // ── Traslados (/api/v1/traslados) ────────────────────────────────────────

    public function traslados()
    {
        [$pagina, $porPagina, $offset] = $this->paginacion();
        return $this->ok([
            'traslados' => Db::all(
                "SELECT t.*, d1.nombre AS origen_nombre, d2.nombre AS destino_nombre, u.username AS usuario_nombre
                 FROM traslados t
                 LEFT JOIN depositos d1 ON t.deposito_origen_id = d1.deposito_id
                 LEFT JOIN depositos d2 ON t.deposito_destino_id = d2.deposito_id
                 LEFT JOIN users u ON t.usuario_envia_id = u.id
                 WHERE t.empresa_id = ? ORDER BY t.traslado_id DESC
                 LIMIT {$porPagina} OFFSET {$offset}",
                [$this->empresaId()]
            ),
            'page'     => $pagina,
            'per_page' => $porPagina,
        ]);
    }

    public function traslado(int $id)
    {
        $traslado = Db::row(
            "SELECT t.*, d1.nombre AS origen_nombre, d2.nombre AS destino_nombre FROM traslados t
             LEFT JOIN depositos d1 ON t.deposito_origen_id = d1.deposito_id
             LEFT JOIN depositos d2 ON t.deposito_destino_id = d2.deposito_id
             WHERE t.traslado_id = ? AND t.empresa_id = ?",
            [$id, $this->empresaId()]
        );
        if (!$traslado) {
            return $this->noEncontrado('Traslado no encontrado');
        }
        $traslado['detalles'] = Db::all(
            "SELECT td.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo FROM traslados_detalle td
             JOIN productos p ON p.producto_id = td.producto_id WHERE td.traslado_id = ?",
            [$id]
        );
        return $this->ok($traslado);
    }

    /** POST {deposito_origen_id, deposito_destino_id, notas?, items[{producto_id, cantidad}]} */
    public function crearTraslado()
    {
        $data = $this->entrada();
        try {
            Db::begin();
            $id = TrasladoService::enviar(
                (int) ($data['deposito_origen_id'] ?? 0),
                (int) ($data['deposito_destino_id'] ?? 0),
                $data['items'] ?? [],
                (string) ($data['notas'] ?? ''),
                ['empresa_id' => $this->empresaId(), 'sucursal_id' => $this->sucursalId()]
            );
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->fallo($this->mensajeError($e, 'Error al registrar el traslado.'), 422);
        }

        AuditService::log('inventario.traslado', "Traslado #{$id} enviado por API", $id, 'traslado');
        return $this->ok(['traslado_id' => $id, 'estado' => 'en_transito'], 'Traslado enviado', 201);
    }

    public function recibirTraslado(int $id)
    {
        try {
            Db::begin();
            TrasladoService::recibir($id, ['empresa_id' => $this->empresaId(), 'sucursal_id' => $this->sucursalId()]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->fallo($this->mensajeError($e, 'Error al recibir el traslado.'), 422);
        }

        AuditService::log('inventario.traslado', "Traslado #{$id} recibido por API", $id, 'traslado');
        return $this->ok([], 'Traslado recibido');
    }
}

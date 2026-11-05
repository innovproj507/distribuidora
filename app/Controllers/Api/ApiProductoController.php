<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Libraries\AuditService;
use App\Libraries\Db;
use App\Libraries\ProductoService;

/** /api/v1/productos — permisos: productos.ver / crear / editar / eliminar (por ruta). */
class ApiProductoController extends ApiController
{
    private const SELECT = "SELECT p.*, c.nombre AS categoria_nombre,
                    COALESCE(pa.precio, 0) AS precio_a, COALESCE(pb.precio, 0) AS precio_b, COALESCE(pc.precio, 0) AS precio_c
             FROM productos p
             LEFT JOIN categorias_productos c ON p.categoria_id = c.categoria_id
             LEFT JOIN precios_productos pa ON pa.producto_id = p.producto_id AND pa.tipo_precio = 'a'
             LEFT JOIN precios_productos pb ON pb.producto_id = p.producto_id AND pb.tipo_precio = 'b'
             LEFT JOIN precios_productos pc ON pc.producto_id = p.producto_id AND pc.tipo_precio = 'c'";

    /** GET ?page=&per_page=&q=&categoria_id=&estado=activo|inactivo|todos */
    public function index()
    {
        [$pagina, $porPagina, $offset] = $this->paginacion();

        $where  = ['p.empresa_id = ?'];
        $params = [$this->empresaId()];

        $estado = (string) ($this->request->getGet('estado') ?? 'activo');
        if ($estado !== 'todos') {
            $where[]  = 'p.estado = ?';
            $params[] = $estado === 'inactivo' ? 'inactivo' : 'activo';
        }
        if (($q = trim((string) ($this->request->getGet('q') ?? ''))) !== '') {
            $where[] = '(p.nombre LIKE ? OR p.codigo LIKE ? OR p.codigo_barras LIKE ?)';
            array_push($params, "%{$q}%", "%{$q}%", "%{$q}%");
        }
        if ($categoriaId = (int) ($this->request->getGet('categoria_id') ?? 0)) {
            $where[]  = 'p.categoria_id = ?';
            $params[] = $categoriaId;
        }
        $whereSql = implode(' AND ', $where);

        return $this->ok([
            'productos' => Db::all(self::SELECT . " WHERE {$whereSql} ORDER BY p.nombre LIMIT {$porPagina} OFFSET {$offset}", $params),
            'total'     => (int) Db::value("SELECT COUNT(*) FROM productos p WHERE {$whereSql}", $params),
            'page'      => $pagina,
            'per_page'  => $porPagina,
        ]);
    }

    /** GET ?q= — búsqueda rápida con stock de la sucursal y unidades. */
    public function buscar()
    {
        $q = trim((string) ($this->request->getGet('q') ?? ''));
        if ($q === '') {
            return $this->ok(['productos' => []]);
        }

        $sucursalId = $this->sucursalId();
        $productos  = Db::all(
            "SELECT p.producto_id, p.codigo, p.nombre, p.codigo_barras, p.itbms, p.maneja_lotes, p.imagen_principal, p.marca,
                    COALESCE((SELECT SUM(i.existencia) FROM inventario i JOIN depositos d ON i.deposito_id = d.deposito_id
                              WHERE i.producto_id = p.producto_id AND d.sucursal_id = ?), 0) AS stock,
                    COALESCE(pa.precio, 0) AS precio_a, COALESCE(pb.precio, 0) AS precio_b, COALESCE(pc.precio, 0) AS precio_c
             FROM productos p
             LEFT JOIN precios_productos pa ON pa.producto_id = p.producto_id AND pa.tipo_precio = 'a'
             LEFT JOIN precios_productos pb ON pb.producto_id = p.producto_id AND pb.tipo_precio = 'b'
             LEFT JOIN precios_productos pc ON pc.producto_id = p.producto_id AND pc.tipo_precio = 'c'
             WHERE p.estado = 'activo' AND p.empresa_id = ?
               AND (p.nombre LIKE ? OR p.codigo LIKE ? OR p.codigo_barras LIKE ?)
             ORDER BY (p.codigo = ? OR p.codigo_barras = ?) DESC, p.nombre
             LIMIT 30",
            [$sucursalId, $this->empresaId(), "%{$q}%", "%{$q}%", "%{$q}%", $q, $q]
        );

        foreach ($productos as &$p) {
            $p['unidades'] = Db::all("SELECT * FROM productos_unidades WHERE producto_id = ?", [$p['producto_id']]);
        }
        unset($p);

        return $this->ok(['productos' => $productos]);
    }

    public function detalle(int $id)
    {
        $producto = Db::row(self::SELECT . ' WHERE p.producto_id = ? AND p.empresa_id = ?', [$id, $this->empresaId()]);
        if (!$producto) {
            return $this->noEncontrado('Producto no encontrado');
        }

        $producto['precios']  = array_column(Db::all("SELECT tipo_precio, precio FROM precios_productos WHERE producto_id = ?", [$id]), 'precio', 'tipo_precio');
        $producto['stock']    = Db::all(
            "SELECT i.deposito_id, d.nombre AS deposito_nombre, i.existencia, i.minimo, i.costo_promedio
             FROM inventario i JOIN depositos d ON d.deposito_id = i.deposito_id WHERE i.producto_id = ?",
            [$id]
        );
        $producto['unidades'] = Db::all("SELECT * FROM productos_unidades WHERE producto_id = ?", [$id]);

        return $this->ok($producto);
    }

    /**
     * POST {codigo, nombre, categoria_id?, costo?, itbms?, stock_minimo?, descripcion?, codigo_barras?, marca?,
     *       proveedor_id?, precio_a?, precio_b?, precio_c?, stock_inicial?, deposito_id?}
     */
    public function crear()
    {
        $data = $this->entrada();
        if (trim((string) ($data['nombre'] ?? '')) === '' || trim((string) ($data['codigo'] ?? '')) === '') {
            return $this->fallo('Nombre y código son obligatorios.', 422);
        }

        $depositoId = $this->depositoId($data['deposito_id'] ?? null);
        if (!$depositoId) {
            return $this->fallo('Depósito no válido.', 422);
        }

        try {
            Db::begin();
            $productoId = ProductoService::crear(
                $this->empresaId(),
                $this->datos($data) + ['estado' => 'activo'],
                $this->precios($data),
                [[$depositoId, max(0, (float) ($data['stock_inicial'] ?? 0))]]
            );
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->fallo($this->mensajeError($e, 'Error al crear el producto.'), 422);
        }

        AuditService::log('productos.crear', "Producto creado por API: {$data['nombre']} ({$data['codigo']})", $productoId, 'producto');
        return $this->ok(['producto_id' => $productoId], 'Producto creado correctamente', 201);
    }

    /** PUT — actualización parcial: solo cambian los campos enviados. */
    public function actualizar(int $id)
    {
        $actual = ProductoService::buscar($id, $this->empresaId());
        if (!$actual) {
            return $this->noEncontrado('Producto no encontrado');
        }

        $data  = $this->entrada();
        $datos = array_intersect_key($this->datos($data), $data) + ['codigo' => $actual['codigo']];
        if (isset($data['estado'])) {
            $datos['estado'] = $data['estado'] === 'inactivo' ? 'inactivo' : 'activo';
        }

        try {
            Db::begin();
            ProductoService::actualizar($id, $this->empresaId(), $datos, $this->precios($data));
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->fallo($this->mensajeError($e, 'Error al actualizar el producto.'), 422);
        }

        AuditService::log('productos.editar', "Producto actualizado por API: #{$id}", $id, 'producto');
        return $this->ok([], 'Producto actualizado correctamente');
    }

    /** DELETE — desactiva (no borra: conserva historial de ventas y kardex). */
    public function eliminar(int $id)
    {
        if (!ProductoService::buscar($id, $this->empresaId())) {
            return $this->noEncontrado('Producto no encontrado');
        }
        Db::update('productos', ['estado' => 'inactivo'], ['producto_id' => $id]);
        AuditService::log('productos.eliminar', "Producto desactivado por API: #{$id}", $id, 'producto');
        return $this->ok([], 'Producto desactivado correctamente');
    }

    private function datos(array $data): array
    {
        $datos = [];
        foreach (['codigo', 'nombre', 'descripcion', 'codigo_barras', 'marca', 'supplier_part_no'] as $campo) {
            if (array_key_exists($campo, $data)) $datos[$campo] = trim((string) $data[$campo]) ?: ($campo === 'codigo' || $campo === 'nombre' ? '' : null);
        }
        foreach (['costo', 'itbms', 'stock_minimo'] as $campo) {
            if (array_key_exists($campo, $data)) $datos[$campo] = (float) $data[$campo];
        }
        foreach (['categoria_id', 'proveedor_id'] as $campo) {
            if (array_key_exists($campo, $data)) $datos[$campo] = (int) $data[$campo] ?: null;
        }
        return $datos;
    }

    /** @return array<string, float> solo los precios enviados */
    private function precios(array $data): array
    {
        $precios = [];
        foreach (['a', 'b', 'c', 'promocional'] as $tipo) {
            if (isset($data["precio_{$tipo}"]) && $data["precio_{$tipo}"] !== '') {
                $precios[$tipo] = (float) $data["precio_{$tipo}"];
            }
        }
        return $precios;
    }
}

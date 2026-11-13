<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\AuditService;
use App\Libraries\Db;
use App\Libraries\Fila;
use App\Libraries\InventarioService;
use App\Libraries\PrecioTienda;
use App\Libraries\ProductoService;
use App\Libraries\SimpleXlsxWriter;
use App\Libraries\SucursalService;

class ProductoController extends BaseController
{
    // ── Lectura ──────────────────────────────────────────────────────────────

    public function index(): string
    {
        $empresaId  = (int) $this->empresaId();
        $depositoId = $this->depositoActivo();
        $page       = max(1, (int) ($this->request->getGet('page') ?? 1));
        $porPagina  = (int) env('PAGINATION_PER_PAGE', 25);
        $buscar     = (string) ($this->request->getGet('buscar') ?? '');
        $cat        = (string) ($this->request->getGet('categoria') ?? '');
        $categoriaId = $cat !== '' && $cat !== 'todos' ? (int) $cat : null;
        $estado     = (string) ($this->request->getGet('estado') ?? '') ?: null;

        // Solo productos que tienen registro de inventario en el depósito activo
        $where  = 'p.empresa_id = ? AND EXISTS (SELECT 1 FROM inventario i2 WHERE i2.producto_id = p.producto_id AND i2.deposito_id = ?)';
        $params = [$empresaId, $depositoId];
        if ($buscar !== '') {
            $where .= ' AND (p.nombre LIKE ? OR p.codigo LIKE ? OR p.codigo_barras LIKE ?)';
            array_push($params, "%{$buscar}%", "%{$buscar}%", "%{$buscar}%");
        }
        if ($categoriaId !== null) {
            $where .= ' AND p.categoria_id = ?';
            $params[] = $categoriaId;
        }
        if ($estado !== null) {
            $where .= ' AND p.estado = ?';
            $params[] = $estado;
        }

        $total  = (int) Db::value("SELECT COUNT(*) FROM productos p WHERE {$where}", $params);
        $s      = (int) $this->sucursalId(); // precios de esta tienda
        $offset = ($page - 1) * $porPagina;

        $productos = Db::all(
            "SELECT p.*, COALESCE(NULLIF(i.costo_promedio, 0), p.costo, 0) AS costo, c.nombre AS categoria_nombre, pr.nombre AS proveedor_nombre,
                    COALESCE(i.existencia, 0) AS stock_total, COALESCE(i.costo_promedio, 0) AS costo_promedio, i.fecha_vencimiento,
                    COALESCE(pa.precio, 0) AS precio_a, COALESCE(pb.precio, 0) AS precio_b, COALESCE(pc.precio, 0) AS precio_c
             FROM productos p
             LEFT JOIN categorias_productos c ON p.categoria_id = c.categoria_id
             LEFT JOIN proveedores pr         ON p.proveedor_id = pr.proveedor_id
             LEFT JOIN inventario i           ON i.producto_id = p.producto_id AND i.deposito_id = ?
             LEFT JOIN precios_productos pa   ON pa.producto_id = p.producto_id AND pa.tipo_precio = 'a' AND pa.sucursal_id = {$s}
             LEFT JOIN precios_productos pb   ON pb.producto_id = p.producto_id AND pb.tipo_precio = 'b' AND pb.sucursal_id = {$s}
             LEFT JOIN precios_productos pc   ON pc.producto_id = p.producto_id AND pc.tipo_precio = 'c' AND pc.sucursal_id = {$s}
             WHERE {$where}
             ORDER BY p.fecha_creacion DESC
             LIMIT {$porPagina} OFFSET {$offset}",
            [$depositoId, ...$params]
        );

        $statsParams = [$depositoId, $empresaId];

        return $this->render('inventario.lista', [
            'page_title'         => 'Inventario',
            'page_subtitle'      => 'Gestión de productos',
            'productos'          => Fila::lista($this->conPresentaciones($productos)),
            'pagination'         => paginacion($total, $page, $porPagina),
            'totalProductos'     => (int) Db::value("SELECT COUNT(DISTINCT p.producto_id) FROM productos p JOIN inventario i ON i.producto_id = p.producto_id AND i.deposito_id = ? WHERE p.empresa_id = ?", $statsParams),
            'totalCategorias'    => (int) Db::value("SELECT COUNT(*) FROM categorias_productos WHERE empresa_id = ?", [$empresaId]),
            'productosStockBajo' => (int) Db::value("SELECT COUNT(*) FROM productos p JOIN inventario i ON i.producto_id = p.producto_id AND i.deposito_id = ? WHERE p.empresa_id = ? AND i.existencia > 0 AND i.existencia <= p.stock_minimo", $statsParams),
            'productosSinStock'  => (int) Db::value("SELECT COUNT(*) FROM productos p JOIN inventario i ON i.producto_id = p.producto_id AND i.deposito_id = ? WHERE p.empresa_id = ? AND i.existencia <= 0", $statsParams),
            'categorias'         => Db::all("SELECT categoria_id, nombre FROM categorias_productos WHERE empresa_id = ? ORDER BY nombre", [$empresaId]),
            'buscar'             => $buscar,
            'categoria_filtro'   => $categoriaId,
            'estado_filtro'      => $estado,
        ]);
    }

    public function detalle(int $productoId)
    {
        $producto = ProductoService::buscar($productoId, (int) $this->empresaId());
        if (!$producto) {
            $this->error('Producto no encontrado o no autorizado.');
            return redirect()->to('/inventario');
        }

        // Precio, presentaciones y costo son los de la tienda activa
        $s = (int) $this->sucursalId();
        $producto['costo'] = PrecioTienda::costo($productoId, $s);

        return $this->render('inventario.detalle', [
            'page_title'    => $producto['nombre'],
            'page_subtitle' => 'Detalle del producto',
            'producto'      => new Fila($producto),
            'precios'       => Fila::lista(Db::all("SELECT * FROM precios_productos WHERE producto_id = ? AND sucursal_id = ?", [$productoId, $s])),
            'inventarios'   => Db::all(
                "SELECT i.*, d.nombre AS deposito_nombre FROM inventario i
                 LEFT JOIN depositos d ON i.deposito_id = d.deposito_id WHERE i.producto_id = ?",
                [$productoId]
            ),
            'unidades'  => PrecioTienda::unidades($productoId, $s, 'u.unidad_id'),
            'categoria' => $producto['categoria_id'] ? Fila::o(Db::row("SELECT * FROM categorias_productos WHERE categoria_id = ?", [$producto['categoria_id']])) : null,
            'proveedor' => $producto['proveedor_id'] ? Fila::o(Db::row("SELECT * FROM proveedores WHERE proveedor_id = ?", [$producto['proveedor_id']])) : null,
        ]);
    }

    public function kardex(): string
    {
        $sucursalId = $this->sucursalId();
        $productoId = $this->request->getGet('producto_id');
        $depositoId = $this->request->getGet('deposito_id');
        $tipo       = $this->request->getGet('tipo');

        $sql = "SELECT m.*, p.nombre AS producto_nombre, d.nombre AS deposito_nombre, u.username AS usuario_nombre
                FROM inventario_movimientos m
                JOIN inventario i ON m.inventario_id = i.inventario_id
                JOIN productos p  ON i.producto_id   = p.producto_id
                JOIN depositos d  ON i.deposito_id   = d.deposito_id
                LEFT JOIN users u ON m.usuario_id    = u.id
                WHERE d.sucursal_id = ?";
        $params = [$sucursalId];

        if ($productoId) { $sql .= ' AND p.producto_id = ?'; $params[] = (int) $productoId; }
        if ($depositoId) { $sql .= ' AND d.deposito_id = ?'; $params[] = (int) $depositoId; }
        if ($tipo)       { $sql .= ' AND m.tipo = ?';        $params[] = $tipo; }

        return $this->render('inventario.kardex', [
            'page_title'    => 'Auditoría de Inventario (Kardex)',
            'page_subtitle' => 'Trazabilidad total de movimientos',
            'movimientos'   => Db::all($sql . ' ORDER BY m.fecha_registro DESC LIMIT 100', $params),
            'depositos'     => Db::all("SELECT deposito_id, nombre FROM depositos WHERE sucursal_id = ?", [$sucursalId]),
            'productos'     => Fila::lista(Db::all(
                "SELECT p.producto_id, p.nombre FROM productos p
                 JOIN inventario i ON i.producto_id = p.producto_id
                 JOIN depositos d  ON d.deposito_id = i.deposito_id AND d.sucursal_id = ?
                 GROUP BY p.producto_id, p.nombre ORDER BY p.nombre",
                [$sucursalId]
            )),
            // La vista lee estas claves (antes llegaban como productoId/depositoId y el filtro no se marcaba)
            'filtros' => ['producto_id' => $productoId, 'deposito_id' => $depositoId, 'tipo' => $tipo],
        ]);
    }

    // ── Alta / edición ───────────────────────────────────────────────────────

    public function crear(): string
    {
        return $this->render('inventario.crear', $this->datosFormulario() + [
            'page_title'    => 'Nuevo Producto',
            'page_subtitle' => 'Agregar producto al inventario',
        ]);
    }

    public function guardar()
    {
        $datos = $this->datosProducto();
        // Sin costos.ver (encargado) el producto nace sin costo: lo pone el dueño/gerente después
        $datos['costo'] = ver_costos() ? (float) ($this->request->getPost('costo_inicial') ?? 0) : 0.0;
        $datos['imagen_principal'] = $this->subirImagen('imagen_principal', 'productos');
        // Sin código: se genera según la categoría (ALI158, ESC073...)
        if ($datos['codigo'] === '') {
            $datos['codigo'] = ProductoService::siguienteCodigo((int) $this->empresaId(), $datos['categoria_id']);
        }

        try {
            $vencimientos = $this->vencimientosFormulario();
            Db::begin();
            $productoId = ProductoService::crear((int) $this->empresaId(), $datos, $this->preciosFormulario(['a', 'b', 'c']), $this->stocksFormulario());
            ProductoService::guardarUnidades($productoId, $this->unidadesFormulario());
            $this->guardarVencimientos($productoId, $vencimientos);
            Db::commit();
        } catch (\DomainException $e) {
            Db::rollback();
            $this->error($e->getMessage());
            return redirect()->to('/inventario/nuevo')->withInput();
        }

        AuditService::log('productos.crear', "Producto creado: {$datos['nombre']} (SKU: {$datos['codigo']})", $productoId, 'producto', [], $this->empresaId());
        $this->success('Producto creado exitosamente.');
        return redirect()->to('/inventario');
    }

    /** GET ?categoria_id= → {codigo, prefijo}: siguiente código libre para esa categoría. */
    public function siguienteCodigo()
    {
        $categoriaId = (int) ($this->request->getGet('categoria_id') ?? 0) ?: null;
        $empresaId   = (int) $this->empresaId();
        return $this->json([
            'codigo'  => ProductoService::siguienteCodigo($empresaId, $categoriaId),
            'prefijo' => ProductoService::prefijoCategoria($empresaId, $categoriaId),
        ]);
    }

    public function editar(int $productoId)
    {
        $producto = ProductoService::buscar($productoId, (int) $this->empresaId());
        if (!$producto) {
            $this->error('Producto no encontrado o no autorizado.');
            return redirect()->to('/inventario');
        }

        // Se edita el precio, las presentaciones y el costo de la tienda activa (la otra no cambia)
        $s = (int) $this->sucursalId();
        $producto['costo'] = PrecioTienda::costo($productoId, $s);

        return $this->render('inventario.editar', $this->datosFormulario() + [
            'page_title'    => 'Editar Producto',
            'page_subtitle' => $producto['nombre'],
            'producto'      => new Fila($producto),
            'precios'       => Fila::lista(Db::all("SELECT * FROM precios_productos WHERE producto_id = ? AND sucursal_id = ?", [$productoId, $s])),
            'unidades'      => PrecioTienda::unidades($productoId, $s, 'u.unidad_id'),
            'inventariosActuales' => Db::all(
                "SELECT i.*, d.nombre AS deposito_nombre FROM inventario i
                 JOIN depositos d ON i.deposito_id = d.deposito_id WHERE i.producto_id = ?",
                [$productoId]
            ),
        ]);
    }

    public function actualizar(int $productoId)
    {
        $datos = $this->datosProducto();
        if (ver_costos()) {
            $datos['costo'] = (float) ($this->request->getPost('costo') ?? 0);
        } // sin costos.ver no se envía 'costo' y ProductoService::actualizar conserva el actual
        $datos['imagen_principal'] = $this->subirImagen('imagen_principal', 'productos');

        try {
            $vencimientos = $this->vencimientosFormulario();
            Db::begin();
            ProductoService::actualizar($productoId, (int) $this->empresaId(), $datos, $this->preciosFormulario(['a', 'b', 'c', 'promocional']));
            ProductoService::guardarUnidades($productoId, $this->unidadesFormulario());
            $this->guardarVencimientos($productoId, $vencimientos);
            Db::commit();
        } catch (\DomainException $e) {
            Db::rollback();
            $this->error($e->getMessage());
            return redirect()->to("/inventario/{$productoId}/editar");
        }

        AuditService::log('productos.editar', "Producto actualizado: {$datos['nombre']} (SKU: {$datos['codigo']})", $productoId, 'producto', [], $this->empresaId());
        $this->success('Producto actualizado exitosamente.');
        return redirect()->to("/inventario/{$productoId}");
    }

    public function eliminar(int $productoId)
    {
        // Con historial no se borra: se desactiva
        foreach (['ventas_detalle' => 'ventas', 'compras_detalle' => 'compras', 'cotizaciones_detalle' => 'cotizaciones', 'traslados_detalle' => 'traslados'] as $tabla => $etiqueta) {
            $n = (int) Db::value("SELECT COUNT(*) FROM {$tabla} WHERE producto_id = ?", [$productoId]);
            if ($n > 0) {
                $this->error("No se puede eliminar: el producto tiene {$n} registro(s) en {$etiqueta}. Desactívalo en su lugar para que deje de aparecer en búsquedas.");
                return redirect()->to('/inventario');
            }
        }

        try {
            ProductoService::eliminar($productoId, (int) $this->empresaId());
            AuditService::log('productos.eliminar', "Producto #{$productoId} eliminado", $productoId, 'producto', [], $this->empresaId());
            $this->success('Producto eliminado exitosamente.');
        } catch (\DomainException $e) {
            $this->error($e->getMessage());
        } catch (\Throwable) {
            $this->error('No se puede eliminar el producto porque tiene datos asociados. Desactívalo en su lugar.');
        }

        return redirect()->to('/inventario');
    }

    // ── Stock y precios ──────────────────────────────────────────────────────

    public function agregarStock(int $productoId)
    {
        if (!ProductoService::buscar($productoId, (int) $this->empresaId())) {
            $this->error('Producto no encontrado o no autorizado.');
            return redirect()->to('/inventario');
        }

        $cantidad   = (float) ($this->request->getPost('cantidad') ?? 0);
        $depositoId = (int) $this->request->getPost('deposito_id');
        $costoPost  = ver_costos() ? (float) ($this->request->getPost('costo') ?? 0) : 0.0;

        // Cantidad en una presentación (bandeja, caja…): se pasa a unidades base; el costo escrito es por esa presentación
        $unidadId = (int) ($this->request->getPost('unidad_id') ?? 0);
        $etiqueta = 'unidades';
        if ($unidadId) {
            $u = Db::row("SELECT nombre, factor_conversion FROM productos_unidades WHERE unidad_id = ? AND producto_id = ?", [$unidadId, $productoId]);
            if (!$u || (float) $u['factor_conversion'] <= 0) {
                $this->error('La presentación elegida no es de este producto.');
                return redirect()->to("/inventario/{$productoId}");
            }
            $factor    = (float) $u['factor_conversion'];
            $etiqueta  = "unidades ({$cantidad} × {$u['nombre']})";
            $cantidad  = round($cantidad * $factor, 2);
            $costoPost = $costoPost > 0 ? $costoPost / $factor : 0.0;
        }

        if (!SucursalService::puedeUsarDeposito($depositoId, (int) $this->sucursalId())) {
            $this->error('Ese depósito no pertenece a tu sucursal o no tienes acceso.');
            return redirect()->to("/inventario/{$productoId}");
        }

        try {
            Db::begin();
            // Sin costos.ver se usa el costo del producto (agregarStock lo toma si viene 0)
            ProductoService::agregarStock($productoId, $depositoId, $cantidad, $costoPost);
            Db::commit();
            $this->success("Se agregaron {$cantidad} {$etiqueta} al inventario.");
        } catch (\InvalidArgumentException $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }

        return redirect()->to("/inventario/{$productoId}");
    }

    /** Motivos fijos de salida de stock sin venta (para poder sumar el consumo interno por motivo). */
    public const MOTIVOS_SALIDA = ['Consumo ayudantes', 'Consumo dueño', 'Uso local', 'Merma / dañado', 'Vencido', 'Otro'];

    /**
     * Salida de stock que no es venta: consumo de ayudantes o del dueño, uso en la tienda, merma.
     * No toca la caja ni las ventas; queda en el kardex como 'salida' con el motivo.
     */
    public function sacarStock(int $productoId)
    {
        $producto = Db::row("SELECT producto_id, nombre, maneja_lotes FROM productos WHERE producto_id = ? AND empresa_id = ?", [$productoId, $this->empresaId()]);
        if (!$producto) {
            $this->error('Producto no encontrado o no autorizado.');
            return redirect()->to('/inventario');
        }
        if ($producto['maneja_lotes']) {
            $this->error('Este producto maneja lotes; su stock se mueve desde la gestión de lotes.');
            return redirect()->to("/inventario/{$productoId}");
        }

        $cantidad   = (float) ($this->request->getPost('cantidad') ?? 0);
        $depositoId = (int) $this->request->getPost('deposito_id');
        $motivo     = (string) $this->request->getPost('motivo');
        $detalle    = trim((string) ($this->request->getPost('detalle') ?? ''));
        if (!in_array($motivo, self::MOTIVOS_SALIDA, true)) {
            $this->error('Elige el motivo de la salida.');
            return redirect()->to("/inventario/{$productoId}");
        }
        if ($motivo === 'Otro' && $detalle === '') {
            $this->error('Escribe el detalle de la salida.');
            return redirect()->to("/inventario/{$productoId}");
        }
        $texto = mb_substr($detalle !== '' ? "{$motivo}: {$detalle}" : $motivo, 0, 250);

        $unidadId = (int) ($this->request->getPost('unidad_id') ?? 0);
        $etiqueta = 'unidades';
        if ($unidadId) {
            $u = Db::row("SELECT nombre, factor_conversion FROM productos_unidades WHERE unidad_id = ? AND producto_id = ?", [$unidadId, $productoId]);
            if (!$u || (float) $u['factor_conversion'] <= 0) {
                $this->error('La presentación elegida no es de este producto.');
                return redirect()->to("/inventario/{$productoId}");
            }
            $etiqueta = "unidades ({$cantidad} × {$u['nombre']})";
            $cantidad = round($cantidad * (float) $u['factor_conversion'], 2);
        }
        if ($cantidad <= 0) {
            $this->error('La cantidad debe ser mayor que cero.');
            return redirect()->to("/inventario/{$productoId}");
        }
        if (!SucursalService::puedeUsarDeposito($depositoId, (int) $this->sucursalId())) {
            $this->error('Ese depósito no pertenece a tu sucursal o no tienes acceso.');
            return redirect()->to("/inventario/{$productoId}");
        }

        try {
            Db::begin();
            $inv = Db::row("SELECT inventario_id, existencia FROM inventario WHERE producto_id = ? AND deposito_id = ? FOR UPDATE", [$productoId, $depositoId]);
            if (!$inv || $cantidad > (float) $inv['existencia'] + 0.0001) {
                Db::rollback();
                $this->error('No hay stock suficiente. Existencia actual: ' . (float) ($inv['existencia'] ?? 0));
                return redirect()->to("/inventario/{$productoId}");
            }
            $existencia = InventarioService::registrar((int) $inv['inventario_id'], 'salida', $cantidad, null, 'salida_interna', $texto);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            log_message('error', 'Salida de stock: ' . $e->getMessage());
            $this->error('No se pudo registrar la salida.');
            return redirect()->to("/inventario/{$productoId}");
        }

        AuditService::log('inventario.ajustar', "Salida de {$cantidad} uds de {$producto['nombre']} ({$texto})", $productoId, 'producto',
            ['tipo' => 'salida', 'cantidad' => $cantidad, 'deposito_id' => $depositoId, 'existencia' => $existencia, 'motivo' => $texto], $this->empresaId());
        $this->success("Se sacaron {$cantidad} {$etiqueta} del inventario ({$texto}).");
        return redirect()->to("/inventario/{$productoId}");
    }

    /** Agrega 'presentaciones' (bandeja, caja…) a cada fila, para mostrar el stock desglosado. */
    private function conPresentaciones(array $productos): array
    {
        if (!$productos) return $productos;
        $ids  = array_map(fn ($p) => (int) $p['producto_id'], $productos);
        $pres = [];
        foreach (Db::all(
            "SELECT producto_id, nombre, factor_conversion FROM productos_unidades WHERE producto_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")",
            $ids
        ) as $u) {
            $pres[(int) $u['producto_id']][] = $u;
        }
        foreach ($productos as &$p) {
            $p['presentaciones'] = $pres[(int) $p['producto_id']] ?? [];
        }
        return $productos;
    }

    /** GET muestra el formulario; POST guarda los precios A/B/C/promocional. */
    public function precios(int $productoId)
    {
        $producto = ProductoService::buscar($productoId, (int) $this->empresaId());
        if (!$producto) {
            $this->error('No autorizado.');
            return redirect()->to('/inventario');
        }

        if ($this->request->is('post')) {
            foreach ($this->preciosFormulario(['a', 'b', 'c', 'promocional']) as $tipo => $monto) {
                ProductoService::reemplazarPrecio($productoId, $tipo, $monto);
            }
            AuditService::log('productos.precios', "Precios actualizados: {$producto['nombre']}", $productoId, 'producto', [], $this->empresaId());
            $this->success('Precios actualizados.');
            return redirect()->to("/inventario/{$productoId}/precios");
        }

        return $this->render('inventario.precios', [
            'page_title'    => 'Gestionar Precios',
            'page_subtitle' => $producto['nombre'],
            'product'       => $producto,
            'precios'       => Db::all("SELECT * FROM precios_productos WHERE producto_id = ? AND sucursal_id = ?", [$productoId, (int) $this->sucursalId()]),
        ]);
    }

    public function exportarExcel()
    {
        if ($r = $this->exigir('productos.ver', '/inventario')) return $r;

        // Los tipos de precio se guardan en minúscula ('a', 'b'); antes se buscaba 'A'/'B' y el Excel salía en 0.
        // Precio, costo y stock de la tienda activa (cada tienda tiene los suyos)
        $s = (int) $this->sucursalId();
        $productos = Db::all(
            "SELECT p.codigo, p.nombre, cp.nombre AS categoria, p.marca,
                    COALESCE(pa.precio, 0) AS precio_a, COALESCE(pb.precio, 0) AS precio_b,
                    " . PrecioTienda::sqlCosto($s) . " AS costo, COALESCE(SUM(i.existencia), 0) AS stock, p.estado
             FROM productos p
             LEFT JOIN categorias_productos cp ON p.categoria_id = cp.categoria_id
             LEFT JOIN precios_productos pa ON pa.producto_id = p.producto_id AND pa.tipo_precio = 'a' AND pa.sucursal_id = {$s}
             LEFT JOIN precios_productos pb ON pb.producto_id = p.producto_id AND pb.tipo_precio = 'b' AND pb.sucursal_id = {$s}
             LEFT JOIN inventario i ON i.producto_id = p.producto_id AND i.deposito_id IN (SELECT deposito_id FROM depositos WHERE sucursal_id = {$s})
             WHERE p.empresa_id = ?
             GROUP BY p.producto_id, p.codigo, p.nombre, cp.nombre, p.marca, pa.precio, pb.precio, p.costo, p.estado
             ORDER BY cp.nombre, p.nombre",
            [$this->empresaId()]
        );

        $conCosto = ver_costos();
        $filas = array_map(fn ($p) => array_merge(
            [$p['codigo'] ?? '', $p['nombre'] ?? '', $p['categoria'] ?? '', $p['marca'] ?? '', number_format((float) $p['precio_a'], 2, '.', '')],
            $conCosto ? [number_format((float) $p['costo'], 2, '.', '')] : [],
            [(int) $p['stock'], $p['estado'] ?? '']
        ), $productos);

        return SimpleXlsxWriter::descarga(
            'inventario_' . date('Y-m-d') . '.xlsx',
            array_merge(['Código', 'Nombre', 'Categoría', 'Marca', 'Precio'], $conCosto ? ['Costo'] : [], ['Stock', 'Estado']),
            $filas
        );
    }

    // ── Creación rápida desde el formulario (JSON) ───────────────────────────

    public function crearCategoriaRapida()
    {
        $nombre = trim((string) ($this->jsonInput()['nombre'] ?? ''));
        if ($nombre === '') {
            return $this->json(['error' => 'El nombre es requerido'], 422);
        }

        $id = Db::insert('categorias_productos', ['empresa_id' => $this->empresaId(), 'nombre' => $nombre, 'nivel' => 1, 'padre_id' => null]);
        AuditService::log('inventario.categoria.crear_rapido', "Categoría creada: {$nombre}", $id, 'categoria', [], $this->empresaId());

        return $this->json(['success' => true, 'categoria' => ['id' => $id, 'nombre' => $nombre]]);
    }

    public function crearMarcaRapida()
    {
        $nombre = trim((string) ($this->jsonInput()['nombre'] ?? ''));
        if ($nombre === '') {
            return $this->json(['error' => 'El nombre es requerido'], 422);
        }

        // Si ya existe, simplemente se devuelve
        if (!Db::value("SELECT 1 FROM marcas WHERE empresa_id = ? AND nombre = ?", [$this->empresaId(), $nombre])) {
            Db::insert('marcas', ['empresa_id' => $this->empresaId(), 'nombre' => $nombre]);
            AuditService::log('inventario.marca.crear_rapido', "Marca creada: {$nombre}", null, 'marca', [], $this->empresaId());
        }

        return $this->json(['success' => true, 'marca' => ['nombre' => $nombre]]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function depositoActivo(): int
    {
        return $this->depositoId() ?: (int) (Db::value(
            "SELECT deposito_id FROM depositos WHERE sucursal_id = ? AND estado = 'activo' ORDER BY es_principal DESC LIMIT 1",
            [$this->sucursalId()]
        ) ?? 0);
    }

    private function datosFormulario(): array
    {
        $empresaId = $this->empresaId();
        return [
            'categorias'  => Db::all("SELECT categoria_id, nombre FROM categorias_productos WHERE empresa_id = ? ORDER BY nombre", [$empresaId]),
            'proveedores' => Db::all("SELECT proveedor_id, nombre FROM proveedores WHERE empresa_id = ? AND estado = 'activo' ORDER BY nombre", [$empresaId]),
            'depositos'   => Db::all("SELECT deposito_id, nombre FROM depositos WHERE sucursal_id = ? AND estado = 'activo' ORDER BY es_principal DESC, nombre", [$this->sucursalId()]),
            'marcas'      => Db::all("SELECT DISTINCT nombre FROM marcas WHERE empresa_id = ? ORDER BY nombre", [$empresaId]),
        ];
    }

    /** Campos comunes del formulario de producto (crear y editar). */
    private function datosProducto(): array
    {
        $post = fn (string $k, $def = null) => $this->request->getPost($k) ?? $def;

        $estado = $post('estado', 'activo') === 'inactivo' ? 'inactivo' : 'activo';
        return [
            'codigo'           => trim((string) $post('codigo', '')),
            'nombre'           => trim((string) $post('nombre', '')),
            'categoria_id'     => (int) $post('categoria_id', 0) ?: null,
            'itbms'            => 0.0, // el negocio no cobra ITBMS: ningún producto lo lleva
            'stock_minimo'     => (float) $post('stock_minimo', 0),
            'maneja_lotes'     => $post('maneja_lotes', '0') === '1' ? 1 : 0,
            'controla_vencimiento' => $post('controla_vencimiento', '0') === '1' || $post('maneja_lotes', '0') === '1' ? 1 : 0,
            'estado'           => $estado,
            'descripcion'      => $post('descripcion') ?: null,
            'codigo_barras'    => $post('codigo_barras') ?: null,
            'marca'            => $post('marca') ?: null,
            'proveedor_id'     => $post('proveedor_id') ? (int) $post('proveedor_id') : null,
            'supplier_part_no' => $post('supplier_part_no') ?: null,
            'unidad_medida'    => ProductoService::unidadMedida($post('unidad_medida', 'und')),
        ];
    }

    /** @return array<string, float> tipo => monto (solo los campos enviados) */
    private function preciosFormulario(array $tipos): array
    {
        $precios = [];
        foreach ($tipos as $tipo) {
            $valor = $this->request->getPost("precio_{$tipo}");
            if ($valor !== null && $valor !== '') {
                $precios[$tipo] = (float) $valor;
            }
        }
        return $precios;
    }

    /** @return list<array{0: int, 1: float}> [deposito_id, cantidad] */
    private function stocksFormulario(): array
    {
        $depositos = (array) ($this->request->getPost('depositos_ids') ?? []);
        $cantidades = (array) ($this->request->getPost('stocks') ?? []);
        $resultado = [];

        foreach (array_values($depositos) as $i => $depositoId) {
            $cantidad = (float) ($cantidades[$i] ?? 0);
            if ($cantidad > 0 || $i === 0) {
                $resultado[] = [(int) $depositoId, $cantidad];
            }
        }
        return $resultado;
    }

    /**
     * Fechas de vencimiento del formulario: vence[deposito_id] = 'Y-m-d' | ''. Solo depósitos de la
     * sucursal activa que el usuario puede usar. Lanza DomainException si una fecha no es válida.
     */
    private function vencimientosFormulario(): array
    {
        $resultado = [];
        foreach ((array) ($this->request->getPost('vence') ?? []) as $depositoId => $fecha) {
            if (\App\Libraries\SucursalService::puedeUsarDeposito((int) $depositoId, (int) $this->sucursalId())) {
                $resultado[(int) $depositoId] = \App\Libraries\InventarioService::fechaVencimiento((string) $fecha);
            }
        }
        return $resultado;
    }

    private function guardarVencimientos(int $productoId, array $vencimientos): void
    {
        // Producto que no lleva vencimiento: se borran las fechas que tuviera
        if (!Db::value("SELECT controla_vencimiento FROM productos WHERE producto_id = ?", [$productoId])) {
            Db::query("UPDATE inventario SET fecha_vencimiento = NULL WHERE producto_id = ?", [$productoId]);
            return;
        }
        foreach ($vencimientos as $depositoId => $fecha) {
            \App\Libraries\InventarioService::fijarVencimiento($productoId, $depositoId, $fecha);
        }
    }

    private function unidadesFormulario(): array
    {
        return json_decode((string) ($this->request->getPost('unidades_json') ?? '[]'), true) ?: [];
    }
}

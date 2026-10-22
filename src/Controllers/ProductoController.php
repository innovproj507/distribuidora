<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Application\Inventory\RegisterProductCommand;
use App\Application\Inventory\UpdateProductCommand;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Inventory\Domain\Model\ProductId;
use App\Models\PrecioProducto;
use App\Models\Producto;
use App\Services\AuditService;
use App\Services\SimpleXlsxWriter;
use App\Shared\Infrastructure\ServiceFactory;

/**
 * ProductoController - Controlador delgado (thin controller).
 * Responsabilidades: extraer datos del request, delegar a use cases/queries,
 * pasar resultados a la vista o redirigir. Sin SQL, sin reglas de negocio.
 */
class ProductoController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    // READ
    // ──────────────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $companyId  = $this->empresaId();
        $branchId   = $this->sucursalId();
        $depositId  = $this->resolveDepositId($branchId);
        $page       = max(1, (int) $this->request->get('page', '1'));
        $perPage    = (int) ($_ENV['PAGINATION_PER_PAGE'] ?? 25);
        $search     = $this->request->get('buscar', '');
        $cat        = $this->request->get('categoria', '');
        $categoryId = $cat !== '' && $cat !== 'todos' ? (int) $cat : null;
        $status     = $this->request->get('estado') ?: null;

        $query  = ServiceFactory::getProductListQuery();
        $result = $query->paginate($companyId, $depositId, $page, $perPage, $search, $categoryId, $status);
        $stats  = $query->stats($companyId, $depositId);

        $this->view('inventario.lista', array_merge($result, $stats, [
            'page_title'      => 'Inventario',
            'page_subtitle'   => 'Gestión de productos',
            'categorias'      => $query->categories($companyId),
            'buscar'          => $search,
            'categoria_filtro' => $categoryId,
            'estado_filtro'   => $status,
        ]));
    }

    public function detalle(int $producto_id): void
    {
        $producto = Producto::find($producto_id);

        if (!$producto || (int)$producto->empresa_id !== $this->empresaId()) {
            $this->error('Producto no encontrado o no autorizado.');
            $this->redirect('/inventario');
            return;
        }

        $precios    = PrecioProducto::where('producto_id', $producto_id);
        $inventarios = Database::query(
            "SELECT i.*, d.nombre as deposito_nombre
             FROM inventario i LEFT JOIN depositos d ON i.deposito_id = d.deposito_id
             WHERE i.producto_id = ?",
            [$producto_id]
        )->fetchAll();
        $unidades = Database::query(
            "SELECT * FROM productos_unidades WHERE producto_id = ? ORDER BY unidad_id ASC",
            [$producto_id]
        )->fetchAll();

        $categoria = $producto->categoria_id
            ? \App\Models\CategoriaProducto::find((int) $producto->categoria_id)
            : null;

        $proveedor = $producto->proveedor_id
            ? \App\Models\Proveedor::find((int) $producto->proveedor_id)
            : null;

        $this->view('inventario.detalle', [
            'page_title'    => $producto->nombre,
            'page_subtitle' => 'Detalle del producto',
            'producto'      => $producto,
            'precios'       => $precios,
            'inventarios'   => $inventarios,
            'unidades'      => $unidades,
            'categoria'     => $categoria,
            'proveedor'     => $proveedor,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // CREATE
    // ──────────────────────────────────────────────────────────────────────────

    public function crear(): void
    {
        $query = ServiceFactory::getProductListQuery();
        $form  = $query->formData($this->empresaId(), $this->sucursalId());

        $this->view('inventario.crear', array_merge($form, [
            'page_title'    => 'Nuevo Producto',
            'page_subtitle' => 'Agregar producto al inventario',
        ]));
    }

    public function guardar(): void
    {
        if (!$this->verifyCsrf()) return;

        $imagePath = $this->handleUpload('imagen_principal', 'productos');
        $command   = new RegisterProductCommand(
            companyId:      $this->empresaId(),
            sku:            $this->request->post('codigo', ''),
            name:           $this->request->post('nombre', ''),
            categoryId:     (int) $this->request->post('categoria_id', '0') ?: null,
            cost:           (float) $this->request->post('costo_inicial', '0'),
            taxRate:        (float) $this->request->post('itbms', '0.07'),
            minStock:       (float) $this->request->post('stock_minimo', '0'),
            managesBatches: $this->request->post('maneja_lotes', '0') === '1',
            status:         $this->request->post('estado', 'activo'),
            description:    $this->request->post('descripcion') ?: null,
            barcode:        $this->request->post('codigo_barras') ?: null,
            brand:          $this->request->post('marca') ?: null,
            supplierId:     $this->request->post('proveedor_id') ? (int) $this->request->post('proveedor_id') : null,
            supplierPartNo: $this->request->post('supplier_part_no') ?: null,
            imagePath:      $imagePath,
            prices:         $this->extractPrices(),
            depositStocks:  $this->extractDepositStocks(),
        );

        try {
            $productId = ServiceFactory::getRegisterProductUseCase()->execute($command);
            $this->saveAuxiliaryUnits($productId->value());
            AuditService::log(
                'productos.crear',
                "Producto creado: {$command->name} (SKU: {$command->sku})",
                $productId->value(), 'producto',
                [], $this->empresaId()
            );
            $this->success('Producto creado exitosamente.');
            $this->redirect('/inventario');
        } catch (\DomainException $e) {
            $this->error($e->getMessage());
            $this->redirect('/inventario/nuevo');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // UPDATE
    // ──────────────────────────────────────────────────────────────────────────

    public function editar(int $producto_id): void
    {
        $producto = Producto::find($producto_id);

        if (!$producto || (int)$producto->empresa_id !== $this->empresaId()) {
            $this->error('Producto no encontrado o no autorizado.');
            $this->redirect('/inventario');
            return;
        }

        $query  = ServiceFactory::getProductListQuery();
        $form   = $query->formData($this->empresaId(), $this->sucursalId());
        $prices = PrecioProducto::where('producto_id', $producto_id);
        $units  = Database::query(
            "SELECT * FROM productos_unidades WHERE producto_id = ?",
            [$producto_id]
        )->fetchAll();

        $this->view('inventario.editar', array_merge($form, [
            'page_title'    => 'Editar Producto',
            'page_subtitle' => $producto->nombre,
            'producto'      => $producto,
            'precios'       => $prices,
            'unidades'      => $units,
        ]));
    }

    public function actualizar(int $producto_id): void
    {
        if (!$this->verifyCsrf()) return;

        $imagePath = $this->handleUpload('imagen_principal', 'productos');
        $command   = new UpdateProductCommand(
            productId:      $producto_id,
            companyId:      $this->empresaId(),
            sku:            $this->request->post('codigo', ''),
            name:           $this->request->post('nombre', ''),
            categoryId:     (int) $this->request->post('categoria_id', '0') ?: null,
            cost:           (float) $this->request->post('costo', '0'),
            taxRate:        (float) $this->request->post('itbms', '0.07'),
            minStock:       (float) $this->request->post('stock_minimo', '0'),
            managesBatches: $this->request->post('maneja_lotes', '0') === '1',
            status:         $this->request->post('estado', 'activo'),
            description:    $this->request->post('descripcion') ?: null,
            barcode:        $this->request->post('codigo_barras') ?: null,
            brand:          $this->request->post('marca') ?: null,
            supplierId:     $this->request->post('proveedor_id') ? (int) $this->request->post('proveedor_id') : null,
            supplierPartNo: $this->request->post('supplier_part_no') ?: null,
            newImagePath:   $imagePath,
            prices:         $this->extractPrices(['a', 'b', 'c', 'promocional']),
        );

        try {
            ServiceFactory::getUpdateProductUseCase()->execute($command);
            $this->saveAuxiliaryUnits($producto_id);
            AuditService::log(
                'productos.editar',
                "Producto actualizado: {$command->name} (SKU: {$command->sku})",
                $producto_id, 'producto',
                [], $this->empresaId()
            );
            $this->success('Producto actualizado exitosamente.');
            $this->redirect("/inventario/{$producto_id}");
        } catch (\DomainException $e) {
            $this->error($e->getMessage());
            $this->redirect("/inventario/{$producto_id}/editar");
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // DELETE
    // ──────────────────────────────────────────────────────────────────────────

    public function eliminar(int $producto_id): void
    {
        if (!$this->verifyCsrf()) return;

        // Verificar si el producto tiene movimientos en otras tablas
        $tablas = [
            'ventas_detalle'       => 'ventas',
            'compras_detalle'      => 'compras',
            'cotizaciones_detalle' => 'cotizaciones',
            'traslados_detalle'    => 'traslados',
        ];
        foreach ($tablas as $tabla => $label) {
            $n = (int) Database::query(
                "SELECT COUNT(*) AS n FROM {$tabla} WHERE producto_id = ?",
                [$producto_id]
            )->fetch()['n'];
            if ($n > 0) {
                $this->error(
                    "No se puede eliminar: el producto tiene {$n} registro(s) en {$label}. " .
                    "Desactívalo en su lugar para que deje de aparecer en búsquedas."
                );
                $this->redirect('/inventario');
                return;
            }
        }

        try {
            ServiceFactory::getDeleteProductUseCase()->execute($producto_id, $this->empresaId());
            $this->success('Producto eliminado exitosamente.');
        } catch (\DomainException $e) {
            $this->error($e->getMessage());
        } catch (\Throwable $e) {
            $this->error('No se puede eliminar el producto porque tiene datos asociados. Desactívalo en su lugar.');
        }

        $this->redirect('/inventario');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // STOCK / PRECIOS
    // ──────────────────────────────────────────────────────────────────────────

    public function agregarStock(int $producto_id): void
    {
        if (!$this->verifyCsrf()) return;

        $depositId = (int) $this->request->post('deposito_id');
        $cantidad  = (float) $this->request->post('cantidad', '0');
        $costo     = (float) $this->request->post('costo', '0');

        try {
            ServiceFactory::getAddStockUseCase()->execute($producto_id, $depositId, $cantidad, $costo);
            $this->success("Se agregaron {$cantidad} unidades al inventario.");
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
        }

        $this->redirect("/inventario/{$producto_id}");
    }

    public function precios(int $producto_id): void
    {
        $repo    = ServiceFactory::getProductRepository();
        $product = $repo->findById(new ProductId($producto_id));

        if ($product === null || $product->companyId() !== $this->empresaId()) {
            $this->error('No autorizado.');
            $this->redirect('/inventario');
            return;
        }

        if ($this->request->isPost()) {
            if (!$this->verifyCsrf()) return;

            $command = new UpdateProductCommand(
                productId:  $producto_id,
                companyId:  $this->empresaId(),
                sku:        $product->sku(),
                name:       $product->name(),
                categoryId: $product->categoryId(),
                cost:       $product->cost()->amount(),
                taxRate:    $product->taxRate(),
                prices:     $this->extractPrices(['a', 'b', 'c', 'promocional']),
            );

            ServiceFactory::getUpdateProductUseCase()->execute($command);
            $this->success('Precios actualizados.');
            $this->redirect("/inventario/{$producto_id}/precios");
            return;
        }

        $this->view('inventario.precios', [
            'page_title'    => 'Gestionar Precios',
            'page_subtitle' => $product->name(),
            'product'       => $product,
            'precios'       => PrecioProducto::where('producto_id', $producto_id),
        ]);
    }

    public function kardex(): void
    {
        $sucursalId = $this->sucursalId();
        $productoId = $this->request->get('producto_id');
        $depositoId = $this->request->get('deposito_id');
        $tipo       = $this->request->get('tipo');

        $sql    = "SELECT m.*, p.nombre as producto_nombre, d.nombre as deposito_nombre,
                          u.username as usuario_nombre
                   FROM inventario_movimientos m
                   JOIN inventario i  ON m.inventario_id = i.inventario_id
                   JOIN productos p   ON i.producto_id   = p.producto_id
                   JOIN depositos d   ON i.deposito_id   = d.deposito_id
                   LEFT JOIN users u  ON m.usuario_id    = u.id
                   WHERE d.sucursal_id = ?";
        $params = [$sucursalId];

        if ($productoId) { $sql .= " AND p.producto_id = ?"; $params[] = $productoId; }
        if ($depositoId) { $sql .= " AND d.deposito_id = ?"; $params[] = $depositoId; }
        if ($tipo)       { $sql .= " AND m.tipo = ?";        $params[] = $tipo; }

        $sql .= " ORDER BY m.fecha_registro DESC LIMIT 100";

        $query = ServiceFactory::getProductListQuery();

        $this->view('inventario.kardex', [
            'page_title'    => 'Auditoría de Inventario (Kardex)',
            'page_subtitle' => 'Trazabilidad total de movimientos',
            'movimientos'   => Database::query($sql, $params)->fetchAll(),
            'depositos'     => Database::query(
                "SELECT deposito_id, nombre FROM depositos WHERE sucursal_id = ?",
                [$sucursalId]
            )->fetchAll(),
            'productos'     => Database::query(
                "SELECT p.producto_id, p.nombre FROM productos p
                 JOIN inventario i ON i.producto_id = p.producto_id
                 JOIN depositos d  ON d.deposito_id = i.deposito_id AND d.sucursal_id = ?
                 GROUP BY p.producto_id ORDER BY p.nombre",
                [$sucursalId]
            )->fetchAll(),
            'filtros'       => compact('productoId', 'depositoId', 'tipo'),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers privados
    // ──────────────────────────────────────────────────────────────────────────

    private function resolveDepositId(?int $branchId): int
    {
        $deposito = $this->session->get('deposito_actual');
        if ($deposito) {
            return (int) $deposito['deposito_id'];
        }
        if ($branchId === null) return 0;
        return ServiceFactory::getProductListQuery()->defaultDepositId($branchId);
    }

    /** @return array<array{type: string, amount: float}> */
    private function extractPrices(array $types = ['a', 'b', 'c']): array
    {
        $prices = [];
        foreach ($types as $type) {
            $val = $this->request->post("precio_{$type}");
            if ($val !== null && $val !== '') {
                $prices[] = ['type' => $type, 'amount' => (float) $val];
            }
        }
        return $prices;
    }

    /** @return array<array{deposit_id: int, quantity: float}> */
    private function extractDepositStocks(): array
    {
        $depositIds = $this->request->post('depositos_ids', []);
        $stocks     = $this->request->post('stocks', []);
        $result     = [];

        foreach ($depositIds as $index => $depId) {
            $qty = (float) ($stocks[$index] ?? 0);
            if ($qty > 0 || $index === 0) {
                $result[] = ['deposit_id' => (int) $depId, 'quantity' => $qty];
            }
        }
        return $result;
    }

    private function saveAuxiliaryUnits(int $productoId): void
    {
        $json    = $this->request->post('unidades_json', '[]');
        $unidades = json_decode($json, true) ?? [];

        Database::query("DELETE FROM productos_unidades WHERE producto_id = ?", [$productoId]);

        foreach ($unidades as $u) {
            $nombre = trim($u['nombre'] ?? '');
            if ($nombre === '') continue;

            $factor = max(0.001, (float) ($u['factor_conversion'] ?? 1.0));

            Database::query(
                "INSERT INTO productos_unidades
                    (producto_id, nombre, factor_conversion, precio_a, precio_b, precio_c, codigo_barras)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $productoId,
                    $nombre,
                    $factor,
                    (float) ($u['precio_a'] ?? 0),
                    (float) ($u['precio_b'] ?? 0),
                    (float) ($u['precio_c'] ?? 0),
                    trim($u['codigo_barras'] ?? ''),
                ]
            );
        }
    }

    public function exportarExcel(): void
    {
        if (!Auth::can('productos.ver')) {
            $this->error('Sin permiso.');
            $this->redirect('/inventario');
            return;
        }

        $productos = Database::query(
            "SELECT p.codigo, p.nombre,
                    cp.nombre                              AS categoria,
                    p.marca,
                    COALESCE(pa.precio, 0)                AS precio_a,
                    COALESCE(pb.precio, 0)                AS precio_b,
                    p.costo,
                    COALESCE(SUM(i.existencia), 0)        AS stock,
                    p.estado
             FROM productos p
             LEFT JOIN categorias_productos cp ON p.categoria_id = cp.categoria_id
             LEFT JOIN precios_productos pa ON pa.producto_id = p.producto_id AND pa.tipo_precio = 'A'
             LEFT JOIN precios_productos pb ON pb.producto_id = p.producto_id AND pb.tipo_precio = 'B'
             LEFT JOIN inventario i ON i.producto_id = p.producto_id
             WHERE p.empresa_id = ?
             GROUP BY p.producto_id, pa.precio, pb.precio
             ORDER BY cp.nombre, p.nombre",
            [$this->empresaId()]
        )->fetchAll();

        $headers = ['Código', 'Nombre', 'Categoría', 'Marca', 'Precio A', 'Precio B', 'Costo', 'Stock', 'Estado'];

        $rows = array_map(fn($p) => [
            $p['codigo']        ?? '',
            $p['nombre']        ?? '',
            $p['categoria']     ?? '',
            $p['marca']         ?? '',
            number_format((float) $p['precio_a'], 2, '.', ''),
            number_format((float) $p['precio_b'], 2, '.', ''),
            number_format((float) $p['costo'],    2, '.', ''),
            (int) $p['stock'],
            $p['estado']        ?? '',
        ], $productos);

        SimpleXlsxWriter::output('inventario_' . date('Y-m-d') . '.xlsx', $headers, $rows);
    }

    // ── Creación rápida desde formulario de producto ──────────────────────────

    public function crearCategoriaRapida(): void
    {
        $data   = $this->request->json() ?: [];
        $nombre = trim($data['nombre'] ?? '');

        if ($nombre === '') {
            $this->json(['error' => 'El nombre es requerido'], 422);
            return;
        }

        $empresaId = $this->empresaId();

        try {
            Database::query(
                "INSERT INTO categorias_productos (empresa_id, nombre, nivel, padre_id) VALUES (?, ?, 1, NULL)",
                [$empresaId, $nombre]
            );
            $id = (int) Database::lastInsertId();

            AuditService::log('inventario.categoria.crear_rapido', "Categoría creada: {$nombre}", $id, 'categoria', [], $empresaId);
            $this->json(['success' => true, 'categoria' => ['id' => $id, 'nombre' => $nombre]]);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    public function crearMarcaRapida(): void
    {
        $data   = $this->request->json() ?: [];
        $nombre = trim($data['nombre'] ?? '');

        if ($nombre === '') {
            $this->json(['error' => 'El nombre es requerido'], 422);
            return;
        }

        $empresaId = $this->empresaId();

        // Si ya existe, simplemente la devuelve
        $existe = (int) Database::query(
            "SELECT COUNT(*) AS n FROM marcas WHERE empresa_id = ? AND nombre = ?",
            [$empresaId, $nombre]
        )->fetch()['n'];

        if (!$existe) {
            try {
                Database::query(
                    "INSERT INTO marcas (empresa_id, nombre) VALUES (?, ?)",
                    [$empresaId, $nombre]
                );
                AuditService::log('inventario.marca.crear_rapido', "Marca creada: {$nombre}", null, 'marca', [], $empresaId);
            } catch (\Throwable $e) {
                $this->json(['error' => $e->getMessage()], 500);
                return;
            }
        }

        $this->json(['success' => true, 'marca' => ['nombre' => $nombre]]);
    }
}

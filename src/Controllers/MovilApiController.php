<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Models\Movimiento;
use App\Services\AuditService;

/**
 * MovilApiController - Endpoints JSON propios de la PWA móvil (/m/api/*)
 *
 * Solo cubre lo que no existe ya en el sistema web: la lista paginada de
 * productos con stock del depósito actual y el registro de entradas/salidas.
 * Ventas, clientes y compras usan los endpoints web existentes.
 */
class MovilApiController extends Controller
{
    private const POR_PAGINA = 40;

    /**
     * GET /m/api/productos?q=&categoria=&stock_bajo=1&offset=
     */
    public function productos(): void
    {
        if (!Auth::can('pos.usar') && !Auth::can('productos.ver')
            && !Auth::can('inventario.ver') && !Auth::can('compras.crear')) {
            $this->json(['error' => 'No tienes permiso para ver productos.'], 403);
            return;
        }

        $q           = trim((string) $this->request->get('q', ''));
        $categoriaId = (int) $this->request->get('categoria', 0);
        $soloBajo    = $this->request->get('stock_bajo') === '1';
        $offset      = max(0, (int) $this->request->get('offset', 0));

        $where  = ["p.estado = 'activo'", 'p.empresa_id = ?'];
        $params = [$this->depositoId(), $this->empresaId()];

        if ($q !== '') {
            // Código / código de barras con la frase completa, o nombre con todas las palabras
            $terminos   = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [$q];
            $condNombre = implode(' AND ', array_fill(0, count($terminos), 'p.nombre LIKE ?'));
            $where[]    = "(p.codigo LIKE ? OR p.codigo_barras LIKE ? OR ({$condNombre}))";
            $params[]   = "%{$q}%";
            $params[]   = "%{$q}%";
            foreach ($terminos as $t) {
                $params[] = "%{$t}%";
            }
        }
        if ($categoriaId > 0) {
            $where[]  = 'p.categoria_id = ?';
            $params[] = $categoriaId;
        }
        if ($soloBajo) {
            $where[] = 'COALESCE(i.existencia, 0) <= COALESCE(i.minimo, 0)';
        }

        $limite = self::POR_PAGINA + 1; // uno extra para saber si hay más páginas
        $filas = Database::query(
            "SELECT p.producto_id, p.codigo, p.codigo_barras, p.nombre, p.costo, p.maneja_lotes,
                    p.imagen_principal, c.nombre AS categoria_nombre,
                    COALESCE(i.existencia, 0) AS existencia, COALESCE(i.minimo, 0) AS minimo,
                    COALESCE(pa.precio, 0) AS precio_a,
                    COALESCE(pb.precio, 0) AS precio_b,
                    COALESCE(pc.precio, 0) AS precio_c
             FROM productos p
             LEFT JOIN inventario i ON i.producto_id = p.producto_id AND i.deposito_id = ?
             LEFT JOIN categorias_productos c ON c.categoria_id = p.categoria_id
             LEFT JOIN precios_productos pa ON pa.producto_id = p.producto_id AND pa.tipo_precio = 'a'
             LEFT JOIN precios_productos pb ON pb.producto_id = p.producto_id AND pb.tipo_precio = 'b'
             LEFT JOIN precios_productos pc ON pc.producto_id = p.producto_id AND pc.tipo_precio = 'c'
             WHERE " . implode(' AND ', $where) . "
             ORDER BY p.nombre
             LIMIT {$limite} OFFSET {$offset}",
            $params
        )->fetchAll();

        $hayMas = count($filas) > self::POR_PAGINA;
        $filas  = array_slice($filas, 0, self::POR_PAGINA);

        $verCosto = Auth::can('productos.editar') || Auth::can('compras.crear');
        $productos = array_map(function (array $p) use ($verCosto): array {
            $item = [
                'producto_id'      => (int) $p['producto_id'],
                'codigo'           => $p['codigo'],
                'codigo_barras'    => $p['codigo_barras'],
                'nombre'           => $p['nombre'],
                'categoria_nombre' => $p['categoria_nombre'],
                'imagen'           => $p['imagen_principal'] ? '/assets/uploads/' . $p['imagen_principal'] : null,
                'maneja_lotes'     => (bool) $p['maneja_lotes'],
                'existencia'       => (float) $p['existencia'],
                'minimo'           => (float) $p['minimo'],
                'precio_a'         => (float) $p['precio_a'],
                'precio_b'         => (float) $p['precio_b'],
                'precio_c'         => (float) $p['precio_c'],
            ];
            if ($verCosto) {
                $item['costo'] = (float) $p['costo'];
            }
            return $item;
        }, $filas);

        $this->json([
            'productos'   => $productos,
            'hay_mas'     => $hayMas,
            'offset'      => $offset + count($productos),
            'deposito_id' => $this->depositoId(),
        ]);
    }

    /**
     * POST /m/api/movimiento  {producto_id, tipo: entrada|salida, cantidad, motivo}
     */
    public function movimiento(): void
    {
        if (!$this->verificarCsrfHeader()) return;

        if (!Auth::can('inventario.ajustar')) {
            $this->json(['error' => 'No tienes permiso para ajustar inventario.'], 403);
            return;
        }

        $data       = $this->request->json();
        $productoId = (int) ($data['producto_id'] ?? 0);
        $tipo       = ($data['tipo'] ?? '') === 'salida' ? 'salida' : 'entrada';
        $cantidad   = (float) ($data['cantidad'] ?? 0);
        $motivo     = trim((string) ($data['motivo'] ?? ''));
        $depositoId = $this->depositoId();

        if ($cantidad <= 0) {
            $this->json(['error' => 'La cantidad debe ser mayor que cero.'], 422);
            return;
        }

        $producto = Database::query(
            "SELECT producto_id, nombre, maneja_lotes FROM productos WHERE producto_id = ? AND empresa_id = ?",
            [$productoId, $this->empresaId()]
        )->fetch();

        if (!$producto || !$depositoId) {
            $this->json(['error' => 'Producto o depósito no encontrado.'], 404);
            return;
        }
        if ($producto['maneja_lotes']) {
            // Pitfall del sistema: el stock de productos con lotes se mueve solo por LoteController
            $this->json(['error' => 'Este producto maneja lotes; registra el movimiento desde la versión web.'], 422);
            return;
        }

        try {
            Database::beginTransaction();

            $inv = Database::query(
                "SELECT inventario_id, existencia FROM inventario WHERE producto_id = ? AND deposito_id = ? FOR UPDATE",
                [$productoId, $depositoId]
            )->fetch();

            if (!$inv) {
                Database::query(
                    "INSERT INTO inventario (producto_id, deposito_id, existencia) VALUES (?, ?, 0)",
                    [$productoId, $depositoId]
                );
                $inv = ['inventario_id' => (int) Database::lastInsertId(), 'existencia' => 0];
            }

            if ($tipo === 'salida' && $cantidad > (float) $inv['existencia']) {
                Database::rollback();
                $this->json(['error' => 'No hay stock suficiente. Existencia actual: ' . (float) $inv['existencia']], 422);
                return;
            }

            Movimiento::registrar(
                (int) $inv['inventario_id'],
                $tipo,
                $cantidad,
                null,
                'movil',
                $motivo !== '' ? $motivo : ($tipo === 'entrada' ? 'Entrada desde móvil' : 'Salida desde móvil')
            );

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            $this->json(['error' => 'No se pudo registrar el movimiento.'], 500);
            return;
        }

        $existencia = (float) Database::query(
            "SELECT existencia FROM inventario WHERE inventario_id = ?",
            [$inv['inventario_id']]
        )->fetch()['existencia'];

        AuditService::log(
            'inventario.ajustar',
            ucfirst($tipo) . " de {$cantidad} uds de {$producto['nombre']} (móvil)",
            $productoId, 'producto',
            ['tipo' => $tipo, 'cantidad' => $cantidad, 'deposito_id' => $depositoId, 'existencia' => $existencia],
            $this->empresaId()
        );

        $this->json(['success' => true, 'existencia' => $existencia]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function verificarCsrfHeader(): bool
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($this->session->verifyCsrf($token)) return true;
        $this->json(['error' => 'La sesión cambió. Recarga la página e intenta de nuevo.'], 419);
        return false;
    }

    private function depositoId(): int
    {
        $deposito = $this->session->get('deposito_actual');
        return $deposito ? (int) $deposito['deposito_id'] : 0;
    }
}

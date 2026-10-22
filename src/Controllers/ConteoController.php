<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Auth;
use App\Services\AuditService;

/**
 * ConteoController - Inventario cíclico / conteo físico con reconciliación.
 *
 * Flujo:
 *   1. crear()   → abre una sesión de conteo para un depósito
 *   2. contar()  → el usuario ingresa cantidades físicas por producto
 *   3. reconciliar() → muestra diferencias y permite aplicar ajustes
 *   4. cerrar()  → cierra la sesión y registra movimientos de ajuste
 */
class ConteoController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    // Listado de sesiones
    // ──────────────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $conteos = Database::query(
            "SELECT c.*, d.nombre AS deposito_nombre,
                    u.username AS usuario_nombre
             FROM inventario_conteos c
             JOIN depositos d ON c.deposito_id = d.deposito_id
             LEFT JOIN users u ON c.usuario_id = u.id
             WHERE c.empresa_id = ?
             ORDER BY c.fecha_registro DESC",
            [$this->empresaId()]
        )->fetchAll();

        $this->view('inventario.conteos.index', [
            'page_title'    => 'Inventario Físico',
            'page_subtitle' => 'Conteos y reconciliación de stock',
            'conteos'       => $conteos,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Crear sesión
    // ──────────────────────────────────────────────────────────────────────────

    public function crear(): void
    {
        $depositos = Database::query(
            "SELECT d.* FROM depositos d
             WHERE d.sucursal_id = ? AND d.estado = 'activo'
             ORDER BY d.nombre",
            [$this->sucursalId()]
        )->fetchAll();

        $this->view('inventario.conteos.crear', [
            'page_title'    => 'Nuevo Conteo',
            'page_subtitle' => 'Iniciar sesión de inventario físico',
            'depositos'     => $depositos,
        ]);
    }

    public function guardar(): void
    {
        if (!$this->verifyCsrf()) return;

        $depositoId  = (int) $this->request->post('deposito_id');
        $descripcion = $this->request->post('descripcion', '');
        $tipo        = $this->request->post('tipo', 'completo');
        $empresaId   = $this->empresaId();

        // Verificar que el depósito pertenece a la empresa
        $dep = Database::query(
            "SELECT d.sucursal_id FROM depositos d
             JOIN branches b ON d.sucursal_id = b.sucursal_id
             WHERE d.deposito_id = ? AND b.empresa_id = ?",
            [$depositoId, $empresaId]
        )->fetch();

        if (!$dep) {
            $this->error('Depósito no válido.');
            $this->redirect('/inventario/conteos/nuevo');
            return;
        }

        $sucursalId = (int) $dep['sucursal_id'];

        // Crear sesión de conteo
        Database::query(
            "INSERT INTO inventario_conteos
                (empresa_id, sucursal_id, deposito_id, descripcion, tipo, estado, usuario_id, fecha_inicio)
             VALUES (?, ?, ?, ?, ?, 'en_proceso', ?, NOW())",
            [$empresaId, $sucursalId, $depositoId, $descripcion, $tipo, Auth::user()['id']]
        );

        $conteo_id = (int) Database::lastInsertId();

        // Cargar productos del depósito con su stock actual
        $productos = Database::query(
            "SELECT p.producto_id, i.existencia, i.costo_promedio
             FROM inventario i
             JOIN productos p ON i.producto_id = p.producto_id
             WHERE i.deposito_id = ? AND p.empresa_id = ? AND p.estado = 'activo'",
            [$depositoId, $empresaId]
        )->fetchAll();

        foreach ($productos as $prod) {
            Database::query(
                "INSERT INTO conteo_detalle
                    (conteo_id, producto_id, cantidad_sistema, costo_unitario)
                 VALUES (?, ?, ?, ?)",
                [$conteo_id, $prod['producto_id'], $prod['existencia'], $prod['costo_promedio']]
            );
        }

        // Actualizar total de productos
        Database::query(
            "UPDATE inventario_conteos SET total_productos = ? WHERE conteo_id = ?",
            [count($productos), $conteo_id]
        );

        $this->success('Sesión de conteo iniciada con ' . count($productos) . ' productos.');
        $this->redirect("/inventario/conteos/{$conteo_id}/contar");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Ingresar cantidades contadas
    // ──────────────────────────────────────────────────────────────────────────

    public function contar(int $conteo_id): void
    {
        $conteo = $this->findConteo($conteo_id);
        if (!$conteo) return;

        if ($conteo['estado'] === 'completado') {
            $this->redirect("/inventario/conteos/{$conteo_id}/reconciliar");
            return;
        }

        $items = Database::query(
            "SELECT cd.*, p.nombre, p.codigo, p.codigo_barras
             FROM conteo_detalle cd
             JOIN productos p ON cd.producto_id = p.producto_id
             WHERE cd.conteo_id = ?
             ORDER BY p.nombre ASC",
            [$conteo_id]
        )->fetchAll();

        $this->view('inventario.conteos.contar', [
            'page_title'    => 'Conteo Físico',
            'page_subtitle' => $conteo['descripcion'] ?: 'Ingrese cantidades contadas',
            'conteo'        => $conteo,
            'items'         => $items,
        ]);
    }

    public function guardarConteo(int $conteo_id): void
    {
        if (!$this->verifyCsrf()) return;

        $conteo = $this->findConteo($conteo_id);
        if (!$conteo) return;

        $cantidades = $this->request->post('cantidad', []);

        foreach ($cantidades as $productoId => $cantidad) {
            $qty = $cantidad === '' ? null : (float) $cantidad;
            Database::query(
                "UPDATE conteo_detalle
                 SET cantidad_contada = ?,
                     estado = IF(? IS NULL, 'pendiente', 'contado')
                 WHERE conteo_id = ? AND producto_id = ?",
                [$qty, $qty, $conteo_id, (int) $productoId]
            );
        }

        // Calcular diferencias y total
        $diferencias = (int) Database::query(
            "SELECT COUNT(*) as t FROM conteo_detalle
             WHERE conteo_id = ? AND diferencia <> 0 AND estado = 'contado'",
            [$conteo_id]
        )->fetch()['t'];

        Database::query(
            "UPDATE inventario_conteos SET total_diferencias = ? WHERE conteo_id = ?",
            [$diferencias, $conteo_id]
        );

        $this->success('Conteo guardado.');
        $this->redirect("/inventario/conteos/{$conteo_id}/contar");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Reconciliación
    // ──────────────────────────────────────────────────────────────────────────

    public function reconciliar(int $conteo_id): void
    {
        $conteo = $this->findConteo($conteo_id);
        if (!$conteo) return;

        $items = Database::query(
            "SELECT cd.*, p.nombre, p.codigo,
                    (cd.diferencia * cd.costo_unitario) AS impacto_costo
             FROM conteo_detalle cd
             JOIN productos p ON cd.producto_id = p.producto_id
             WHERE cd.conteo_id = ? AND cd.estado = 'contado'
             ORDER BY ABS(cd.diferencia) DESC",
            [$conteo_id]
        )->fetchAll();

        $totales = [
            'productos'   => count($items),
            'diferencias' => 0,
            'sobrantes'   => 0,
            'faltantes'   => 0,
            'impacto'     => 0.0,
        ];

        foreach ($items as $item) {
            $dif = (float) $item['diferencia'];
            if ($dif != 0) {
                $totales['diferencias']++;
                if ($dif > 0) $totales['sobrantes']++;
                else $totales['faltantes']++;
                $totales['impacto'] += (float) $item['impacto_costo'];
            }
        }

        $this->view('inventario.conteos.reconciliar', [
            'page_title'    => 'Reconciliación',
            'page_subtitle' => $conteo['descripcion'] ?: 'Comparación sistema vs. conteo físico',
            'conteo'        => $conteo,
            'items'         => $items,
            'totales'       => $totales,
        ]);
    }

    public function aplicarAjustes(int $conteo_id): void
    {
        if (!$this->verifyCsrf()) return;
        if (!$this->requirePermission('conteos.reconciliar')) return;

        $conteo = $this->findConteo($conteo_id);
        if (!$conteo || $conteo['estado'] === 'completado') {
            $this->error('Conteo ya procesado.');
            $this->redirect('/inventario/conteos');
            return;
        }

        $items = Database::query(
            "SELECT cd.*, p.producto_id
             FROM conteo_detalle cd
             JOIN productos p ON cd.producto_id = p.producto_id
             WHERE cd.conteo_id = ? AND cd.estado = 'contado' AND cd.diferencia <> 0",
            [$conteo_id]
        )->fetchAll();

        $usuarioId = Auth::user()['id'];

        foreach ($items as $item) {
            $dif        = (float) $item['diferencia'];
            $productoId = (int) $item['producto_id'];
            $depositoId = (int) $conteo['deposito_id'];

            // Obtener inventario actual
            $inv = Database::query(
                "SELECT inventario_id, existencia FROM inventario
                 WHERE producto_id = ? AND deposito_id = ?",
                [$productoId, $depositoId]
            )->fetch();

            if (!$inv) continue;

            $saldoAnterior = (float) $inv['existencia'];
            $saldoNuevo    = $saldoAnterior + $dif;

            // Actualizar existencia
            Database::query(
                "UPDATE inventario SET existencia = ? WHERE inventario_id = ?",
                [$saldoNuevo, $inv['inventario_id']]
            );

            // Registrar movimiento de ajuste en kardex
            Database::query(
                "INSERT INTO inventario_movimientos
                    (inventario_id, tipo, cantidad, saldo_anterior, saldo_nuevo,
                     usuario_id, referencia_id, referencia_tipo, motivo)
                 VALUES (?, 'ajuste', ?, ?, ?, ?, ?, 'conteo', 'Ajuste por conteo físico')",
                [
                    (int) $inv['inventario_id'],
                    abs($dif), $saldoAnterior, $saldoNuevo,
                    $usuarioId, $conteo_id,
                ]
            );

            // Marcar línea como ajustada
            Database::query(
                "UPDATE conteo_detalle SET estado = 'ajustado' WHERE detalle_id = ?",
                [(int) $item['detalle_id']]
            );
        }

        // Cerrar conteo
        Database::query(
            "UPDATE inventario_conteos SET estado = 'completado', fecha_cierre = NOW() WHERE conteo_id = ?",
            [$conteo_id]
        );

        AuditService::log(
            'inventario.ajustar',
            "Ajuste de inventario por conteo #{$conteo_id}: " . count($items) . ' líneas ajustadas',
            $conteo_id, 'conteo',
            ['deposito_id' => $conteo['deposito_id'], 'diferencias' => count($items)],
            $this->empresaId()
        );

        $this->success('Ajustes aplicados. Inventario actualizado.');
        $this->redirect("/inventario/conteos/{$conteo_id}/reconciliar");
    }

    public function cancelar(int $conteo_id): void
    {
        if (!$this->verifyCsrf()) return;

        $conteo = $this->findConteo($conteo_id);
        if (!$conteo) return;

        Database::query(
            "UPDATE inventario_conteos SET estado = 'cancelado' WHERE conteo_id = ?",
            [$conteo_id]
        );

        $this->warning('Conteo cancelado.');
        $this->redirect('/inventario/conteos');
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function findConteo(int $conteo_id): ?array
    {
        $conteo = Database::query(
            "SELECT c.*, d.nombre AS deposito_nombre
             FROM inventario_conteos c
             JOIN depositos d ON c.deposito_id = d.deposito_id
             WHERE c.conteo_id = ? AND c.empresa_id = ?",
            [$conteo_id, $this->empresaId()]
        )->fetch();

        if (!$conteo) {
            $this->error('Conteo no encontrado.');
            $this->redirect('/inventario/conteos');
            return null;
        }

        return $conteo;
    }
}

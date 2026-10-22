<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Auth;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Services\OrdenCompraAutoService;

class OrdenCompraAutoController extends Controller
{
    public function index(): void
    {
        $empresaId = $this->empresaId();

        $sugerencias = Database::query(
            "SELECT s.*,
                    p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                    d.nombre AS deposito_nombre,
                    pr.nombre AS proveedor_nombre,
                    (s.cantidad_sugerida * s.costo_estimado) AS total_estimado
             FROM ordenes_compra_sugeridas s
             JOIN productos  p  ON s.producto_id  = p.producto_id
             JOIN depositos  d  ON s.deposito_id  = d.deposito_id
             LEFT JOIN proveedores pr ON s.proveedor_id = pr.proveedor_id
             WHERE s.empresa_id = ? AND s.estado = 'pendiente'
             ORDER BY s.motivo, p.nombre",
            [$empresaId]
        )->fetchAll();

        $totales = [
            'stock_minimo'     => 0,
            'demanda_historica'=> 0,
            'monto'            => 0.0,
        ];
        foreach ($sugerencias as $s) {
            $totales[$s['motivo']]++;
            $totales['monto'] += (float) $s['total_estimado'];
        }

        $this->view('compras.sugerencias.index', [
            'page_title'    => 'Órdenes de Compra Sugeridas',
            'page_subtitle' => 'Generadas por punto de reorden y demanda histórica',
            'sugerencias'   => $sugerencias,
            'totales'       => $totales,
        ]);
    }

    public function generar(): void
    {
        if (!$this->verifyCsrf()) return;

        $service = new OrdenCompraAutoService($this->empresaId());
        $total   = $service->regenerar();

        $this->success("Se generaron {$total} sugerencias de compra.");
        $this->redirect('/compras/sugerencias');
    }

    public function convertir(int $sugerencia_id): void
    {
        if (!$this->verifyCsrf()) return;

        $sug = Database::query(
            "SELECT s.*, p.nombre AS producto_nombre
             FROM ordenes_compra_sugeridas s
             JOIN productos p ON s.producto_id = p.producto_id
             WHERE s.sugerencia_id = ? AND s.empresa_id = ? AND s.estado = 'pendiente'",
            [$sugerencia_id, $this->empresaId()]
        )->fetch();

        if (!$sug) {
            $this->error('Sugerencia no encontrada o ya procesada.');
            $this->redirect('/compras/sugerencias');
            return;
        }

        try {
            Database::beginTransaction();

            $compra = Compra::create([
                'proveedor_id'             => $sug['proveedor_id'],
                'sucursal_id'              => $this->sucursalId(),
                'empresa_id'               => $this->empresaId(),
                'deposito_id'              => (int) $sug['deposito_id'],
                'numero_factura'           => '',
                'numero_factura_proveedor' => '',
                'monto_subtotal'           => $sug['cantidad_sugerida'] * $sug['costo_estimado'],
                'monto_itbms'              => 0,
                'monto_total'              => $sug['cantidad_sugerida'] * $sug['costo_estimado'],
                'estado'                   => 'pendiente',
                'fecha_compra'             => date('Y-m-d'),
                'notas'                    => 'Generada automáticamente — ' . ($sug['motivo'] === 'stock_minimo' ? 'punto de reorden' : 'demanda histórica'),
                'usuario_id'               => Auth::id(),
            ]);

            CompraDetalle::create([
                'compra_id'         => $compra->compra_id,
                'producto_id'       => (int) $sug['producto_id'],
                'cantidad'          => (float) $sug['cantidad_sugerida'],
                'cantidad_recibida' => 0,
                'costo'             => (float) $sug['costo_estimado'],
                'itbms'             => 0,
                'total_linea'       => $sug['cantidad_sugerida'] * $sug['costo_estimado'],
            ]);

            Database::query(
                "UPDATE ordenes_compra_sugeridas SET estado = 'convertida' WHERE sugerencia_id = ?",
                [$sugerencia_id]
            );

            Database::commit();

            $this->success("Orden de compra pendiente creada para \"{$sug['producto_nombre']}\".");
            $this->redirect("/compras/{$compra->compra_id}");

        } catch (\Exception $e) {
            Database::rollback();
            $this->error('Error al convertir: ' . $e->getMessage());
            $this->redirect('/compras/sugerencias');
        }
    }

    public function descartar(int $sugerencia_id): void
    {
        if (!$this->verifyCsrf()) return;

        Database::query(
            "UPDATE ordenes_compra_sugeridas SET estado = 'descartada'
             WHERE sugerencia_id = ? AND empresa_id = ?",
            [$sugerencia_id, $this->empresaId()]
        );

        $this->redirect('/compras/sugerencias');
    }
}

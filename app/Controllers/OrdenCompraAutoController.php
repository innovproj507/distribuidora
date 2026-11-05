<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\AuditService;
use App\Libraries\Auth;
use App\Libraries\Db;
use App\Libraries\OrdenCompraAutoService;

/**
 * Sugerencias de compra (punto de reorden y demanda histórica).
 * Permisos por ruta: sugerencias.ver / gestionar.
 */
class OrdenCompraAutoController extends BaseController
{
    public function index(): string
    {
        $sugerencias = Db::all(
            "SELECT s.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                    d.nombre AS deposito_nombre, pr.nombre AS proveedor_nombre,
                    (s.cantidad_sugerida * s.costo_estimado) AS total_estimado
             FROM ordenes_compra_sugeridas s
             JOIN productos p ON s.producto_id = p.producto_id
             JOIN depositos d ON s.deposito_id = d.deposito_id
             LEFT JOIN proveedores pr ON s.proveedor_id = pr.proveedor_id
             WHERE s.empresa_id = ? AND s.estado = 'pendiente'
             ORDER BY s.motivo, p.nombre",
            [$this->empresaId()]
        );

        $totales = ['stock_minimo' => 0, 'demanda_historica' => 0, 'monto' => 0.0];
        foreach ($sugerencias as $s) {
            $totales[$s['motivo']]++;
            $totales['monto'] += (float) $s['total_estimado'];
        }

        return $this->render('compras.sugerencias.index', [
            'page_title'    => 'Órdenes de Compra Sugeridas',
            'page_subtitle' => 'Generadas por punto de reorden y demanda histórica',
            'sugerencias'   => $sugerencias,
            'totales'       => $totales,
        ]);
    }

    public function generar()
    {
        $total = (new OrdenCompraAutoService((int) $this->empresaId()))->regenerar();
        $this->success("Se generaron {$total} sugerencias de compra.");
        return redirect()->to('/compras/sugerencias');
    }

    /** Crea una orden de compra pendiente (se recibe después desde /compras/{id}/recibir). */
    public function convertir(int $sugerenciaId)
    {
        $sug = Db::row(
            "SELECT s.*, p.nombre AS producto_nombre
             FROM ordenes_compra_sugeridas s JOIN productos p ON s.producto_id = p.producto_id
             WHERE s.sugerencia_id = ? AND s.empresa_id = ? AND s.estado = 'pendiente'",
            [$sugerenciaId, $this->empresaId()]
        );

        if (!$sug) {
            $this->error('Sugerencia no encontrada o ya procesada.');
            return redirect()->to('/compras/sugerencias');
        }

        $monto = (float) $sug['cantidad_sugerida'] * (float) $sug['costo_estimado'];

        try {
            Db::begin();
            $compraId = Db::insert('compras', [
                'proveedor_id'             => $sug['proveedor_id'],
                'sucursal_id'              => $this->sucursalId(),
                'empresa_id'               => $this->empresaId(),
                'deposito_id'              => (int) $sug['deposito_id'],
                // Referencia identificable hasta que llegue la factura real del proveedor
                'numero_factura'           => 'SUG-' . date('YmdHis') . '-' . $sugerenciaId,
                'numero_factura_proveedor' => null,
                'monto_subtotal'           => $monto,
                'monto_itbms'              => 0,
                'monto_total'              => $monto,
                'estado'                   => 'pendiente',
                'fecha_compra'             => date('Y-m-d'),
                'notas'                    => 'Generada automáticamente — ' . ($sug['motivo'] === 'stock_minimo' ? 'punto de reorden' : 'demanda histórica'),
                'usuario_id'               => Auth::id(),
            ]);
            Db::insert('compras_detalle', [
                'compra_id'         => $compraId,
                'producto_id'       => (int) $sug['producto_id'],
                'cantidad'          => (float) $sug['cantidad_sugerida'],
                'cantidad_recibida' => 0,
                'costo'             => (float) $sug['costo_estimado'],
                'itbms'             => 0,
                'total_linea'       => $monto,
            ]);
            Db::update('ordenes_compra_sugeridas', ['estado' => 'convertida'], ['sugerencia_id' => $sugerenciaId]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error('Error al convertir: ' . $e->getMessage());
            return redirect()->to('/compras/sugerencias');
        }

        AuditService::log('compras.crear', "Orden pendiente desde sugerencia #{$sugerenciaId}: {$sug['producto_nombre']}", $compraId, 'compra', [], $this->empresaId());
        $this->success("Orden de compra pendiente creada para \"{$sug['producto_nombre']}\".");
        return redirect()->to("/compras/{$compraId}");
    }

    public function descartar(int $sugerenciaId)
    {
        Db::query(
            "UPDATE ordenes_compra_sugeridas SET estado = 'descartada' WHERE sugerencia_id = ? AND empresa_id = ?",
            [$sugerenciaId, $this->empresaId()]
        );
        return redirect()->to('/compras/sugerencias');
    }
}

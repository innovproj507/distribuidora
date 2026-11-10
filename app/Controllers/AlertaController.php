<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\AlertaInventarioService;
use App\Libraries\Db;

/** Alertas de inventario (se regeneran en cada visita). Permisos por ruta: alertas.ver / resolver. */
class AlertaController extends BaseController
{
    public function index(): string
    {
        $empresaId = (int) $this->empresaId();
        (new AlertaInventarioService($empresaId))->regenerar();

        $alertas = Db::all(
            "SELECT a.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo, d.nombre AS deposito_nombre
             FROM alertas_inventario a
             LEFT JOIN productos p ON a.producto_id = p.producto_id
             LEFT JOIN depositos d ON a.deposito_id = d.deposito_id
             WHERE a.empresa_id = ? AND a.estado = 'activa'
               AND a.deposito_id IN (SELECT deposito_id FROM depositos WHERE sucursal_id = ?)
             ORDER BY FIELD(a.prioridad, 'critica', 'alta', 'media', 'baja'),
                      FIELD(a.tipo, 'lote_vencido', 'vencimiento_proximo', 'stock_minimo', 'stock_exceso', 'rotacion_lenta')",
            [$empresaId, (int) $this->sucursalId()] // solo las de la tienda activa
        );

        $resumen = ['critica' => 0, 'alta' => 0, 'media' => 0, 'baja' => 0, 'por_tipo' => []];
        foreach ($alertas as $a) {
            $resumen[$a['prioridad']]++;
            $resumen['por_tipo'][$a['tipo']] = ($resumen['por_tipo'][$a['tipo']] ?? 0) + 1;
        }

        return $this->render('inventario.alertas', [
            'page_title'    => 'Alertas de Inventario',
            'page_subtitle' => 'Revisión automática del estado del inventario',
            'alertas'       => $alertas,
            'resumen'       => $resumen,
        ]);
    }

    public function resolver(int $alertaId)
    {
        Db::query(
            "UPDATE alertas_inventario SET estado = 'resuelta', fecha_lectura = NOW() WHERE alerta_id = ? AND empresa_id = ?",
            [$alertaId, $this->empresaId()]
        );
        $this->success('Alerta marcada como resuelta.');
        return redirect()->to('/inventario/alertas');
    }

    public function resolverTodas()
    {
        $sql    = "UPDATE alertas_inventario SET estado = 'resuelta', fecha_lectura = NOW() WHERE empresa_id = ? AND estado = 'activa'
                   AND deposito_id IN (SELECT deposito_id FROM depositos WHERE sucursal_id = ?)";
        $params = [$this->empresaId(), (int) $this->sucursalId()];
        if (($tipo = (string) ($this->request->getPost('tipo') ?? '')) !== '') {
            $sql .= ' AND tipo = ?';
            $params[] = $tipo;
        }
        Db::query($sql, $params);

        $this->success('Alertas resueltas.');
        return redirect()->to('/inventario/alertas');
    }
}

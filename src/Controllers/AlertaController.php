<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AlertaInventarioService;

class AlertaController extends Controller
{
    public function index(): void
    {
        $empresaId = $this->empresaId();

        // Regenera alertas en cada visita (on-demand)
        (new AlertaInventarioService($empresaId))->regenerar();

        $alertas = Database::query(
            "SELECT a.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                    d.nombre AS deposito_nombre
             FROM alertas_inventario a
             LEFT JOIN productos p ON a.producto_id = p.producto_id
             LEFT JOIN depositos  d ON a.deposito_id  = d.deposito_id
             WHERE a.empresa_id = ? AND a.estado = 'activa'
             ORDER BY
                FIELD(a.prioridad,'critica','alta','media','baja'),
                FIELD(a.tipo,'lote_vencido','vencimiento_proximo','stock_minimo','stock_exceso','rotacion_lenta')",
            [$empresaId]
        )->fetchAll();

        $resumen = [
            'critica' => 0, 'alta' => 0, 'media' => 0, 'baja' => 0,
            'por_tipo' => [],
        ];
        foreach ($alertas as $a) {
            $resumen[$a['prioridad']]++;
            $resumen['por_tipo'][$a['tipo']] = ($resumen['por_tipo'][$a['tipo']] ?? 0) + 1;
        }

        $this->view('inventario.alertas', [
            'page_title'    => 'Alertas de Inventario',
            'page_subtitle' => 'Revisión automática del estado del inventario',
            'alertas'       => $alertas,
            'resumen'       => $resumen,
        ]);
    }

    public function resolver(int $alertaId): void
    {
        if (!$this->verifyCsrf()) return;

        Database::query(
            "UPDATE alertas_inventario SET estado = 'resuelta', fecha_lectura = NOW()
             WHERE alerta_id = ? AND empresa_id = ?",
            [$alertaId, $this->empresaId()]
        );

        $this->success('Alerta marcada como resuelta.');
        $this->redirect('/inventario/alertas');
    }

    public function resolverTodas(): void
    {
        if (!$this->verifyCsrf()) return;

        $tipo = $this->request->post('tipo', '');

        $sql    = "UPDATE alertas_inventario SET estado = 'resuelta', fecha_lectura = NOW()
                   WHERE empresa_id = ? AND estado = 'activa'";
        $params = [$this->empresaId()];

        if ($tipo !== '') {
            $sql    .= " AND tipo = ?";
            $params[] = $tipo;
        }

        Database::query($sql, $params);
        $this->success('Alertas resueltas.');
        $this->redirect('/inventario/alertas');
    }
}

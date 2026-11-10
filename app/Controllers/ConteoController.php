<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\AuditService;
use App\Libraries\Auth;
use App\Libraries\Db;

/**
 * Inventario físico (conteo cíclico) con reconciliación:
 *   guardar()        abre la sesión y toma la foto del stock del depósito
 *   contar()         se ingresan las cantidades físicas
 *   reconciliar()    muestra diferencias (conteo_detalle.diferencia es columna generada)
 *   aplicarAjustes() lleva el stock a lo contado y cierra la sesión
 * Permisos por ruta: conteos.ver / crear / reconciliar.
 */
class ConteoController extends BaseController
{
    public function index(): string
    {
        return $this->render('inventario.conteos.index', [
            'page_title'    => 'Inventario Físico',
            'page_subtitle' => 'Conteos y reconciliación de stock',
            'conteos'       => Db::all(
                "SELECT c.*, d.nombre AS deposito_nombre, u.username AS usuario_nombre
                 FROM inventario_conteos c
                 JOIN depositos d ON c.deposito_id = d.deposito_id
                 LEFT JOIN users u ON c.usuario_id = u.id
                 WHERE c.empresa_id = ? AND c.sucursal_id = ?
                 ORDER BY c.fecha_registro DESC",
                [$this->empresaId(), (int) $this->sucursalId()]
            ),
        ]);
    }

    public function crear(): string
    {
        return $this->render('inventario.conteos.crear', [
            'page_title'    => 'Nuevo Conteo',
            'page_subtitle' => 'Iniciar sesión de inventario físico',
            'depositos'     => \App\Libraries\SucursalService::depositosDe((int) $this->sucursalId()),
        ]);
    }

    public function guardar()
    {
        $depositoId = (int) $this->request->getPost('deposito_id');
        $tipo       = in_array($this->request->getPost('tipo'), ['completo', 'ciclico', 'aleatorio'], true) ? $this->request->getPost('tipo') : 'completo';
        $empresaId  = $this->empresaId();

        // Solo depósitos de la sucursal activa que el usuario puede usar
        $sucursalId = (int) $this->sucursalId();
        if (!\App\Libraries\SucursalService::puedeUsarDeposito($depositoId, $sucursalId)) {
            $this->error('Depósito no válido.');
            return redirect()->to('/inventario/conteos/nuevo');
        }

        Db::begin();
        $conteoId = Db::insert('inventario_conteos', [
            'empresa_id'   => $empresaId,
            'sucursal_id'  => $sucursalId,
            'deposito_id'  => $depositoId,
            'descripcion'  => (string) ($this->request->getPost('descripcion') ?? ''),
            'tipo'         => $tipo,
            'estado'       => 'en_proceso',
            'usuario_id'   => Auth::id(),
            'fecha_inicio' => date('Y-m-d H:i:s'),
        ]);

        // Foto del stock actual de cada producto activo del depósito
        Db::query(
            "INSERT INTO conteo_detalle (conteo_id, producto_id, cantidad_sistema, costo_unitario)
             SELECT ?, p.producto_id, i.existencia, i.costo_promedio
             FROM inventario i JOIN productos p ON i.producto_id = p.producto_id
             WHERE i.deposito_id = ? AND p.empresa_id = ? AND p.estado = 'activo'",
            [$conteoId, $depositoId, $empresaId]
        );
        $total = (int) Db::value("SELECT COUNT(*) FROM conteo_detalle WHERE conteo_id = ?", [$conteoId]);
        Db::update('inventario_conteos', ['total_productos' => $total], ['conteo_id' => $conteoId]);
        Db::commit();

        $this->success("Sesión de conteo iniciada con {$total} productos.");
        return redirect()->to("/inventario/conteos/{$conteoId}/contar");
    }

    public function contar(int $conteoId)
    {
        $conteo = $this->buscar($conteoId);
        if (!$conteo) {
            $this->error('Conteo no encontrado.');
            return redirect()->to('/inventario/conteos');
        }
        if ($conteo['estado'] === 'completado') {
            return redirect()->to("/inventario/conteos/{$conteoId}/reconciliar");
        }

        return $this->render('inventario.conteos.contar', [
            'page_title'    => 'Conteo Físico',
            'page_subtitle' => $conteo['descripcion'] ?: 'Ingrese cantidades contadas',
            'conteo'        => $conteo,
            'items'         => Db::all(
                "SELECT cd.*, p.nombre, p.codigo, p.codigo_barras
                 FROM conteo_detalle cd JOIN productos p ON cd.producto_id = p.producto_id
                 WHERE cd.conteo_id = ? ORDER BY p.nombre",
                [$conteoId]
            ),
        ]);
    }

    public function guardarConteo(int $conteoId)
    {
        $conteo = $this->buscar($conteoId);
        if (!$conteo || $conteo['estado'] !== 'en_proceso') {
            $this->error('El conteo no está abierto.');
            return redirect()->to('/inventario/conteos');
        }

        try {
            Db::begin();
            foreach ((array) ($this->request->getPost('cantidad') ?? []) as $productoId => $cantidad) {
                $qty = $cantidad === '' ? null : (float) $cantidad;
                \App\Libraries\ConteoService::anotar($conteoId, (int) $productoId, $qty);
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error($e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo guardar el conteo.');
            return redirect()->to("/inventario/conteos/{$conteoId}/contar");
        }

        $this->success('Conteo guardado.');
        return redirect()->to("/inventario/conteos/{$conteoId}/contar");
    }

    public function reconciliar(int $conteoId)
    {
        $conteo = $this->buscar($conteoId);
        if (!$conteo) {
            $this->error('Conteo no encontrado.');
            return redirect()->to('/inventario/conteos');
        }

        $items = Db::all(
            "SELECT cd.*, p.nombre, p.codigo, (cd.diferencia * cd.costo_unitario) AS impacto_costo
             FROM conteo_detalle cd JOIN productos p ON cd.producto_id = p.producto_id
             WHERE cd.conteo_id = ? AND cd.estado IN ('contado', 'ajustado')
             ORDER BY ABS(cd.diferencia) DESC",
            [$conteoId]
        );

        $totales = ['productos' => count($items), 'diferencias' => 0, 'sobrantes' => 0, 'faltantes' => 0, 'impacto' => 0.0];
        foreach ($items as $item) {
            $dif = (float) $item['diferencia'];
            if ($dif != 0) {
                $totales['diferencias']++;
                $dif > 0 ? $totales['sobrantes']++ : $totales['faltantes']++;
                $totales['impacto'] += (float) $item['impacto_costo'];
            }
        }

        return $this->render('inventario.conteos.reconciliar', [
            'page_title'    => 'Reconciliación',
            'page_subtitle' => $conteo['descripcion'] ?: 'Comparación sistema vs. conteo físico',
            'conteo'        => $conteo,
            'items'         => $items,
            'totales'       => $totales,
        ]);
    }

    public function aplicarAjustes(int $conteoId)
    {
        $conteo = $this->buscar($conteoId);
        if (!$conteo || $conteo['estado'] !== 'en_proceso') {
            $this->error('Conteo ya procesado.');
            return redirect()->to('/inventario/conteos');
        }

        try {
            Db::begin();
            $ajustadas = \App\Libraries\ConteoService::aplicarAjustes($conteoId, (int) $this->empresaId(), (int) $conteo['sucursal_id']);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error('No se pudieron aplicar los ajustes: ' . $e->getMessage());
            return redirect()->to("/inventario/conteos/{$conteoId}/reconciliar");
        }

        AuditService::log(
            'inventario.ajustar', "Ajuste de inventario por conteo #{$conteoId}: {$ajustadas} líneas ajustadas",
            $conteoId, 'conteo', ['deposito_id' => $conteo['deposito_id'], 'diferencias' => $ajustadas], $this->empresaId()
        );
        $this->success('Ajustes aplicados. Inventario actualizado.');
        return redirect()->to("/inventario/conteos/{$conteoId}/reconciliar");
    }

    public function cancelar(int $conteoId)
    {
        $conteo = $this->buscar($conteoId);
        if (!$conteo || $conteo['estado'] === 'completado') {
            $this->error($conteo ? 'Un conteo ya aplicado no se puede cancelar.' : 'Conteo no encontrado.');
            return redirect()->to('/inventario/conteos');
        }

        Db::update('inventario_conteos', ['estado' => 'cancelado'], ['conteo_id' => $conteoId]);
        $this->warning('Conteo cancelado.');
        return redirect()->to('/inventario/conteos');
    }

    private function buscar(int $conteoId): ?array
    {
        return Db::row(
            "SELECT c.*, d.nombre AS deposito_nombre FROM inventario_conteos c
             JOIN depositos d ON c.deposito_id = d.deposito_id
             WHERE c.conteo_id = ? AND c.empresa_id = ?",
            [$conteoId, $this->empresaId()]
        );
    }
}

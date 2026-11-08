<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\AuditService;
use App\Libraries\Auth;
use App\Libraries\CajaService;
use App\Libraries\Db;

/**
 * Caja con arqueo (web). /caja = la caja propia (abrir, entradas/salidas, cerrar);
 * /cajas = historial de arqueos de las sucursales que el usuario puede ver.
 */
class CajaController extends BaseController
{
    public function miCaja()
    {
        $caja = CajaService::abierta((int) Auth::id(), (int) $this->sucursalId());

        return $this->render('cajas.mi_caja', [
            'page_title'     => 'Mi Caja',
            'page_subtitle'  => $caja ? 'Caja abierta' : 'Abrir caja',
            'resumen'        => $caja ? CajaService::resumen((int) $caja['caja_id']) : null,
            'ultima'         => $caja ? null : Db::row(
                "SELECT caja_id, cerrada_en, efectivo_contado, diferencia_efectivo FROM cajas
                 WHERE usuario_id = ? AND sucursal_id = ? AND estado = 'cerrada' ORDER BY caja_id DESC LIMIT 1",
                [Auth::id(), $this->sucursalId()]
            ),
            'formas'         => CajaService::FORMAS,
            'denominaciones' => CajaService::DENOMINACIONES,
            'volver'         => $this->volverSeguro(),
        ]);
    }

    public function abrir()
    {
        try {
            $id = CajaService::abrir(
                (int) $this->empresaId(), (int) $this->sucursalId(),
                (float) ($this->request->getPost('monto_inicial') ?? 0), (string) $this->request->getPost('notas')
            );
            AuditService::log('caja.abrir', 'Caja #' . $id . ' abierta con $' . number_format((float) $this->request->getPost('monto_inicial'), 2), $id, 'caja', [], $this->empresaId());
            $this->success('Caja abierta. ¡Buenas ventas!');
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
        }
        return redirect()->to($this->volverSeguro() ?: '/caja');
    }

    public function movimiento()
    {
        $caja = CajaService::abierta((int) Auth::id(), (int) $this->sucursalId());
        if (!$caja) {
            $this->error('No tienes una caja abierta.');
            return redirect()->to('/caja');
        }
        $tipo  = $this->request->getPost('tipo') === 'salida' ? 'salida' : 'entrada';
        $monto = (float) ($this->request->getPost('monto') ?? 0);
        try {
            CajaService::movimiento((int) $caja['caja_id'], $tipo, $monto, (string) $this->request->getPost('motivo'));
            AuditService::log('caja.movimiento', ucfirst($tipo) . ' de $' . number_format($monto, 2) . ' en caja #' . $caja['caja_id'], (int) $caja['caja_id'], 'caja', [], $this->empresaId());
            $this->success(($tipo === 'entrada' ? 'Entrada' : 'Salida') . ' registrada.');
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
        }
        return redirect()->to($this->volverSeguro() ?: '/caja');
    }

    /** Cierra la caja propia, o (con cajas.gestionar) una caja que otro dejó abierta. */
    public function cerrar()
    {
        $cajaId = (int) ($this->request->getPost('caja_id') ?? 0);
        $propia = CajaService::abierta((int) Auth::id(), (int) $this->sucursalId());

        if ($cajaId && (!$propia || (int) $propia['caja_id'] !== $cajaId)) {
            if (!Auth::can('cajas.gestionar') || !$this->cajaVisible($cajaId)) {
                $this->error('No puedes cerrar esa caja.');
                return redirect()->to('/cajas');
            }
        } else {
            if (!$propia) {
                $this->error('No tienes una caja abierta.');
                return redirect()->to('/caja');
            }
            $cajaId = (int) $propia['caja_id'];
        }

        try {
            Db::begin();
            $arqueo = CajaService::cerrar(
                $cajaId,
                (array) ($this->request->getPost('declarado') ?? []),
                (array) ($this->request->getPost('billetes') ?? []),
                (string) $this->request->getPost('notas')
            );
            Db::commit();
        } catch (\RuntimeException $e) {
            Db::rollback();
            $this->error($e->getMessage());
            return redirect()->to($this->volverSeguro() ?: '/caja');
        }

        $dif = $arqueo['formas']['EFECTIVO']['diferencia'];
        AuditService::log('caja.cerrar', "Caja #{$cajaId} cerrada. Diferencia en efectivo: $" . number_format($dif, 2), $cajaId, 'caja', ['arqueo' => $arqueo['formas']], $this->empresaId());
        $dif == 0.0
            ? $this->success('Caja cerrada. El efectivo cuadra exacto.')
            : $this->warning('Caja cerrada con ' . ($dif > 0 ? 'sobrante' : 'faltante') . ' de $' . number_format(abs($dif), 2) . ' en efectivo.');
        return redirect()->to($this->volverSeguro() ?: "/cajas/{$cajaId}");
    }

    public function index(): string
    {
        $desde      = (string) ($this->request->getGet('desde') ?: date('Y-m-01'));
        $hasta      = (string) ($this->request->getGet('hasta') ?: date('Y-m-d'));
        $usuarioId  = (int) ($this->request->getGet('usuario') ?? 0);
        $sucursales = $this->sucursalesIds();
        $in         = implode(',', array_fill(0, count($sucursales), '?'));

        $where  = "c.sucursal_id IN ({$in}) AND DATE(c.abierta_en) BETWEEN ? AND ?";
        $params = array_merge($sucursales, [$desde, $hasta]);
        if ($usuarioId) {
            $where   .= ' AND c.usuario_id = ?';
            $params[] = $usuarioId;
        }

        $cajas = Db::all(
            "SELECT c.*, u.username AS cajero, b.nombre AS sucursal,
                    (SELECT COALESCE(SUM(v.total), 0) FROM ventas v WHERE v.caja_id = c.caja_id AND v.estado <> 'anulada') AS ventas_total,
                    (SELECT COUNT(*) FROM ventas v WHERE v.caja_id = c.caja_id AND v.estado <> 'anulada') AS ventas_cantidad
             FROM cajas c LEFT JOIN users u ON u.id = c.usuario_id LEFT JOIN branches b ON b.sucursal_id = c.sucursal_id
             WHERE {$where} ORDER BY c.abierta_en DESC LIMIT 300",
            $params
        );

        return $this->render('cajas.lista', [
            'page_title'    => 'Cajas',
            'page_subtitle' => 'Aperturas, cierres y arqueos',
            'cajas'         => $cajas,
            'desde'         => $desde,
            'hasta'         => $hasta,
            'usuario'       => $usuarioId,
            'usuarios'      => Db::all("SELECT DISTINCT u.id, u.username FROM cajas c JOIN users u ON u.id = c.usuario_id WHERE c.sucursal_id IN ({$in}) ORDER BY u.username", $sucursales),
            'totales'       => [
                'faltante' => array_sum(array_map(fn ($c) => min(0, (float) $c['diferencia_efectivo']), $cajas)),
                'sobrante' => array_sum(array_map(fn ($c) => max(0, (float) $c['diferencia_efectivo']), $cajas)),
                'ventas'   => array_sum(array_column($cajas, 'ventas_total')),
                'abiertas' => count(array_filter($cajas, fn ($c) => $c['estado'] === 'abierta')),
            ],
        ]);
    }

    public function detalle(int $id)
    {
        if (!$this->cajaVisible($id)) {
            $this->error('Caja no encontrada.');
            return redirect()->to(Auth::can('cajas.ver') ? '/cajas' : '/caja');
        }
        $r = CajaService::resumen($id);
        // Sin cajas.ver solo se ve la propia
        if (!Auth::can('cajas.ver') && (int) $r['caja']['usuario_id'] !== (int) Auth::id()) {
            $this->error('Solo puedes ver tus propias cajas.');
            return redirect()->to('/caja');
        }

        return $this->render('cajas.detalle', [
            'page_title'     => 'Caja #' . $id,
            'page_subtitle'  => ($r['caja']['cajero'] ?? '') . ' · ' . ($r['caja']['sucursal'] ?? ''),
            'r'              => $r,
            'arqueo'         => $r['caja']['arqueo'] ? json_decode((string) $r['caja']['arqueo'], true) : null,
            'ventas'         => Db::all(
                "SELECT v.venta_id, v.numero_factura, v.fecha, v.total, v.forma_pago, v.estado, c.nombre AS cliente
                 FROM ventas v LEFT JOIN clientes c ON c.cliente_id = v.cliente_id WHERE v.caja_id = ? ORDER BY v.fecha",
                [$id]
            ),
            'cobros'         => Db::all(
                "SELECT co.numero, co.fecha, co.monto, co.forma_pago, cl.nombre AS cliente FROM cobros co
                 LEFT JOIN clientes cl ON cl.cliente_id = co.cliente_id WHERE co.caja_id = ? ORDER BY co.fecha",
                [$id]
            ),
            'formas'         => CajaService::FORMAS,
            'denominaciones' => CajaService::DENOMINACIONES,
            'puedeCerrar'    => $r['caja']['estado'] === 'abierta' && Auth::can('cajas.gestionar'),
        ]);
    }

    // ── Auxiliares ───────────────────────────────────────────────────────────

    /** La caja es de una sucursal que el usuario puede ver. */
    private function cajaVisible(int $id): bool
    {
        $suc = (int) Db::value("SELECT sucursal_id FROM cajas WHERE caja_id = ? AND empresa_id = ?", [$id, $this->empresaId()]);
        return $suc && in_array($suc, $this->sucursalesIds(), true);
    }

    /** @return int[] */
    private function sucursalesIds(): array
    {
        return array_map('intval', array_column($this->session->get('sucursales_disponibles') ?? [], 'sucursal_id')) ?: [0];
    }

    /** ?volver=/ruta interna (para regresar al POS después de abrir caja). */
    private function volverSeguro(): string
    {
        $v = (string) ($this->request->getPost('volver') ?? $this->request->getGet('volver') ?? '');
        return preg_match('#^/[a-z0-9/_-]*$#i', $v) ? $v : '';
    }
}

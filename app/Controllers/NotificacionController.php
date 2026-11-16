<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Auth;
use App\Libraries\Db;

/**
 * GET /api/notificaciones — lo que necesita atención en la tienda activa (campana de la barra superior).
 * Se calcula en vivo, solo con lo que el usuario puede ver; cada aviso lleva a la pantalla que lo resuelve.
 */
class NotificacionController extends BaseController
{
    public function index()
    {
        $s       = (int) $this->sucursalId();
        $empresa = (int) $this->empresaId();
        $deps    = array_map('intval', array_column(Db::all("SELECT deposito_id FROM depositos WHERE sucursal_id = ?", [$s]), 'deposito_id')) ?: [0];
        $in      = implode(',', $deps);
        $avisos  = [];
        $agregar = function (int $n, string $icono, string $color, string $texto, string $url) use (&$avisos): void {
            if ($n > 0) $avisos[] = ['n' => $n, 'icono' => $icono, 'color' => $color, 'texto' => $texto, 'url' => $url];
        };

        if (Auth::can('traslados.ver') || Auth::can('traslados.recibir')) {
            $n = (int) Db::value("SELECT COUNT(*) FROM traslados WHERE estado = 'en_transito' AND deposito_destino_id IN ({$in})");
            $agregar($n, 'fa-truck', 'sky', $n === 1 ? 'traslado por recibir' : 'traslados por recibir', '/traslados');
        }
        if (Auth::can('compras.ver') || Auth::can('compras.recibir')) {
            $n = (int) Db::value("SELECT COUNT(*) FROM compras WHERE sucursal_id = ? AND estado IN ('pendiente','parcialmente_recibida')", [$s]);
            $agregar($n, 'fa-truck-loading', 'violet', $n === 1 ? 'compra pendiente de recibir' : 'compras pendientes de recibir', '/compras');
        }
        if (Auth::can('productos.ver') || Auth::can('alertas.ver')) {
            $n = (int) Db::value(
                "SELECT COUNT(DISTINCT i.producto_id) FROM inventario i JOIN productos p ON p.producto_id = i.producto_id
                 WHERE i.deposito_id IN ({$in}) AND p.estado = 'activo' AND i.minimo > 0 AND i.existencia <= i.minimo"
            );
            $agregar($n, 'fa-box-open', 'amber', $n === 1 ? 'producto en stock mínimo' : 'productos en stock mínimo', '/inventario/alertas');
            $n = (int) Db::value(
                "SELECT COUNT(DISTINCT i.producto_id) FROM inventario i JOIN productos p ON p.producto_id = i.producto_id
                 WHERE i.deposito_id IN ({$in}) AND p.estado = 'activo' AND i.existencia > 0
                   AND i.fecha_vencimiento IS NOT NULL AND i.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
            );
            $agregar($n, 'fa-hourglass-half', 'rose', $n === 1 ? 'producto vencido o por vencer' : 'productos vencidos o por vencer', '/inventario/alertas');
        }
        if (Auth::can('cajas.ver')) {
            $n = (int) Db::value("SELECT COUNT(*) FROM cajas WHERE sucursal_id = ? AND estado = 'abierta' AND DATE(abierta_en) < CURDATE()", [$s]);
            $agregar($n, 'fa-cash-register', 'rose', $n === 1 ? 'caja abierta desde otro día (sin cerrar)' : 'cajas abiertas desde otro día (sin cerrar)', '/cajas');
        }
        if (Auth::can('clientes.ver')) {
            $n = (int) Db::value("SELECT COUNT(*) FROM clientes WHERE empresa_id = ? AND estado = 'activo' AND saldo_pendiente > 0 AND saldo_pendiente > limite_credito", [$empresa]);
            $agregar($n, 'fa-user-clock', 'orange', $n === 1 ? 'cliente debe más de su límite de crédito' : 'clientes deben más de su límite de crédito', '/clientes/cuentas-por-cobrar?ver=excedidos');
        }

        return $this->json(['total' => array_sum(array_column($avisos, 'n')), 'avisos' => $avisos]);
    }
}

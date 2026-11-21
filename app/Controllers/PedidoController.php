<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Auth;
use App\Libraries\AuditService;
use App\Libraries\Db;
use App\Libraries\PedidoTiendaService;

/**
 * Hacer pedido: la tienda arma la lista de lo que necesita, la manda por WhatsApp a la persona
 * que compra en la ciudad y queda como compra pendiente para recibirla cuando llegue.
 * Misma pantalla en la web (/pedidos) y en el celular (/m/pedidos).
 */
class PedidoController extends BaseController
{
    public function index()
    {
        return $this->render('pedidos.index', $this->datos() + ['page_title' => 'Hacer pedido']);
    }

    public function movil()
    {
        if (!Auth::can('pedidos.crear')) return redirect()->to('/m/mas');
        return $this->render('movil.pedidos', $this->datos() + [
            'page_title'  => 'Hacer pedido',
            'headerTitle' => 'Hacer pedido',
            'activeTab'   => 'mas',
        ]);
    }

    private function datos(): array
    {
        $empresa  = (int) $this->empresaId();
        $sucursal = (int) $this->sucursalId();
        return [
            'catalogo'     => PedidoTiendaService::catalogo($empresa, $sucursal),
            'destinatario' => PedidoTiendaService::destinatario($empresa),
            'historial'    => PedidoTiendaService::historial($empresa, $sucursal),
            'puedeConfig'  => Auth::can('compras.crear') || Auth::can('usuarios.gestionar'),
            'usuarioId'    => (int) Auth::id(),
        ];
    }

    /** POST /pedidos/guardar {lineas, nuevos, notas, texto} → compra pendiente. */
    public function guardar()
    {
        if (!Auth::can('pedidos.crear')) return $this->json(['error' => 'Sin permiso para hacer pedidos.'], 403);
        $in = $this->jsonInput();
        try {
            Db::begin();
            $r = PedidoTiendaService::crear(
                (array) ($in['lineas'] ?? []),
                (array) ($in['nuevos'] ?? []),
                (string) ($in['notas'] ?? ''),
                (string) ($in['texto'] ?? ''),
                ['empresa_id' => $this->empresaId(), 'sucursal_id' => $this->sucursalId(), 'deposito_id' => $this->depositoId()]
            );
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            log_message('error', 'Pedido de tienda: ' . $e->getMessage());
            return $this->json(['error' => $e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo guardar el pedido.'], 422);
        }
        AuditService::log('pedidos.crear', "Pedido {$r['numero']} enviado por WhatsApp (" . count((array) ($in['lineas'] ?? [])) . ' productos)', $r['compra_id'], 'compra', [], $this->empresaId());
        $dest = PedidoTiendaService::destinatario((int) $this->empresaId());
        return $this->json([
            'success'   => true,
            'compra_id' => $r['compra_id'],
            'numero'    => $r['numero'],
            'texto'     => $r['texto'],
            'url'       => 'https://wa.me/' . $dest['wa'] . '?text=' . rawurlencode($r['texto']),
        ]);
    }

    /** GET /pedidos/{id} — líneas y texto de un pedido de la tienda (repetir / reenviar). */
    public function detalle(int $id)
    {
        if (!Auth::can('pedidos.crear')) return $this->json(['error' => 'Sin permiso para hacer pedidos.'], 403);
        $p = PedidoTiendaService::detalle($id, (int) $this->empresaId(), (int) $this->sucursalId());
        if (!$p) return $this->json(['error' => 'Pedido no encontrado en esta tienda.'], 404);
        $dest = PedidoTiendaService::destinatario((int) $this->empresaId());
        $p['url'] = 'https://wa.me/' . $dest['wa'] . '?text=' . rawurlencode((string) $p['pedido_texto']);
        return $this->json($p);
    }

    /** POST /pedidos/config {contacto, telefono} — a quién se le manda el pedido. */
    public function config()
    {
        if (!Auth::can('compras.crear') && !Auth::can('usuarios.gestionar')) {
            return $this->json(['error' => 'Solo el dueño o el gerente cambian a quién va el pedido.'], 403);
        }
        $in  = $this->jsonInput();
        $con = mb_substr(trim((string) ($in['contacto'] ?? '')), 0, 80);
        $tel = mb_substr(trim((string) ($in['telefono'] ?? '')), 0, 30);
        if ($tel !== '' && \App\Libraries\EstadoCuenta::telefonoWhatsapp($tel) === '') {
            return $this->json(['error' => 'El número no parece válido. Ej.: 6589-8898 o +507 6589-8898.'], 422);
        }
        Db::update('companies', ['pedido_contacto' => $con ?: null, 'pedido_telefono' => $tel ?: null], ['empresa_id' => $this->empresaId()]);
        AuditService::log('pedidos.config', "Pedidos se envían a {$con} ({$tel})", (int) $this->empresaId(), 'companies', [], $this->empresaId());
        return $this->json(['success' => true] + PedidoTiendaService::destinatario((int) $this->empresaId()));
    }
}

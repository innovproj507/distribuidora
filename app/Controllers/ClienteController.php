<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\AuditService;
use App\Libraries\Auth;
use App\Libraries\Db;
use App\Libraries\Fila;
use App\Libraries\NumeracionService;

/** Clientes, direcciones y cuentas por cobrar. Permisos por ruta: clientes.ver / crear / editar / eliminar. */
class ClienteController extends BaseController
{
    public function index(): string
    {
        $empresaId = $this->empresaId();
        $page      = max(1, (int) ($this->request->getGet('page') ?? 1));
        $buscar    = (string) ($this->request->getGet('buscar') ?? '');
        $estado    = (string) ($this->request->getGet('estado') ?? '');
        $tipo      = (string) ($this->request->getGet('tipo') ?? '');
        $porPagina = in_array((int) $this->request->getGet('por_pagina'), [10, 25, 50, 100], true) ? (int) $this->request->getGet('por_pagina') : (int) env('PAGINATION_PER_PAGE', 25);

        $where  = 'empresa_id = ?';
        $params = [$empresaId];
        if ($buscar !== '') {
            $where .= ' AND (nombre LIKE ? OR codigo LIKE ? OR ruc LIKE ?)';
            array_push($params, "%{$buscar}%", "%{$buscar}%", "%{$buscar}%");
        }
        if ($estado !== '') {
            $where .= ' AND estado = ?';
            $params[] = $estado;
        }
        if ($tipo !== '') {
            $where .= ' AND tipo = ?';
            $params[] = $tipo;
        }

        $total      = (int) Db::value("SELECT COUNT(*) FROM clientes WHERE {$where}", $params);
        $offset     = ($page - 1) * $porPagina;
        $pagination = paginacion($total, $page, $porPagina) + ['per_page' => $porPagina];

        return $this->render('clientes.lista', [
            'page_title'    => 'Clientes',
            'page_subtitle' => 'Gestión de clientes',
            'clientes'      => Fila::lista(Db::all("SELECT * FROM clientes WHERE {$where} ORDER BY nombre ASC LIMIT {$porPagina} OFFSET {$offset}", $params)),
            'pagination'    => $pagination,
            'stats'         => Db::row(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN estado = 'activo' THEN 1 ELSE 0 END) AS activos,
                        SUM(CASE WHEN estado = 'inactivo' THEN 1 ELSE 0 END) AS inactivos,
                        SUM(saldo_pendiente) AS saldo_total
                 FROM clientes WHERE empresa_id = ?",
                [$empresaId]
            ),
            'buscar'        => $buscar,
            'estado_filtro' => $estado,
            'tipo_filtro'   => $tipo,
            'por_pagina'    => $porPagina,
        ]);
    }

    public function crear(): string
    {
        return $this->render('clientes.crear', [
            'page_title'    => 'Nuevo Cliente',
            'page_subtitle' => 'Registrar cliente',
            'nuevo_codigo'  => NumeracionService::siguiente('cliente', $this->empresaId()),
        ]);
    }

    public function guardar()
    {
        $datos = $this->datos();
        if ($datos['nombre'] === '') {
            $this->error('El nombre es requerido.');
            return redirect()->to('/clientes/nuevo');
        }
        if ($datos['codigo'] === '') {
            $datos['codigo'] = NumeracionService::siguiente('cliente', $this->empresaId());
        }

        $id = Db::insert('clientes', $datos + [
            'empresa_id'     => $this->empresaId(),
            'estado'         => 'activo',
            'saldo'          => 0,
            'vendedor_id'    => Auth::id(),
            'fecha_registro' => date('Y-m-d H:i:s'),
        ]);
        AuditService::log('clientes.crear', "Cliente creado: {$datos['nombre']}", $id, 'cliente', [], $this->empresaId());

        $this->success('Cliente creado exitosamente.');
        return redirect()->to('/clientes');
    }

    public function detalle(int $clienteId)
    {
        $cliente = $this->buscar($clienteId);
        if (!$cliente) {
            $this->error('No autorizado.');
            return redirect()->to('/clientes');
        }

        return $this->render('clientes.detalle', [
            'page_title'    => $cliente['nombre'],
            'page_subtitle' => 'Detalle del cliente',
            'cliente'       => new Fila($cliente),
            'direcciones'   => Fila::lista(Db::all("SELECT * FROM direcciones_cliente WHERE cliente_id = ?", [$clienteId])),
            'ventas'        => Db::all("SELECT * FROM ventas WHERE cliente_id = ? AND sucursal_id = ? ORDER BY fecha DESC LIMIT 20", [$clienteId, $this->sucursalId()]),
        ]);
    }

    public function editar(int $clienteId)
    {
        $cliente = $this->buscar($clienteId);
        if (!$cliente) {
            $this->error('No autorizado.');
            return redirect()->to('/clientes');
        }

        return $this->render('clientes.editar', [
            'page_title'    => 'Editar Cliente',
            'page_subtitle' => $cliente['nombre'],
            'cliente'       => new Fila($cliente),
        ]);
    }

    public function actualizar(int $clienteId)
    {
        if (!$this->buscar($clienteId)) {
            $this->error('No autorizado.');
            return redirect()->to('/clientes');
        }

        Db::update('clientes', $this->datos(), ['cliente_id' => $clienteId]);
        $this->success('Cliente actualizado.');
        return redirect()->to("/clientes/{$clienteId}");
    }

    public function cambiarEstado(int $clienteId)
    {
        $cliente = $this->buscar($clienteId);
        if (!$cliente) {
            $this->error('No autorizado.');
            return redirect()->to('/clientes');
        }

        $nuevo = $cliente['estado'] === 'activo' ? 'inactivo' : 'activo';
        Db::update('clientes', ['estado' => $nuevo], ['cliente_id' => $clienteId]);
        $this->success("Cliente marcado como {$nuevo}.");
        return redirect()->to('/clientes');
    }

    public function eliminar(int $clienteId)
    {
        $cliente = $this->buscar($clienteId);
        if (!$cliente) {
            $this->error('No autorizado.');
            return redirect()->to('/clientes');
        }

        // Con ventas, cotizaciones o saldo no se borra (perdería el historial y la deuda)
        $usos = (int) Db::value("SELECT COUNT(*) FROM ventas WHERE cliente_id = ?", [$clienteId])
              + (int) Db::value("SELECT COUNT(*) FROM cotizaciones WHERE cliente_id = ?", [$clienteId]);
        if ($usos > 0 || (float) $cliente['saldo_pendiente'] > 0) {
            Db::update('clientes', ['estado' => 'inactivo'], ['cliente_id' => $clienteId]);
            $this->warning("El cliente tiene ventas, cotizaciones o saldo pendiente: se marcó como inactivo en lugar de eliminarse.");
            return redirect()->to('/clientes');
        }

        Db::begin();
        Db::query("DELETE FROM direcciones_cliente WHERE cliente_id = ?", [$clienteId]);
        Db::query("DELETE FROM clientes WHERE cliente_id = ?", [$clienteId]);
        Db::commit();
        AuditService::log('clientes.eliminar', "Cliente eliminado: {$cliente['nombre']}", $clienteId, 'cliente', [], $this->empresaId());

        $this->success('Cliente eliminado.');
        return redirect()->to('/clientes');
    }

    public function agregarDireccion(int $clienteId)
    {
        if (!$this->buscar($clienteId)) {
            $this->error('No autorizado.');
            return redirect()->to('/clientes');
        }

        $direccion = trim((string) ($this->request->getPost('direccion') ?? ''));
        if ($direccion === '') {
            $this->error('La dirección es requerida.');
            return redirect()->to("/clientes/{$clienteId}");
        }

        // Columnas reales de direcciones_cliente: tipo, direccion, ciudad, provincia, pais, estado
        Db::insert('direcciones_cliente', [
            'cliente_id' => $clienteId,
            'direccion'  => $direccion,
            'ciudad'     => trim((string) ($this->request->getPost('ciudad') ?? '')) ?: null,
        ]);

        $this->success('Dirección agregada.');
        return redirect()->to("/clientes/{$clienteId}");
    }

    public function eliminarDireccion(int $direccionId)
    {
        $clienteId = Db::value(
            "SELECT d.cliente_id FROM direcciones_cliente d JOIN clientes c ON c.cliente_id = d.cliente_id
             WHERE d.direccion_id = ? AND c.empresa_id = ?",
            [$direccionId, $this->empresaId()]
        );
        if (!$clienteId) {
            $this->error('Dirección no encontrada.');
            return redirect()->to('/clientes');
        }

        Db::query("DELETE FROM direcciones_cliente WHERE direccion_id = ?", [$direccionId]);
        $this->success('Dirección eliminada.');
        return redirect()->to("/clientes/{$clienteId}");
    }

    // ── JSON ─────────────────────────────────────────────────────────────────

    public function crearRapido()
    {
        $data   = $this->jsonInput();
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            return $this->json(['error' => 'El nombre es requerido'], 422);
        }

        $cliente = [
            'empresa_id'     => $this->empresaId(),
            'codigo'         => trim((string) ($data['codigo'] ?? '')) ?: NumeracionService::siguiente('cliente', $this->empresaId()),
            'nombre'         => $nombre,
            'tipo'           => $data['tipo'] ?? 'natural',
            'telefono'       => (string) ($data['telefono'] ?? ''),
            'estado'         => 'activo',
            'saldo'          => 0,
            'vendedor_id'    => Auth::id(),
            'fecha_registro' => date('Y-m-d H:i:s'),
        ];
        $cliente['cliente_id'] = Db::insert('clientes', $cliente);

        return $this->json(['success' => true, 'cliente' => $cliente]);
    }

    public function buscarAjax()
    {
        $q = (string) ($this->request->getGet('q') ?? '');
        return $this->json(['clientes' => Db::all(
            "SELECT cliente_id, codigo, nombre, ruc, telefono FROM clientes
             WHERE estado = 'activo' AND empresa_id = ? AND (nombre LIKE ? OR codigo LIKE ? OR ruc LIKE ?)
             LIMIT 20",
            [$this->empresaId(), "%{$q}%", "%{$q}%", "%{$q}%"]
        )]);
    }

    public function cuentasPorCobrar(): string
    {
        return $this->render('clientes.cuentas_cobrar', [
            'page_title'    => 'Cuentas por Cobrar',
            'page_subtitle' => 'Gestión de cartera y créditos',
            'cuentas'       => Db::all(
                "SELECT c.cliente_id, c.nombre, c.ruc, c.telefono, c.limite_credito AS cupo_credito, c.saldo_pendiente,
                        (SELECT MAX(fecha) FROM ventas WHERE cliente_id = c.cliente_id AND forma_pago = 'CREDITO') AS ultima_venta_credito
                 FROM clientes c
                 WHERE c.saldo_pendiente > 0 AND c.empresa_id = ?
                 ORDER BY c.saldo_pendiente DESC",
                [$this->empresaId()]
            ),
            'stats' => Db::row(
                "SELECT SUM(CASE WHEN saldo_pendiente > 0 THEN 1 ELSE 0 END) AS total_deudores,
                        SUM(saldo_pendiente) AS total_deuda, SUM(limite_credito) AS total_cupo
                 FROM clientes WHERE empresa_id = ?",
                [$this->empresaId()]
            ),
        ]);
    }

    private function buscar(int $clienteId): ?array
    {
        return Db::row("SELECT * FROM clientes WHERE cliente_id = ? AND empresa_id = ?", [$clienteId, $this->empresaId()]);
    }

    private function datos(): array
    {
        $post = fn (string $k, string $def = '') => trim((string) ($this->request->getPost($k) ?? $def));
        return [
            'codigo'         => $post('codigo'),
            'nombre'         => $post('nombre'),
            'tipo'           => $post('tipo', 'natural'),
            'ruc'            => $post('ruc'),
            'dv'             => $post('dv'),
            'direccion'      => $post('direccion'),
            'telefono'       => $post('telefono'),
            'email'          => $post('email'),
            'limite_credito' => (float) $post('limite_credito', '0'),
            'dias_credito'   => (int) $post('dias_credito', '0'),
            'itbms'          => $post('itbms', 'SI') === 'NO' ? 0.00 : 7.00,
        ];
    }
}

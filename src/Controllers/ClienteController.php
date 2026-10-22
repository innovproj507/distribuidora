<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Models\Cliente;
use App\Models\DireccionCliente;

class ClienteController extends Controller
{
    public function index(): void
    {
        $page = (int) ($this->request->get('page', '1'));
        $buscar = $this->request->get('buscar', '');
        $estado = $this->request->get('estado', '');
        $tipo = $this->request->get('tipo', '');
        $perPage = (int) ($this->request->get('por_pagina', $_ENV['PAGINATION_PER_PAGE'] ?? 25));

        $where = 'empresa_id = ?';
        $params = [$this->empresaId()];

        if ($buscar) {
            $where .= " AND (nombre LIKE ? OR codigo LIKE ? OR ruc LIKE ?)";
            $params[] = "%{$buscar}%";
            $params[] = "%{$buscar}%";
            $params[] = "%{$buscar}%";
        }
        if ($estado) {
            $where .= " AND estado = ?";
            $params[] = $estado;
        }
        if ($tipo) {
            $where .= " AND tipo = ?";
            $params[] = $tipo;
        }

        $pagination = Cliente::paginate($perPage, $page, $where, $params, 'nombre ASC');
        $stats = Cliente::getStats();

        $this->view('clientes.lista', [
            'page_title' => 'Clientes',
            'page_subtitle' => 'Gestión de clientes',
            'clientes' => $pagination['items'],
            'pagination' => $pagination,
            'stats' => $stats,
            'buscar' => $buscar,
            'estado_filtro' => $estado,
            'tipo_filtro' => $tipo,
            'por_pagina' => $perPage,
        ]);
    }

    public function crear(): void
    {
        $this->view('clientes.crear', [
            'page_title' => 'Nuevo Cliente',
            'page_subtitle' => 'Registrar cliente',
            'nuevo_codigo' => $this->nextClientCode(),
        ]);
    }

    public function guardar(): void
    {
        if (!$this->verifyCsrf()) return;

        $codigo = trim($this->request->post('codigo', ''));
        if (empty($codigo)) {
            $codigo = $this->nextClientCode();
        }

        $data = [
            'empresa_id' => $this->empresaId(),
            'codigo' => $codigo,
            'nombre' => $this->request->post('nombre', ''),
            'tipo' => $this->request->post('tipo', 'natural'),
            'ruc' => $this->request->post('ruc', ''),
            'dv' => $this->request->post('dv', ''),
            'direccion' => $this->request->post('direccion', ''),
            'telefono' => $this->request->post('telefono', ''),
            'email' => $this->request->post('email', ''),
            'limite_credito' => $this->request->post('limite_credito', '0'),
            'dias_credito' => $this->request->post('dias_credito', '0'),
            'itbms' => ($this->request->post('itbms', 'SI') === 'NO') ? 0.00 : 7.00,
            'estado' => 'activo',
            'saldo' => 0,
            'vendedor_id' => Auth::id(),
            'fecha_registro' => date('Y-m-d H:i:s'),
        ];

        $cliente = Cliente::create($data);
        $this->success('Cliente creado exitosamente.');
        $this->redirect('/clientes');
    }

    public function detalle(int $cliente_id): void
    {
        $cliente = Cliente::findOrFail($cliente_id);
        if ($cliente->empresa_id != $this->empresaId()) {
            $this->error('No autorizado.');
            $this->redirect('/clientes');
            return;
        }

        $direcciones = DireccionCliente::where('cliente_id', $cliente_id);

        $ventas = Database::query(
            "SELECT * FROM ventas WHERE cliente_id = ? AND sucursal_id = ? ORDER BY fecha DESC LIMIT 20",
            [$cliente_id, $this->sucursalId()]
        )->fetchAll();

        $this->view('clientes.detalle', [
            'page_title' => $cliente->nombre,
            'page_subtitle' => 'Detalle del cliente',
            'cliente' => $cliente,
            'direcciones' => $direcciones,
            'ventas' => $ventas,
        ]);
    }

    public function editar(int $cliente_id): void
    {
        $cliente = Cliente::findOrFail($cliente_id);
        if ($cliente->empresa_id != $this->empresaId()) {
            $this->error('No autorizado.');
            $this->redirect('/clientes');
            return;
        }
        $this->view('clientes.editar', [
            'page_title' => 'Editar Cliente',
            'page_subtitle' => $cliente->nombre,
            'cliente' => $cliente,
        ]);
    }

    public function actualizar(int $cliente_id): void
    {
        if (!$this->verifyCsrf()) return;
        $cliente = Cliente::findOrFail($cliente_id);
        if ($cliente->empresa_id != $this->empresaId()) {
            $this->error('No autorizado.');
            $this->redirect('/clientes');
            return;
        }

        $cliente->update([
            'codigo' => $this->request->post('codigo', ''),
            'nombre' => $this->request->post('nombre', ''),
            'tipo' => $this->request->post('tipo', 'natural'),
            'ruc' => $this->request->post('ruc', ''),
            'dv' => $this->request->post('dv', ''),
            'direccion' => $this->request->post('direccion', ''),
            'telefono' => $this->request->post('telefono', ''),
            'email' => $this->request->post('email', ''),
            'limite_credito' => $this->request->post('limite_credito', '0'),
            'dias_credito' => $this->request->post('dias_credito', '0'),
            'itbms' => ($this->request->post('itbms', 'SI') === 'NO') ? 0.00 : 7.00,
        ]);

        $this->success('Cliente actualizado.');
        $this->redirect("/clientes/{$cliente_id}");
    }

    public function cambiarEstado(int $cliente_id): void
    {
        if (!$this->verifyCsrf()) return;
        $cliente = Cliente::findOrFail($cliente_id);
        if ($cliente->empresa_id != $this->empresaId()) {
            $this->error('No autorizado.');
            $this->redirect('/clientes');
            return;
        }
        $nuevoEstado = $cliente->estado === 'activo' ? 'inactivo' : 'activo';
        $cliente->update(['estado' => $nuevoEstado]);
        $this->success("Cliente marcado como {$nuevoEstado}.");
        $this->redirect('/clientes');
    }

    public function eliminar(int $cliente_id): void
    {
        if (!$this->verifyCsrf()) return;
        $cliente = Cliente::findOrFail($cliente_id);
        if ($cliente->empresa_id != $this->empresaId()) {
            $this->error('No autorizado.');
            $this->redirect('/clientes');
            return;
        }
        Cliente::destroy($cliente_id);
        $this->success('Cliente eliminado.');
        $this->redirect('/clientes');
    }

    public function agregarDireccion(int $cliente_id): void
    {
        if (!$this->verifyCsrf()) return;

        DireccionCliente::create([
            'cliente_id' => $cliente_id,
            'direccion' => $this->request->post('direccion', ''),
            'telefono' => $this->request->post('telefono', ''),
            'celular' => $this->request->post('celular', ''),
            'email' => $this->request->post('email', ''),
            'principal' => $this->request->post('principal', '0'),
        ]);

        $this->success('Dirección agregada.');
        $this->redirect("/clientes/{$cliente_id}");
    }

    public function eliminarDireccion(int $direccion_id): void
    {
        if (!$this->verifyCsrf()) return;
        $dir = DireccionCliente::findOrFail($direccion_id);
        $clienteId = $dir->cliente_id;
        $dir->delete();
        $this->success('Dirección eliminada.');
        $this->redirect("/clientes/{$clienteId}");
    }

    /**
     * AJAX: Crear cliente rápido
     */
    public function crearRapido(): void
    {
        if (!$this->request->isPost()) {
            $this->json(['error' => 'Método no permitido'], 405);
            return;
        }

        $data = $this->request->json();

        $cliente = Cliente::create([
            'empresa_id' => $this->empresaId(),
            'codigo' => $data['codigo'] ?? '',
            'nombre' => $data['nombre'] ?? '',
            'tipo' => $data['tipo'] ?? 'natural',
            'telefono' => $data['telefono'] ?? '',
            'estado' => 'activo',
            'saldo' => 0,
            'vendedor_id' => Auth::id(),
            'fecha_registro' => date('Y-m-d H:i:s'),
        ]);

        $this->json([
            'success' => true,
            'cliente' => $cliente->toArray(),
        ]);
    }

    private function nextClientCode(): string
    {
        return \App\Services\NumeracionService::siguiente('cliente', $this->empresaId());
    }

    public function buscarAjax(): void
    {
        $q = $this->request->get('q', '');
        $clientes = Database::query(
            "SELECT cliente_id, codigo, nombre, ruc, telefono FROM clientes 
             WHERE estado = 'activo' AND empresa_id = ? AND (nombre LIKE ? OR codigo LIKE ? OR ruc LIKE ?)
             LIMIT 20",
            [$this->empresaId(), "%{$q}%", "%{$q}%", "%{$q}%"]
        )->fetchAll();

        $this->json(['clientes' => $clientes]);
    }

    /**
     * Reporte de Cuentas por Cobrar
     */
    public function cuentasPorCobrar(): void
    {
        $sucursalId = $this->sucursalId();
        
        $cuentas = Database::query(
            "SELECT c.cliente_id, c.nombre, c.ruc, c.telefono, c.limite_credito as cupo_credito, c.saldo_pendiente,
                    (SELECT MAX(fecha) FROM ventas WHERE cliente_id = c.cliente_id AND forma_pago = 'CREDITO') as ultima_venta_credito
             FROM clientes c
             WHERE c.saldo_pendiente > 0 AND c.empresa_id = ?
             ORDER BY c.saldo_pendiente DESC",
            [$this->empresaId()]
        )->fetchAll();

        $stats = Database::query(
            "SELECT COUNT(*) as total_deudores, SUM(saldo_pendiente) as total_deuda, SUM(limite_credito) as total_cupo
             FROM clientes WHERE empresa_id = ?",
            [$this->empresaId()]
        )->fetch();

        $this->view('clientes.cuentas_cobrar', [
            'page_title' => 'Cuentas por Cobrar',
            'page_subtitle' => 'Gestión de cartera y créditos',
            'cuentas' => $cuentas,
            'stats' => $stats
        ]);
    }
}

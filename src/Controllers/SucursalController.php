<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Sucursal;

class SucursalController extends Controller
{
    public function index(): void
    {
        $empresaId = $this->empresaId();
        
        $sucursales = Database::query(
            "SELECT * FROM branches WHERE empresa_id = ? ORDER BY nombre",
            [$empresaId]
        )->fetchAll();

        $this->view('sucursales.index', [
            'page_title' => 'Sucursales',
            'page_subtitle' => 'Gestión de sucursales de la empresa',
            'sucursales' => $sucursales,
        ]);
    }

    public function crear(): void
    {
        $this->view('sucursales.crear', [
            'page_title' => 'Nueva Sucursal',
            'page_subtitle' => 'Agregar una nueva sucursal',
        ]);
    }

    public function guardar(): void
    {
        if (!$this->verifyCsrf()) return;

        Sucursal::create([
            'empresa_id' => $this->empresaId(),
            'codigo' => $this->request->post('codigo', ''),
            'nombre' => $this->request->post('nombre', ''),
            'direccion' => $this->request->post('direccion', ''),
            'telefono' => $this->request->post('telefono', ''),
            'email' => $this->request->post('email', ''),
            'es_principal' => $this->request->post('es_principal', '0'),
            'activa' => $this->request->post('activa', '1'),
        ]);

        $this->success('Sucursal creada exitosamente.');
        $this->redirect('/configuracion/sucursales');
    }

    public function editar(int $sucursal_id): void
    {
        $sucursal = Sucursal::findOrFail($sucursal_id);
        if ($sucursal->empresa_id != $this->empresaId()) {
            $this->error('No autorizado.');
            $this->redirect('/configuracion/sucursales');
            return;
        }

        $this->view('sucursales.editar', [
            'page_title' => 'Editar Sucursal',
            'page_subtitle' => $sucursal->nombre,
            'sucursal' => $sucursal,
        ]);
    }

    public function actualizar(int $sucursal_id): void
    {
        if (!$this->verifyCsrf()) return;
        
        $sucursal = Sucursal::findOrFail($sucursal_id);
        if ($sucursal->empresa_id != $this->empresaId()) {
            $this->error('No autorizado.');
            $this->redirect('/configuracion/sucursales');
            return;
        }

        $sucursal->update([
            'codigo' => $this->request->post('codigo', ''),
            'nombre' => $this->request->post('nombre', ''),
            'direccion' => $this->request->post('direccion', ''),
            'telefono' => $this->request->post('telefono', ''),
            'email' => $this->request->post('email', ''),
            'es_principal' => $this->request->post('es_principal', '0'),
            'activa' => $this->request->post('activa', '0'),
        ]);

        $this->success('Sucursal actualizada exitosamente.');
        $this->redirect('/configuracion/sucursales');
    }

    public function eliminar(int $sucursal_id): void
    {
        if (!$this->verifyCsrf()) return;
        
        $sucursal = Sucursal::findOrFail($sucursal_id);
        if ($sucursal->empresa_id != $this->empresaId()) {
            $this->error('No autorizado.');
            $this->redirect('/configuracion/sucursales');
            return;
        }
        
        // Prevent deletion if only one branch left or logic rules applies (could be added)

        Sucursal::destroy($sucursal_id);
        $this->success('Sucursal eliminada.');
        $this->redirect('/configuracion/sucursales');
    }
}

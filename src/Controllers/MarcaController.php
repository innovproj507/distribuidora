<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

class MarcaController extends Controller
{
    public function index(): void
    {
        $empresaId = $this->empresaId();
        $page      = max(1, (int) $this->request->get('page', '1'));
        $perPage   = \in_array((int) $this->request->get('por_pagina', '25'), [10, 25, 50, 100], true)
                        ? (int) $this->request->get('por_pagina', '25') : 25;
        $buscar    = trim($this->request->get('buscar', ''));

        $where  = 'm.empresa_id = ?';
        $params = [$empresaId];

        if ($buscar !== '') {
            $where   .= ' AND m.nombre LIKE ?';
            $params[] = "%{$buscar}%";
        }

        $offset = ($page - 1) * $perPage;

        $total = (int) Database::query(
            "SELECT COUNT(*) AS total FROM marcas m WHERE {$where}",
            $params
        )->fetch()['total'];

        $marcas = Database::query(
            "SELECT m.*, COUNT(p.producto_id) AS total_productos
             FROM marcas m
             LEFT JOIN productos p ON p.marca = m.nombre AND p.empresa_id = m.empresa_id
             WHERE {$where}
             GROUP BY m.marca_id
             ORDER BY m.nombre ASC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        )->fetchAll();

        $totalPages = max(1, (int) ceil($total / $perPage));

        $stats = Database::query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN (
                    SELECT COUNT(*) FROM productos p
                    WHERE p.marca = m.nombre AND p.empresa_id = m.empresa_id
                ) > 0 THEN 1 ELSE 0 END) AS con_productos,
                (SELECT COUNT(*) FROM productos
                 WHERE empresa_id = ? AND marca IS NOT NULL AND marca != '') AS total_asignados
             FROM marcas m WHERE m.empresa_id = ?",
            [$empresaId, $empresaId]
        )->fetch();

        $this->view('inventario.marcas', [
            'page_title'    => 'Marcas',
            'page_subtitle' => 'Gestión de marcas de productos',
            'marcas'        => $marcas,
            'stats'         => $stats,
            'pagination'    => [
                'total'         => $total,
                'per_page'      => $perPage,
                'current_page'  => $page,
                'total_pages'   => $totalPages,
                'has_previous'  => $page > 1,
                'has_next'      => $page < $totalPages,
                'previous_page' => max(1, $page - 1),
                'next_page'     => min($totalPages, $page + 1),
            ],
        ]);
    }

    public function guardar(): void
    {
        if (!$this->verifyCsrf()) return;

        $nombre = trim($this->request->post('nombre', ''));
        if ($nombre === '') {
            $this->error('El nombre de la marca es requerido.');
            $this->redirect('/inventario/marcas');
            return;
        }

        try {
            Database::query(
                "INSERT INTO marcas (empresa_id, nombre) VALUES (?, ?)",
                [$this->empresaId(), $nombre]
            );
            $this->success("Marca \"{$nombre}\" creada.");
        } catch (\Exception $e) {
            $this->error('Ya existe una marca con ese nombre.');
        }

        $this->redirect('/inventario/marcas');
    }

    public function actualizar(int $marca_id): void
    {
        if (!$this->verifyCsrf()) return;

        $nombre = trim($this->request->post('nombre', ''));
        if ($nombre === '') {
            $this->error('El nombre es requerido.');
            $this->redirect('/inventario/marcas');
            return;
        }

        $marca = Database::query(
            "SELECT * FROM marcas WHERE marca_id = ? AND empresa_id = ?",
            [$marca_id, $this->empresaId()]
        )->fetch();

        if (!$marca) {
            $this->error('Marca no encontrada.');
            $this->redirect('/inventario/marcas');
            return;
        }

        // Actualizar el campo marca en productos también
        Database::query(
            "UPDATE productos SET marca = ? WHERE marca = ? AND empresa_id = ?",
            [$nombre, $marca['nombre'], $this->empresaId()]
        );
        Database::query(
            "UPDATE marcas SET nombre = ? WHERE marca_id = ?",
            [$nombre, $marca_id]
        );

        $this->success('Marca actualizada.');
        $this->redirect('/inventario/marcas');
    }

    public function eliminar(int $marca_id): void
    {
        if (!$this->verifyCsrf()) return;

        $marca = Database::query(
            "SELECT * FROM marcas WHERE marca_id = ? AND empresa_id = ?",
            [$marca_id, $this->empresaId()]
        )->fetch();

        if (!$marca) {
            $this->error('Marca no encontrada.');
            $this->redirect('/inventario/marcas');
            return;
        }

        // Limpiar campo marca en productos
        Database::query(
            "UPDATE productos SET marca = NULL WHERE marca = ? AND empresa_id = ?",
            [$marca['nombre'], $this->empresaId()]
        );
        Database::query("DELETE FROM marcas WHERE marca_id = ?", [$marca_id]);

        $this->success('Marca eliminada.');
        $this->redirect('/inventario/marcas');
    }
}

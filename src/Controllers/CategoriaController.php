<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\CategoriaProducto;

class CategoriaController extends Controller
{
    public function index(): void
    {
        if (!$this->requirePermission('categorias.ver')) return;

        $empresaId = $this->empresaId();
        $page      = max(1, (int) $this->request->get('page', '1'));
        $perPage   = \in_array((int) $this->request->get('por_pagina', '25'), [10, 25, 50, 100], true)
                        ? (int) $this->request->get('por_pagina', '25') : 25;
        $buscar    = trim($this->request->get('buscar', ''));
        $padreId   = $this->request->get('padre_id', '');

        $where  = 'c.empresa_id = ?';
        $params = [$empresaId];

        if ($buscar !== '') {
            $where   .= ' AND (c.nombre LIKE ? OR c.descripcion LIKE ?)';
            $params[] = "%{$buscar}%";
            $params[] = "%{$buscar}%";
        }
        if ($padreId === '0') {
            $where .= ' AND c.padre_id IS NULL';
        } elseif ($padreId !== '') {
            $where   .= ' AND c.padre_id = ?';
            $params[] = (int) $padreId;
        }

        $offset = ($page - 1) * $perPage;

        $total = (int) Database::query(
            "SELECT COUNT(*) AS total FROM categorias_productos c WHERE {$where}",
            $params
        )->fetch()['total'];

        $categorias = Database::query(
            "SELECT c.*,
                    p.nombre AS padre_nombre,
                    (SELECT COUNT(*) FROM categorias_productos s WHERE s.padre_id = c.categoria_id) AS total_subcategorias,
                    (SELECT COUNT(*) FROM productos pr WHERE pr.categoria_id = c.categoria_id AND pr.empresa_id = c.empresa_id) AS total_productos
             FROM categorias_productos c
             LEFT JOIN categorias_productos p ON p.categoria_id = c.padre_id
             WHERE {$where}
             ORDER BY c.nombre ASC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        )->fetchAll();

        $totalPages = max(1, (int) ceil($total / $perPage));

        // Stats globales (sin filtros aplicados)
        $stats = Database::query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN padre_id IS NULL THEN 1 ELSE 0 END) AS raiz,
                SUM(CASE WHEN padre_id IS NOT NULL THEN 1 ELSE 0 END) AS subcategorias,
                SUM(CASE WHEN (SELECT COUNT(*) FROM productos pr WHERE pr.categoria_id = c.categoria_id) > 0 THEN 1 ELSE 0 END) AS con_productos
             FROM categorias_productos c
             WHERE c.empresa_id = ?",
            [$empresaId]
        )->fetch();

        $todasCategorias = CategoriaProducto::where(['empresa_id' => $empresaId], 'nombre ASC');

        $this->view('categorias.lista', [
            'page_title'      => 'Categorías',
            'page_subtitle'   => 'Gestión de categorías de productos',
            'categorias'      => $categorias,
            'todasCategorias' => $todasCategorias,
            'stats'           => $stats,
            'pagination'      => [
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

    public function crear(): void
    {
        if (!$this->requirePermission('categorias.crear')) return;

        $categorias = CategoriaProducto::where(['empresa_id' => $this->empresaId()], 'nombre ASC');
        $this->view('categorias.crear', [
            'page_title' => 'Nueva Categoría',
            'page_subtitle' => 'Crear categoría de productos',
            'categorias' => $categorias,
        ]);
    }

    public function guardar(): void
    {
        if (!$this->requirePermission('categorias.crear')) return;
        if (!$this->verifyCsrf()) return;

        $data = [
            'empresa_id' => $this->empresaId(),
            'nombre' => $this->request->post('nombre', ''),
            'padre_id' => $this->request->post('padre_id') ?: null,
            'descripcion' => $this->request->post('descripcion', ''),
        ];

        $imagen = $this->handleUpload('imagen', 'categorias');
        if ($imagen) $data['imagen'] = $imagen;

        CategoriaProducto::create($data);
        $this->success('Categoría creada exitosamente.');
        $this->redirect('/inventario/categorias');
    }

    public function editar(int $categoria_id): void
    {
        if (!$this->requirePermission('categorias.editar')) return;

        $empresaId = $this->empresaId();
        $categoria = CategoriaProducto::whereFirst([
            'categoria_id' => $categoria_id,
            'empresa_id' => $empresaId
        ]);

        if (!$categoria) {
            $this->error('Categoría no encontrada o no autorizada.');
            $this->redirect('/inventario/categorias');
            return;
        }

        $categorias = CategoriaProducto::where(['empresa_id' => $empresaId], 'nombre ASC');

        $this->view('categorias.editar', [
            'page_title' => 'Editar Categoría',
            'page_subtitle' => $categoria->nombre,
            'categoria' => $categoria,
            'categorias' => $categorias,
            'action' => "/inventario/categorias/{$categoria->categoria_id}/editar"
        ]);
    }

    public function actualizar(int $categoria_id): void
    {
        if (!$this->requirePermission('categorias.editar')) return;
        if (!$this->verifyCsrf()) return;

        $empresaId = $this->empresaId();
        $categoria = CategoriaProducto::whereFirst([
            'categoria_id' => $categoria_id,
            'empresa_id' => $empresaId
        ]);

        if (!$categoria) {
            $this->error('Acción no permitida.');
            $this->redirect('/inventario/categorias');
            return;
        }

        $data = [
            'nombre' => $this->request->post('nombre', ''),
            'padre_id' => $this->request->post('padre_id') ?: null,
            'descripcion' => $this->request->post('descripcion', ''),
        ];

        $imagen = $this->handleUpload('imagen', 'categorias');
        if ($imagen) $data['imagen'] = $imagen;

        $categoria->update($data);
        $this->success('Categoría actualizada.');
        $this->redirect('/inventario/categorias');
    }

    public function eliminar(int $categoria_id): void
    {
        if (!$this->requirePermission('categorias.eliminar')) return;
        if (!$this->verifyCsrf()) return;
        
        $empresaId = $this->empresaId();
        $categoria = CategoriaProducto::whereFirst([
            'categoria_id' => $categoria_id,
            'empresa_id' => $empresaId
        ]);

        if (!$categoria) {
            $this->error('Acción no permitida.');
            $this->redirect('/inventario/categorias');
            return;
        }

        $categoria->delete();
        $this->success('Categoría eliminada.');
        $this->redirect('/inventario/categorias');
    }
}

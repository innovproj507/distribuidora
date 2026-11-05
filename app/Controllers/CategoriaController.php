<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\AuditService;
use App\Libraries\Db;
use App\Libraries\Fila;

/** Permisos por ruta: categorias.ver / crear / editar / eliminar. */
class CategoriaController extends BaseController
{
    public function index(): string
    {
        $empresaId = $this->empresaId();
        $page      = max(1, (int) ($this->request->getGet('page') ?? 1));
        $porPagina = in_array((int) $this->request->getGet('por_pagina'), [10, 25, 50, 100], true) ? (int) $this->request->getGet('por_pagina') : 25;
        $buscar    = trim((string) ($this->request->getGet('buscar') ?? ''));
        $padreId   = (string) ($this->request->getGet('padre_id') ?? '');

        $where  = 'c.empresa_id = ?';
        $params = [$empresaId];
        if ($buscar !== '') {
            $where .= ' AND (c.nombre LIKE ? OR c.descripcion LIKE ?)';
            array_push($params, "%{$buscar}%", "%{$buscar}%");
        }
        if ($padreId === '0') {
            $where .= ' AND c.padre_id IS NULL';
        } elseif ($padreId !== '') {
            $where .= ' AND c.padre_id = ?';
            $params[] = (int) $padreId;
        }

        $total  = (int) Db::value("SELECT COUNT(*) FROM categorias_productos c WHERE {$where}", $params);
        $offset = ($page - 1) * $porPagina;

        $pagination = paginacion($total, $page, $porPagina) + ['per_page' => $porPagina];

        return $this->render('categorias.lista', [
            'page_title'    => 'Categorías',
            'page_subtitle' => 'Gestión de categorías de productos',
            'categorias'    => Db::all(
                "SELECT c.*, p.nombre AS padre_nombre,
                        (SELECT COUNT(*) FROM categorias_productos s WHERE s.padre_id = c.categoria_id) AS total_subcategorias,
                        (SELECT COUNT(*) FROM productos pr WHERE pr.categoria_id = c.categoria_id AND pr.empresa_id = c.empresa_id) AS total_productos
                 FROM categorias_productos c
                 LEFT JOIN categorias_productos p ON p.categoria_id = c.padre_id
                 WHERE {$where}
                 ORDER BY c.nombre ASC
                 LIMIT {$porPagina} OFFSET {$offset}",
                $params
            ),
            'todasCategorias' => $this->todas(),
            'stats'           => Db::row(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN padre_id IS NULL THEN 1 ELSE 0 END) AS raiz,
                        SUM(CASE WHEN padre_id IS NOT NULL THEN 1 ELSE 0 END) AS subcategorias,
                        SUM(CASE WHEN (SELECT COUNT(*) FROM productos pr WHERE pr.categoria_id = c.categoria_id) > 0 THEN 1 ELSE 0 END) AS con_productos
                 FROM categorias_productos c WHERE c.empresa_id = ?",
                [$empresaId]
            ),
            'pagination' => $pagination,
        ]);
    }

    public function crear(): string
    {
        return $this->render('categorias.crear', [
            'page_title'    => 'Nueva Categoría',
            'page_subtitle' => 'Crear categoría de productos',
            'categorias'    => $this->todas(),
        ]);
    }

    public function guardar()
    {
        $datos = $this->datos();
        if ($datos['nombre'] === '') {
            $this->error('El nombre es requerido.');
            return redirect()->to('/inventario/categorias/nueva');
        }

        $id = Db::insert('categorias_productos', $datos + ['empresa_id' => $this->empresaId()]);
        AuditService::log('inventario.categoria.crear', "Categoría creada: {$datos['nombre']}", $id, 'categoria', [], $this->empresaId());

        $this->success('Categoría creada exitosamente.');
        return redirect()->to('/inventario/categorias');
    }

    public function editar(int $categoriaId)
    {
        $categoria = $this->buscar($categoriaId);
        if (!$categoria) {
            $this->error('Categoría no encontrada o no autorizada.');
            return redirect()->to('/inventario/categorias');
        }

        return $this->render('categorias.editar', [
            'page_title'    => 'Editar Categoría',
            'page_subtitle' => $categoria['nombre'],
            'categoria'     => new Fila($categoria),
            'categorias'    => $this->todas(),
            'action'        => "/inventario/categorias/{$categoriaId}/editar",
        ]);
    }

    public function actualizar(int $categoriaId)
    {
        if (!$this->buscar($categoriaId)) {
            $this->error('Acción no permitida.');
            return redirect()->to('/inventario/categorias');
        }

        $datos = $this->datos();
        if ((int) $datos['padre_id'] === $categoriaId) {
            $datos['padre_id'] = null; // una categoría no puede ser su propio padre
        }

        Db::update('categorias_productos', $datos, ['categoria_id' => $categoriaId]);
        $this->success('Categoría actualizada.');
        return redirect()->to('/inventario/categorias');
    }

    public function eliminar(int $categoriaId)
    {
        $categoria = $this->buscar($categoriaId);
        if (!$categoria) {
            $this->error('Acción no permitida.');
            return redirect()->to('/inventario/categorias');
        }

        // productos.categoria_id no tiene FK: se dejan sin categoría en vez de apuntar a un id inexistente
        Db::query("UPDATE productos SET categoria_id = NULL WHERE categoria_id = ? AND empresa_id = ?", [$categoriaId, $this->empresaId()]);
        Db::query("DELETE FROM categorias_productos WHERE categoria_id = ?", [$categoriaId]);
        AuditService::log('inventario.categoria.eliminar', "Categoría eliminada: {$categoria['nombre']}", $categoriaId, 'categoria', [], $this->empresaId());

        $this->success('Categoría eliminada.');
        return redirect()->to('/inventario/categorias');
    }

    private function buscar(int $categoriaId): ?array
    {
        return Db::row("SELECT * FROM categorias_productos WHERE categoria_id = ? AND empresa_id = ?", [$categoriaId, $this->empresaId()]);
    }

    private function todas(): array
    {
        return Fila::lista(Db::all("SELECT * FROM categorias_productos WHERE empresa_id = ? ORDER BY nombre", [$this->empresaId()]));
    }

    private function datos(): array
    {
        $datos = [
            'nombre'      => trim((string) ($this->request->getPost('nombre') ?? '')),
            'padre_id'    => $this->request->getPost('padre_id') ? (int) $this->request->getPost('padre_id') : null,
            'descripcion' => (string) ($this->request->getPost('descripcion') ?? ''),
        ];
        if ($imagen = $this->subirImagen('imagen', 'categorias')) {
            $datos['imagen'] = $imagen;
        }
        return $datos;
    }
}

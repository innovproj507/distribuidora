<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Db;

/**
 * Marcas. productos.marca guarda el nombre (no un id), así que renombrar o borrar
 * una marca también actualiza los productos que la usan.
 */
class MarcaController extends BaseController
{
    public function index(): string
    {
        $empresaId = $this->empresaId();
        $page      = max(1, (int) ($this->request->getGet('page') ?? 1));
        $porPagina = in_array((int) $this->request->getGet('por_pagina'), [10, 25, 50, 100], true) ? (int) $this->request->getGet('por_pagina') : 25;
        $buscar    = trim((string) ($this->request->getGet('buscar') ?? ''));

        $where  = 'm.empresa_id = ?';
        $params = [$empresaId];
        if ($buscar !== '') {
            $where .= ' AND m.nombre LIKE ?';
            $params[] = "%{$buscar}%";
        }

        $total  = (int) Db::value("SELECT COUNT(*) FROM marcas m WHERE {$where}", $params);
        $offset = ($page - 1) * $porPagina;

        return $this->render('inventario.marcas', [
            'page_title'    => 'Marcas',
            'page_subtitle' => 'Gestión de marcas de productos',
            'marcas'        => Db::all(
                "SELECT m.*, COUNT(p.producto_id) AS total_productos
                 FROM marcas m
                 LEFT JOIN productos p ON p.marca = m.nombre AND p.empresa_id = m.empresa_id
                 WHERE {$where}
                 GROUP BY m.marca_id
                 ORDER BY m.nombre ASC
                 LIMIT {$porPagina} OFFSET {$offset}",
                $params
            ),
            'stats' => Db::row(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN (SELECT COUNT(*) FROM productos p WHERE p.marca = m.nombre AND p.empresa_id = m.empresa_id) > 0 THEN 1 ELSE 0 END) AS con_productos,
                        (SELECT COUNT(*) FROM productos WHERE empresa_id = ? AND marca IS NOT NULL AND marca != '') AS total_asignados
                 FROM marcas m WHERE m.empresa_id = ?",
                [$empresaId, $empresaId]
            ),
            'pagination' => paginacion($total, $page, $porPagina) + ['per_page' => $porPagina],
        ]);
    }

    public function guardar()
    {
        $nombre = trim((string) ($this->request->getPost('nombre') ?? ''));
        if ($nombre === '') {
            $this->error('El nombre de la marca es requerido.');
            return redirect()->to('/inventario/marcas');
        }

        if (Db::value("SELECT 1 FROM marcas WHERE empresa_id = ? AND nombre = ?", [$this->empresaId(), $nombre])) {
            $this->error('Ya existe una marca con ese nombre.');
        } else {
            Db::insert('marcas', ['empresa_id' => $this->empresaId(), 'nombre' => $nombre]);
            $this->success("Marca \"{$nombre}\" creada.");
        }

        return redirect()->to('/inventario/marcas');
    }

    public function actualizar(int $marcaId)
    {
        $nombre = trim((string) ($this->request->getPost('nombre') ?? ''));
        $marca  = $this->buscar($marcaId);

        if ($nombre === '' || !$marca) {
            $this->error($nombre === '' ? 'El nombre es requerido.' : 'Marca no encontrada.');
            return redirect()->to('/inventario/marcas');
        }

        Db::begin();
        Db::query("UPDATE productos SET marca = ? WHERE marca = ? AND empresa_id = ?", [$nombre, $marca['nombre'], $this->empresaId()]);
        Db::update('marcas', ['nombre' => $nombre], ['marca_id' => $marcaId]);
        Db::commit();

        $this->success('Marca actualizada.');
        return redirect()->to('/inventario/marcas');
    }

    public function eliminar(int $marcaId)
    {
        $marca = $this->buscar($marcaId);
        if (!$marca) {
            $this->error('Marca no encontrada.');
            return redirect()->to('/inventario/marcas');
        }

        Db::begin();
        Db::query("UPDATE productos SET marca = NULL WHERE marca = ? AND empresa_id = ?", [$marca['nombre'], $this->empresaId()]);
        Db::query("DELETE FROM marcas WHERE marca_id = ?", [$marcaId]);
        Db::commit();

        $this->success('Marca eliminada.');
        return redirect()->to('/inventario/marcas');
    }

    private function buscar(int $marcaId): ?array
    {
        return Db::row("SELECT * FROM marcas WHERE marca_id = ? AND empresa_id = ?", [$marcaId, $this->empresaId()]);
    }
}

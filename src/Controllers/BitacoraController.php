<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

class BitacoraController extends Controller
{
    public function index(): void
    {
        if (!$this->requirePermission('bitacora.ver')) return;

        $page    = max(1, (int) $this->request->get('page', '1'));
        $perPage = 50;
        $offset  = ($page - 1) * $perPage;

        $modulo   = $this->request->get('modulo', '');
        $usuario  = $this->request->get('usuario', '');
        $desde    = $this->request->get('desde', '');
        $hasta    = $this->request->get('hasta', '');

        $where  = ['a.empresa_id = ?'];
        $params = [$this->empresaId()];

        if ($modulo !== '') {
            $where[]  = 'a.modulo = ?';
            $params[] = $modulo;
        }
        if ($usuario !== '') {
            $where[]  = 'a.username LIKE ?';
            $params[] = "%{$usuario}%";
        }
        if ($desde !== '') {
            $where[]  = 'DATE(a.fecha_registro) >= ?';
            $params[] = $desde;
        }
        if ($hasta !== '') {
            $where[]  = 'DATE(a.fecha_registro) <= ?';
            $params[] = $hasta;
        }

        $whereClause = implode(' AND ', $where);

        $total = (int) Database::query(
            "SELECT COUNT(*) FROM audit_log a WHERE {$whereClause}",
            $params
        )->fetchColumn();

        $logs = Database::query(
            "SELECT a.* FROM audit_log a
             WHERE {$whereClause}
             ORDER BY a.fecha_registro DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        )->fetchAll();

        $modulos = Database::query(
            "SELECT DISTINCT modulo FROM audit_log WHERE empresa_id = ? ORDER BY modulo",
            [$this->empresaId()]
        )->fetchAll(\PDO::FETCH_COLUMN);

        $totalPaginas = (int) ceil($total / $perPage);

        $this->view('bitacora.index', [
            'page_title'    => 'Bitácora de Auditoría',
            'page_subtitle' => 'Registro de acciones del sistema',
            'logs'          => $logs,
            'modulos'       => $modulos,
            'total'         => $total,
            'pagina'        => $page,
            'total_paginas' => $totalPaginas,
            'filtros'       => compact('modulo', 'usuario', 'desde', 'hasta'),
        ]);
    }
}

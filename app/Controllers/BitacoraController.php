<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Db;

/** Bitácora de auditoría (audit_log). Permiso por ruta: bitacora.ver. */
class BitacoraController extends BaseController
{
    private const POR_PAGINA = 50;

    public function index(): string
    {
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $filtros = [
            'modulo'  => (string) ($this->request->getGet('modulo') ?? ''),
            'usuario' => (string) ($this->request->getGet('usuario') ?? ''),
            'desde'   => (string) ($this->request->getGet('desde') ?? ''),
            'hasta'   => (string) ($this->request->getGet('hasta') ?? ''),
        ];

        $where  = ['a.empresa_id = ?'];
        $params = [$this->empresaId()];
        if ($filtros['modulo'] !== '')  { $where[] = 'a.modulo = ?';                  $params[] = $filtros['modulo']; }
        if ($filtros['usuario'] !== '') { $where[] = 'a.username LIKE ?';             $params[] = "%{$filtros['usuario']}%"; }
        if ($filtros['desde'] !== '')   { $where[] = 'DATE(a.fecha_registro) >= ?';   $params[] = $filtros['desde']; }
        if ($filtros['hasta'] !== '')   { $where[] = 'DATE(a.fecha_registro) <= ?';   $params[] = $filtros['hasta']; }
        $whereSql = implode(' AND ', $where);

        $total  = (int) Db::value("SELECT COUNT(*) FROM audit_log a WHERE {$whereSql}", $params);
        $offset = ($page - 1) * self::POR_PAGINA;

        return $this->render('bitacora.index', [
            'page_title'    => 'Bitácora de Auditoría',
            'page_subtitle' => 'Registro de acciones del sistema',
            'logs'          => Db::all("SELECT a.* FROM audit_log a WHERE {$whereSql} ORDER BY a.fecha_registro DESC, a.log_id DESC LIMIT " . self::POR_PAGINA . " OFFSET {$offset}", $params),
            'modulos'       => array_column(Db::all("SELECT DISTINCT modulo FROM audit_log WHERE empresa_id = ? ORDER BY modulo", [$this->empresaId()]), 'modulo'),
            'total'         => $total,
            'pagina'        => $page,
            'total_paginas' => (int) ceil($total / self::POR_PAGINA),
            'filtros'       => $filtros,
        ]);
    }
}

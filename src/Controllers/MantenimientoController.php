<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Models\MantSoftware;
use App\Models\MantTarea;
use App\Models\MantEjecucion;
use App\Services\AuditService;

class MantenimientoController extends Controller
{
    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function index(): void
    {
        $empresaId = $this->empresaId();

        $stats = [
            'software' => MantSoftware::getStats($empresaId),
            'tareas'   => MantTarea::getStats($empresaId),
        ];

        $proximas = Database::query(
            "SELECT t.tarea_id, t.nombre, t.frecuencia, t.prioridad,
                    t.proxima_ejecucion, t.duracion_estimada,
                    s.nombre AS software_nombre, s.tipo AS software_tipo
             FROM mant_tareas t
             JOIN mant_software s ON t.software_id = s.software_id
             WHERE t.empresa_id = ? AND t.activa = 1
               AND (t.proxima_ejecucion IS NULL
                    OR t.proxima_ejecucion <= DATE_ADD(CURDATE(), INTERVAL 14 DAY))
             ORDER BY t.proxima_ejecucion ASC
             LIMIT 10",
            [$empresaId]
        )->fetchAll();

        $recientes = Database::query(
            "SELECT e.ejecucion_id, e.fecha_ejecucion, e.estado, e.duracion_real, e.notas,
                    t.nombre AS tarea_nombre,
                    s.nombre AS software_nombre,
                    u.username AS usuario
             FROM mant_ejecuciones e
             JOIN mant_tareas t ON e.tarea_id = t.tarea_id
             JOIN mant_software s ON t.software_id = s.software_id
             LEFT JOIN users u ON e.usuario_id = u.id
             WHERE e.empresa_id = ?
             ORDER BY e.fecha_ejecucion DESC
             LIMIT 10",
            [$empresaId]
        )->fetchAll();

        $this->view('mantenimiento.index', [
            'page_title'    => 'Mantenimiento',
            'page_subtitle' => 'Plan preventivo de software',
            'stats'         => $stats,
            'proximas'      => $proximas,
            'recientes'     => $recientes,
        ]);
    }

    // ── Software ──────────────────────────────────────────────────────────────

    public function software(): void
    {
        $empresaId = $this->empresaId();
        $page    = max(1, (int) $this->request->get('page', '1'));
        $buscar  = $this->request->get('buscar', '');
        $tipo    = $this->request->get('tipo', '');
        $estado  = $this->request->get('estado', '');
        $perPage = (int) ($_ENV['PAGINATION_PER_PAGE'] ?? 25);

        $where  = 's.empresa_id = ?';
        $params = [$empresaId];

        if ($buscar) {
            $where .= ' AND (s.nombre LIKE ? OR s.proveedor LIKE ? OR s.servidor LIKE ?)';
            $params[] = "%{$buscar}%";
            $params[] = "%{$buscar}%";
            $params[] = "%{$buscar}%";
        }
        if ($tipo) {
            $where .= ' AND s.tipo = ?';
            $params[] = $tipo;
        }
        if ($estado) {
            $where .= ' AND s.estado = ?';
            $params[] = $estado;
        }

        $total  = (int) Database::query(
            "SELECT COUNT(*) as t FROM mant_software s WHERE {$where}", $params
        )->fetch()['t'];

        $offset = ($page - 1) * $perPage;
        $rows   = Database::query(
            "SELECT s.*,
                    (SELECT COUNT(*) FROM mant_tareas t
                     WHERE t.software_id = s.software_id AND t.activa = 1) AS tareas_activas
             FROM mant_software s
             WHERE {$where}
             ORDER BY s.nombre ASC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        )->fetchAll();

        $totalPages = max(1, (int) ceil($total / $perPage));
        $pagination = [
            'items'        => $rows,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'total_pages'  => $totalPages,
            'has_previous' => $page > 1,
            'has_next'     => $page < $totalPages,
            'previous_page' => max(1, $page - 1),
            'next_page'    => min($totalPages, $page + 1),
        ];

        $this->view('mantenimiento.software.lista', [
            'page_title'    => 'Software',
            'page_subtitle' => 'Registro de sistemas y aplicaciones',
            'software_list' => $rows,
            'pagination'    => $pagination,
            'stats'         => MantSoftware::getStats($empresaId),
            'buscar'        => $buscar,
            'tipo_filtro'   => $tipo,
            'estado_filtro' => $estado,
        ]);
    }

    public function crearSoftware(): void
    {
        $this->view('mantenimiento.software.form', [
            'page_title'    => 'Nuevo Software',
            'page_subtitle' => 'Registrar sistema o aplicación',
            'software'      => null,
            'action'        => '/mantenimiento/software/nuevo',
        ]);
    }

    public function guardarSoftware(): void
    {
        if (!$this->verifyCsrf()) return;

        $sw = MantSoftware::create([
            'empresa_id'                 => $this->empresaId(),
            'nombre'                     => trim($this->request->post('nombre', '')),
            'version'                    => trim($this->request->post('version', '')),
            'proveedor'                  => trim($this->request->post('proveedor', '')),
            'tipo'                       => $this->request->post('tipo', 'aplicacion'),
            'servidor'                   => trim($this->request->post('servidor', '')),
            'fecha_instalacion'          => $this->request->post('fecha_instalacion') ?: null,
            'fecha_vencimiento_licencia' => $this->request->post('fecha_vencimiento_licencia') ?: null,
            'contacto_soporte'           => trim($this->request->post('contacto_soporte', '')),
            'notas'                      => trim($this->request->post('notas', '')),
            'estado'                     => $this->request->post('estado', 'activo'),
            'fecha_registro'             => date('Y-m-d H:i:s'),
        ]);

        AuditService::log('mantenimiento.software.crear', "Software registrado: {$sw->nombre}", $sw->software_id, 'mant_software');
        $this->success('Software registrado exitosamente.');
        $this->redirect('/mantenimiento/software');
    }

    public function editarSoftware(int $software_id): void
    {
        $software = MantSoftware::find($software_id);
        if (!$software || (int) $software->empresa_id !== $this->empresaId()) {
            $this->error('Software no encontrado.');
            $this->redirect('/mantenimiento/software');
            return;
        }

        $this->view('mantenimiento.software.form', [
            'page_title'    => 'Editar Software',
            'page_subtitle' => 'Actualizar información del sistema',
            'software'      => $software,
            'action'        => "/mantenimiento/software/{$software_id}/editar",
        ]);
    }

    public function actualizarSoftware(int $software_id): void
    {
        if (!$this->verifyCsrf()) return;

        $software = MantSoftware::find($software_id);
        if (!$software || (int) $software->empresa_id !== $this->empresaId()) {
            $this->error('Software no encontrado.');
            $this->redirect('/mantenimiento/software');
            return;
        }

        $software->update([
            'nombre'                     => trim($this->request->post('nombre', '')),
            'version'                    => trim($this->request->post('version', '')),
            'proveedor'                  => trim($this->request->post('proveedor', '')),
            'tipo'                       => $this->request->post('tipo', 'aplicacion'),
            'servidor'                   => trim($this->request->post('servidor', '')),
            'fecha_instalacion'          => $this->request->post('fecha_instalacion') ?: null,
            'fecha_vencimiento_licencia' => $this->request->post('fecha_vencimiento_licencia') ?: null,
            'contacto_soporte'           => trim($this->request->post('contacto_soporte', '')),
            'notas'                      => trim($this->request->post('notas', '')),
            'estado'                     => $this->request->post('estado', 'activo'),
        ]);

        AuditService::log('mantenimiento.software.actualizar', "Software actualizado: {$software->nombre}", $software_id, 'mant_software');
        $this->success('Software actualizado exitosamente.');
        $this->redirect('/mantenimiento/software');
    }

    public function eliminarSoftware(int $software_id): void
    {
        if (!$this->verifyCsrf()) return;

        $software = MantSoftware::find($software_id);
        if (!$software || (int) $software->empresa_id !== $this->empresaId()) {
            $this->error('Software no encontrado.');
            $this->redirect('/mantenimiento/software');
            return;
        }

        $nombre = $software->nombre;
        $software->delete();

        AuditService::log('mantenimiento.software.eliminar', "Software eliminado: {$nombre}", $software_id, 'mant_software');
        $this->success('Software eliminado.');
        $this->redirect('/mantenimiento/software');
    }

    // ── Tareas ────────────────────────────────────────────────────────────────

    public function tareas(): void
    {
        $empresaId = $this->empresaId();
        $page    = max(1, (int) $this->request->get('page', '1'));
        $buscar  = $this->request->get('buscar', '');
        $swId    = $this->request->get('software', '');
        $frec    = $this->request->get('frecuencia', '');
        $prior   = $this->request->get('prioridad', '');
        $perPage = (int) ($_ENV['PAGINATION_PER_PAGE'] ?? 25);

        $where  = 't.empresa_id = ?';
        $params = [$empresaId];

        if ($buscar) {
            $where .= ' AND t.nombre LIKE ?';
            $params[] = "%{$buscar}%";
        }
        if ($swId) {
            $where .= ' AND t.software_id = ?';
            $params[] = $swId;
        }
        if ($frec) {
            $where .= ' AND t.frecuencia = ?';
            $params[] = $frec;
        }
        if ($prior) {
            $where .= ' AND t.prioridad = ?';
            $params[] = $prior;
        }

        $total = (int) Database::query(
            "SELECT COUNT(*) as t FROM mant_tareas t WHERE {$where}", $params
        )->fetch()['t'];

        $offset = ($page - 1) * $perPage;
        $tareas = Database::query(
            "SELECT t.*, s.nombre AS software_nombre, s.tipo AS software_tipo
             FROM mant_tareas t
             JOIN mant_software s ON t.software_id = s.software_id
             WHERE {$where}
             ORDER BY
                 CASE WHEN t.proxima_ejecucion IS NULL THEN 1 ELSE 0 END,
                 t.proxima_ejecucion ASC,
                 CASE t.prioridad WHEN 'alta' THEN 1 WHEN 'media' THEN 2 ELSE 3 END
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        )->fetchAll();

        $totalPages = max(1, (int) ceil($total / $perPage));
        $pagination = [
            'items'        => $tareas,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'total_pages'  => $totalPages,
            'has_previous' => $page > 1,
            'has_next'     => $page < $totalPages,
            'previous_page' => max(1, $page - 1),
            'next_page'    => min($totalPages, $page + 1),
        ];

        $softwareList = Database::query(
            "SELECT software_id, nombre FROM mant_software
             WHERE empresa_id = ? AND estado = 'activo' ORDER BY nombre",
            [$empresaId]
        )->fetchAll();

        $this->view('mantenimiento.tareas.lista', [
            'page_title'        => 'Plan Preventivo',
            'page_subtitle'     => 'Tareas de mantenimiento programadas',
            'tareas'            => $tareas,
            'pagination'        => $pagination,
            'stats'             => MantTarea::getStats($empresaId),
            'software_list'     => $softwareList,
            'buscar'            => $buscar,
            'software_filtro'   => $swId,
            'frecuencia_filtro' => $frec,
            'prioridad_filtro'  => $prior,
        ]);
    }

    public function crearTarea(): void
    {
        $softwareList = Database::query(
            "SELECT software_id, nombre FROM mant_software
             WHERE empresa_id = ? AND estado = 'activo' ORDER BY nombre",
            [$this->empresaId()]
        )->fetchAll();

        $this->view('mantenimiento.tareas.form', [
            'page_title'    => 'Nueva Tarea',
            'page_subtitle' => 'Registrar tarea preventiva',
            'tarea'         => null,
            'action'        => '/mantenimiento/tareas/nueva',
            'software_list' => $softwareList,
        ]);
    }

    public function guardarTarea(): void
    {
        if (!$this->verifyCsrf()) return;

        $tarea = MantTarea::create([
            'software_id'       => (int) $this->request->post('software_id', 0),
            'empresa_id'        => $this->empresaId(),
            'nombre'            => trim($this->request->post('nombre', '')),
            'descripcion'       => trim($this->request->post('descripcion', '')),
            'frecuencia'        => $this->request->post('frecuencia', 'mensual'),
            'prioridad'         => $this->request->post('prioridad', 'media'),
            'responsable'       => trim($this->request->post('responsable', '')),
            'duracion_estimada' => max(1, (int) $this->request->post('duracion_estimada', 30)),
            'activa'            => 1,
            'proxima_ejecucion' => $this->request->post('proxima_ejecucion') ?: null,
            'fecha_registro'    => date('Y-m-d H:i:s'),
        ]);

        AuditService::log('mantenimiento.tarea.crear', "Tarea registrada: {$tarea->nombre}", $tarea->tarea_id, 'mant_tareas');
        $this->success('Tarea registrada exitosamente.');
        $this->redirect('/mantenimiento/tareas');
    }

    public function editarTarea(int $tarea_id): void
    {
        $tarea = MantTarea::find($tarea_id);
        if (!$tarea || (int) $tarea->empresa_id !== $this->empresaId()) {
            $this->error('Tarea no encontrada.');
            $this->redirect('/mantenimiento/tareas');
            return;
        }

        $softwareList = Database::query(
            "SELECT software_id, nombre FROM mant_software
             WHERE empresa_id = ? AND estado = 'activo' ORDER BY nombre",
            [$this->empresaId()]
        )->fetchAll();

        $this->view('mantenimiento.tareas.form', [
            'page_title'    => 'Editar Tarea',
            'page_subtitle' => 'Actualizar tarea preventiva',
            'tarea'         => $tarea,
            'action'        => "/mantenimiento/tareas/{$tarea_id}/editar",
            'software_list' => $softwareList,
        ]);
    }

    public function actualizarTarea(int $tarea_id): void
    {
        if (!$this->verifyCsrf()) return;

        $tarea = MantTarea::find($tarea_id);
        if (!$tarea || (int) $tarea->empresa_id !== $this->empresaId()) {
            $this->error('Tarea no encontrada.');
            $this->redirect('/mantenimiento/tareas');
            return;
        }

        $tarea->update([
            'software_id'       => (int) $this->request->post('software_id', 0),
            'nombre'            => trim($this->request->post('nombre', '')),
            'descripcion'       => trim($this->request->post('descripcion', '')),
            'frecuencia'        => $this->request->post('frecuencia', 'mensual'),
            'prioridad'         => $this->request->post('prioridad', 'media'),
            'responsable'       => trim($this->request->post('responsable', '')),
            'duracion_estimada' => max(1, (int) $this->request->post('duracion_estimada', 30)),
            'activa'            => (int) $this->request->post('activa', 1),
            'proxima_ejecucion' => $this->request->post('proxima_ejecucion') ?: null,
        ]);

        AuditService::log('mantenimiento.tarea.actualizar', "Tarea actualizada: {$tarea->nombre}", $tarea_id, 'mant_tareas');
        $this->success('Tarea actualizada exitosamente.');
        $this->redirect('/mantenimiento/tareas');
    }

    public function ejecutarTarea(int $tarea_id): void
    {
        if (!$this->verifyCsrf()) return;

        $tarea = MantTarea::find($tarea_id);
        if (!$tarea || (int) $tarea->empresa_id !== $this->empresaId()) {
            $this->error('Tarea no encontrada.');
            $this->redirect('/mantenimiento/tareas');
            return;
        }

        $ahora    = date('Y-m-d H:i:s');
        $hoy      = date('Y-m-d');
        $estado   = $this->request->post('estado', 'completado');
        $duracion = (int) $this->request->post('duracion_real', 0) ?: null;
        $notas    = trim($this->request->post('notas', ''));

        MantEjecucion::create([
            'tarea_id'        => $tarea_id,
            'empresa_id'      => $this->empresaId(),
            'usuario_id'      => Auth::id(),
            'fecha_ejecucion' => $ahora,
            'duracion_real'   => $duracion,
            'estado'          => $estado,
            'notas'           => $notas,
            'fecha_registro'  => $ahora,
        ]);

        $proxima = match ($tarea->frecuencia) {
            'diaria'     => date('Y-m-d', strtotime('+1 day')),
            'semanal'    => date('Y-m-d', strtotime('+7 days')),
            'mensual'    => date('Y-m-d', strtotime('+1 month')),
            'trimestral' => date('Y-m-d', strtotime('+3 months')),
            'semestral'  => date('Y-m-d', strtotime('+6 months')),
            'anual'      => date('Y-m-d', strtotime('+1 year')),
            default      => null,
        };

        $tarea->update([
            'ultima_ejecucion'  => $hoy,
            'proxima_ejecucion' => $proxima,
        ]);

        AuditService::log('mantenimiento.tarea.ejecutar', "Tarea ejecutada ({$estado}): {$tarea->nombre}", $tarea_id, 'mant_tareas');
        $this->success("Ejecución registrada. Próxima: " . ($proxima ? date('d/m/Y', strtotime($proxima)) : 'sin programar') . ".");
        $this->redirect('/mantenimiento/tareas');
    }

    public function eliminarTarea(int $tarea_id): void
    {
        if (!$this->verifyCsrf()) return;

        $tarea = MantTarea::find($tarea_id);
        if (!$tarea || (int) $tarea->empresa_id !== $this->empresaId()) {
            $this->error('Tarea no encontrada.');
            $this->redirect('/mantenimiento/tareas');
            return;
        }

        $nombre = $tarea->nombre;
        $tarea->delete();

        AuditService::log('mantenimiento.tarea.eliminar', "Tarea eliminada: {$nombre}", $tarea_id, 'mant_tareas');
        $this->success('Tarea eliminada.');
        $this->redirect('/mantenimiento/tareas');
    }
}

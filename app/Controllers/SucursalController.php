<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\AuditService;
use App\Libraries\Db;
use App\Libraries\SucursalService;

/**
 * Sucursales: listado con ubicación, alta/edición (cada sucursal nueva nace con su depósito
 * principal y el catálogo completo en existencia 0), y cambio de sucursal/depósito activo.
 */
class SucursalController extends BaseController
{
    public function index(): string
    {
        $sucursales = Db::all(
            "SELECT b.*,
                    (SELECT COUNT(*) FROM depositos d WHERE d.sucursal_id = b.sucursal_id AND d.estado = 'activo') AS depositos,
                    (SELECT COALESCE(SUM(i.existencia), 0) FROM inventario i JOIN depositos d ON d.deposito_id = i.deposito_id
                      WHERE d.sucursal_id = b.sucursal_id) AS stock,
                    (SELECT COUNT(*) FROM ventas v WHERE v.sucursal_id = b.sucursal_id AND v.estado <> 'anulada'
                      AND v.fecha >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS ventas_mes,
                    (SELECT COALESCE(SUM(v.total), 0) FROM ventas v WHERE v.sucursal_id = b.sucursal_id AND v.estado <> 'anulada'
                      AND v.fecha >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS total_mes
             FROM branches b
             WHERE b.empresa_id = ?
             ORDER BY b.activa DESC, b.es_principal DESC, b.nombre",
            [$this->empresaId()]
        );
        foreach ($sucursales as &$s) {
            $s['enlace_mapa'] = SucursalService::enlaceMapa($s);
        }
        unset($s);

        return $this->render('sucursales.lista', [
            'page_title'    => 'Sucursales',
            'page_subtitle' => 'Tiendas, ubicación y serie de documentos',
            'sucursales'    => $sucursales,
            'numeracion'    => \App\Libraries\NumeracionService::configuracionCompleta($this->empresaId()),
        ]);
    }

    public function crear(): string
    {
        return $this->formulario([], '/sucursales/nueva');
    }

    public function guardar()
    {
        $empresaId = (int) $this->empresaId();
        try {
            $datos = SucursalService::normalizar($this->request->getPost(), $empresaId);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return redirect()->to('/sucursales/nueva')->withInput();
        }

        try {
            Db::begin();
            $id = SucursalService::crear($datos, $empresaId);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            log_message('error', 'Crear sucursal: ' . $e->getMessage());
            $this->error('No se pudo crear la sucursal.');
            return redirect()->to('/sucursales/nueva')->withInput();
        }

        AuditService::log('sucursal_crear', "Sucursal {$datos['codigo']} - {$datos['nombre']}", $id, 'sucursal', $datos);
        $this->success("Sucursal {$datos['nombre']} creada con su depósito principal.");
        return redirect()->to('/sucursales');
    }

    public function editar(int $id)
    {
        $sucursal = $this->buscar($id);
        if (!$sucursal) {
            $this->error('Sucursal no encontrada.');
            return redirect()->to('/sucursales');
        }
        return $this->formulario($sucursal, "/sucursales/{$id}/editar");
    }

    public function actualizar(int $id)
    {
        $sucursal = $this->buscar($id);
        if (!$sucursal) {
            $this->error('Sucursal no encontrada.');
            return redirect()->to('/sucursales');
        }

        try {
            $datos = SucursalService::normalizar($this->request->getPost(), (int) $this->empresaId(), $id);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return redirect()->to("/sucursales/{$id}/editar")->withInput();
        }

        // Cambiar el prefijo con documentos emitidos abre una serie nueva; se avisa.
        $avisoSerie = ($sucursal['prefijo'] ?? null) !== $datos['prefijo']
            && Db::value("SELECT COUNT(*) FROM ventas WHERE sucursal_id = ?", [$id]) > 0;

        Db::update('branches', $datos, ['sucursal_id' => $id]);
        AuditService::log('sucursal_editar', "Sucursal {$datos['codigo']} - {$datos['nombre']}", $id, 'sucursal', $datos);

        $this->success('Sucursal actualizada.');
        if ($avisoSerie) {
            $this->warning('Cambiaste el prefijo de serie: las próximas facturas y cotizaciones de esta sucursal empiezan una numeración nueva.');
        }
        return redirect()->to('/sucursales');
    }

    /** Activa/desactiva. La principal no se puede desactivar. */
    public function toggleActiva(int $id)
    {
        $sucursal = $this->buscar($id);
        if (!$sucursal) {
            $this->error('Sucursal no encontrada.');
        } elseif ((int) $sucursal['es_principal'] === 1) {
            $this->error('La sucursal principal no se puede desactivar.');
        } else {
            $nueva = (int) $sucursal['activa'] === 1 ? 0 : 1;
            Db::update('branches', ['activa' => $nueva], ['sucursal_id' => $id]);
            AuditService::log($nueva ? 'sucursal_activar' : 'sucursal_desactivar', "Sucursal {$sucursal['nombre']}", $id, 'sucursal');
            $this->success($nueva ? 'Sucursal activada.' : 'Sucursal desactivada. Sus usuarios pasan a otra sucursal disponible.');
        }
        return redirect()->to('/sucursales');
    }

    // ── Cambio de contexto (barra superior y PWA) ────────────────────────────

    public function cambiar()
    {
        $id  = (int) $this->request->getPost('sucursal_id');
        $ctx = SucursalService::contexto($id);

        if ($ctx === null || (int) $ctx['sucursal_actual']['sucursal_id'] !== $id) {
            $this->error('No tienes acceso a esa sucursal.');
        } else {
            $this->session->set($ctx);
            SucursalService::recordar($id, (int) $ctx['empresa_actual']['empresa_id']);
            $this->success('Ahora trabajas en ' . $ctx['sucursal_actual']['nombre'] . '.');
        }
        return redirect()->to($this->volver());
    }

    public function cambiarDeposito()
    {
        $id        = (int) $this->request->getPost('deposito_id');
        $depositos = SucursalService::depositosDe((int) $this->sucursalId());
        $elegido   = null;
        foreach ($depositos as $d) {
            if ((int) $d['deposito_id'] === $id) {
                $elegido = $d;
            }
        }

        if ($elegido === null) {
            $this->error('Ese depósito no pertenece a tu sucursal o no tienes acceso.');
        } else {
            $this->session->set('deposito_actual', $elegido);
            $this->success('Depósito activo: ' . $elegido['nombre'] . '.');
        }
        return redirect()->to($this->volver());
    }

    // ── Auxiliares ───────────────────────────────────────────────────────────

    private function formulario(array $sucursal, string $action): string
    {
        $nueva = empty($sucursal);
        return $this->render('sucursales.form', [
            'page_title'    => $nueva ? 'Nueva sucursal' : 'Editar sucursal',
            'page_subtitle' => 'Sucursales',
            'sucursal'      => $sucursal,
            'action'        => $action,
            'nueva'         => $nueva,
            'cfg_factura'   => \App\Libraries\NumeracionService::config('factura', $this->empresaId()),
        ]);
    }

    private function buscar(int $id): ?array
    {
        return Db::row("SELECT * FROM branches WHERE sucursal_id = ? AND empresa_id = ?", [$id, $this->empresaId()]);
    }

    /** Vuelve a la página de origen si es del mismo sitio; si no, al dashboard. */
    private function volver(): string
    {
        $ref  = (string) $this->request->getServer('HTTP_REFERER');
        $host = parse_url($ref, PHP_URL_HOST);
        if ($ref === '' || $host !== $this->request->getUri()->getHost()) {
            return '/';
        }
        $ruta = (string) (parse_url($ref, PHP_URL_PATH) ?? '/');
        // Una pantalla con id (/ventas/venta/15, /inventario/depositos/3/editar) puede ser de la
        // otra sucursal: se vuelve a la sección (/ventas, /inventario)
        if (preg_match('#/\d+(/|$)#', $ruta)) {
            $ruta = '/' . explode('/', trim($ruta, '/'))[0];
        }
        return $ruta === '' ? '/' : $ruta;
    }
}

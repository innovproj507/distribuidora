<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\AuditService;
use App\Libraries\Db;
use App\Libraries\Fila;
use App\Libraries\NumeracionService;

/** Configuración de la empresa, nomenclatura de documentos y páginas informativas. */
class ConfiguracionController extends BaseController
{
    private const TIPOS_NUMERACION = ['cotizacion', 'factura', 'cliente', 'proveedor'];

    public function sistema(): string
    {
        $empresaId = $this->empresaId();
        $empresa   = Db::row("SELECT * FROM companies WHERE empresa_id = ?", [$empresaId]) ?? [];

        return $this->render('configuracion.sistema', [
            'page_title'    => 'Configuración',
            'page_subtitle' => 'Configuración del sistema',
            'empresa'       => new Fila($empresa),
            'sucursales'    => Fila::lista(Db::all("SELECT * FROM branches WHERE empresa_id = ?", [$empresaId])),
            'stats'         => [
                'usuarios_activos' => (int) Db::value("SELECT COUNT(*) FROM users WHERE is_active = 1"),
                'total_ventas'     => (int) Db::value("SELECT COUNT(*) FROM ventas WHERE empresa_id = ?", [$empresaId]),
                'total_productos'  => (int) Db::value("SELECT COUNT(*) FROM productos WHERE empresa_id = ?", [$empresaId]),
                'total_clientes'   => (int) Db::value("SELECT COUNT(*) FROM clientes WHERE empresa_id = ?", [$empresaId]),
            ],
            'system'        => $this->estadoServidor(),
            'configuracion' => [
                'nombre_negocio'            => $empresa['nombre_comercial'] ?? '',
                'moneda'                    => $empresa['moneda'] ?? 'USD',
                'impuesto'                  => (float) env('ITBMS_RATE', 0.07),
                'serie_facturacion'         => '001-001',
                'resolucion_dgi'            => 'N/A',
                'impresora_predeterminada'  => 'ticket',
                'impresora_ticket'          => 'POS-58',
                'impresora_a4'              => 'Virtual Printer',
                'backup_automatico'         => true,
                'hora_backup'               => '23:00',
                'sincronizacion_automatica' => true,
                'pos_desc_cajero'           => (int) ($empresa['pos_desc_cajero'] ?? 10),
                'pos_desc_gerente'          => (int) ($empresa['pos_desc_gerente'] ?? 50),
                'margen_precio_a'           => (float) ($empresa['margen_precio_a'] ?? 35),
                'margen_precio_b'           => (float) ($empresa['margen_precio_b'] ?? 20),
                'margen_precio_c'           => (float) ($empresa['margen_precio_c'] ?? 10),
            ],
            'numeracion' => NumeracionService::configuracionCompleta($empresaId),
        ]);
    }

    public function guardar()
    {
        $empresaId = $this->empresaId();
        $num       = fn (string $k, float $def, float $max) => max(0, min($max, (float) ($this->request->getPost($k) ?? $def)));

        $datos = [
            'nombre_comercial' => trim((string) ($this->request->getPost('nombre_negocio') ?? '')),
            'ai_enabled'       => $this->request->getPost('ai_enabled') ? 1 : 0,
            'pos_desc_cajero'  => (int) $num('pos_desc_cajero', 10, 100),
            'pos_desc_gerente' => (int) $num('pos_desc_gerente', 50, 100),
            'margen_precio_a'  => $num('margen_precio_a', 35, 1000),
        ];
        // La pantalla maneja un solo precio: los márgenes B/C solo se guardan si vienen en el formulario
        foreach (['margen_precio_b', 'margen_precio_c'] as $campo) {
            if ($this->request->getPost($campo) !== null) {
                $datos[$campo] = $num($campo, 0, 1000);
            }
        }
        Db::update('companies', $datos, ['empresa_id' => $empresaId]);

        // La nomenclatura también puede venir en este formulario
        $this->guardarTiposNumeracion(true);

        // Para que el nombre nuevo se vea de inmediato en el menú
        $this->session->set('empresa_actual', Db::row("SELECT * FROM companies WHERE empresa_id = ?", [$empresaId]));
        AuditService::log('configuracion.guardar', 'Configuración de la empresa actualizada', $empresaId, 'empresa', [], $empresaId);

        $this->success('Configuración guardada exitosamente.');
        return redirect()->to('/configuracion');
    }

    public function roles(): string
    {
        return $this->render('configuracion.roles', ['page_title' => 'Roles y Permisos', 'page_subtitle' => 'Gestión de seguridad']);
    }

    public function impresoras(): string
    {
        return $this->render('configuracion.impresoras', ['page_title' => 'Impresoras', 'page_subtitle' => 'Configuración de impresión directa']);
    }

    /** Formato de los números de cotización, factura, cliente y proveedor. */
    public function numeracion(): string
    {
        return $this->render('configuracion.numeracion', [
            'page_title'    => 'Nomenclatura',
            'page_subtitle' => 'Formato de números de cotización, factura, cliente y proveedor',
            'config'        => NumeracionService::configuracionCompleta($this->empresaId()),
        ]);
    }

    public function guardarNumeracion()
    {
        $this->guardarTiposNumeracion(false);
        $this->success('Nomenclatura guardada exitosamente.');
        return redirect()->to('/configuracion/numeracion');
    }

    /** @param bool $soloSiVienen true = ignora los tipos que no llegaron en el formulario */
    private function guardarTiposNumeracion(bool $soloSiVienen): void
    {
        foreach (self::TIPOS_NUMERACION as $tipo) {
            if ($soloSiVienen && $this->request->getPost("{$tipo}_prefijo") === null) {
                continue;
            }
            NumeracionService::guardar($tipo, (int) $this->empresaId(), [
                'prefijo'   => $this->request->getPost("{$tipo}_prefijo") ?? '',
                'separador' => $this->request->getPost("{$tipo}_separador") ?? '',
                'digitos'   => $this->request->getPost("{$tipo}_digitos") ?? 4,
                'reinicio'  => $this->request->getPost("{$tipo}_reinicio") ?? 'nunca',
            ]);
        }
    }

    /**
     * Datos reales del servidor (el sistema anterior mostraba porcentajes aleatorios).
     * CPU solo está disponible en Linux (sys_getloadavg); en Windows queda en 0.
     */
    private function estadoServidor(): array
    {
        $total = @disk_total_space(ROOTPATH) ?: 0;
        $libre = @disk_free_space(ROOTPATH) ?: 0;

        $limite  = ini_get('memory_limit');
        $limiteB = $limite === '-1' ? 0 : (int) $limite * match (strtoupper(substr($limite, -1))) { 'G' => 1073741824, 'M' => 1048576, 'K' => 1024, default => 1 };

        $carga = function_exists('sys_getloadavg') ? (sys_getloadavg()[0] ?? 0) : 0;

        return [
            'uso_cpu'           => (int) min(100, round($carga * 100 / max(1, (int) (getenv('NUMBER_OF_PROCESSORS') ?: 1)))),
            'uso_memoria'       => $limiteB > 0 ? (int) round(memory_get_peak_usage(true) * 100 / $limiteB) : 0,
            'uso_disco'         => $total > 0 ? (int) round(($total - $libre) * 100 / $total) : 0,
            'debug_mode'        => ENVIRONMENT !== 'production',
            'sistema_operativo' => PHP_OS,
        ];
    }
}

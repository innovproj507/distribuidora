<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\AuditService;
use App\Libraries\Auth;
use App\Libraries\Db;

/**
 * Limpieza de la base: vacía productos y transacciones conservando usuarios, empresa,
 * sucursales, depósitos, categorías y módulos. Solo superusuarios, y hay que escribir LIMPIAR.
 */
class LimpiezaController extends BaseController
{
    /** En orden de dependencias. (El sistema anterior listaba 'inventario_conteos_detalle', que no existe.) */
    private const LIMPIAR = [
        'mant_ejecuciones', 'mant_tareas', 'mant_software',
        'lote_movimientos', 'lotes',
        'inventario_movimientos', 'conteo_detalle', 'inventario_conteos',
        'alertas_inventario', 'ordenes_compra_sugeridas', 'audit_log',
        'ventas_pagos', 'notas_credito', 'ventas_detalle', 'ventas',
        'cotizaciones_detalle', 'cotizaciones',
        'compras_detalle', 'compras', 'proveedor_evaluaciones',
        'traslados_detalle', 'traslados',
        'direcciones_cliente', 'clientes', 'proveedores',
        'inventario', 'precios_productos', 'productos_unidades_precios', 'productos_unidades', 'marcas', 'productos',
    ];

    private const CONSERVAR = [
        'users'                    => 'Usuarios y contraseñas',
        'user_profiles'            => 'Perfiles de usuario',
        'user_profiles_sucursales' => 'Sucursales por usuario',
        'companies'                => 'Configuración de empresa',
        'branches'                 => 'Sucursales',
        'depositos'                => 'Depósitos / almacenes',
        'categorias_productos'     => 'Categorías de productos',
        'modules'                  => 'Módulos instalados',
    ];

    public function index()
    {
        if (!Auth::isSuperuser()) {
            $this->error('Acceso restringido a superusuarios.');
            return redirect()->to('/');
        }

        return $this->render('configuracion.limpieza', [
            'page_title'     => 'Limpieza de Base de Datos',
            'page_subtitle'  => 'Eliminar productos y datos de transacciones',
            'limpiar'        => $this->contar(self::LIMPIAR),
            'conservar'      => $this->contar(array_keys(self::CONSERVAR)),
            'conservar_desc' => self::CONSERVAR,
        ]);
    }

    public function ejecutar()
    {
        if (!Auth::isSuperuser()) {
            $this->error('Acceso restringido a superusuarios.');
            return redirect()->to('/');
        }
        if ($this->request->getPost('confirmar') !== 'LIMPIAR') {
            $this->error('Debes escribir LIMPIAR para confirmar la operación.');
            return redirect()->to('/configuracion/limpieza');
        }

        $existentes = $this->tablasExistentes();
        $eliminados = 0;
        $errores    = [];

        Db::query('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::LIMPIAR as $tabla) {
            if (!in_array($tabla, $existentes, true)) continue;
            try {
                $eliminados += (int) Db::value("SELECT COUNT(*) FROM `{$tabla}`");
                Db::query("TRUNCATE TABLE `{$tabla}`");
            } catch (\Throwable $e) {
                $errores[] = "{$tabla}: " . $e->getMessage();
            }
        }
        Db::query('SET FOREIGN_KEY_CHECKS = 1');

        // Después del TRUNCATE de audit_log, así queda como primer registro
        AuditService::log('core.limpieza', "Limpieza de base de datos: {$eliminados} registros eliminados", null, null, ['errores' => $errores], $this->empresaId());

        empty($errores)
            ? $this->success('Limpieza completada. Se eliminaron ' . number_format($eliminados) . ' registros.')
            : $this->warning('Limpieza parcial (' . number_format($eliminados) . ' eliminados). Errores: ' . implode(' | ', $errores));

        return redirect()->to('/configuracion/limpieza');
    }

    /** @return array<string, ?int> tabla => filas (null si la tabla no existe) */
    private function contar(array $tablas): array
    {
        $existentes = $this->tablasExistentes();
        $resultado  = [];
        foreach ($tablas as $tabla) {
            $resultado[$tabla] = in_array($tabla, $existentes, true) ? (int) Db::value("SELECT COUNT(*) FROM `{$tabla}`") : null;
        }
        return $resultado;
    }

    private function tablasExistentes(): array
    {
        return array_column(Db::all("SELECT TABLE_NAME AS t FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()"), 't');
    }
}

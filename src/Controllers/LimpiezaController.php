<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

class LimpiezaController extends Controller
{
    // Tablas a limpiar, en orden correcto para FK
    private const LIMPIAR = [
        'mant_ejecuciones',
        'mant_tareas',
        'mant_software',
        'lote_movimientos',
        'lotes',
        'inventario_movimientos',
        'inventario_conteos_detalle',
        'inventario_conteos',
        'alertas_inventario',
        'ordenes_compra_sugeridas',
        'audit_log',
        'ventas_detalle',
        'ventas',
        'cotizaciones_detalle',
        'cotizaciones',
        'compras_detalle',
        'compras',
        'traslados_detalle',
        'traslados',
        'direcciones_cliente',
        'clientes',
        'proveedores',
        'inventario',
        'precios_productos',
        'productos_unidades',
        'marcas',
        'productos',
    ];

    // Tablas que se conservan
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

    public function index(): void
    {
        if (!Auth::isSuperuser()) {
            $this->error('Acceso restringido a superusuarios.');
            $this->redirect('/dashboard');
            return;
        }

        $db = $_ENV['DB_DATABASE'] ?? 'pos_empresa';

        $limpiar  = $this->contarTablas(self::LIMPIAR,  $db);
        $conservar = $this->contarTablas(array_keys(self::CONSERVAR), $db);

        $this->view('configuracion.limpieza', [
            'page_title'    => 'Limpieza de Base de Datos',
            'page_subtitle' => 'Eliminar productos y datos de transacciones',
            'limpiar'       => $limpiar,
            'conservar'     => $conservar,
            'conservar_desc' => self::CONSERVAR,
        ]);
    }

    public function ejecutar(): void
    {
        if (!Auth::isSuperuser()) {
            $this->error('Acceso restringido a superusuarios.');
            $this->redirect('/dashboard');
            return;
        }

        if (!$this->verifyCsrf()) return;

        if ($this->request->post('confirmar', '') !== 'LIMPIAR') {
            $this->error('Debes escribir LIMPIAR para confirmar la operación.');
            $this->redirect('/configuracion/limpieza');
            return;
        }

        $pdo = Database::getInstance();
        $pdo->exec('SET foreign_key_checks = 0');

        $eliminados = 0;
        $errores    = [];

        foreach (self::LIMPIAR as $tabla) {
            if (!$this->tableExists($tabla)) continue;
            try {
                $n = (int) Database::query("SELECT COUNT(*) AS n FROM `{$tabla}`")->fetch()['n'];
                $pdo->exec("TRUNCATE TABLE `{$tabla}`");
                $eliminados += $n;
            } catch (\Throwable $e) {
                $errores[] = "{$tabla}: " . $e->getMessage();
            }
        }

        $pdo->exec('SET foreign_key_checks = 1');

        if (empty($errores)) {
            $this->success("Limpieza completada. Se eliminaron " . number_format($eliminados) . " registros.");
        } else {
            $this->warning("Limpieza parcial (" . number_format($eliminados) . " eliminados). Errores: " . implode(' | ', $errores));
        }

        $this->redirect('/configuracion/limpieza');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function tableExists(string $table): bool
    {
        $db  = $_ENV['DB_DATABASE'] ?? 'pos_empresa';
        $row = Database::query(
            "SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
            [$db, $table]
        )->fetch();
        return (int)($row['n'] ?? 0) > 0;
    }

    private function contarTablas(array $tablas, string $db): array
    {
        $result = [];
        foreach ($tablas as $tabla) {
            $exists = (int) Database::query(
                "SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
                [$db, $tabla]
            )->fetch()['n'];

            $result[$tabla] = $exists
                ? (int) Database::query("SELECT COUNT(*) AS n FROM `{$tabla}`")->fetch()['n']
                : null; // null = no existe
        }
        return $result;
    }
}

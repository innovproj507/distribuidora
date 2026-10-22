<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Auth;
use App\Models\Empresa;
use App\Models\Sucursal;

class ConfiguracionController extends Controller
{
    public function sistema(): void
    {
        $empresa = null;
        $sucursales = [];

        $empresaId = $this->empresaId();
        if ($empresaId) {
            $empresa = Empresa::find($empresaId);
            $sucursales = Sucursal::where('empresa_id', $empresaId);
        }

        // Métricas para la vista original
        $stats = [
            'usuarios_activos' => \App\Models\Usuario::count("is_active = 1"),
            'total_ventas' => \App\Models\Venta::count(),
            'total_productos' => \App\Models\Producto::count(),
            'total_clientes' => \App\Models\Cliente::count(),
        ];

        // Mock de uso de sistema (tricky en PHP multiplataforma sin extensiones)
        $system = [
            'uso_cpu' => rand(5, 15),
            'uso_memoria' => rand(20, 40),
            'uso_disco' => rand(10, 30),
            'debug_mode' => $_ENV['APP_DEBUG'] ?? false,
            'sistema_operativo' => PHP_OS,
        ];

        $this->view('configuracion.sistema', [
            'page_title' => 'Configuración',
            'page_subtitle' => 'Configuración del sistema',
            'empresa' => $empresa,
            'sucursales' => $sucursales,
            'stats' => $stats,
            'system' => $system,
            'configuracion' => [
                'nombre_negocio' => $empresa->nombre_comercial ?? '',
                'moneda' => 'USD',
                'impuesto' => (float)($_ENV['ITBMS_RATE'] ?? 7),
                'serie_facturacion' => '001-001',
                'resolucion_dgi' => 'N/A',
                'impresora_predeterminada' => 'ticket',
                'impresora_ticket' => 'POS-58',
                'impresora_a4' => 'Virtual Printer',
                'backup_automatico' => true,
                'hora_backup' => '23:00',
                'sincronizacion_automatica' => true,
                'pos_desc_cajero' => (int)($empresa->pos_desc_cajero ?? 10),
                'pos_desc_gerente' => (int)($empresa->pos_desc_gerente ?? 50),
                'margen_precio_a' => (float)($empresa->margen_precio_a ?? 35),
                'margen_precio_b' => (float)($empresa->margen_precio_b ?? 20),
                'margen_precio_c' => (float)($empresa->margen_precio_c ?? 10),
            ],
            'numeracion' => \App\Services\NumeracionService::configuracionCompleta($empresaId),
        ]);
    }

    public function roles(): void
    {
        $this->view('configuracion.roles', [
            'page_title' => 'Roles y Permisos',
            'page_subtitle' => 'Gestión de seguridad',
        ]);
    }

    public function impresoras(): void
    {
        $this->view('configuracion.impresoras', [
            'page_title' => 'Impresoras',
            'page_subtitle' => 'Configuración de impresión directa',
        ]);
    }

    /**
     * Nomenclatura de documentos y códigos: cotización, factura, cliente, proveedor.
     */
    public function numeracion(): void
    {
        $empresaId = $this->empresaId();

        $this->view('configuracion.numeracion', [
            'page_title' => 'Nomenclatura',
            'page_subtitle' => 'Formato de números de cotización, factura, cliente y proveedor',
            'config' => \App\Services\NumeracionService::configuracionCompleta($empresaId),
        ]);
    }

    public function guardarNumeracion(): void
    {
        if (!$this->verifyCsrf()) return;

        $empresaId = $this->empresaId();
        foreach (['cotizacion', 'factura', 'cliente', 'proveedor'] as $tipo) {
            \App\Services\NumeracionService::guardar($tipo, $empresaId, [
                'prefijo'   => $this->request->post("{$tipo}_prefijo", ''),
                'separador' => $this->request->post("{$tipo}_separador", ''),
                'digitos'   => $this->request->post("{$tipo}_digitos", 4),
                'reinicio'  => $this->request->post("{$tipo}_reinicio", 'nunca'),
            ]);
        }

        $this->success('Nomenclatura guardada exitosamente.');
        $this->redirect('/configuracion/numeracion');
    }

    public function guardar(): void
    {
        if (!$this->verifyCsrf()) return;

        $empresaId = $this->empresaId();
        $empresa = Empresa::findOrFail($empresaId);

        $data = [
            'nombre_comercial' => $this->request->post('nombre_negocio', ''),
            'ai_enabled' => $this->request->post('ai_enabled') ? 1 : 0,
            'pos_desc_cajero'  => max(0, min(100, (int)$this->request->post('pos_desc_cajero', 10))),
            'pos_desc_gerente' => max(0, min(100, (int)$this->request->post('pos_desc_gerente', 50))),
            'margen_precio_a'  => max(0, min(1000, (float)$this->request->post('margen_precio_a', 35))),
            'margen_precio_b'  => max(0, min(1000, (float)$this->request->post('margen_precio_b', 20))),
            'margen_precio_c'  => max(0, min(1000, (float)$this->request->post('margen_precio_c', 10))),
        ];

        // Nota: Otros campos como moneda e impuesto podrían requerir cambios en .env o tabla de config separada
        // Por ahora enfocamos en los campos del modelo Empresa
        $empresa->update($data);

        // Nomenclatura de documentos (cotización, factura, cliente, proveedor), si vino en el formulario
        foreach (['cotizacion', 'factura', 'cliente', 'proveedor'] as $tipo) {
            if ($this->request->post("{$tipo}_prefijo") === null) continue;
            \App\Services\NumeracionService::guardar($tipo, $empresaId, [
                'prefijo'   => $this->request->post("{$tipo}_prefijo", ''),
                'separador' => $this->request->post("{$tipo}_separador", ''),
                'digitos'   => $this->request->post("{$tipo}_digitos", 4),
                'reinicio'  => $this->request->post("{$tipo}_reinicio", 'nunca'),
            ]);
        }

        // Refrescar empresa_actual en sesión para que el nombre se refleje de inmediato
        $empresaFresh = Database::query("SELECT * FROM companies WHERE empresa_id = ?", [$empresaId])->fetch();
        if ($empresaFresh) {
            $this->session->set('empresa_actual', $empresaFresh);
        }

        $this->success('Configuración guardada exitosamente.');
        $this->redirect('/configuracion');
    }

    public function cambiarSucursal(int $sucursal_id): void
    {
        $sucursal = Sucursal::findOrFail($sucursal_id);
        $userId = Auth::id();

        // 1. Verificar que el usuario tenga acceso a esta sucursal (seguridad multi-tenant)
        $perfil = Database::query("SELECT perfil_id FROM user_profiles WHERE user_id = ?", [$userId])->fetch();
        if (!$perfil) {
            $this->error("No se encontró el perfil de usuario.");
            $this->redirect('/');
            return;
        }

        $tieneAcceso = Database::query(
            "SELECT 1 FROM user_profiles_sucursales WHERE perfilusuario_id = ? AND sucursal_id = ?",
            [$perfil['perfil_id'], $sucursal_id]
        )->fetch();

        if (!$tieneAcceso && !Auth::isSuperuser()) {
            $this->error("No tienes permiso para acceder a esta sucursal.");
            $this->redirect('/');
            return;
        }

        // 2. Actualizar en perfil de usuario (DB)
        Database::query(
            "UPDATE user_profiles SET sucursal_actual_id = ? WHERE user_id = ?",
            [$sucursal_id, $userId]
        );

        // 3. Actualizar Sucursal en sesión
        $this->session->set('sucursal_actual', $sucursal->toArray());

        // 4. Actualizar Depósitos de la nueva sucursal en sesión
        $todosDepositos = Database::query(
            "SELECT * FROM depositos WHERE sucursal_id = ? AND estado = 'activo' ORDER BY es_principal DESC",
            [$sucursal_id]
        )->fetchAll();

        $permitidos = \App\Core\Auth::depositosPermitidos();
        $depositos  = empty($permitidos)
            ? $todosDepositos
            : array_values(array_filter($todosDepositos, fn($d) => in_array((int)$d['deposito_id'], $permitidos, true)));

        $this->session->set('depositos_disponibles', $depositos);

        // Resetear al depósito principal de la nueva sucursal
        if (!empty($depositos)) {
            $this->session->set('deposito_actual', $depositos[0]);
        } else {
            $this->session->remove('deposito_actual');
        }

        $this->success("Sucursal cambiada a {$sucursal->nombre}.");
        $this->redirect('/');
    }

    public function cambiarDeposito(int $deposito_id): void
    {
        $sucursalActual = $this->session->get('sucursal_actual');
        if (!$sucursalActual) {
            $this->error("No hay sucursal activa.");
            $this->back();
            return;
        }

        $deposito = Database::query(
            "SELECT * FROM depositos WHERE deposito_id = ? AND sucursal_id = ?",
            [$deposito_id, $sucursalActual['sucursal_id']]
        )->fetch();

        if (!$deposito) {
            $this->error("Depósito no encontrado o no pertenece a la sucursal activa.");
            $this->back();
            return;
        }

        $permitidos = \App\Core\Auth::depositosPermitidos();
        if (!empty($permitidos) && !in_array($deposito_id, $permitidos, true)) {
            $this->error("No tienes permiso para acceder a ese depósito.");
            $this->back();
            return;
        }

        $this->session->set('deposito_actual', $deposito);
        $this->success("Depósito cambiado a {$deposito['nombre']}.");
        $this->back();
    }

    public function suscripcion(): void
    {
        $empresa = $this->empresaActual();
        if (!$empresa) {
            $this->error('No se pudo encontrar la información de la empresa.');
            $this->redirect('/');
            return;
        }

        // Obtener plan actual
        $plan = Database::query(
            "SELECT * FROM configuracion_planes WHERE plan_id = ?",
            [$empresa['plan_id'] ?? 1]
        )->fetch();

        // Obtener planes disponibles
        $planes = Database::query("SELECT * FROM configuracion_planes WHERE activo = 1 ORDER BY precio ASC")->fetchAll();

        // Calcular uso actual
        $uso = [
            'usuarios' => Database::query("SELECT COUNT(*) as t FROM users")->fetch()['t'],
            'sucursales' => Database::query("SELECT COUNT(*) as t FROM branches WHERE empresa_id = ?", [$empresa['empresa_id']])->fetch()['t'],
            'depositos' => Database::query("SELECT COUNT(*) as t FROM depositos WHERE sucursal_id IN (SELECT sucursal_id FROM branches WHERE empresa_id = ?)", [$empresa['empresa_id']])->fetch()['t'],
            'productos' => Database::query("SELECT COUNT(*) as t FROM productos WHERE empresa_id = ?", [$empresa['empresa_id']])->fetch()['t'],
        ];

        $this->view('configuracion.suscripcion', [
            'page_title' => 'Suscripción y Mi Plan',
            'page_subtitle' => 'Gestiona los límites y recursos de tu cuenta SaaS',
            'empresa' => $empresa,
            'plan' => $plan,
            'planes' => $planes,
            'uso' => $uso
        ]);
    }
}

<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Application;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;

/**
 * SucursalMiddleware - Detecta empresa y sucursal activa del usuario
 * Replica la lógica de SucursalMiddleware de Django
 */
class SucursalMiddleware
{
    public function handle(Request $request): bool
    {
        if (!Auth::check()) return true;

        $app = Application::getInstance();
        $session = $app->getSession();

        // MODO SUCURSAL ÚNICA: auto-selecciona la empresa y sucursal principal sin
        // requerir perfiles de usuario ni tabla user_profiles_sucursales.
        // Para reactivar multi-sucursal: reemplazar este bloque con el código comentado abajo.

        if ($session->has('sucursal_actual') && $session->get('sucursal_actual') !== null) {
            // Validar que la sucursal en sesión sigue existiendo en DB
            $currSucursal = $session->get('sucursal_actual');
            $stillExists = Database::query(
                "SELECT sucursal_id FROM branches WHERE sucursal_id = ? AND activa = 1",
                [$currSucursal['sucursal_id']]
            )->fetch();

            if (!$stillExists) {
                // Sucursal eliminada: limpiar sesión y volver a detectar
                $session->remove('sucursal_actual');
                $session->remove('deposito_actual');
                $session->remove('depositos_disponibles');
                $session->remove('sucursales_disponibles');
            } else {
                // Validar también que el depósito activo sigue existiendo
                $currDeposito = $session->get('deposito_actual');
                $depositoValido = $currDeposito && Database::query(
                    "SELECT deposito_id FROM depositos WHERE deposito_id = ? AND estado = 'activo'",
                    [$currDeposito['deposito_id']]
                )->fetch();

                if (!$depositoValido) {
                    // Depósito eliminado: reasignar al primero disponible
                    $depositos = $this->filtrarDepositos(Database::query(
                        "SELECT * FROM depositos WHERE sucursal_id = ? AND estado = 'activo' ORDER BY es_principal DESC",
                        [$currSucursal['sucursal_id']]
                    )->fetchAll());
                    $session->set('depositos_disponibles', $depositos);
                    $session->set('deposito_actual', $depositos[0] ?? null);
                } elseif (!$session->has('depositos_disponibles') || empty($session->get('depositos_disponibles'))) {
                    $depositos = $this->filtrarDepositos(Database::query(
                        "SELECT * FROM depositos WHERE sucursal_id = ? AND estado = 'activo' ORDER BY es_principal DESC",
                        [$currSucursal['sucursal_id']]
                    )->fetchAll());
                    $session->set('depositos_disponibles', $depositos);
                }

                return true;
            }
        }

        // Primera carga: seleccionar empresa y sucursal principal automáticamente
        $empresa = Database::query(
            "SELECT * FROM companies WHERE activa = 1 ORDER BY empresa_id LIMIT 1"
        )->fetch();

        if (!$empresa) return true;

        $sucursal = Database::query(
            "SELECT * FROM branches WHERE empresa_id = ? AND activa = 1 ORDER BY es_principal DESC, sucursal_id LIMIT 1",
            [$empresa['empresa_id']]
        )->fetch();

        if (!$sucursal) return true;

        $depositos = $this->filtrarDepositos(Database::query(
            "SELECT * FROM depositos WHERE sucursal_id = ? AND estado = 'activo' ORDER BY es_principal DESC",
            [$sucursal['sucursal_id']]
        )->fetchAll());

        $session->set('empresa_actual', $empresa);
        $session->set('sucursal_actual', $sucursal);
        $session->set('sucursales_disponibles', [$sucursal]);
        $session->set('depositos_disponibles', $depositos);
        if (!empty($depositos)) {
            $session->set('deposito_actual', $depositos[0]);
        }

        return true;

        /* ═══════════════════════════════════════════════════════════════════
         * CÓDIGO MULTI-SUCURSAL — inactivo (sistema opera con una sola sucursal)
         * Descomentar cuando se requiera soporte para múltiples sucursales.
         * ═══════════════════════════════════════════════════════════════════

        $userId = Auth::id();

        $perfil = Database::query(
            "SELECT p.*, e.empresa_id as emp_id, e.codigo as emp_codigo, e.nombre_comercial, e.razon_social, e.ruc, e.logo
             FROM user_profiles p
             JOIN companies e ON p.empresa_id = e.empresa_id
             WHERE p.user_id = ?",
            [$userId]
        )->fetch();

        if ($perfil) {
            $session->set('empresa_actual', [
                'empresa_id'      => $perfil['emp_id'],
                'codigo'          => $perfil['emp_codigo'],
                'nombre_comercial'=> $perfil['nombre_comercial'],
                'razon_social'    => $perfil['razon_social'],
                'ruc'             => $perfil['ruc'],
                'logo'            => $perfil['logo'],
            ]);

            $sucursales = Database::query(
                "SELECT s.* FROM branches s
                 JOIN user_profiles_sucursales ps ON s.sucursal_id = ps.sucursal_id
                 WHERE ps.perfilusuario_id = ? AND s.activa = 1",
                [$perfil['perfil_id']]
            )->fetchAll();

            $session->set('sucursales_disponibles', $sucursales);

            if ($perfil['sucursal_actual_id']) {
                $sucursalActual = Database::query(
                    "SELECT * FROM branches WHERE sucursal_id = ?",
                    [$perfil['sucursal_actual_id']]
                )->fetch();
                $session->set('sucursal_actual', $sucursalActual);
            } elseif (!empty($sucursales)) {
                $session->set('sucursal_actual', $sucursales[0]);
                Database::query(
                    "UPDATE user_profiles SET sucursal_actual_id = ? WHERE perfil_id = ?",
                    [$sucursales[0]['sucursal_id'], $perfil['perfil_id']]
                );
            }

            $currSucursal = $session->get('sucursal_actual');
            if ($currSucursal) {
                $depositos = $this->filtrarDepositos(Database::query(
                    "SELECT * FROM depositos WHERE sucursal_id = ? AND estado = 'activo' ORDER BY es_principal DESC",
                    [$currSucursal['sucursal_id']]
                )->fetchAll());
                $session->set('depositos_disponibles', $depositos);

                if (!$session->has('deposito_actual') || empty($session->get('deposito_actual'))) {
                    if (!empty($depositos)) $session->set('deposito_actual', $depositos[0]);
                } else {
                    $curr = $session->get('deposito_actual');
                    $valid = array_filter($depositos, fn($d) => $d['deposito_id'] == $curr['deposito_id']);
                    if (empty($valid) && !empty($depositos)) $session->set('deposito_actual', $depositos[0]);
                }
            }
        } else {
            // Sin perfil: crear automáticamente con la sucursal principal
            $empresa = Database::query(
                "SELECT * FROM companies WHERE activa = 1 ORDER BY empresa_id LIMIT 1"
            )->fetch();
            if ($empresa) {
                $sucursal = Database::query(
                    "SELECT * FROM branches WHERE empresa_id = ? AND activa = 1 ORDER BY sucursal_id LIMIT 1",
                    [$empresa['empresa_id']]
                )->fetch();
                if ($sucursal) {
                    Database::query(
                        "INSERT INTO user_profiles (user_id, empresa_id, sucursal_actual_id, cargo) VALUES (?, ?, ?, '')",
                        [$userId, $empresa['empresa_id'], $sucursal['sucursal_id']]
                    );
                    $perfilId = Database::lastInsertId();
                    Database::query(
                        "INSERT INTO user_profiles_sucursales (perfilusuario_id, sucursal_id) VALUES (?, ?)",
                        [$perfilId, $sucursal['sucursal_id']]
                    );
                    $depositos = $this->filtrarDepositos(Database::query(
                        "SELECT * FROM depositos WHERE sucursal_id = ? AND estado = 'activo' ORDER BY es_principal DESC",
                        [$sucursal['sucursal_id']]
                    )->fetchAll());
                    $session->set('empresa_actual', $empresa);
                    $session->set('sucursal_actual', $sucursal);
                    $session->set('sucursales_disponibles', [$sucursal]);
                    $session->set('depositos_disponibles', $depositos);
                    if (!empty($depositos)) $session->set('deposito_actual', $depositos[0]);
                }
            }
        }

        return true;
        */
    }

    /**
     * Filtra la lista de depósitos según los permisos del usuario.
     * Si el usuario no tiene restricciones retorna el array sin cambios.
     */
    private function filtrarDepositos(array $depositos): array
    {
        $permitidos = Auth::depositosPermitidos();
        if (empty($permitidos)) {
            return $depositos;
        }
        return array_values(
            array_filter($depositos, fn($d) => in_array((int) $d['deposito_id'], $permitidos, true))
        );
    }

    /**
     * Fuerza la asignación de una empresa y sucursal principal
     * Útil para impersonación de Superadmin
     */
    private function forceTenantAssignment($session, int $empresaId): void
    {
        $empresa = Database::query("SELECT * FROM companies WHERE empresa_id = ?", [$empresaId])->fetch();
        if (!$empresa) return;

        $sucursal = Database::query(
            "SELECT * FROM branches WHERE empresa_id = ? AND activa = 1 ORDER BY es_principal DESC LIMIT 1",
            [$empresaId]
        )->fetch();

        if ($sucursal) {
            $session->set('empresa_actual', $empresa);
            $session->set('sucursal_actual', $sucursal);
            $session->set('sucursales_disponibles', [$sucursal]); // Al menos la principal

            $depositos = $this->filtrarDepositos(Database::query(
                "SELECT * FROM depositos WHERE sucursal_id = ? AND estado = 'activo' ORDER BY es_principal DESC",
                [$sucursal['sucursal_id']]
            )->fetchAll());

            $session->set('depositos_disponibles', $depositos);
            if (!empty($depositos)) {
                $session->set('deposito_actual', $depositos[0]);
            }
        }
    }
}

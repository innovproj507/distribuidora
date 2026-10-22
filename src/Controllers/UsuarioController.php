<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\AuditService;

class UsuarioController extends Controller
{
    public function index(): void
    {
        if (!$this->requirePermission('usuarios.ver')) return;

        $usuarios = Database::query(
            "SELECT u.id, u.username, u.first_name, u.last_name, u.email,
                    u.is_active, u.rol, u.date_joined, u.totp_habilitado,
                    up.cargo
             FROM users u
             LEFT JOIN user_profiles up ON u.id = up.user_id AND up.empresa_id = ?
             ORDER BY u.rol, u.username",
            [$this->empresaId()]
        )->fetchAll();

        $this->view('usuarios.index', [
            'page_title'    => 'Usuarios',
            'page_subtitle' => 'Gestión de roles y accesos',
            'usuarios'      => $usuarios,
            'roles'         => ['cajero', 'supervisor', 'gerente', 'auditor', 'superadmin'],
        ]);
    }

    public function perfil(): void
    {
        $user = Database::query(
            "SELECT id, username, first_name, last_name, email FROM users WHERE id = ?",
            [Auth::id()]
        )->fetch();

        if ($this->request->method() === 'POST') {
            if (!$this->verifyCsrf()) return;

            $firstName = trim($this->request->post('first_name', ''));
            $lastName  = trim($this->request->post('last_name', ''));
            $email     = trim($this->request->post('email', ''));
            $currentPw = $this->request->post('current_password', '');
            $newPw     = $this->request->post('new_password', '');

            Database::query(
                "UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?",
                [$firstName, $lastName, $email, Auth::id()]
            );

            if ($currentPw !== '' && $newPw !== '') {
                $stored = Database::query("SELECT password FROM users WHERE id = ?", [Auth::id()])->fetchColumn();
                if (!Auth::verifyPassword($currentPw, $stored)) {
                    $this->error('La contraseña actual es incorrecta.');
                    $this->redirect('/usuarios/perfil');
                    return;
                }
                Database::query(
                    "UPDATE users SET password = ? WHERE id = ?",
                    [password_hash($newPw, PASSWORD_BCRYPT), Auth::id()]
                );
            }

            $fullname = trim("{$firstName} {$lastName}") ?: $user['username'];
            $this->session->set('user_fullname', $fullname);

            AuditService::log('usuarios.perfil', 'Perfil actualizado', Auth::id(), 'usuario', [], $this->empresaId());
            $this->success('Perfil actualizado correctamente.');
            $this->redirect('/usuarios/perfil');
            return;
        }

        $this->view('usuarios.perfil', [
            'page_title' => 'Mi Perfil',
            'usuario'    => (object) $user,
            'auth'       => ['name' => Auth::name()],
        ]);
    }

    public function crear(): void
    {
        if (!$this->requirePermission('usuarios.gestionar')) return;

        $this->view('usuarios.crear', [
            'page_title'    => 'Nuevo Usuario',
            'page_subtitle' => 'Crear cuenta de acceso',
            'roles'         => ['cajero', 'supervisor', 'gerente', 'auditor', 'superadmin'],
        ]);
    }

    public function guardar(): void
    {
        if (!$this->verifyCsrf()) return;
        if (!$this->requirePermission('usuarios.gestionar')) return;

        $username  = trim($this->request->post('username', ''));
        $firstName = trim($this->request->post('first_name', ''));
        $lastName  = trim($this->request->post('last_name', ''));
        $email     = trim($this->request->post('email', ''));
        $password  = $this->request->post('password', '');
        $email     = $email !== '' ? $email : null;
        $rol       = $this->request->post('rol', 'cajero');
        $isStaff   = $this->request->post('is_staff') ? 1 : 0;
        $isActive  = $this->request->post('is_active') ? 1 : 0;

        $rolesValidos = ['cajero', 'supervisor', 'gerente', 'auditor', 'superadmin'];
        if ($username === '' || $password === '') {
            $this->error('Usuario y contraseña son obligatorios.');
            $this->redirect('/usuarios/nuevo');
            return;
        }
        if (!in_array($rol, $rolesValidos, true)) {
            $this->error('Rol no válido.');
            $this->redirect('/usuarios/nuevo');
            return;
        }
        if ($rol === 'superadmin' && Auth::rol() !== 'superadmin') {
            $this->error('Solo un superadmin puede crear ese rol.');
            $this->redirect('/usuarios/nuevo');
            return;
        }

        $existe = Database::query("SELECT id FROM users WHERE username = ?", [$username])->fetch();
        if ($existe) {
            $this->error("El nombre de usuario '{$username}' ya está en uso.");
            $this->redirect('/usuarios/nuevo');
            return;
        }

        Database::query(
            "INSERT INTO users (username, password, first_name, last_name, email, rol, is_staff, is_active, is_superuser, date_joined)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $username,
                password_hash($password, PASSWORD_BCRYPT),
                $firstName,
                $lastName,
                $email,
                $rol,
                $isStaff,
                $isActive,
                $rol === 'superadmin' ? 1 : 0,
            ]
        );

        AuditService::log('usuarios.crear', "Usuario creado: {$username} (rol: {$rol})", null, 'usuario', [], $this->empresaId());
        $this->success("Usuario '{$username}' creado correctamente.");
        $this->redirect('/usuarios');
    }

    public function cambiarRol(): void
    {
        if (!$this->verifyCsrf()) return;
        if (!$this->requirePermission('usuarios.gestionar')) return;

        $userId   = (int) $this->request->post('user_id');
        $nuevoRol = $this->request->post('rol');
        $validos  = ['cajero', 'supervisor', 'gerente', 'auditor', 'superadmin'];

        if (!in_array($nuevoRol, $validos, true)) {
            $this->error('Rol no válido.');
            $this->redirect('/usuarios');
            return;
        }
        if ($nuevoRol === 'superadmin' && Auth::rol() !== 'superadmin') {
            $this->error('Solo un superadmin puede asignar ese rol.');
            $this->redirect('/usuarios');
            return;
        }
        if ($userId === Auth::id()) {
            $this->error('No puedes cambiar tu propio rol.');
            $this->redirect('/usuarios');
            return;
        }

        $anterior = Database::query("SELECT rol, username FROM users WHERE id = ?", [$userId])->fetch();
        Database::query("UPDATE users SET rol = ? WHERE id = ?", [$nuevoRol, $userId]);
        AuditService::log(
            'usuarios.cambiar_rol',
            "Rol de '{$anterior['username']}' cambiado de '{$anterior['rol']}' a '{$nuevoRol}'",
            $userId, 'usuario', [], $this->empresaId()
        );
        $this->success('Rol actualizado.');
        $this->redirect('/usuarios');
    }

    public function toggleActivo(): void
    {
        if (!$this->verifyCsrf()) return;
        if (!$this->requirePermission('usuarios.gestionar')) return;

        $userId = (int) $this->request->post('user_id');
        if ($userId === Auth::id()) {
            $this->error('No puedes desactivar tu propia cuenta.');
            $this->redirect('/usuarios');
            return;
        }

        $target = Database::query("SELECT username, is_active FROM users WHERE id = ?", [$userId])->fetch();
        Database::query("UPDATE users SET is_active = NOT is_active WHERE id = ?", [$userId]);
        $nuevoEstado = $target['is_active'] ? 'inactivo' : 'activo';
        AuditService::log(
            'usuarios.toggle_activo',
            "Usuario '{$target['username']}' marcado como {$nuevoEstado}",
            $userId, 'usuario', [], $this->empresaId()
        );
        $this->success('Estado del usuario actualizado.');
        $this->redirect('/usuarios');
    }

    public function editar(int $user_id): void
    {
        if (!$this->requirePermission('usuarios.gestionar')) return;

        $usuario = Database::query(
            "SELECT id, username, first_name, last_name, email, rol, is_staff, is_active FROM users WHERE id = ?",
            [$user_id]
        )->fetch();

        if (!$usuario) {
            $this->error('Usuario no encontrado.');
            $this->redirect('/usuarios');
            return;
        }

        $this->view('usuarios.form', [
            'page_title'    => 'Editar Usuario',
            'page_subtitle' => 'Modificar datos de acceso',
            'usuario'       => (object) $usuario,
            'action'        => "/usuarios/{$user_id}/editar",
            'roles'         => ['cajero', 'supervisor', 'gerente', 'auditor', 'superadmin'],
        ]);
    }

    public function actualizar(int $user_id): void
    {
        if (!$this->verifyCsrf()) return;
        if (!$this->requirePermission('usuarios.gestionar')) return;

        $usuario = Database::query(
            "SELECT id, username, rol FROM users WHERE id = ?",
            [$user_id]
        )->fetch();

        if (!$usuario) {
            $this->error('Usuario no encontrado.');
            $this->redirect('/usuarios');
            return;
        }

        if ($usuario['rol'] === 'superadmin' && Auth::rol() !== 'superadmin') {
            $this->error('No puedes editar un superadmin.');
            $this->redirect('/usuarios');
            return;
        }

        $firstName = trim($this->request->post('first_name', ''));
        $lastName  = trim($this->request->post('last_name', ''));
        $email     = trim($this->request->post('email', ''));
        $email     = $email !== '' ? $email : null;
        $isStaff   = $this->request->post('is_staff') ? 1 : 0;
        $isActive  = $this->request->post('is_active') ? 1 : 0;
        $password  = $this->request->post('password', '');

        $rol = $this->request->post('rol', $usuario['rol']);
        $rolesValidos = ['cajero', 'supervisor', 'gerente', 'auditor', 'superadmin'];
        if (!in_array($rol, $rolesValidos, true)) $rol = $usuario['rol'];
        if ($rol === 'superadmin' && Auth::rol() !== 'superadmin') $rol = $usuario['rol'];

        if ($user_id === Auth::id() && !$isActive) {
            $this->error('No puedes desactivar tu propia cuenta.');
            $this->redirect("/usuarios/{$user_id}/editar");
            return;
        }

        Database::query(
            "UPDATE users SET first_name=?, last_name=?, email=?, is_staff=?, is_active=?, rol=?, is_superuser=? WHERE id=?",
            [$firstName, $lastName, $email, $isStaff, $isActive, $rol, $rol === 'superadmin' ? 1 : 0, $user_id]
        );

        if ($password !== '') {
            Database::query(
                "UPDATE users SET password = ? WHERE id = ?",
                [password_hash($password, PASSWORD_BCRYPT), $user_id]
            );
        }

        AuditService::log('usuarios.editar', "Usuario '{$usuario['username']}' actualizado", $user_id, 'usuario', [], $this->empresaId());
        $this->success('Usuario actualizado correctamente.');
        $this->redirect('/usuarios');
    }

    public function eliminar(int $user_id): void
    {
        if (!$this->verifyCsrf()) return;
        if (!$this->requirePermission('usuarios.gestionar')) return;

        if ($user_id === Auth::id()) {
            $this->error('No puedes eliminar tu propia cuenta.');
            $this->redirect('/usuarios');
            return;
        }

        $usuario = Database::query("SELECT username, rol FROM users WHERE id = ?", [$user_id])->fetch();
        if (!$usuario) {
            $this->error('Usuario no encontrado.');
            $this->redirect('/usuarios');
            return;
        }

        if ($usuario['rol'] === 'superadmin' && Auth::rol() !== 'superadmin') {
            $this->error('No puedes eliminar un superadmin.');
            $this->redirect('/usuarios');
            return;
        }

        Database::query("DELETE FROM users WHERE id = ?", [$user_id]);
        AuditService::log('usuarios.eliminar', "Usuario '{$usuario['username']}' eliminado", null, 'usuario', [], $this->empresaId());
        $this->success("Usuario '{$usuario['username']}' eliminado.");
        $this->redirect('/usuarios');
    }

    public function permisos(int $user_id): void
    {
        if (!$this->requirePermission('usuarios.gestionar')) return;

        $usuario = Database::query(
            "SELECT id, username, first_name, last_name, rol FROM users WHERE id = ?",
            [$user_id]
        )->fetch();

        if (!$usuario) {
            $this->error('Usuario no encontrado.');
            $this->redirect('/usuarios');
            return;
        }

        // Todos los depósitos de la empresa agrupados por sucursal
        $depositos = Database::query(
            "SELECT d.deposito_id, d.nombre AS deposito_nombre, d.es_principal,
                    b.sucursal_id, b.nombre AS sucursal_nombre
             FROM depositos d
             JOIN branches b ON d.sucursal_id = b.sucursal_id
             WHERE b.empresa_id = ? AND d.estado = 'activo'
             ORDER BY b.nombre, d.nombre",
            [$this->empresaId()]
        )->fetchAll();

        // IDs actualmente asignados
        $asignados = Database::query(
            "SELECT deposito_id FROM user_depositos_permitidos WHERE user_id = ?",
            [$user_id]
        )->fetchAll(\PDO::FETCH_COLUMN);

        $asignados = array_map('intval', $asignados);

        $this->view('usuarios.permisos', [
            'page_title'    => 'Permisos de Depósito',
            'page_subtitle' => "Acceso contextual: {$usuario['username']}",
            'usuario'       => $usuario,
            'depositos'     => $depositos,
            'asignados'     => $asignados,
        ]);
    }

    public function guardarPermisos(): void
    {
        if (!$this->verifyCsrf()) return;
        if (!$this->requirePermission('usuarios.gestionar')) return;

        $userId     = (int) $this->request->post('user_id');
        $depositoIds = $this->request->post('depositos', []);

        if ($userId === 0) {
            $this->error('Usuario no válido.');
            $this->redirect('/usuarios');
            return;
        }

        // Verificar que el usuario existe
        $usuario = Database::query("SELECT username FROM users WHERE id = ?", [$userId])->fetch();
        if (!$usuario) {
            $this->error('Usuario no encontrado.');
            $this->redirect('/usuarios');
            return;
        }

        // Reemplazar permisos: borrar y re-insertar
        Database::query("DELETE FROM user_depositos_permitidos WHERE user_id = ?", [$userId]);

        $insertados = 0;
        foreach ($depositoIds as $depId) {
            $depId = (int) $depId;
            if ($depId <= 0) continue;
            Database::query(
                "INSERT IGNORE INTO user_depositos_permitidos (user_id, deposito_id) VALUES (?, ?)",
                [$userId, $depId]
            );
            $insertados++;
        }

        AuditService::log(
            'usuarios.permisos_deposito',
            "Permisos de depósito actualizados para '{$usuario['username']}': {$insertados} depósito(s) asignado(s)",
            $userId, 'usuario', ['depositos' => $depositoIds], $this->empresaId()
        );

        $msg = $insertados === 0
            ? "Sin restricciones: '{$usuario['username']}' puede acceder a todos los depósitos."
            : "Permisos actualizados: {$insertados} depósito(s) asignado(s).";

        $this->success($msg);
        $this->redirect("/usuarios/{$userId}/permisos");
    }
}

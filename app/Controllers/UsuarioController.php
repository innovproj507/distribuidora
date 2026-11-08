<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\AuditService;
use App\Libraries\Auth;
use App\Libraries\Db;
use App\Libraries\Fila;

/**
 * Usuarios, roles y depósitos permitidos. Permisos por ruta: usuarios.ver / gestionar.
 * Reglas: solo un superadmin crea o asigna el rol superadmin; nadie cambia su propio rol
 * ni se desactiva o elimina a sí mismo.
 */
class UsuarioController extends BaseController
{
    private const ROLES = ['vendedor', 'cajero', 'supervisor', 'gerente', 'auditor', 'superadmin'];

    public function index(): string
    {
        return $this->render('usuarios.index', [
            'page_title'    => 'Usuarios',
            'page_subtitle' => 'Gestión de roles y accesos',
            'usuarios'      => Db::all(
                "SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.is_active, u.rol,
                        u.date_joined, u.totp_habilitado, up.cargo
                 FROM users u
                 LEFT JOIN user_profiles up ON u.id = up.user_id AND up.empresa_id = ?
                 ORDER BY u.rol, u.username",
                [$this->empresaId()]
            ),
            'roles' => self::ROLES,
        ]);
    }

    public function detalle(int $userId)
    {
        $usuario = Db::row("SELECT id, username, first_name, last_name, email, rol, is_staff, is_active, date_joined FROM users WHERE id = ?", [$userId]);
        if (!$usuario) {
            $this->error('Usuario no encontrado.');
            return redirect()->to('/usuarios');
        }

        return $this->render('usuarios.detalle', [
            'page_title'    => $usuario['username'],
            'page_subtitle' => 'Detalle del usuario',
            'usuario'       => new Fila($usuario + ['last_login' => null]),
        ]);
    }

    /** GET muestra el perfil propio; POST lo actualiza (y la contraseña, si se pide). */
    public function perfil()
    {
        $user = Db::row("SELECT id, username, first_name, last_name, email FROM users WHERE id = ?", [Auth::id()]);

        if (!$this->request->is('post')) {
            return $this->render('usuarios.perfil', ['page_title' => 'Mi Perfil', 'usuario' => new Fila($user)]);
        }

        $post      = fn (string $k) => trim((string) ($this->request->getPost($k) ?? ''));
        $firstName = $post('first_name');
        $lastName  = $post('last_name');
        $currentPw = (string) ($this->request->getPost('current_password') ?? '');
        $newPw     = (string) ($this->request->getPost('new_password') ?? '');

        if ($newPw !== '') {
            $guardada = (string) Db::value("SELECT password FROM users WHERE id = ?", [Auth::id()]);
            if (!Auth::verifyPassword($currentPw, $guardada)) {
                $this->error('La contraseña actual es incorrecta.');
                return redirect()->to('/usuarios/perfil');
            }
            if ($msg = Auth::errorPassword($newPw, (string) ($user['username'] ?? ''))) {
                $this->error($msg);
                return redirect()->to('/usuarios/perfil');
            }
        }

        Db::update('users', ['first_name' => $firstName, 'last_name' => $lastName, 'email' => $post('email') ?: null], ['id' => Auth::id()]);
        if ($newPw !== '') {
            Db::update('users', ['password' => password_hash($newPw, PASSWORD_BCRYPT)], ['id' => Auth::id()]);
        }

        $this->session->set('user_fullname', trim("{$firstName} {$lastName}") ?: $user['username']);
        AuditService::log('usuarios.perfil', 'Perfil actualizado', Auth::id(), 'usuario', [], $this->empresaId());

        $this->success('Perfil actualizado correctamente.');
        return redirect()->to('/usuarios/perfil');
    }

    public function crear(): string
    {
        return $this->render('usuarios.crear', ['page_title' => 'Nuevo Usuario', 'page_subtitle' => 'Crear cuenta de acceso', 'roles' => self::ROLES]);
    }

    public function guardar()
    {
        $post     = fn (string $k) => trim((string) ($this->request->getPost($k) ?? ''));
        $username = $post('username');
        $password = (string) ($this->request->getPost('password') ?? '');
        $rol      = $post('rol') ?: 'cajero';

        $error = match (true) {
            $username === '' || $password === ''                       => 'Usuario y contraseña son obligatorios.',
            ($e = Auth::errorPassword($password, $username)) !== null   => $e,
            !in_array($rol, self::ROLES, true)                         => 'Rol no válido.',
            $rol === 'superadmin' && Auth::rol() !== 'superadmin'       => 'Solo un superadmin puede crear ese rol.',
            (bool) Db::value("SELECT 1 FROM users WHERE username = ?", [$username]) => "El nombre de usuario '{$username}' ya está en uso.",
            default => null,
        };
        if ($error) {
            $this->error($error);
            return redirect()->to('/usuarios/nuevo');
        }

        $id = Db::insert('users', [
            'username'     => $username,
            'password'     => password_hash($password, PASSWORD_BCRYPT),
            'first_name'   => $post('first_name'),
            'last_name'    => $post('last_name'),
            'email'        => $post('email') ?: null,
            'rol'          => $rol,
            'is_staff'     => $this->request->getPost('is_staff') ? 1 : 0,
            'is_active'    => $this->request->getPost('is_active') ? 1 : 0,
            'is_superuser' => $rol === 'superadmin' ? 1 : 0,
            'date_joined'  => date('Y-m-d H:i:s'),
        ]);
        AuditService::log('usuarios.crear', "Usuario creado: {$username} (rol: {$rol})", $id, 'usuario', [], $this->empresaId());

        $this->success("Usuario '{$username}' creado correctamente.");
        return redirect()->to('/usuarios');
    }

    public function editar(int $userId)
    {
        $usuario = Db::row("SELECT id, username, first_name, last_name, email, rol, is_staff, is_active FROM users WHERE id = ?", [$userId]);
        if (!$usuario) {
            $this->error('Usuario no encontrado.');
            return redirect()->to('/usuarios');
        }

        return $this->render('usuarios.form', [
            'page_title'    => 'Editar Usuario',
            'page_subtitle' => 'Modificar datos de acceso',
            'usuario'       => new Fila($usuario),
            'action'        => "/usuarios/{$userId}/editar",
            'roles'         => self::ROLES,
        ]);
    }

    public function actualizar(int $userId)
    {
        $usuario = Db::row("SELECT id, username, rol FROM users WHERE id = ?", [$userId]);
        if (!$usuario) {
            $this->error('Usuario no encontrado.');
            return redirect()->to('/usuarios');
        }
        if ($usuario['rol'] === 'superadmin' && Auth::rol() !== 'superadmin') {
            $this->error('No puedes editar un superadmin.');
            return redirect()->to('/usuarios');
        }

        $isActive = $this->request->getPost('is_active') ? 1 : 0;
        if ($userId === Auth::id() && !$isActive) {
            $this->error('No puedes desactivar tu propia cuenta.');
            return redirect()->to("/usuarios/{$userId}/editar");
        }

        $rol = (string) ($this->request->getPost('rol') ?? $usuario['rol']);
        if (!in_array($rol, self::ROLES, true) || ($rol === 'superadmin' && Auth::rol() !== 'superadmin') || $userId === Auth::id()) {
            $rol = $usuario['rol']; // rol inválido, no permitido o propio: se conserva
        }

        // Nombre de usuario (con el que se inicia sesión): se puede cambiar, sin repetir
        $username = trim((string) ($this->request->getPost('username') ?? $usuario['username']));
        if (!preg_match('/^[A-Za-z0-9._\-]{3,50}$/', $username)) {
            $this->error('El usuario debe tener de 3 a 50 caracteres: letras, números, punto, guion o guion bajo (sin espacios).');
            return redirect()->to("/usuarios/{$userId}/editar");
        }
        if ($username !== $usuario['username'] && Db::value("SELECT 1 FROM users WHERE username = ? AND id <> ?", [$username, $userId])) {
            $this->error("El nombre de usuario '{$username}' ya está en uso.");
            return redirect()->to("/usuarios/{$userId}/editar");
        }

        $password = (string) ($this->request->getPost('password') ?? '');
        if ($password !== '' && ($msg = Auth::errorPassword($password, $username))) {
            $this->error($msg);
            return redirect()->to("/usuarios/{$userId}/editar");
        }

        $post = fn (string $k) => trim((string) ($this->request->getPost($k) ?? ''));
        Db::update('users', [
            'username'     => $username,
            'first_name'   => $post('first_name'),
            'last_name'    => $post('last_name'),
            'email'        => $post('email') ?: null,
            'is_staff'     => $this->request->getPost('is_staff') ? 1 : 0,
            'is_active'    => $isActive,
            'rol'          => $rol,
            'is_superuser' => $rol === 'superadmin' ? 1 : 0,
        ], ['id' => $userId]);

        if ($password !== '') {
            Db::update('users', ['password' => password_hash($password, PASSWORD_BCRYPT)], ['id' => $userId]);
        }

        AuditService::log('usuarios.editar', "Usuario '{$usuario['username']}' actualizado", $userId, 'usuario', [], $this->empresaId());
        $this->success('Usuario actualizado correctamente.');
        return redirect()->to('/usuarios');
    }

    public function cambiarRol()
    {
        $userId   = (int) $this->request->getPost('user_id');
        $nuevoRol = (string) $this->request->getPost('rol');
        $anterior = Db::row("SELECT rol, username FROM users WHERE id = ?", [$userId]);

        $error = match (true) {
            !$anterior                                                       => 'Usuario no encontrado.',
            !in_array($nuevoRol, self::ROLES, true)                          => 'Rol no válido.',
            ($nuevoRol === 'superadmin' || $anterior['rol'] === 'superadmin') && Auth::rol() !== 'superadmin' => 'Solo un superadmin puede asignar o quitar ese rol.',
            $userId === Auth::id()                                           => 'No puedes cambiar tu propio rol.',
            default => null,
        };
        if ($error) {
            $this->error($error);
            return redirect()->to('/usuarios');
        }

        Db::update('users', ['rol' => $nuevoRol, 'is_superuser' => $nuevoRol === 'superadmin' ? 1 : 0], ['id' => $userId]);
        AuditService::log('usuarios.cambiar_rol', "Rol de '{$anterior['username']}' cambiado de '{$anterior['rol']}' a '{$nuevoRol}'", $userId, 'usuario', [], $this->empresaId());

        $this->success('Rol actualizado.');
        return redirect()->to('/usuarios');
    }

    /** POST /usuarios/toggle-activo (user_id en el formulario) o /usuarios/{id}/estado. */
    public function toggleActivo(?int $userId = null)
    {
        $userId ??= (int) $this->request->getPost('user_id');
        $target = Db::row("SELECT username, is_active, rol FROM users WHERE id = ?", [$userId]);

        if (!$target || $userId === Auth::id()) {
            $this->error($target ? 'No puedes desactivar tu propia cuenta.' : 'Usuario no encontrado.');
            return redirect()->to('/usuarios');
        }
        if ($target['rol'] === 'superadmin' && Auth::rol() !== 'superadmin') {
            $this->error('No puedes desactivar un superadmin.');
            return redirect()->to('/usuarios');
        }

        Db::query("UPDATE users SET is_active = NOT is_active WHERE id = ?", [$userId]);
        $nuevo = $target['is_active'] ? 'inactivo' : 'activo';
        AuditService::log('usuarios.toggle_activo', "Usuario '{$target['username']}' marcado como {$nuevo}", $userId, 'usuario', [], $this->empresaId());

        $this->success('Estado del usuario actualizado.');
        return redirect()->to('/usuarios');
    }

    public function eliminar(int $userId)
    {
        $usuario = Db::row("SELECT username, rol FROM users WHERE id = ?", [$userId]);

        if ($userId === Auth::id() || !$usuario) {
            $this->error($usuario ? 'No puedes eliminar tu propia cuenta.' : 'Usuario no encontrado.');
            return redirect()->to('/usuarios');
        }
        if ($usuario['rol'] === 'superadmin' && Auth::rol() !== 'superadmin') {
            $this->error('No puedes eliminar un superadmin.');
            return redirect()->to('/usuarios');
        }

        // Con ventas, compras o movimientos a su nombre no se borra: se desactiva
        $historial = (int) Db::value("SELECT COUNT(*) FROM ventas WHERE vendedor_id = ?", [$userId])
                   + (int) Db::value("SELECT COUNT(*) FROM compras WHERE usuario_id = ?", [$userId])
                   + (int) Db::value("SELECT COUNT(*) FROM inventario_movimientos WHERE usuario_id = ?", [$userId]);
        if ($historial > 0) {
            Db::update('users', ['is_active' => 0], ['id' => $userId]);
            $this->warning("'{$usuario['username']}' tiene operaciones registradas: se desactivó en lugar de eliminarse.");
            return redirect()->to('/usuarios');
        }

        Db::begin();
        Db::query("DELETE FROM user_depositos_permitidos WHERE user_id = ?", [$userId]);
        Db::query("DELETE FROM user_profiles_sucursales WHERE perfilusuario_id IN (SELECT perfil_id FROM user_profiles WHERE user_id = ?)", [$userId]);
        Db::query("DELETE FROM user_profiles WHERE user_id = ?", [$userId]);
        Db::query("DELETE FROM users WHERE id = ?", [$userId]);
        Db::commit();

        AuditService::log('usuarios.eliminar', "Usuario '{$usuario['username']}' eliminado", null, 'usuario', [], $this->empresaId());
        $this->success("Usuario '{$usuario['username']}' eliminado.");
        return redirect()->to('/usuarios');
    }

    // ── Depósitos permitidos ─────────────────────────────────────────────────

    public function permisos(int $userId)
    {
        $usuario = Db::row("SELECT id, username, first_name, last_name, rol FROM users WHERE id = ?", [$userId]);
        if (!$usuario) {
            $this->error('Usuario no encontrado.');
            return redirect()->to('/usuarios');
        }

        return $this->render('usuarios.permisos', [
            'page_title'    => 'Permisos de Depósito',
            'page_subtitle' => "Acceso contextual: {$usuario['username']}",
            'usuario'       => $usuario,
            'depositos'     => Db::all(
                "SELECT d.deposito_id, d.nombre AS deposito_nombre, d.es_principal, b.sucursal_id, b.nombre AS sucursal_nombre
                 FROM depositos d JOIN branches b ON d.sucursal_id = b.sucursal_id
                 WHERE b.empresa_id = ? AND d.estado = 'activo'
                 ORDER BY b.nombre, d.nombre",
                [$this->empresaId()]
            ),
            'asignados' => array_map('intval', array_column(Db::all("SELECT deposito_id FROM user_depositos_permitidos WHERE user_id = ?", [$userId]), 'deposito_id')),
            'sucursales' => Db::all(
                "SELECT sucursal_id, nombre, direccion, es_principal FROM branches WHERE empresa_id = ? AND activa = 1 ORDER BY es_principal DESC, nombre",
                [$this->empresaId()]
            ),
            'sucursales_asignadas' => array_map('intval', array_column(Db::all(
                "SELECT ups.sucursal_id FROM user_profiles_sucursales ups
                 JOIN user_profiles p ON p.perfil_id = ups.perfilusuario_id WHERE p.user_id = ?",
                [$userId]
            ), 'sucursal_id')),
            've_todas' => in_array($usuario['rol'], \App\Libraries\SucursalService::ROLES_TODAS, true),
        ]);
    }

    public function guardarPermisos()
    {
        $userId  = (int) $this->request->getPost('user_id');
        $usuario = $userId ? Db::row("SELECT username FROM users WHERE id = ?", [$userId]) : null;
        if (!$usuario) {
            $this->error('Usuario no encontrado.');
            return redirect()->to('/usuarios');
        }

        $empresaId = (int) $this->empresaId();

        // Solo ids que existen en esta empresa
        $validosDep = array_map('intval', array_column(Db::all(
            "SELECT d.deposito_id FROM depositos d JOIN branches b ON b.sucursal_id = d.sucursal_id WHERE b.empresa_id = ?",
            [$empresaId]
        ), 'deposito_id'));
        $validosSuc = array_map('intval', array_column(Db::all("SELECT sucursal_id FROM branches WHERE empresa_id = ?", [$empresaId]), 'sucursal_id'));

        $ids = array_values(array_intersect(array_unique(array_map('intval', (array) ($this->request->getPost('depositos') ?? []))), $validosDep));
        $sucursales = array_values(array_intersect(array_unique(array_map('intval', (array) ($this->request->getPost('sucursales') ?? []))), $validosSuc));

        Db::begin();
        Db::query("DELETE FROM user_depositos_permitidos WHERE user_id = ?", [$userId]);
        foreach ($ids as $depositoId) {
            Db::insert('user_depositos_permitidos', ['user_id' => $userId, 'deposito_id' => $depositoId]);
        }

        // Sucursales asignadas (tabla ligada al perfil; se crea el perfil si el usuario no tiene)
        $perfilId = (int) Db::value("SELECT perfil_id FROM user_profiles WHERE user_id = ?", [$userId]);
        if (!$perfilId) {
            $perfilId = Db::insert('user_profiles', [
                'user_id'            => $userId,
                'empresa_id'         => $empresaId,
                'sucursal_actual_id' => $sucursales[0] ?? $this->sucursalId(),
            ]);
        }
        Db::query("DELETE FROM user_profiles_sucursales WHERE perfilusuario_id = ?", [$perfilId]);
        foreach ($sucursales as $sucursalId) {
            Db::insert('user_profiles_sucursales', ['perfilusuario_id' => $perfilId, 'sucursal_id' => $sucursalId]);
        }
        // Si la sucursal recordada ya no está asignada, pasa a la primera asignada
        if ($sucursales && !in_array((int) Db::value("SELECT sucursal_actual_id FROM user_profiles WHERE perfil_id = ?", [$perfilId]), $sucursales, true)) {
            Db::update('user_profiles', ['sucursal_actual_id' => $sucursales[0]], ['perfil_id' => $perfilId]);
        }
        Db::commit();

        AuditService::log(
            'usuarios.permisos_deposito',
            "Accesos actualizados para '{$usuario['username']}': " . count($sucursales) . ' sucursal(es), ' . count($ids) . ' depósito(s)',
            $userId, 'usuario', ['sucursales' => $sucursales, 'depositos' => $ids], $empresaId
        );
        $this->success("Accesos de '{$usuario['username']}' actualizados: "
            . (count($sucursales) ? count($sucursales) . ' sucursal(es)' : 'sucursal del perfil')
            . ', ' . (count($ids) ? count($ids) . ' depósito(s)' : 'todos los depósitos') . '.');
        return redirect()->to("/usuarios/{$userId}/permisos");
    }
}

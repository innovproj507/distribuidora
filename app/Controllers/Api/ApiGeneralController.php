<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Libraries\AuditService;
use App\Libraries\Auth;
use App\Libraries\Db;

/** Dashboard, reportes, configuración de empresa y usuarios por API. */
class ApiGeneralController extends ApiController
{
    private const ROLES = ['cajero', 'supervisor', 'gerente', 'auditor', 'superadmin'];

    /** Campos públicos de un usuario (nunca password, token ni secreto TOTP). */
    private const CAMPOS_USUARIO = 'id, username, first_name, last_name, email, rol, is_active, is_staff, is_superuser, totp_habilitado, date_joined';

    // ── Dashboard (/api/v1/dashboard) ────────────────────────────────────────

    public function dashboard()
    {
        $sucursalId = $this->sucursalId();
        $depositoId = $this->depositoId();

        $ventasHoy = Db::row(
            "SELECT COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total FROM ventas
             WHERE sucursal_id = ? AND DATE(fecha) = CURDATE() AND estado != 'anulada'",
            [$sucursalId]
        );

        return $this->ok([
            'ventas_hoy'     => ['total' => (float) $ventasHoy['total'], 'cantidad' => (int) $ventasHoy['cantidad']],
            'ventas_mes'     => (float) Db::value(
                "SELECT COALESCE(SUM(total), 0) FROM ventas WHERE sucursal_id = ? AND fecha >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND estado != 'anulada'",
                [$sucursalId]
            ),
            'totalProductos' => (int) Db::value("SELECT COUNT(*) FROM inventario i JOIN productos p ON p.producto_id = i.producto_id WHERE i.deposito_id = ? AND p.estado = 'activo'", [$depositoId]),
            'stockBajo'      => (int) Db::value("SELECT COUNT(*) FROM inventario i JOIN productos p ON p.producto_id = i.producto_id WHERE i.deposito_id = ? AND p.estado = 'activo' AND i.existencia > 0 AND i.existencia <= i.minimo", [$depositoId]),
            'agotados'       => (int) Db::value("SELECT COUNT(*) FROM inventario i JOIN productos p ON p.producto_id = i.producto_id WHERE i.deposito_id = ? AND p.estado = 'activo' AND i.existencia <= 0", [$depositoId]),
            'listaStockBajo' => Db::all(
                "SELECT p.producto_id, p.nombre, i.existencia, i.minimo FROM inventario i JOIN productos p ON p.producto_id = i.producto_id
                 WHERE i.deposito_id = ? AND p.estado = 'activo' AND i.existencia <= i.minimo ORDER BY i.existencia LIMIT 5",
                [$depositoId]
            ),
            'ultimasVentas'  => Db::all(
                "SELECT v.venta_id, v.numero_factura, v.total, v.estado, v.fecha, c.nombre AS cliente_nombre
                 FROM ventas v LEFT JOIN clientes c ON v.cliente_id = c.cliente_id
                 WHERE v.sucursal_id = ? ORDER BY v.fecha DESC LIMIT 5",
                [$sucursalId]
            ),
        ]);
    }

    // ── Reportes (/api/v1/reportes) ──────────────────────────────────────────

    /** GET ?inicio=&fin= (por defecto el mes en curso) */
    public function reporteVentas()
    {
        [$inicio, $fin] = $this->rango();
        return $this->ok(['ventas' => Db::all(
            "SELECT DATE(fecha) AS fecha, COUNT(*) AS cantidad, SUM(subtotal) AS subtotal, SUM(itbms) AS itbms, SUM(total) AS total, SUM(costo) AS costo
             FROM ventas WHERE empresa_id = ? AND estado != 'anulada' AND DATE(fecha) BETWEEN ? AND ?
             GROUP BY DATE(fecha) ORDER BY fecha",
            [$this->empresaId(), $inicio, $fin]
        ), 'inicio' => $inicio, 'fin' => $fin]);
    }

    /** GET ?inicio=&fin=&limit= */
    public function productosTop()
    {
        [$inicio, $fin] = $this->rango();
        $limite = min(100, max(1, (int) ($this->request->getGet('limit') ?? 10)));
        return $this->ok(['productos' => Db::all(
            "SELECT p.producto_id, p.codigo, p.nombre, SUM(d.cantidad) AS total_vendido, SUM(d.total_linea) AS ingresos
             FROM ventas_detalle d JOIN productos p ON d.producto_id = p.producto_id JOIN ventas v ON d.venta_id = v.venta_id
             WHERE v.empresa_id = ? AND v.estado != 'anulada' AND DATE(v.fecha) BETWEEN ? AND ?
             GROUP BY p.producto_id, p.codigo, p.nombre ORDER BY total_vendido DESC LIMIT {$limite}",
            [$this->empresaId(), $inicio, $fin]
        )]);
    }

    public function stockCritico()
    {
        return $this->ok(['productos' => Db::all(
            "SELECT p.producto_id, p.codigo, p.nombre, p.stock_minimo, c.nombre AS categoria_nombre,
                    COALESCE(SUM(i.existencia), 0) AS stock_actual
             FROM productos p
             LEFT JOIN categorias_productos c ON p.categoria_id = c.categoria_id
             LEFT JOIN inventario i ON p.producto_id = i.producto_id
             WHERE p.empresa_id = ? AND p.estado = 'activo'
             GROUP BY p.producto_id, p.codigo, p.nombre, p.stock_minimo, c.nombre
             HAVING stock_actual <= p.stock_minimo
             ORDER BY stock_actual",
            [$this->empresaId()]
        )]);
    }

    /** GET ?inicio=&fin= — compras por proveedor (no existía en la API anterior aunque tenía ruta). */
    public function reporteCompras()
    {
        [$inicio, $fin] = $this->rango();
        return $this->ok(['compras' => Db::all(
            "SELECT COALESCE(p.nombre, 'Sin proveedor') AS proveedor, COUNT(*) AS cantidad, SUM(c.monto_total) AS total
             FROM compras c LEFT JOIN proveedores p ON c.proveedor_id = p.proveedor_id
             WHERE c.empresa_id = ? AND c.estado != 'cancelada' AND c.fecha_compra BETWEEN ? AND ?
             GROUP BY p.proveedor_id, p.nombre ORDER BY total DESC",
            [$this->empresaId(), $inicio, $fin]
        ), 'inicio' => $inicio, 'fin' => $fin]);
    }

    /** @return array{0: string, 1: string} */
    private function rango(): array
    {
        return [
            (string) ($this->request->getGet('inicio') ?: date('Y-m-01')),
            (string) ($this->request->getGet('fin') ?: date('Y-m-d')),
        ];
    }

    // ── Configuración (/api/v1/configuracion) ────────────────────────────────

    public function empresa()
    {
        return $this->ok([
            'empresa'    => Db::row("SELECT * FROM companies WHERE empresa_id = ?", [$this->empresaId()]),
            'sucursales' => Db::all("SELECT * FROM branches WHERE empresa_id = ? AND activa = 1", [$this->empresaId()]),
        ]);
    }

    public function sucursal(int $id)
    {
        $sucursal = Db::row("SELECT * FROM branches WHERE sucursal_id = ? AND empresa_id = ?", [$id, $this->empresaId()]);
        return $sucursal ? $this->ok($sucursal) : $this->noEncontrado('Sucursal no encontrada');
    }

    /** PUT — solo los campos enviados. */
    public function actualizarEmpresa()
    {
        $data  = $this->entrada();
        $datos = array_intersect_key($data, array_flip(['razon_social', 'nombre_comercial', 'ruc', 'dv', 'email', 'telefono', 'direccion', 'sitio_web']));
        if (!$datos) {
            return $this->fallo('No se enviaron campos para actualizar.', 422);
        }
        Db::update('companies', $datos, ['empresa_id' => $this->empresaId()]);
        AuditService::log('configuracion.guardar', 'Datos de la empresa actualizados por API', $this->empresaId(), 'empresa', array_keys($datos));
        return $this->ok([], 'Configuración de empresa actualizada');
    }

    // ── Usuarios (/api/v1/usuarios) ──────────────────────────────────────────

    public function usuarios()
    {
        return $this->ok(['usuarios' => Db::all("SELECT " . self::CAMPOS_USUARIO . " FROM users ORDER BY rol, username")]);
    }

    /** GET /usuarios/perfil — el usuario del token (sin datos sensibles). */
    public function perfil()
    {
        return $this->ok(Db::row("SELECT " . self::CAMPOS_USUARIO . " FROM users WHERE id = ?", [Auth::id()]) + [
            'empresa_id'  => $this->empresaId(),
            'sucursal_id' => $this->sucursalId(),
            'deposito_id' => $this->depositoId(),
        ]);
    }

    public function usuario(int $id)
    {
        $usuario = Db::row("SELECT " . self::CAMPOS_USUARIO . " FROM users WHERE id = ?", [$id]);
        return $usuario ? $this->ok($usuario) : $this->noEncontrado('Usuario no encontrado');
    }

    /** POST {username, password, first_name?, last_name?, email?, rol?=cajero, is_staff?} */
    public function crearUsuario()
    {
        $data     = $this->entrada();
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $rol      = (string) ($data['rol'] ?? 'cajero');

        $error = match (true) {
            $username === '' || $password === ''                  => 'username y password son obligatorios.',
            strlen($password) < 8                                  => 'La contraseña debe tener al menos 8 caracteres.',
            !in_array($rol, self::ROLES, true)                     => 'Rol no válido.',
            $rol === 'superadmin' && Auth::rol() !== 'superadmin'  => 'Solo un superadmin puede crear ese rol.',
            (bool) Db::value("SELECT 1 FROM users WHERE username = ?", [$username]) => 'El nombre de usuario ya está en uso.',
            default => null,
        };
        if ($error) {
            return $this->fallo($error, 422);
        }

        $id = Db::insert('users', [
            'username'     => $username,
            'password'     => password_hash($password, PASSWORD_BCRYPT),
            'first_name'   => trim((string) ($data['first_name'] ?? '')),
            'last_name'    => trim((string) ($data['last_name'] ?? '')),
            'email'        => trim((string) ($data['email'] ?? '')) ?: null,
            'rol'          => $rol,
            'is_staff'     => !empty($data['is_staff']) ? 1 : 0,
            'is_active'    => 1,
            'is_superuser' => $rol === 'superadmin' ? 1 : 0,
            'date_joined'  => date('Y-m-d H:i:s'),
        ]);
        AuditService::log('usuarios.crear', "Usuario creado por API: {$username} (rol: {$rol})", $id, 'usuario');
        return $this->ok(['usuario_id' => $id], 'Usuario creado con éxito', 201);
    }

    /**
     * PUT — con usuarios.gestionar se edita a cualquiera (salvo superadmins si no lo eres);
     * sin ese permiso solo el propio perfil, y cambiar la contraseña exige current_password.
     */
    public function actualizarUsuario(int $id)
    {
        $objetivo = Db::row("SELECT id, username, password, rol FROM users WHERE id = ?", [$id]);
        if (!$objetivo) {
            return $this->noEncontrado('Usuario no encontrado');
        }

        $esPropio   = $id === Auth::id();
        $gestionar  = Auth::can('usuarios.gestionar');
        if (!$esPropio && !$gestionar) {
            return $this->fallo('No tiene permisos para editar este usuario.', 403);
        }
        if (!$esPropio && $objetivo['rol'] === 'superadmin' && Auth::rol() !== 'superadmin') {
            return $this->fallo('No puedes editar un superadmin.', 403);
        }

        $data  = $this->entrada();
        $datos = [];
        foreach (['first_name', 'last_name', 'email'] as $campo) {
            if (array_key_exists($campo, $data)) {
                $datos[$campo] = trim((string) $data[$campo]) ?: ($campo === 'email' ? null : '');
            }
        }

        if (!empty($data['password'])) {
            if (strlen((string) $data['password']) < 8) {
                return $this->fallo('La contraseña debe tener al menos 8 caracteres.', 422);
            }
            if ($esPropio && !Auth::verifyPassword((string) ($data['current_password'] ?? ''), $objetivo['password'])) {
                return $this->fallo('La contraseña actual es incorrecta.', 422);
            }
            $datos['password'] = password_hash((string) $data['password'], PASSWORD_BCRYPT);
        }

        // Rol y estado: solo con usuarios.gestionar y nunca sobre uno mismo
        if ($gestionar && !$esPropio) {
            if (isset($data['rol']) && in_array($data['rol'], self::ROLES, true) && ($data['rol'] !== 'superadmin' || Auth::rol() === 'superadmin')) {
                $datos['rol']          = $data['rol'];
                $datos['is_superuser'] = $data['rol'] === 'superadmin' ? 1 : 0;
            }
            if (array_key_exists('is_active', $data)) {
                $datos['is_active'] = !empty($data['is_active']) ? 1 : 0;
                if (!$datos['is_active']) {
                    $datos['api_token'] = null; // un usuario desactivado pierde su token
                }
            }
            if (array_key_exists('is_staff', $data)) {
                $datos['is_staff'] = !empty($data['is_staff']) ? 1 : 0;
            }
        }

        if (!$datos) {
            return $this->fallo('No se enviaron campos para actualizar.', 422);
        }
        Db::update('users', $datos, ['id' => $id]);
        AuditService::log('usuarios.editar', "Usuario '{$objetivo['username']}' actualizado por API", $id, 'usuario', array_keys($datos));
        return $this->ok([], 'Usuario actualizado correctamente');
    }
}

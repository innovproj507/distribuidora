<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Multi-sucursal: qué sucursales y depósitos puede usar cada usuario, cambio de la
 * sucursal activa, y alta/edición de sucursales (cada una nace con su depósito principal).
 *
 * Reglas de acceso:
 *   - superadmin, superusuario y gerente ven todas las sucursales activas de la empresa.
 *   - El resto ve las asignadas en user_profiles_sucursales; si no tiene ninguna asignada,
 *     queda en la sucursal de su perfil (o la principal), como en el modo de sucursal única.
 *   - Depósitos: si el usuario tiene depósitos permitidos en esa sucursal, solo esos;
 *     si no tiene restricciones en esa sucursal, todos los activos de la sucursal.
 */
final class SucursalService
{
    /** Roles que pueden cambiar entre todas las sucursales sin asignación. */
    public const ROLES_TODAS = ['superadmin', 'gerente'];

    public static function veTodas(): bool
    {
        return Auth::isSuperuser() || in_array(Auth::rol(), self::ROLES_TODAS, true);
    }

    /** Sucursales activas que el usuario actual puede usar (la principal primero). */
    public static function disponibles(int $empresaId): array
    {
        $todas = Db::all(
            "SELECT * FROM branches WHERE empresa_id = ? AND activa = 1 ORDER BY es_principal DESC, nombre",
            [$empresaId]
        );
        if (self::veTodas() || count($todas) <= 1) {
            return $todas;
        }

        $asignadas = array_map('intval', array_column(Db::all(
            "SELECT ups.sucursal_id FROM user_profiles_sucursales ups
             JOIN user_profiles p ON p.perfil_id = ups.perfilusuario_id
             WHERE p.user_id = ?",
            [Auth::id()]
        ), 'sucursal_id'));

        if (empty($asignadas)) {
            $perfil      = (int) Db::value("SELECT sucursal_actual_id FROM user_profiles WHERE user_id = ?", [Auth::id()]);
            $asignadas   = $perfil ? [$perfil] : [];
            $filtradas   = array_values(array_filter($todas, fn ($s) => in_array((int) $s['sucursal_id'], $asignadas, true)));
            return $filtradas ?: [$todas[0]];
        }

        return array_values(array_filter($todas, fn ($s) => in_array((int) $s['sucursal_id'], $asignadas, true)));
    }

    /** Depósitos activos de la sucursal que el usuario actual puede usar (el principal primero). */
    public static function depositosDe(int $sucursalId): array
    {
        $depositos = Db::all(
            "SELECT * FROM depositos WHERE sucursal_id = ? AND estado = 'activo' ORDER BY es_principal DESC, nombre",
            [$sucursalId]
        );
        $permitidos = Auth::depositosPermitidos();
        if (empty($permitidos)) {
            return $depositos;
        }
        $filtrados = array_values(array_filter($depositos, fn ($d) => in_array((int) $d['deposito_id'], $permitidos, true)));
        // Sin restricciones dentro de esta sucursal: puede usar todos sus depósitos
        return $filtrados ?: $depositos;
    }

    /**
     * Arma el contexto de sesión. Sin $sucursalId usa la sucursal guardada en el perfil del
     * usuario si sigue disponible. Devuelve null si no hay empresa o sucursal utilizable.
     *
     * @return array{empresa_actual: array, sucursal_actual: array, sucursales_disponibles: array, depositos_disponibles: array, deposito_actual: ?array}|null
     */
    public static function contexto(?int $sucursalId = null, ?int $depositoId = null): ?array
    {
        $empresa = Db::row("SELECT * FROM companies WHERE activa = 1 ORDER BY empresa_id LIMIT 1");
        if (!$empresa) {
            return null;
        }

        $sucursales = self::disponibles((int) $empresa['empresa_id']);
        if (empty($sucursales)) {
            return null;
        }

        $sucursalId ??= (int) Db::value("SELECT sucursal_actual_id FROM user_profiles WHERE user_id = ?", [Auth::id()]);
        $sucursal   = self::buscar($sucursales, 'sucursal_id', $sucursalId) ?? $sucursales[0];

        $depositos = self::depositosDe((int) $sucursal['sucursal_id']);
        $deposito  = self::buscar($depositos, 'deposito_id', $depositoId) ?? ($depositos[0] ?? null);

        return [
            'empresa_actual'         => $empresa,
            'sucursal_actual'        => $sucursal,
            'sucursales_disponibles' => $sucursales,
            'depositos_disponibles'  => $depositos,
            'deposito_actual'        => $deposito,
        ];
    }

    /** Guarda la sucursal elegida en el perfil del usuario (la recuerda el próximo login y la usa la API). */
    public static function recordar(int $sucursalId, int $empresaId): void
    {
        $perfilId = Db::value("SELECT perfil_id FROM user_profiles WHERE user_id = ?", [Auth::id()]);
        if ($perfilId) {
            Db::update('user_profiles', ['sucursal_actual_id' => $sucursalId], ['perfil_id' => $perfilId]);
        } else {
            Db::insert('user_profiles', ['user_id' => Auth::id(), 'empresa_id' => $empresaId, 'sucursal_actual_id' => $sucursalId]);
        }
    }

    // ── Alta y edición ───────────────────────────────────────────────────────

    /**
     * Valida y normaliza los datos del formulario. Lanza RuntimeException con el mensaje
     * para el usuario si algo no es válido.
     */
    public static function normalizar(array $in, int $empresaId, ?int $sucursalId = null): array
    {
        $nombre  = trim((string) ($in['nombre'] ?? ''));
        $codigo  = strtoupper(trim((string) ($in['codigo'] ?? '')));
        $prefijo = strtoupper(trim((string) ($in['prefijo'] ?? '')));

        if ($nombre === '') {
            throw new \RuntimeException('El nombre de la sucursal es obligatorio.');
        }
        if ($codigo === '') {
            throw new \RuntimeException('El código de la sucursal es obligatorio.');
        }
        if ($prefijo !== '' && !preg_match('/^[A-Z0-9]{1,6}$/', $prefijo)) {
            throw new \RuntimeException('El prefijo de serie solo admite letras y números (máximo 6).');
        }

        $excluir = $sucursalId ?? 0;
        if (Db::value("SELECT COUNT(*) FROM branches WHERE empresa_id = ? AND codigo = ? AND sucursal_id <> ?", [$empresaId, $codigo, $excluir])) {
            throw new \RuntimeException("Ya existe una sucursal con el código {$codigo}.");
        }
        if ($prefijo !== '' && Db::value("SELECT COUNT(*) FROM branches WHERE empresa_id = ? AND prefijo = ? AND sucursal_id <> ?", [$empresaId, $prefijo, $excluir])) {
            throw new \RuntimeException("El prefijo de serie {$prefijo} ya lo usa otra sucursal.");
        }

        $lat = self::coordenada($in['latitud'] ?? null, 90, 'latitud');
        $lng = self::coordenada($in['longitud'] ?? null, 180, 'longitud');

        $mapa = trim((string) ($in['mapa_url'] ?? ''));
        if ($mapa !== '' && !preg_match('#^https://#i', $mapa)) {
            throw new \RuntimeException('El enlace del mapa debe empezar con https://');
        }

        $email = trim((string) ($in['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('El correo de la sucursal no es válido.');
        }

        return [
            'nombre'    => mb_substr($nombre, 0, 150),
            'codigo'    => mb_substr($codigo, 0, 20),
            'prefijo'   => $prefijo !== '' ? $prefijo : null,
            'direccion' => trim((string) ($in['direccion'] ?? '')) ?: null,
            'telefono'  => mb_substr(trim((string) ($in['telefono'] ?? '')), 0, 50) ?: null,
            'email'     => $email ?: null,
            'mapa_url'  => $mapa !== '' ? mb_substr($mapa, 0, 500) : null,
            'latitud'   => $lat,
            'longitud'  => $lng,
        ];
    }

    /** Crea la sucursal con su depósito principal y le da fila de inventario a cada producto. */
    public static function crear(array $datos, int $empresaId): int
    {
        $sucursalId = Db::insert('branches', $datos + [
            'empresa_id'   => $empresaId,
            'es_principal' => 0,
            'activa'       => 1,
        ]);

        $depositoId = Db::insert('depositos', [
            'sucursal_id'  => $sucursalId,
            'codigo'       => mb_substr('BOD-' . $datos['codigo'], 0, 20),
            'nombre'       => 'Almacén ' . $datos['nombre'],
            'descripcion'  => 'Depósito principal de la sucursal',
            'es_principal' => 1,
            'estado'       => 'activo',
        ]);
        self::poblarInventario($depositoId);

        return $sucursalId;
    }

    /**
     * Crea en el depósito una fila de inventario (existencia 0) para cada producto que no
     * la tenga, para que el catálogo completo se vea y se pueda vender/recibir en él.
     * El costo promedio arranca con el costo del producto. El mínimo arranca en 0 para no
     * llenar las alertas con todo el catálogo de una sucursal nueva (se ajusta por depósito).
     */
    public static function poblarInventario(int $depositoId, ?int $productoId = null): int
    {
        $sql    = "INSERT IGNORE INTO inventario (producto_id, deposito_id, existencia, minimo, costo_promedio, ultimo_costo)
                   SELECT p.producto_id, ?, 0, 0, COALESCE(p.costo, 0), COALESCE(p.costo, 0)
                   FROM productos p
                   WHERE p.estado = 'activo'";
        $params = [$depositoId];
        if ($productoId !== null) {
            $sql     .= ' AND p.producto_id = ?';
            $params[] = $productoId;
        }
        Db::query($sql, $params);
        return Db::conn()->affectedRows();
    }

    /** Crea la fila de inventario de un producto nuevo en todos los depósitos activos. */
    public static function poblarProducto(int $productoId): void
    {
        foreach (Db::all("SELECT deposito_id FROM depositos WHERE estado = 'activo'") as $d) {
            self::poblarInventario((int) $d['deposito_id'], $productoId);
        }
    }

    /** Enlace de mapa: el guardado, o uno de Google Maps a partir de las coordenadas. */
    public static function enlaceMapa(array $sucursal): ?string
    {
        if (!empty($sucursal['mapa_url'])) {
            return $sucursal['mapa_url'];
        }
        if (isset($sucursal['latitud'], $sucursal['longitud']) && $sucursal['latitud'] !== null && $sucursal['longitud'] !== null) {
            return 'https://www.google.com/maps?q=' . (float) $sucursal['latitud'] . ',' . (float) $sucursal['longitud'];
        }
        if (!empty($sucursal['direccion'])) {
            return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode((string) $sucursal['direccion']);
        }
        return null;
    }

    private static function coordenada(mixed $valor, int $limite, string $campo): ?float
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }
        $valor = str_replace(',', '.', $valor);
        if (!is_numeric($valor) || abs((float) $valor) > $limite) {
            throw new \RuntimeException("La {$campo} no es válida.");
        }
        return round((float) $valor, 7);
    }

    private static function buscar(array $filas, string $clave, ?int $id): ?array
    {
        if (!$id) {
            return null;
        }
        foreach ($filas as $f) {
            if ((int) $f[$clave] === $id) {
                return $f;
            }
        }
        return null;
    }
}

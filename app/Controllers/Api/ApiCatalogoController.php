<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Libraries\AuditService;
use App\Libraries\Auth;
use App\Libraries\Db;
use App\Libraries\NumeracionService;

/**
 * Catálogos simples de la API: categorías, proveedores y clientes.
 * Las bajas de proveedores y clientes desactivan (conservan historial).
 */
class ApiCatalogoController extends ApiController
{
    // ── Categorías (/api/v1/categorias) ──────────────────────────────────────

    public function categorias()
    {
        return $this->ok(['categorias' => Db::all(
            "SELECT c.*, (SELECT COUNT(*) FROM productos p WHERE p.categoria_id = c.categoria_id) AS total_productos
             FROM categorias_productos c WHERE c.empresa_id = ? ORDER BY c.nombre",
            [$this->empresaId()]
        )]);
    }

    public function categoria(int $id)
    {
        $categoria = $this->buscarCategoria($id);
        return $categoria ? $this->ok(['categoria' => $categoria]) : $this->noEncontrado('Categoría no encontrada');
    }

    public function crearCategoria()
    {
        $data   = $this->entrada();
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            return $this->fallo('El nombre es obligatorio.', 422);
        }

        $id = Db::insert('categorias_productos', [
            'empresa_id'  => $this->empresaId(),
            'nombre'      => $nombre,
            'descripcion' => (string) ($data['descripcion'] ?? ''),
            'padre_id'    => !empty($data['padre_id']) ? (int) $data['padre_id'] : null,
            'nivel'       => !empty($data['padre_id']) ? 2 : 1,
        ]);
        AuditService::log('inventario.categoria.crear', "Categoría creada por API: {$nombre}", $id, 'categoria');
        return $this->ok(['categoria_id' => $id], 'Categoría creada con éxito', 201);
    }

    public function actualizarCategoria(int $id)
    {
        if (!$this->buscarCategoria($id)) {
            return $this->noEncontrado('Categoría no encontrada');
        }
        $data  = $this->entrada();
        $datos = array_filter([
            'nombre'      => isset($data['nombre']) ? trim((string) $data['nombre']) : null,
            'descripcion' => $data['descripcion'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
        if (array_key_exists('padre_id', $data)) {
            $datos['padre_id'] = (int) $data['padre_id'] && (int) $data['padre_id'] !== $id ? (int) $data['padre_id'] : null;
        }
        if ($datos) {
            Db::update('categorias_productos', $datos, ['categoria_id' => $id]);
        }
        return $this->ok([], 'Categoría actualizada correctamente');
    }

    public function eliminarCategoria(int $id)
    {
        $categoria = $this->buscarCategoria($id);
        if (!$categoria) {
            return $this->noEncontrado('Categoría no encontrada');
        }
        Db::begin();
        Db::query("UPDATE productos SET categoria_id = NULL WHERE categoria_id = ? AND empresa_id = ?", [$id, $this->empresaId()]);
        Db::query("UPDATE categorias_productos SET padre_id = NULL WHERE padre_id = ?", [$id]);
        Db::query("DELETE FROM categorias_productos WHERE categoria_id = ?", [$id]);
        Db::commit();
        AuditService::log('inventario.categoria.eliminar', "Categoría eliminada por API: {$categoria['nombre']}", $id, 'categoria');
        return $this->ok([], 'Categoría eliminada correctamente');
    }

    private function buscarCategoria(int $id): ?array
    {
        return Db::row("SELECT * FROM categorias_productos WHERE categoria_id = ? AND empresa_id = ?", [$id, $this->empresaId()]);
    }

    // ── Proveedores (/api/v1/proveedores) ────────────────────────────────────

    public function proveedores()
    {
        return $this->listado('proveedores', 'proveedores');
    }

    public function buscarProveedores()
    {
        return $this->busqueda('proveedores', 'proveedores', ['nombre', 'ruc', 'codigo']);
    }

    public function proveedor(int $id)
    {
        $proveedor = $this->buscarFila('proveedores', 'proveedor_id', $id);
        return $proveedor ? $this->ok($proveedor) : $this->noEncontrado('Proveedor no encontrado');
    }

    public function crearProveedor()
    {
        return $this->crearContacto('proveedores', 'proveedor', 'proveedor_id', 'inventario.proveedor');
    }

    public function actualizarProveedor(int $id)
    {
        return $this->actualizarContacto('proveedores', 'proveedor_id', $id, 'Proveedor');
    }

    public function eliminarProveedor(int $id)
    {
        return $this->desactivar('proveedores', 'proveedor_id', $id, 'Proveedor');
    }

    // ── Clientes (/api/v1/clientes) ──────────────────────────────────────────

    public function clientes()
    {
        return $this->listado('clientes', 'clientes');
    }

    public function buscarClientes()
    {
        return $this->busqueda('clientes', 'clientes', ['nombre', 'ruc', 'codigo', 'telefono']);
    }

    public function cliente(int $id)
    {
        $cliente = $this->buscarFila('clientes', 'cliente_id', $id);
        if (!$cliente) {
            return $this->noEncontrado('Cliente no encontrado');
        }
        $cliente['credito_disponible'] = max(0, (float) $cliente['limite_credito'] - (float) $cliente['saldo_pendiente']);
        return $this->ok($cliente);
    }

    public function crearCliente()
    {
        return $this->crearContacto('clientes', 'cliente', 'cliente_id', 'clientes');
    }

    public function actualizarCliente(int $id)
    {
        return $this->actualizarContacto('clientes', 'cliente_id', $id, 'Cliente');
    }

    public function eliminarCliente(int $id)
    {
        return $this->desactivar('clientes', 'cliente_id', $id, 'Cliente');
    }

    // ── Comunes a proveedores y clientes ─────────────────────────────────────

    private function listado(string $tabla, string $clave)
    {
        [$pagina, $porPagina, $offset] = $this->paginacion();
        $estado = $this->request->getGet('estado') === 'inactivo' ? 'inactivo' : 'activo';

        return $this->ok([
            $clave     => Db::all("SELECT * FROM {$tabla} WHERE empresa_id = ? AND estado = ? ORDER BY nombre LIMIT {$porPagina} OFFSET {$offset}", [$this->empresaId(), $estado]),
            'total'    => (int) Db::value("SELECT COUNT(*) FROM {$tabla} WHERE empresa_id = ? AND estado = ?", [$this->empresaId(), $estado]),
            'page'     => $pagina,
            'per_page' => $porPagina,
        ]);
    }

    private function busqueda(string $tabla, string $clave, array $campos)
    {
        $q = trim((string) ($this->request->getGet('q') ?? ''));
        if ($q === '') {
            return $this->ok([$clave => []]);
        }
        $cond = implode(' OR ', array_map(fn ($c) => "{$c} LIKE ?", $campos));
        return $this->ok([$clave => Db::all(
            "SELECT * FROM {$tabla} WHERE empresa_id = ? AND estado = 'activo' AND ({$cond}) ORDER BY nombre LIMIT 20",
            [$this->empresaId(), ...array_fill(0, count($campos), "%{$q}%")]
        )]);
    }

    private function buscarFila(string $tabla, string $pk, int $id): ?array
    {
        return Db::row("SELECT * FROM {$tabla} WHERE {$pk} = ? AND empresa_id = ?", [$id, $this->empresaId()]);
    }

    /** Campos editables comunes de proveedores y clientes. */
    private function camposContacto(string $tabla, array $data): array
    {
        $campos = ['codigo', 'nombre', 'ruc', 'dv', 'direccion', 'telefono', 'email'];
        $campos = array_merge($campos, $tabla === 'clientes' ? ['tipo', 'limite_credito', 'dias_credito'] : ['contacto']);

        $datos = [];
        foreach ($campos as $c) {
            if (array_key_exists($c, $data)) {
                $datos[$c] = in_array($c, ['limite_credito', 'dias_credito'], true) ? (float) $data[$c] : trim((string) $data[$c]);
            }
        }
        if ($tabla === 'clientes' && array_key_exists('itbms', $data)) {
            $datos['itbms'] = in_array($data['itbms'], ['NO', 0, '0', false], true) ? 0.00 : 7.00;
        }
        return $datos;
    }

    private function crearContacto(string $tabla, string $tipoNumeracion, string $pk, string $modulo)
    {
        $data  = $this->entrada();
        $datos = $this->camposContacto($tabla, $data);
        if (($datos['nombre'] ?? '') === '') {
            return $this->fallo('El nombre es obligatorio.', 422);
        }
        if (($datos['ruc'] ?? '') !== '' && Db::value("SELECT 1 FROM {$tabla} WHERE ruc = ? AND empresa_id = ?", [$datos['ruc'], $this->empresaId()])) {
            return $this->fallo("Ya existe un registro con el RUC '{$datos['ruc']}'.", 422);
        }

        $datos['codigo'] = ($datos['codigo'] ?? '') ?: NumeracionService::siguiente($tipoNumeracion, $this->empresaId());
        $extra = ['empresa_id' => $this->empresaId(), 'estado' => 'activo', 'fecha_registro' => date('Y-m-d H:i:s')];
        if ($tabla === 'clientes') {
            $extra += ['saldo' => 0, 'vendedor_id' => Auth::id()];
        }

        $id = Db::insert($tabla, $datos + $extra);
        AuditService::log("{$modulo}.crear", ucfirst($tipoNumeracion) . " creado por API: {$datos['nombre']}", $id, $tipoNumeracion);
        return $this->ok([$pk => $id, 'codigo' => $datos['codigo']], ucfirst($tipoNumeracion) . ' creado correctamente', 201);
    }

    private function actualizarContacto(string $tabla, string $pk, int $id, string $etiqueta)
    {
        if (!$this->buscarFila($tabla, $pk, $id)) {
            return $this->noEncontrado("{$etiqueta} no encontrado");
        }
        $datos = $this->camposContacto($tabla, $this->entrada());
        if (array_key_exists('nombre', $datos) && $datos['nombre'] === '') {
            return $this->fallo('El nombre no puede quedar vacío.', 422);
        }
        if (($datos['ruc'] ?? '') !== '' && Db::value("SELECT 1 FROM {$tabla} WHERE ruc = ? AND empresa_id = ? AND {$pk} <> ?", [$datos['ruc'], $this->empresaId(), $id])) {
            return $this->fallo("Ya existe un registro con el RUC '{$datos['ruc']}'.", 422);
        }
        if ($datos) {
            Db::update($tabla, $datos, [$pk => $id]);
        }
        return $this->ok([], "{$etiqueta} actualizado correctamente");
    }

    private function desactivar(string $tabla, string $pk, int $id, string $etiqueta)
    {
        if (!$this->buscarFila($tabla, $pk, $id)) {
            return $this->noEncontrado("{$etiqueta} no encontrado");
        }
        Db::update($tabla, ['estado' => 'inactivo'], [$pk => $id]);
        return $this->ok([], "{$etiqueta} desactivado correctamente");
    }
}

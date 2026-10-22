<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Model - Base Model con Active Record Pattern
 * Implements ArrayAccess para compatibilidad con templates que usan $model['key']
 */
abstract class Model implements \ArrayAccess
{
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * Acceso mágico a atributos
     */
    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    // ArrayAccess interface
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->attributes[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->attributes[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }

    /**
     * Obtener todos los atributos
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Obtener DB
     */
    protected static function db(): Database
    {
        return new Database();
    }

    /**
     * Crear nueva instancia estática
     */
    protected static function newInstance(array $attributes = []): static
    {
        return new static($attributes);
    }

    /**
     * Obtener nombre de tabla
     */
    public static function getTable(): string
    {
        return (new static())->table;
    }

    /**
     * Obtener Primary Key name
     */
    public static function getPrimaryKey(): string
    {
        return (new static())->primaryKey;
    }

    /**
     * Buscar por ID
     */
    public static function find(int $id): ?static
    {
        $instance = new static();
        $stmt = Database::query(
            "SELECT * FROM {$instance->table} WHERE {$instance->primaryKey} = ? LIMIT 1",
            [$id]
        );
        $row = $stmt->fetch();
        return $row ? new static($row) : null;
    }

    /**
     * Buscar por ID o lanzar excepción
     */
    public static function findOrFail(int $id): static
    {
        $result = static::find($id);
        if (!$result) {
            http_response_code(404);
            throw new \RuntimeException("Registro no encontrado con ID: {$id}");
        }
        return $result;
    }

    /**
     * Obtener todos los registros
     */
    public static function all(string $orderBy = ''): array
    {
        $instance = new static();
        $sql = "SELECT * FROM {$instance->table}";
        if ($orderBy) $sql .= " ORDER BY {$orderBy}";

        $stmt = Database::query($sql);
        return array_map(fn($row) => new static($row), $stmt->fetchAll());
    }

    /**
     * Buscar con condiciones
     * Puede recibir (columna, valor) o ([col1 => val1, col2 => val2])
     */
    public static function where(string|array $conditions, mixed $value = null, string $operator = '=', string $orderBy = ''): array
    {
        $instance = new static();
        $sql = "SELECT * FROM {$instance->table} WHERE ";
        $params = [];

        if (is_array($conditions)) {
            $whereParts = [];
            foreach ($conditions as $col => $val) {
                $whereParts[] = "{$col} = ?";
                $params[] = $val;
            }
            $sql .= implode(' AND ', $whereParts);
        } else {
            $sql .= "{$conditions} {$operator} ?";
            $params[] = $value;
        }

        if ($orderBy) $sql .= " ORDER BY {$orderBy}";

        $stmt = Database::query($sql, $params);
        return array_map(fn($row) => new static($row), $stmt->fetchAll());
    }

    /**
     * Buscar uno con condiciones
     */
    public static function whereFirst(string|array $conditions, mixed $value = null, string $operator = '='): ?static
    {
        $instance = new static();
        $sql = "SELECT * FROM {$instance->table} WHERE ";
        $params = [];

        if (is_array($conditions)) {
            $whereParts = [];
            foreach ($conditions as $col => $val) {
                $whereParts[] = "{$col} = ?";
                $params[] = $val;
            }
            $sql .= implode(' AND ', $whereParts);
        } else {
            $sql .= "{$conditions} {$operator} ?";
            $params[] = $value;
        }

        $sql .= " LIMIT 1";

        $stmt = Database::query($sql, $params);
        $row = $stmt->fetch();
        return $row ? new static($row) : null;
    }

    /**
     * Consulta personalizada
     */
    public static function query(string $sql, array $params = []): array
    {
        $stmt = Database::query($sql, $params);
        return array_map(fn($row) => new static($row), $stmt->fetchAll());
    }

    /**
     * Consulta raw que retorna arrays
     */
    public static function rawQuery(string $sql, array $params = []): array
    {
        $stmt = Database::query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Contar registros
     */
    public static function count(string $where = '', array $params = []): int
    {
        $instance = new static();
        $sql = "SELECT COUNT(*) as total FROM {$instance->table}";
        if ($where) $sql .= " WHERE {$where}";

        $stmt = Database::query($sql, $params);
        return (int) $stmt->fetch()['total'];
    }

    /**
     * Crear registro
     */
    public static function create(array $data): static
    {
        $instance = new static();
        $fillable = $instance->fillable;

        // Filtrar solo campos permitidos
        if (!empty($fillable)) {
            $data = array_intersect_key($data, array_flip($fillable));
        }

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        Database::query(
            "INSERT INTO {$instance->table} ({$columns}) VALUES ({$placeholders})",
            array_values($data)
        );

        $id = (int) Database::lastInsertId();
        return static::find($id) ?? new static(array_merge($data, [$instance->primaryKey => $id]));
    }

    /**
     * Actualizar registro
     */
    public function update(array $data): bool
    {
        $fillable = $this->fillable;

        if (!empty($fillable)) {
            $data = array_intersect_key($data, array_flip($fillable));
        }

        $sets = implode(', ', array_map(fn($col) => "{$col} = ?", array_keys($data)));
        $values = array_values($data);
        $values[] = $this->attributes[$this->primaryKey];

        Database::query(
            "UPDATE {$this->table} SET {$sets} WHERE {$this->primaryKey} = ?",
            $values
        );

        // Actualizar atributos locales
        $this->attributes = array_merge($this->attributes, $data);
        return true;
    }

    /**
     * Eliminar registro
     */
    public function delete(): bool
    {
        Database::query(
            "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?",
            [$this->attributes[$this->primaryKey]]
        );
        return true;
    }

    /**
     * Eliminar por ID (estático)
     */
    public static function destroy(int $id): bool
    {
        $instance = new static();
        Database::query(
            "DELETE FROM {$instance->table} WHERE {$instance->primaryKey} = ?",
            [$id]
        );
        return true;
    }

    /**
     * Paginación
     */
    public static function paginate(int $perPage = 25, int $page = 1, string $where = '', array $params = [], string $orderBy = ''): array
    {
        $instance = new static();
        $offset = ($page - 1) * $perPage;

        // Contar total
        $countSql = "SELECT COUNT(*) as total FROM {$instance->table}";
        if ($where) $countSql .= " WHERE {$where}";
        $total = (int) Database::query($countSql, $params)->fetch()['total'];

        // Obtener registros
        $sql = "SELECT * FROM {$instance->table}";
        if ($where) $sql .= " WHERE {$where}";
        if ($orderBy) $sql .= " ORDER BY {$orderBy}";
        $sql .= " LIMIT {$perPage} OFFSET {$offset}";

        $stmt = Database::query($sql, $params);
        $items = array_map(fn($row) => new static($row), $stmt->fetchAll());

        $totalPages = (int) ceil($total / $perPage);

        return [
            'items' => $items,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'has_previous' => $page > 1,
            'has_next' => $page < $totalPages,
            'previous_page' => max(1, $page - 1),
            'next_page' => min($totalPages, $page + 1),
        ];
    }
}

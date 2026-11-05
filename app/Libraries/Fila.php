<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Fila de base de datos accesible como objeto ($fila->campo) y como arreglo ($fila['campo']),
 * igual que los modelos del sistema anterior. Solo para vistas portadas que usan ambos
 * estilos; el código nuevo trabaja con arreglos.
 */
final class Fila implements \ArrayAccess, \JsonSerializable
{
    public function __construct(private array $datos = [])
    {
    }

    /** @return list<self> */
    public static function lista(array $filas): array
    {
        return array_map(fn (array $f) => new self($f), $filas);
    }

    public static function o(?array $fila): ?self
    {
        return $fila === null ? null : new self($fila);
    }

    public function __get(string $campo): mixed
    {
        return $this->datos[$campo] ?? null;
    }

    public function __set(string $campo, mixed $valor): void
    {
        $this->datos[$campo] = $valor;
    }

    public function __isset(string $campo): bool
    {
        return isset($this->datos[$campo]);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->datos[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->datos[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->datos[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->datos[$offset]);
    }

    public function toArray(): array
    {
        return $this->datos;
    }

    public function jsonSerialize(): array
    {
        return $this->datos;
    }
}

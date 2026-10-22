<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Tenant - Stub vacío. Este sistema es de empresa única, no SaaS.
 * Mantenido solo para compatibilidad de clases durante la transición.
 */
class Tenant
{
    public static function identify(Request $request): void {}
    public static function isActive(): bool { return true; }
    public static function hasModule(string $slug): bool { return true; }
    public static function current(): ?array { return null; }
    public static function getModules(): array { return []; }
}

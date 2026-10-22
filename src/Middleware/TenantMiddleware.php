<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;

/**
 * TenantMiddleware - Stub vacío. Sistema de empresa única, no hay tenant que resolver.
 */
class TenantMiddleware
{
    public function handle(Request $request): bool
    {
        return true;
    }
}

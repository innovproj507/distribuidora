<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;

/**
 * SaaSLimitMiddleware - Stub vacío. Sistema de empresa única, sin límites de plan.
 */
class SaaSLimitMiddleware
{
    public function handle(Request $request): bool
    {
        return true;
    }
}

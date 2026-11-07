<?php

/**
 * Helpers globales de la aplicación (autocargados en Config\Autoload::$helpers).
 */

if (!function_exists('flash')) {
    /**
     * Agrega un mensaje flash para el siguiente request.
     * Tipos: success | danger | warning | info (los layouts leen $flash[tipo]).
     */
    function flash(string $tipo, string $mensaje): void
    {
        $mensajes = session()->get('_flash') ?? [];
        $mensajes[$tipo][] = $mensaje;
        session()->set('_flash', $mensajes);
    }
}

if (!function_exists('flash_consumir')) {
    /** Devuelve y limpia los mensajes flash pendientes. */
    function flash_consumir(): array
    {
        $mensajes = session()->get('_flash') ?? [];
        session()->remove('_flash');
        return $mensajes;
    }
}

if (!function_exists('paginacion')) {
    /** Datos que espera la vista partials/pagination. */
    function paginacion(int $total, int $pagina, int $porPagina): array
    {
        $paginas = (int) ceil($total / max(1, $porPagina));
        return [
            'total'         => $total,
            'current_page'  => $pagina,
            'total_pages'   => $paginas,
            'has_previous'  => $pagina > 1,
            'has_next'      => $pagina < $paginas,
            'previous_page' => $pagina - 1,
            'next_page'     => $pagina + 1,
        ];
    }
}

if (!function_exists('is_active')) {
    /** True si la URL actual empieza con $patron (para resaltar menús). */
    function is_active(string $patron): bool
    {
        $actual = '/' . trim(service('request')->getUri()->getPath(), '/');
        if ($patron === '/') {
            return $actual === '/';
        }
        return str_starts_with($actual, rtrim($patron, '*'));
    }
}

if (!function_exists('tasa_itbms')) {
    /** Tasa de ITBMS de las ventas (variable ITBMS_RATE del .env; 0 = el negocio no cobra ITBMS). */
    function tasa_itbms(): float
    {
        return max(0.0, (float) env('ITBMS_RATE', 0.07));
    }
}

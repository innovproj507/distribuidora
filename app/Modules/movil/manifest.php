<?php
return [
    'label'        => 'Móvil (PWA)',
    'version'      => '1.0.0',
    'icon'         => 'fas fa-mobile-alt',
    'color'        => 'sky',
    'hex'          => '#1D4FD6',
    'description'  => 'Versión móvil instalable (PWA) en /m: vender, inventario, clientes y compras.',
    'category'     => 'Ventas',
    'depends'      => ['core', 'inventario', 'ventas', 'clientes'],
    'auto_install' => false,
    'menu_order'   => 90,
    // Sin entradas en el sidebar web: la PWA tiene su propia navegación inferior.
    'menu'         => [],
];

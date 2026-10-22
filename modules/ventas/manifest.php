<?php
return [
    'label'        => 'Ventas',
    'version'      => '1.0.0',
    'icon'         => 'fas fa-cash-register',
    'color'        => 'emerald',
    'hex'          => '#10b981',
    'description'  => 'Punto de venta (POS), historial de ventas, facturación y cotizaciones a clientes.',
    'category'     => 'Ventas',
    'depends'      => ['core', 'inventario', 'clientes'],
    'auto_install' => false,
    'menu_order'   => 5,
    'menu' => [
        ['type' => 'section', 'label' => 'Ventas'],
        ['type' => 'link', 'label' => 'Punto de Venta',    'icon' => 'fas fa-cash-register',  'href' => '/ventas/pos'],
        ['type' => 'link', 'label' => 'Historial Ventas',  'icon' => 'fas fa-shopping-cart',  'href' => '/ventas'],
        ['type' => 'link', 'label' => 'Cotizaciones',      'icon' => 'fas fa-file-invoice',   'href' => '/ventas/cotizaciones'],
    ],
];

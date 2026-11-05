<?php
return [
    'label'        => 'Clientes',
    'version'      => '1.0.0',
    'icon'         => 'fas fa-users',
    'color'        => 'violet',
    'hex'          => '#8b5cf6',
    'description'  => 'Directorio de clientes, cuentas por cobrar y gestión de crédito.',
    'category'     => 'Ventas',
    'depends'      => ['core'],
    'auto_install' => false,
    'menu_order'   => 30,
    'menu' => [
        ['type' => 'section', 'label' => 'Clientes'],
        ['type' => 'link', 'label' => 'Clientes',            'icon' => 'fas fa-users',             'href' => '/clientes'],
        ['type' => 'link', 'label' => 'Cuentas por Cobrar',  'icon' => 'fas fa-hand-holding-usd',  'href' => '/clientes/cuentas-por-cobrar'],
    ],
];

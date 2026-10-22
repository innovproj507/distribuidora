<?php
return [
    'label'        => 'Reportes',
    'version'      => '1.0.0',
    'icon'         => 'fas fa-chart-bar',
    'color'        => 'amber',
    'hex'          => '#f59e0b',
    'description'  => 'Reportes de ventas por período, productos más vendidos, inventario actual y clientes top.',
    'category'     => 'Análisis',
    'depends'      => ['core', 'ventas', 'inventario'],
    'auto_install' => false,
    'menu_order'   => 40,
    'menu' => [
        ['type' => 'section', 'label' => 'Reportes'],
        ['type' => 'link', 'label' => 'Reportes', 'icon' => 'fas fa-chart-bar', 'href' => '/reportes'],
    ],
];

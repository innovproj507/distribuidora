<?php
return [
    'label'       => 'Mantenimiento',
    'version'     => '1.0.0',
    'icon'        => 'fas fa-tools',
    'color'       => 'amber',
    'hex'         => '#f59e0b',
    'description' => 'Plan preventivo de mantenimiento de software y sistemas.',
    'category'    => 'Operaciones',
    'depends'     => ['core'],
    'auto_install' => false,
    'menu_order'  => 60,
    'menu' => [
        ['type' => 'section', 'label' => 'Mantenimiento'],
        ['type' => 'link', 'label' => 'Panel',          'icon' => 'fas fa-tachometer-alt',  'href' => '/mantenimiento'],
        ['type' => 'link', 'label' => 'Software',       'icon' => 'fas fa-laptop-code',     'href' => '/mantenimiento/software'],
        ['type' => 'link', 'label' => 'Plan Preventivo','icon' => 'fas fa-calendar-check',  'href' => '/mantenimiento/tareas'],
    ],
];

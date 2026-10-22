<?php
return [
    'label'        => 'Core',
    'version'      => '1.0.0',
    'icon'         => 'fas fa-cube',
    'color'        => 'slate',
    'hex'          => '#64748b',
    'description'  => 'Módulo base del sistema: autenticación, usuarios, configuración del sistema y bitácora de actividad.',
    'category'     => 'Sistema',
    'depends'      => [],
    'auto_install' => true,
    'menu_order'   => 0,

    // Items superiores (siempre primero en el sidebar)
    'menu_top' => [
        ['type' => 'section', 'label' => 'Principal'],
        ['type' => 'link', 'label' => 'Dashboard', 'icon' => 'fas fa-home', 'href' => '/'],
    ],

    // Items de módulo propios (vacío — core no ocupa sección media)
    'menu' => [],

    // Items inferiores (siempre al final del sidebar)
    'menu_bottom' => [
        ['type' => 'section', 'label' => 'Administración'],
        ['type' => 'link', 'label' => 'Configuración',   'icon' => 'fas fa-cog',        'href' => '/configuracion'],
        ['type' => 'link', 'label' => 'Usuarios y Roles','icon' => 'fas fa-users-cog',  'href' => '/usuarios',  'permission' => 'usuarios.ver'],
        ['type' => 'link', 'label' => 'Bitácora',        'icon' => 'fas fa-history',    'href' => '/bitacora',  'permission' => 'bitacora.ver'],
        ['type' => 'link', 'label' => 'Aplicaciones',   'icon' => 'fas fa-th-large',   'href' => '/apps',      'superuser'  => true],
    ],
];

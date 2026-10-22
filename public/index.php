<?php
/**
 * Front Controller - Entry Point
 * Sistema POS - PHP 8.2
 */

define('BASE_PATH', dirname(__DIR__));
define('APP_VERSION', '1.0.0');

// Autoload de Composer
require_once BASE_PATH . '/vendor/autoload.php';

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->load();

// Iniciar la aplicación
$app = new App\Core\Application();
$app->run();

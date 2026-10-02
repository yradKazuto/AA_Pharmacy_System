<?php

/**
 * Entry point for the AA Pharmacy System.
 * Sets up autoloading, configuration, sessions, CSRF, and routing.
 */

// Basic Autoloader for the App\ namespace
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Load configurations
$appConfig = require __DIR__ . '/../config/app.php';

// Set timezone
date_default_timezone_set($appConfig['timezone']);

// Secure session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 3600);

// Start session
session_name($appConfig['session_name']);
session_start();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Create router instance
$router = new \App\Core\Router();

// Load routes (will use the $router instance above)
require __DIR__ . '/../routes/web.php';

// Dispatch the request
$router->dispatch();
<?php

use App\Core\Router;

// NOTE: the $router instance is provided by public/index.php.

// Authentication Routes
$router->get('/login', 'AuthController@login');
$router->post('/login', 'AuthController@doLogin');
$router->get('/logout', 'AuthController@logout');
$router->get('/password-recovery', 'AuthController@passwordRecovery');
$router->post('/password-recovery', 'AuthController@doPasswordRecovery');

// User Management Routes (Admin only)
$router->get('/users', 'UserController@index');
$router->get('/users/create', 'UserController@create');
$router->post('/users', 'UserController@store');
$router->get('/users/{id}/edit', 'UserController@edit');
$router->post('/users/{id}', 'UserController@update');
$router->post('/users/{id}/delete', 'UserController@destroy');

// Role Management Routes (Admin only)
$router->get('/roles', 'RoleController@index');
$router->get('/roles/create', 'RoleController@create');
$router->post('/roles', 'RoleController@store');
$router->get('/roles/{id}/edit', 'RoleController@edit');
$router->post('/roles/{id}', 'RoleController@update');
$router->post('/roles/{id}/delete', 'RoleController@destroy');

// Dashboard Routes (will be implemented in later phases)
// All dashboards require authentication
use App\Middleware\AuthMiddleware;

$router->get('/dashboard', function() {
    AuthMiddleware::guard();
    echo "<h1>Dashboard</h1>";
    echo "<p>Welcome to the dashboard. Implement dashboard views in later phases.</p>";
});

// Role-specific dashboards (placeholders)
$router->get('/admin/dashboard', function() {
    AuthMiddleware::guard();
    echo "<h1>Admin Dashboard</h1>";
    echo "<p>Admin dashboard view.</p>";
});

$router->get('/pharmacist/dashboard', function() {
    AuthMiddleware::guard();
    echo "<h1>Pharmacist Dashboard</h1>";
    echo "<p>Pharmacist dashboard view.</p>";
});

$router->get('/cashier/dashboard', function() {
    AuthMiddleware::guard();
    echo "<h1>Cashier Dashboard</h1>";
    echo "<p>Cashier dashboard view.</p>";
});

$router->get('/customer/dashboard', function() {
    AuthMiddleware::guard();
    echo "<h1>Customer Dashboard</h1>";
    echo "<p>Customer dashboard view (online ordering portal).</p>";
});

// Unauthorized access page
$router->get('/unauthorized', function() {
    echo "<h1>403 Unauthorized</h1>";
    echo "<p>You do not have permission to access this page.</p>";
    echo "<p><a href='/login'>Return to login</a></p>";
});

// Home route
$router->get('/', function() use ($router) {
    // Redirect to login if not authenticated
    if (empty($_SESSION['user_id'])) {
        $router->redirect('/login');
    } else {
        // Redirect to role-specific dashboard
        $role = $_SESSION['role'] ?? 'guest';
        $router->redirect("/{$role}/dashboard");
    }
});
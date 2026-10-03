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

// Product Routes (inventory:view read, inventory:edit mutations)
$router->get('/products', 'ProductController@index');
$router->get('/products/create', 'ProductController@create');
$router->post('/products', 'ProductController@store');
$router->get('/products/{id}/edit', 'ProductController@edit');
$router->post('/products/{id}', 'ProductController@update');
$router->post('/products/{id}/delete', 'ProductController@destroy');

// Batch Routes (receive stock / retire batch)
$router->get('/products/{id}/batches', 'BatchController@index');
$router->post('/products/{id}/batches', 'BatchController@store');
$router->post('/batches/{id}/delete', 'BatchController@destroy');

// Inventory Routes
$router->get('/inventory', 'InventoryController@index');
$router->get('/inventory/alerts', 'InventoryController@alerts');
$router->post('/inventory/batches/{id}/adjust', 'InventoryController@adjust');

// Supplier Routes (purchasing:view read, purchasing:edit mutations)
$router->get('/suppliers', 'SupplierController@index');
$router->get('/suppliers/create', 'SupplierController@create');
$router->post('/suppliers', 'SupplierController@store');
$router->get('/suppliers/{id}/edit', 'SupplierController@edit');
$router->post('/suppliers/{id}', 'SupplierController@update');
$router->post('/suppliers/{id}/delete', 'SupplierController@destroy');

// Purchase Order Routes
$router->get('/purchases', 'PurchaseOrderController@index');
$router->get('/purchases/create', 'PurchaseOrderController@create');
$router->post('/purchases', 'PurchaseOrderController@store');
$router->get('/purchases/suggestions', 'PurchaseOrderController@suggestions');
$router->get('/purchases/{id}', 'PurchaseOrderController@show');
$router->get('/purchases/{id}/receive', 'PurchaseOrderController@receive');
$router->post('/purchases/{id}/receive', 'PurchaseOrderController@storeReceive');
$router->post('/purchases/{id}/cancel', 'PurchaseOrderController@cancel');

// POS / Sale Routes (pos:process, void requires inventory:edit per controller)
$router->get('/pos', 'SaleController@index');
$router->get('/pos/search', 'SaleController@search');
$router->get('/pos/sales', 'SaleController@list');
$router->post('/pos/checkout', 'SaleController@store');
$router->get('/sales/{id}', 'SaleController@show');
$router->post('/sales/{id}/void', 'SaleController@void');

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
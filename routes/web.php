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

// Dashboard Routes (real per-role dashboards, Phase 6)
$router->get('/dashboard', function() {
    // Redirect to the caller's role dashboard (guessing from session on the '/' lander
    // is handled there; here just bounce authenticated users to their role page).
    $role = $_SESSION['role'] ?? 'guest';
    $path = in_array($role, ['admin', 'pharmacist', 'cashier', 'customer']) ? "/{$role}/dashboard" : '/login';
    header("Location: " . $path);
    exit;
});

$router->get('/admin/dashboard', 'DashboardController@admin');
$router->get('/pharmacist/dashboard', 'DashboardController@pharmacist');
$router->get('/cashier/dashboard', 'DashboardController@cashier');

// Customer dashboard stays a placeholder until Phase 7 (online ordering).
use App\Middleware\AuthMiddleware;

$router->get('/customer/dashboard', function() {
    AuthMiddleware::guard();
    echo "<h1>Customer Dashboard</h1>";
    echo "<p>Customer dashboard view (online ordering portal in Phase 7).</p>";
});

// Report Routes (reports:view; read-only)
$router->get('/reports', 'ReportController@index');
$router->get('/reports/sales', 'ReportController@sales');
$router->get('/reports/valuation', 'ReportController@valuation');
$router->get('/reports/movements', 'ReportController@movements');

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
<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\RoleMiddleware;
use App\Models\Product;
use App\Services\StockService;

class ProductController extends Controller
{
    /**
     * Display the list of products with current stock.
     *
     * @return void
     */
    public function index()
    {
        RoleMiddleware::requirePermission('inventory:view', '/unauthorized');

        $productModel = new Product();
        $products = $productModel->getAllWithStock();

        $this->view('products/index', [
            'products' => $products
        ]);
    }

    /**
     * Show the form to create a new product.
     *
     * @return void
     */
    public function create()
    {
        RoleMiddleware::requirePermission('inventory:edit', '/unauthorized');

        $this->view('products/form', [
            'product' => null
        ]);
    }

    /**
     * Store a newly created product.
     *
     * @return void
     */
    public function store()
    {
        RoleMiddleware::requirePermission('inventory:edit', '/unauthorized');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/products/create');
            return;
        }

        if (!self::verifyCsrfToken($_POST['_csrf_token'] ?? '')) {
            $this->redirect('/products/create?error=Invalid CSRF token.');
            return;
        }

        $data = self::inputData();

        $errors = self::validate($data);

        // Check for duplicate product name.
        if (empty($errors) && !empty($data['name'])) {
            $productModel = new Product();
            if ($productModel->findByName($data['name'])) {
                $errors[] = 'A product with this name already exists.';
            }
        }

        if (!empty($errors)) {
            $this->view('products/form', [
                'errors' => $errors,
                'product' => $data
            ]);
            return;
        }

        $productModel = new Product();
        $stmt = $productModel->getDb()->prepare(
            "INSERT INTO products (name, generic_name, category, unit, unit_price, requires_prescription, is_active)
             VALUES (?, ?, ?, ?, ?, ?, 1)"
        );
        $result = $stmt->execute([
            $data['name'],
            $data['generic_name'],
            $data['category'],
            $data['unit'],
            $data['unit_price'],
            $data['requires_prescription']
        ]);

        if ($result) {
            $auditLog = new \App\Models\AuditLog();
            $auditLog->log([
                'user_id' => $_SESSION['user_id'],
                'action' => 'product_create',
                'table_affected' => 'products',
                'record_id' => $productModel->getDb()->lastInsertId(),
                'description' => "Product created: {$data['name']}",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
            $this->redirect('/products?success=Product created successfully.');
        } else {
            $this->view('products/form', [
                'error' => 'Failed to create product.',
                'product' => $data
            ]);
        }
    }

    /**
     * Show the form to edit a product.
     *
     * @param int $id
     * @return void
     */
    public function edit($id)
    {
        RoleMiddleware::requirePermission('inventory:view', '/unauthorized');

        $productId = (int)$id;
        $productModel = new Product();
        $product = $productModel->find($productId);

        if (!$product) {
            $this->redirect('/products?error=Product not found.');
            return;
        }

        $this->view('products/form', [
            'product' => $product
        ]);
    }

    /**
     * Update the specified product.
     *
     * @param int $id
     * @return void
     */
    public function update($id)
    {
        RoleMiddleware::requirePermission('inventory:edit', '/unauthorized');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect("/products/{$id}/edit");
            return;
        }

        if (!self::verifyCsrfToken($_POST['_csrf_token'] ?? '')) {
            $this->redirect("/products/{$id}/edit?error=Invalid CSRF token.");
            return;
        }

        $productId = (int)$id;
        $productModel = new Product();
        $existing = $productModel->find($productId);

        if (!$existing) {
            $this->redirect('/products?error=Product not found.');
            return;
        }

        $data = self::inputData();
        $errors = self::validate($data, $productId);

        // Duplicate name, excluding current product.
        if (empty($errors) && !empty($data['name'])) {
            $dup = $productModel->findByName($data['name']);
            if ($dup && (int)$dup['id'] !== $productId) {
                $errors[] = 'A product with this name already exists.';
            }
        }

        if (!empty($errors)) {
            $this->view('products/form', [
                'errors' => $errors,
                'product' => array_merge($data, ['id' => $productId])
            ]);
            return;
        }

        $stmt = $productModel->getDb()->prepare(
            "UPDATE products SET
                name = ?, generic_name = ?, category = ?, unit = ?,
                unit_price = ?, requires_prescription = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        );
        $result = $stmt->execute([
            $data['name'],
            $data['generic_name'],
            $data['category'],
            $data['unit'],
            $data['unit_price'],
            $data['requires_prescription'],
            $productId
        ]);

        if ($result) {
            $auditLog = new \App\Models\AuditLog();
            $auditLog->log([
                'user_id' => $_SESSION['user_id'],
                'action' => 'product_update',
                'table_affected' => 'products',
                'record_id' => $productId,
                'description' => "Product updated: {$data['name']}",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
            $this->redirect('/products?success=Product updated successfully.');
        } else {
            $this->view('products/form', [
                'error' => 'Failed to update product.',
                'product' => array_merge($data, ['id' => $productId])
            ]);
        }
    }

    /**
     * Soft-deactivate a product.
     *
     * @param int $id
     * @return void
     */
    public function destroy($id)
    {
        RoleMiddleware::requirePermission('inventory:edit', '/unauthorized');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/products');
            return;
        }

        if (!self::verifyCsrfToken($_POST['_csrf_token'] ?? '')) {
            $this->redirect('/products?error=Invalid CSRF token.');
            return;
        }

        $productId = (int)$id;
        $productModel = new Product();
        $product = $productModel->find($productId);

        if (!$product) {
            $this->redirect('/products?error=Product not found.');
            return;
        }

        $result = $productModel->deactivate($productId);

        if ($result) {
            $auditLog = new \App\Models\AuditLog();
            $auditLog->log([
                'user_id' => $_SESSION['user_id'],
                'action' => 'product_deactivate',
                'table_affected' => 'products',
                'record_id' => $productId,
                'description' => "Product deactivated: {$product['name']}",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
            $this->redirect('/products?success=Product deactivated successfully.');
        } else {
            $this->redirect('/products?error=Failed to deactivate product.');
        }
    }

    /**
     * Gather and sanitize form input.
     *
     * @return array
     */
    private static function inputData()
    {
        return [
            'name' => trim($_POST['name'] ?? ''),
            'generic_name' => trim($_POST['generic_name'] ?? ''),
            'category' => trim($_POST['category'] ?? ''),
            'unit' => trim($_POST['unit'] ?? 'pc'),
            'unit_price' => (float)($_POST['unit_price'] ?? 0),
            'requires_prescription' => isset($_POST['requires_prescription']) ? 1 : 0,
        ];
    }

    /**
     * Validate product input.
     *
     * @param array $data
     * @param int|null $ignoreId Used on update not needed here except signature consistency.
     * @return array list of error strings
     */
    private static function validate(array $data, $ignoreId = null)
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors[] = 'Product name is required.';
        } elseif (strlen($data['name']) > 100) {
            $errors[] = 'Product name must be less than 100 characters.';
        }

        if (strlen($data['generic_name']) > 100) {
            $errors[] = 'Generic name must be less than 100 characters.';
        }

        if (strlen($data['category']) > 50) {
            $errors[] = 'Category must be less than 50 characters.';
        }

        if (empty($data['unit'])) {
            $errors[] = 'Unit is required.';
        } elseif (strlen($data['unit']) > 20) {
            $errors[] = 'Unit must be less than 20 characters.';
        }

        if ($data['unit_price'] < 0) {
            $errors[] = 'Unit price cannot be negative.';
        }

        return $errors;
    }

    /**
     * Verify CSRF token.
     *
     * @param string $token
     * @return bool
     */
    private static function verifyCsrfToken($token)
    {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
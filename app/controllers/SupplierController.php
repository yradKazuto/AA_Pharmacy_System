<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\RoleMiddleware;
use App\Models\Supplier;

class SupplierController extends Controller
{
    /**
     * List active suppliers.
     *
     * @return void
     */
    public function index()
    {
        RoleMiddleware::requirePermission('purchasing:view', '/unauthorized');

        $supplierModel = new Supplier();
        $suppliers = $supplierModel->allActive();

        $this->view('suppliers/index', [
            'suppliers' => $suppliers
        ]);
    }

    /**
     * Show the form to create a supplier.
     *
     * @return void
     */
    public function create()
    {
        RoleMiddleware::requirePermission('purchasing:edit', '/unauthorized');

        $this->view('suppliers/form', [
            'supplier' => null
        ]);
    }

    /**
     * Store a new supplier.
     *
     * @return void
     */
    public function store()
    {
        RoleMiddleware::requirePermission('purchasing:edit', '/unauthorized');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/suppliers/create');
            return;
        }

        if (!self::verifyCsrfToken($_POST['_csrf_token'] ?? '')) {
            $this->redirect('/suppliers/create?error=Invalid CSRF token.');
            return;
        }

        $data = self::inputData();
        $errors = self::validate($data);

        // Duplicate name check.
        if (empty($errors) && !empty($data['name'])) {
            $supplierModel = new Supplier();
            if ($supplierModel->findByName($data['name'])) {
                $errors[] = 'A supplier with this name already exists.';
            }
        }

        if (!empty($errors)) {
            $this->view('suppliers/form', [
                'errors' => $errors,
                'supplier' => $data
            ]);
            return;
        }

        $supplierModel = new Supplier();
        $stmt = $supplierModel->getDb()->prepare(
            "INSERT INTO suppliers (name, contact_person, phone, email, address, is_active)
             VALUES (?, ?, ?, ?, ?, 1)"
        );
        $result = $stmt->execute([
            $data['name'],
            $data['contact_person'],
            $data['phone'],
            $data['email'],
            $data['address']
        ]);

        if ($result) {
            $auditLog = new \App\Models\AuditLog();
            $auditLog->log([
                'user_id' => $_SESSION['user_id'],
                'action' => 'supplier_create',
                'table_affected' => 'suppliers',
                'record_id' => $supplierModel->getDb()->lastInsertId(),
                'description' => "Supplier created: {$data['name']}",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
            $this->redirect('/suppliers?success=Supplier created successfully.');
        } else {
            $this->view('suppliers/form', [
                'error' => 'Failed to create supplier.',
                'supplier' => $data
            ]);
        }
    }

    /**
     * Show the edit form for a supplier.
     *
     * @param int $id
     * @return void
     */
    public function edit($id)
    {
        RoleMiddleware::requirePermission('purchasing:edit', '/unauthorized');

        $supplierId = (int)$id;
        $supplierModel = new Supplier();
        $supplier = $supplierModel->find($supplierId);

        if (!$supplier) {
            $this->redirect('/suppliers?error=Supplier not found.');
            return;
        }

        $this->view('suppliers/form', [
            'supplier' => $supplier
        ]);
    }

    /**
     * Update a supplier.
     *
     * @param int $id
     * @return void
     */
    public function update($id)
    {
        RoleMiddleware::requirePermission('purchasing:edit', '/unauthorized');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect("/suppliers/{$id}/edit");
            return;
        }

        if (!self::verifyCsrfToken($_POST['_csrf_token'] ?? '')) {
            $this->redirect("/suppliers/{$id}/edit?error=Invalid CSRF token.");
            return;
        }

        $supplierId = (int)$id;
        $supplierModel = new Supplier();
        $existing = $supplierModel->find($supplierId);

        if (!$existing) {
            $this->redirect('/suppliers?error=Supplier not found.');
            return;
        }

        $data = self::inputData();
        $errors = self::validate($data);

        // Duplicate name, excluding current supplier.
        if (empty($errors) && !empty($data['name'])) {
            $dup = $supplierModel->findByName($data['name']);
            if ($dup && (int)$dup['id'] !== $supplierId) {
                $errors[] = 'A supplier with this name already exists.';
            }
        }

        if (!empty($errors)) {
            $this->view('suppliers/form', [
                'errors' => $errors,
                'supplier' => array_merge($data, ['id' => $supplierId])
            ]);
            return;
        }

        $stmt = $supplierModel->getDb()->prepare(
            "UPDATE suppliers SET
                name = ?, contact_person = ?, phone = ?, email = ?, address = ?,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        );
        $result = $stmt->execute([
            $data['name'],
            $data['contact_person'],
            $data['phone'],
            $data['email'],
            $data['address'],
            $supplierId
        ]);

        if ($result) {
            $auditLog = new \App\Models\AuditLog();
            $auditLog->log([
                'user_id' => $_SESSION['user_id'],
                'action' => 'supplier_update',
                'table_affected' => 'suppliers',
                'record_id' => $supplierId,
                'description' => "Supplier updated: {$data['name']}",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
            $this->redirect('/suppliers?success=Supplier updated successfully.');
        } else {
            $this->view('suppliers/form', [
                'error' => 'Failed to update supplier.',
                'supplier' => array_merge($data, ['id' => $supplierId])
            ]);
        }
    }

    /**
     * Soft-deactivate a supplier.
     *
     * @param int $id
     * @return void
     */
    public function destroy($id)
    {
        RoleMiddleware::requirePermission('purchasing:edit', '/unauthorized');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/suppliers');
            return;
        }

        if (!self::verifyCsrfToken($_POST['_csrf_token'] ?? '')) {
            $this->redirect('/suppliers?error=Invalid CSRF token.');
            return;
        }

        $supplierId = (int)$id;
        $supplierModel = new Supplier();
        $supplier = $supplierModel->find($supplierId);

        if (!$supplier) {
            $this->redirect('/suppliers?error=Supplier not found.');
            return;
        }

        $result = $supplierModel->deactivate($supplierId);

        if ($result) {
            $auditLog = new \App\Models\AuditLog();
            $auditLog->log([
                'user_id' => $_SESSION['user_id'],
                'action' => 'supplier_deactivate',
                'table_affected' => 'suppliers',
                'record_id' => $supplierId,
                'description' => "Supplier deactivated: {$supplier['name']}",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
            $this->redirect('/suppliers?success=Supplier deactivated successfully.');
        } else {
            $this->redirect('/suppliers?error=Failed to deactivate supplier.');
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
            'contact_person' => trim($_POST['contact_person'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
        ];
    }

    /**
     * Validate supplier input.
     *
     * @param array $data
     * @return array list of error strings
     */
    private static function validate(array $data)
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors[] = 'Supplier name is required.';
        } elseif (strlen($data['name']) > 100) {
            $errors[] = 'Supplier name must be less than 100 characters.';
        }

        if (strlen($data['contact_person']) > 100) {
            $errors[] = 'Contact person must be less than 100 characters.';
        }

        if (strlen($data['phone']) > 30) {
            $errors[] = 'Phone must be less than 30 characters.';
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        } elseif (strlen($data['email']) > 100) {
            $errors[] = 'Email must be less than 100 characters.';
        }

        if (strlen($data['address']) > 255) {
            $errors[] = 'Address must be less than 255 characters.';
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

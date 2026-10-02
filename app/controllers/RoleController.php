<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\RoleMiddleware;
use App\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of all roles.
     *
     * @return void
     */
    public function index()
    {
        // Only admin can manage roles
        RoleMiddleware::requireRole('admin', '/unauthorized');

        $roleModel = new Role();
        $roles = $roleModel->getActiveRoles();

        $this->view('roles/index', [
            'roles' => $roles
        ]);
    }

    /**
     * Show the form for creating a new role.
     *
     * @return void
     */
    public function create()
    {
        // Only admin can create roles
        RoleMiddleware::requireRole('admin', '/unauthorized');

        $this->view('roles/form', [
            'role' => null
        ]);
    }

    /**
     * Store a newly created role in storage.
     *
     * @return void
     */
    public function store()
    {
        // Only admin can create roles
        RoleMiddleware::requireRole('admin', '/unauthorized');

        // Only allow POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/roles/create');
            return;
        }

        // Verify CSRF token
        $csrfToken = $_POST['_csrf_token'] ?? '';
        if (!self::verifyCsrfToken($csrfToken)) {
            $this->view('roles/form', [
                'error' => 'Invalid CSRF token.',
                'role' => null
            ]);
            return;
        }

        // Get and validate input
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        // Validation
        $errors = [];

        if (empty($name)) {
            $errors[] = 'Role name is required.';
        } elseif (strlen($name) < 3) {
            $errors[] = 'Role name must be at least 3 characters.';
        } elseif (strlen($name) > 30) {
            $errors[] = 'Role name must be less than 30 characters.';
        }

        if (strlen($description) > 100) {
            $errors[] = 'Description must be less than 100 characters.';
        }

        // Check if role name already exists
        if (empty($errors) && !empty($name)) {
            $roleModel = new Role();
            $existingRole = $roleModel->findByName($name);
            if ($existingRole) {
                $errors[] = 'Role name already exists.';
            }
        }

        if (!empty($errors)) {
            $this->view('roles/form', [
                'errors' => $errors,
                'role' => [
                    'name' => $name,
                    'description' => $description,
                    'is_active' => $isActive
                ]
            ]);
            return;
        }

        // Create the role
        $roleModel = new Role();
        $stmt = $roleModel->getDb()->prepare(
            "INSERT INTO roles (name, description, is_active, created_at, updated_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
        );

        $result = $stmt->execute([$name, $description, $isActive]);

        if ($result) {
            // Log the role creation
            $auditLog = new \App\Models\AuditLog();
            $auditLog->log([
                'user_id' => $_SESSION['user_id'],
                'action' => 'role_create',
                'table_affected' => 'roles',
                'record_id' => $roleModel->getDb()->lastInsertId(),
                'description' => "Role created: {$name}",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);

            $this->redirect('/roles?success=Role created successfully.');
        } else {
            $this->view('roles/form', [
                'error' => 'Failed to create role. Please try again.',
                'role' => [
                    'name' => $name,
                    'description' => $description,
                    'is_active' => $isActive
                ]
            ]);
        }
    }

    /**
     * Show the form for editing the specified role.
     *
     * @param int $id
     * @return void
     */
    public function edit($id)
    {
        // Only admin can edit roles
        RoleMiddleware::requireRole('admin', '/unauthorized');

        $roleId = (int)$id;
        if (empty($roleId)) {
            $this->redirect('/roles');
            return;
        }

        $roleModel = new Role();
        $role = $roleModel->find($roleId);

        if (!$role) {
            $this->redirect('/roles?error=Role not found.');
            return;
        }

        $this->view('roles/form', [
            'role' => $role
        ]);
    }

    /**
     * Update the specified role in storage.
     *
     * @param int $id
     * @return void
     */
    public function update($id)
    {
        // Only admin can update roles
        RoleMiddleware::requireRole('admin', '/unauthorized');

        // Only allow POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect("/roles/{$id}/edit");
            return;
        }

        // Verify CSRF token
        $csrfToken = $_POST['_csrf_token'] ?? '';
        if (!self::verifyCsrfToken($csrfToken)) {
            $this->redirect("/roles/{$id}/edit");
            return;
        }

        $roleId = (int)$id;
        if (empty($roleId)) {
            $this->redirect('/roles');
            return;
        }

        $roleModel = new Role();
        $existingRole = $roleModel->find($roleId);

        if (!$existingRole) {
            $this->redirect('/roles?error=Role not found.');
            return;
        }

        // Get and validate input
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        // Validation
        $errors = [];

        if (empty($name)) {
            $errors[] = 'Role name is required.';
        } elseif (strlen($name) < 3) {
            $errors[] = 'Role name must be at least 3 characters.';
        } elseif (strlen($name) > 30) {
            $errors[] = 'Role name must be less than 30 characters.';
        }

        if (strlen($description) > 100) {
            $errors[] = 'Description must be less than 100 characters.';
        }

        // Check if role name already exists (excluding current role)
        if (empty($errors) && !empty($name)) {
            $roleCheck = $roleModel->findByName($name);
            if ($roleCheck && $roleCheck['id'] != $roleId) {
                $errors[] = 'Role name already exists.';
            }
        }

        if (!empty($errors)) {
            $this->view('roles/form', [
                'errors' => $errors,
                'role' => [
                    'name' => $name,
                    'description' => $description,
                    'is_active' => $isActive
                ]
            ]);
            return;
        }

        // Update the role
        $stmt = $roleModel->getDb()->prepare(
            "UPDATE roles SET
                name = ?,
                description = ?,
                is_active = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?"
        );

        $result = $stmt->execute([$name, $description, $isActive, $roleId]);

        if ($result) {
            // Log the role update
            $auditLog = new \App\Models\AuditLog();
            $auditLog->log([
                'user_id' => $_SESSION['user_id'],
                'action' => 'role_update',
                'table_affected' => 'roles',
                'record_id' => $roleId,
                'description' => "Role updated: {$name}",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);

            $this->redirect('/roles?success=Role updated successfully.');
        } else {
            $this->view('roles/form', [
                'error' => 'Failed to update role. Please try again.',
                'role' => [
                    'name' => $name,
                    'description' => $description,
                    'is_active' => $isActive
                ]
            ]);
        }
    }

    /**
     * Deactivate the specified role (soft delete).
     *
     * @param int $id
     * @return void
     */
    public function destroy($id)
    {
        // Only admin can deactivate roles
        RoleMiddleware::requireRole('admin', '/unauthorized');

        // Only allow POST requests (for safety with CSRF)
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/roles');
            return;
        }

        // Verify CSRF token
        $csrfToken = $_POST['_csrf_token'] ?? '';
        if (!self::verifyCsrfToken($csrfToken)) {
            $this->redirect('/roles');
            return;
        }

        $roleId = (int)$id;
        if (empty($roleId)) {
            $this->redirect('/roles');
            return;
        }

        $roleModel = new Role();
        $roleToDeactivate = $roleModel->find($roleId);

        if (!$roleToDeactivate) {
            $this->redirect('/roles?error=Role not found.');
            return;
        }

        // Prevent deactivating a role that still has active users assigned to it
        $stmt = $roleModel->getDb()->prepare(
            "SELECT COUNT(*) FROM users WHERE role_id = ? AND is_active = 1"
        );
        $stmt->execute([$roleId]);
        $activeUsers = (int)$stmt->fetchColumn();

        if ($activeUsers > 0) {
            $this->redirect("/roles?error=Cannot deactivate: {$activeUsers} active user(s) are assigned to this role.");
            return;
        }

        // Soft delete by setting is_active to 0
        $stmt = $roleModel->getDb()->prepare(
            "UPDATE roles SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
        );
        $result = $stmt->execute([$roleId]);

        if ($result) {
            // Log the role deactivation
            $auditLog = new \App\Models\AuditLog();
            $auditLog->log([
                'user_id' => $_SESSION['user_id'],
                'action' => 'role_delete',
                'table_affected' => 'roles',
                'record_id' => $roleId,
                'description' => "Role deactivated: {$roleToDeactivate['name']}",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);

            $this->redirect('/roles?success=Role deactivated successfully.');
        } else {
            $this->redirect('/roles?error=Failed to deactivate role.');
        }
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
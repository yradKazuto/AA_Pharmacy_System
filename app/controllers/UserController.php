<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\User;
use App\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of all users.
     *
     * @return void
     */
    public function index()
    {
        // Only admin and pharmacist can view users
        RoleMiddleware::requireRole(['admin', 'pharmacist'], '/unauthorized');

        $userModel = new User();
        $users = $userModel->getAllWithRoles();

        $this->view('users/index', [
            'users' => $users
        ]);
    }

    /**
     * Show the form for creating a new user.
     *
     * @return void
     */
    public function create()
    {
        // Only admin can create users
        RoleMiddleware::requireRole('admin', '/unauthorized');

        $roleModel = new Role();
        $roles = $roleModel->getActiveRoles();

        $this->view('users/form', [
            'roles' => $roles,
            'user' => null
        ]);
    }

    /**
     * Store a newly created user in storage.
     *
     * @return void
     */
    public function store()
    {
        // Only admin can create users
        RoleMiddleware::requireRole('admin', '/unauthorized');

        // Only allow POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/users/create');
            return;
        }

        // Verify CSRF token
        $csrfToken = $_POST['_csrf_token'] ?? '';
        if (!self::verifyCsrfToken($csrfToken)) {
            $this->view('users/form', [
                'error' => 'Invalid CSRF token.',
                'roles' => (new Role())->getActiveRoles(),
                'user' => null
            ]);
            return;
        }

        // Get and validate input
        $data = [
            'company_id_number' => trim($_POST['company_id_number'] ?? ''),
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'role_id' => (int)($_POST['role_id'] ?? 0)
        ];

        // Validation
        $errors = [];

        if (empty($data['company_id_number'])) {
            $errors[] = 'Company ID Number is required.';
        } elseif (strlen($data['company_id_number']) > 20) {
            $errors[] = 'Company ID Number must be less than 20 characters.';
        }

        if (empty($data['username'])) {
            $errors[] = 'Username is required.';
        } elseif (strlen($data['username']) < 3) {
            $errors[] = 'Username must be at least 3 characters.';
        } elseif (strlen($data['username']) > 50) {
            $errors[] = 'Username must be less than 50 characters.';
        }

        if (empty($data['email'])) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (strlen($data['email']) > 100) {
            $errors[] = 'Email must be less than 100 characters.';
        }

        if (empty($data['password'])) {
            $errors[] = 'Password is required.';
        } elseif (strlen($data['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }

        if ($data['password'] !== $data['confirm_password']) {
            $errors[] = 'Passwords do not match.';
        }

        if (empty($data['first_name'])) {
            $errors[] = 'First name is required.';
        }

        if (empty($data['last_name'])) {
            $errors[] = 'Last name is required.';
        }

        if (empty($data['role_id'])) {
            $errors[] = 'Role is required.';
        }

        // Check if company_id_number already exists
        if (empty($errors) && !empty($data['company_id_number'])) {
            $userModel = new User();
            $existingUser = $userModel->findByCompanyIdNumber($data['company_id_number']);
            if ($existingUser) {
                $errors[] = 'Company ID Number already exists.';
            }
        }

        // Check if username already exists
        if (empty($errors) && !empty($data['username'])) {
            $userModel = new User();
            $existingUser = $userModel->findByUsernameOrEmail($data['username']);
            if ($existingUser) {
                $errors[] = 'Username already exists.';
            }
        }

        // Check if email already exists
        if (empty($errors) && !empty($data['email'])) {
            $userModel = new User();
            $existingUser = $userModel->findByUsernameOrEmail($data['email']);
            if ($existingUser) {
                $errors[] = 'Email already exists.';
            }
        }

        if (!empty($errors)) {
            $roleModel = new Role();
            $roles = $roleModel->getActiveRoles();

            $this->view('users/form', [
                'errors' => $errors,
                'roles' => $roles,
                'user' => $data
            ]);
            return;
        }

        // Create the user
        $userModel = new User();
        $stmt = $userModel->getDb()->prepare(
            "INSERT INTO users (company_id_number, username, email, password_hash, first_name, last_name, role_id, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
        );

        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        $result = $stmt->execute([
            $data['company_id_number'],
            $data['username'],
            $data['email'],
            $hashedPassword,
            $data['first_name'],
            $data['last_name'],
            $data['role_id']
        ]);

        if ($result) {
            // Log the user creation
            $auditLog = new \App\Models\AuditLog();
            $auditLog->log([
                'user_id' => $_SESSION['user_id'],
                'action' => 'user_create',
                'table_affected' => 'users',
                'record_id' => $userModel->getDb()->lastInsertId(),
                'description' => "User created: {$data['username']} ({$data['email']})",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);

            $this->redirect('/users?success=User created successfully.');
        } else {
            $roleModel = new Role();
            $roles = $roleModel->getActiveRoles();

            $this->view('users/form', [
                'error' => 'Failed to create user. Please try again.',
                'roles' => $roles,
                'user' => $data
            ]);
        }
    }

    /**
     * Show the form for editing the specified user.
     *
     * @param int $id
     * @return void
     */
    public function edit($id)
    {
        // Only admin can edit users
        RoleMiddleware::requireRole('admin', '/unauthorized');

        $userId = (int)$id;
        if (empty($userId)) {
            $this->redirect('/users');
            return;
        }

        $userModel = new User();
        $user = $userModel->find($userId);

        if (!$user) {
            $this->redirect('/users?error=User not found.');
            return;
        }

        $roleModel = new Role();
        $roles = $roleModel->getActiveRoles();

        $this->view('users/form', [
            'roles' => $roles,
            'user' => $user
        ]);
    }

    /**
     * Update the specified user in storage.
     *
     * @param int $id
     * @return void
     */
    public function update($id)
    {
        // Only admin can update users
        RoleMiddleware::requireRole('admin', '/unauthorized');

        // Only allow POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect("/users/{$id}/edit");
            return;
        }

        // Verify CSRF token
        $csrfToken = $_POST['_csrf_token'] ?? '';
        if (!self::verifyCsrfToken($csrfToken)) {
            $this->redirect("/users/{$id}/edit");
            return;
        }

        $userId = (int)$id;
        if (empty($userId)) {
            $this->redirect('/users');
            return;
        }

        $userModel = new User();
        $existingUser = $userModel->find($userId);

        if (!$existingUser) {
            $this->redirect('/users?error=User not found.');
            return;
        }

        // Get and validate input
        $data = [
            'company_id_number' => trim($_POST['company_id_number'] ?? ''),
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'role_id' => (int)($_POST['role_id'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];

        // Validation
        $errors = [];

        if (empty($data['company_id_number'])) {
            $errors[] = 'Company ID Number is required.';
        } elseif (strlen($data['company_id_number']) > 20) {
            $errors[] = 'Company ID Number must be less than 20 characters.';
        }

        if (empty($data['username'])) {
            $errors[] = 'Username is required.';
        } elseif (strlen($data['username']) < 3) {
            $errors[] = 'Username must be at least 3 characters.';
        } elseif (strlen($data['username']) > 50) {
            $errors[] = 'Username must be less than 50 characters.';
        }

        if (empty($data['email'])) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (strlen($data['email']) > 100) {
            $errors[] = 'Email must be less than 100 characters.';
        }

        if (empty($data['first_name'])) {
            $errors[] = 'First name is required.';
        }

        if (empty($data['last_name'])) {
            $errors[] = 'Last name is required.';
        }

        if (empty($data['role_id'])) {
            $errors[] = 'Role is required.';
        }

        // Check if company_id_number already exists (excluding current user)
        if (empty($errors) && !empty($data['company_id_number'])) {
            $userCheck = $userModel->findByCompanyIdNumber($data['company_id_number']);
            if ($userCheck && $userCheck['id'] != $userId) {
                $errors[] = 'Company ID Number already exists.';
            }
        }

        // Check if username already exists (excluding current user)
        if (empty($errors) && !empty($data['username'])) {
            $userCheck = $userModel->findByUsernameOrEmail($data['username']);
            if ($userCheck && $userCheck['id'] != $userId) {
                $errors[] = 'Username already exists.';
            }
        }

        // Check if email already exists (excluding current user)
        if (empty($errors) && !empty($data['email'])) {
            $userCheck = $userModel->findByUsernameOrEmail($data['email']);
            if ($userCheck && $userCheck['id'] != $userId) {
                $errors[] = 'Email already exists.';
            }
        }

        if (!empty($errors)) {
            $roleModel = new Role();
            $roles = $roleModel->getActiveRoles();

            $this->view('users/form', [
                'errors' => $errors,
                'roles' => $roles,
                'user' => $data
            ]);
            return;
        }

        // Update the user
        $stmt = $userModel->getDb()->prepare(
            "UPDATE users SET
                company_id_number = ?,
                username = ?,
                email = ?,
                first_name = ?,
                last_name = ?,
                role_id = ?,
                is_active = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?"
        );

        $result = $stmt->execute([
            $data['company_id_number'],
            $data['username'],
            $data['email'],
            $data['first_name'],
            $data['last_name'],
            $data['role_id'],
            $data['is_active'],
            $userId
        ]);

        if ($result) {
            // Log the user update
            $auditLog = new \App\Models\AuditLog();
            $auditLog->log([
                'user_id' => $_SESSION['user_id'],
                'action' => 'user_update',
                'table_affected' => 'users',
                'record_id' => $userId,
                'description' => "User updated: {$data['username']} ({$data['email']})",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);

            $this->redirect('/users?success=User updated successfully.');
        } else {
            $roleModel = new Role();
            $roles = $roleModel->getActiveRoles();

            $this->view('users/form', [
                'error' => 'Failed to update user. Please try again.',
                'roles' => $roles,
                'user' => $data
            ]);
        }
    }

    /**
     * Remove the specified user from storage.
     *
     * @param int $id
     * @return void
     */
    public function destroy($id)
    {
        // Only admin can delete users
        RoleMiddleware::requireRole('admin', '/unauthorized');

        // Only allow POST requests (for safety with CSRF)
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/users');
            return;
        }

        // Verify CSRF token
        $csrfToken = $_POST['_csrf_token'] ?? '';
        if (!self::verifyCsrfToken($csrfToken)) {
            $this->redirect('/users');
            return;
        }

        $userId = (int)$id;
        if (empty($userId)) {
            $this->redirect('/users');
            return;
        }

        $userModel = new User();
        $userToDelete = $userModel->find($userId);

        if (!$userToDelete) {
            $this->redirect('/users?error=User not found.');
            return;
        }

        // Prevent deleting yourself
        if ($userId == $_SESSION['user_id']) {
            $this->redirect('/users?error=You cannot delete your own account.');
            return;
        }

        // Soft delete by setting is_active to 0
        $stmt = $userModel->getDb()->prepare(
            "UPDATE users SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
        );
        $result = $stmt->execute([$userId]);

        if ($result) {
            // Log the user deletion
            $auditLog = new \App\Models\AuditLog();
            $auditLog->log([
                'user_id' => $_SESSION['user_id'],
                'action' => 'user_delete',
                'table_affected' => 'users',
                'record_id' => $userId,
                'description' => "User deactivated: {$userToDelete['username']} ({$userToDelete['email']})",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);

            $this->redirect('/users?success=User deactivated successfully.');
        } else {
            $this->redirect('/users?error=Failed to deactivate user.');
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
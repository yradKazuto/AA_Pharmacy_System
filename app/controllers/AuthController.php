<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Middleware\GuestMiddleware;
use App\Services\AuthService;

class AuthController extends Controller
{
    /**
     * Show the login form.
     *
     * @return void
     */
    public function login()
    {
        // Redirect to dashboard if already logged in
        GuestMiddleware::redirectIfAuthenticated('/dashboard');

        $this->view('auth/login');
    }

    /**
     * Process the login form submission.
     *
     * @return void
     */
    public function doLogin()
    {
        // Only allow POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/login');
            return;
        }

        // Verify CSRF token
        $csrfToken = $_POST['_csrf_token'] ?? '';
        if (!self::verifyCsrfToken($csrfToken)) {
            $this->view('auth/login', [
                'error' => 'Invalid CSRF token. Please try again.'
            ]);
            return;
        }

        // Get input data
        $identifier = trim($_POST['identifier'] ?? '');
        $password = $_POST['password'] ?? '';

        // Basic server-side validation
        if (empty($identifier) || empty($password)) {
            $this->view('auth/login', [
                'error' => 'Please enter your username/email and password.'
            ]);
            return;
        }

        // Attempt login
        $user = AuthService::login($identifier, $password);

        if ($user) {
            // Redirect based on role
            $redirectUrl = $this->getRedirectUrl($user['role_name']);
            $this->redirect($redirectUrl);
        } else {
            $this->view('auth/login', [
                'error' => 'Invalid username/email or password.'
            ]);
        }
    }

    /**
     * Logout the current user.
     *
     * @return void
     */
    public function logout()
    {
        AuthService::logout();
        $this->redirect('/login');
    }

    /**
     * Show password recovery form (admin only).
     *
     * @return void
     */
    public function passwordRecovery()
    {
        // Only admin can initiate password recovery
        RoleMiddleware::requireRole('admin', '/unauthorized');

        $userModel = new \App\Models\User();
        $users = $userModel->getAllWithRoles();

        $this->view('auth/password_recovery', [
            'users' => $users
        ]);
    }

    /**
     * Process password recovery (admin only).
     *
     * @return void
     */
    public function doPasswordRecovery()
    {
        // Only allow POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/password-recovery');
            return;
        }

        // Only admin can initiate password recovery
        RoleMiddleware::requireRole('admin', '/unauthorized');

        // Verify CSRF token
        $csrfToken = $_POST['_csrf_token'] ?? '';
        if (!self::verifyCsrfToken($csrfToken)) {
            $this->redirect('/password-recovery');
            return;
        }

        $userId = (int)($_POST['user_id'] ?? 0);

        if (empty($userId)) {
            $this->view('auth/password_recovery', [
                'error' => 'Please select a user.',
                'users' => (new \App\Models\User())->getAllWithRoles()
            ]);
            return;
        }

        // Initiate password recovery
        $tempPassword = AuthService::initiatePasswordRecovery($userId, $_SESSION['user_id']);

        // Display the temporary password to the admin
        $userModel = new \App\Models\User();
        $user = $userModel->find($userId);

        $this->view('auth/password_recovery', [
            'success' => true,
            'message' => "Temporary password generated for user {$user['username']}.",
            'temp_password' => $tempPassword,
            'users' => $userModel->getAllWithRoles()
        ]);
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

    /**
     * Get redirect URL based on user role.
     *
     * @param string $roleName
     * @return string
     */
    private function getRedirectUrl($roleName)
    {
        switch (strtolower($roleName)) {
            case 'admin':
                return '/admin/dashboard';
            case 'pharmacist':
                return '/pharmacist/dashboard';
            case 'cashier':
                return '/cashier/dashboard';
            case 'customer':
                return '/customer/dashboard';
            default:
                return '/dashboard';
        }
    }
}
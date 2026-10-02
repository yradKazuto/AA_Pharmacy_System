<?php

namespace App\Middleware;

use App\Core\Controller;
use App\Models\User;
use App\Models\Role;

class RoleMiddleware extends Controller
{
    /**
     * Check if the current user has the required role(s).
     *
     * @param array|string $roles Required role name(s)
     * @return bool
     */
    public static function hasRole($roles)
    {
        if (empty($_SESSION['user_id'])) {
            return false;
        }

        $userModel = new User();
        $user = $userModel->find($_SESSION['user_id']);

        if (!$user) {
            return false;
        }

        $roleModel = new Role();
        $userRole = $roleModel->find($user['role_id']);

        if (!$userRole) {
            return false;
        }

        // Normalize to array
        $requiredRoles = is_array($roles) ? $roles : [$roles];

        return in_array(strtolower($userRole['name']), array_map('strtolower', $requiredRoles));
    }

    /**
     * Redirect if user does not have required role(s).
     * Redirects to /login if not authenticated, /unauthorized if wrong role.
     *
     * @param array|string $roles Required role name(s)
     * @param string $redirectUrl URL to redirect to if unauthorized (role mismatch)
     * @return void
     */
    public static function requireRole($roles, $redirectUrl = '/unauthorized')
    {
        if (empty($_SESSION['user_id'])) {
            // Not authenticated — send to login
            header("Location: /login");
            exit;
        }
        if (!self::hasRole($roles)) {
            // Authenticated but wrong role
            header("Location: " . $redirectUrl);
            exit;
        }
    }

    /**
     * Check if user has permission based on role capabilities.
     * This can be extended with a permissions table if needed.
     *
     * @param string $permission Permission slug
     * @return bool
     */
    public static function can($permission)
    {
        if (empty($_SESSION['user_id'])) {
            return false;
        }

        // Simple permission mapping based on role
        $userModel = new User();
        $user = $userModel->find($_SESSION['user_id']);

        if (!$user) {
            return false;
        }

        $roleName = strtolower($user['role_name']);

        // Define role permissions
        $permissions = [
            'admin' => ['*'], // Full access
            'pharmacist' => [
                'inventory:view', 'inventory:edit', 'purchasing:view', 'purchasing:edit',
                'pos:process', 'orders:review', 'reports:view'
            ],
            'cashier' => [
                'inventory:view', 'pos:process', 'reports:view'
            ],
            'customer' => [
                'catalog:view', 'orders:create', 'orders:view_own'
            ]
        ];

        // Check for wildcard permission
        if (isset($permissions[$roleName]) && in_array('*', $permissions[$roleName])) {
            return true;
        }

        // Check specific permission
        return isset($permissions[$roleName]) && in_array($permission, $permissions[$roleName]);
    }

    /**
     * Redirect if user cannot perform the specified action.
     *
     * @param string $permission Permission slug
     * @param string $redirectUrl URL to redirect to if unauthorized
     * @return void
     */
    public static function requirePermission($permission, $redirectUrl = '/unauthorized')
    {
        if (!self::can($permission)) {
            header("Location: " . $redirectUrl);
            exit;
        }
    }
}
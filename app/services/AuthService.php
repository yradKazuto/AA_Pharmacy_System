<?php

namespace App\Services;

use App\Core\Controller;
use App\Models\User;
use App\Models\AuditLog;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

class AuthService extends Controller
{
    /**
     * Attempt to log in a user.
     *
     * @param string $identifier Username or email
     * @param string $password Plain text password
     * @return array|bool User data on success, false on failure
     */
    public static function login($identifier, $password)
    {
        $userModel = new User();
        $user = $userModel->findByUsernameOrEmail($identifier);

        if ($user && password_verify($password, $user['password_hash'])) {
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);

            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role_name'];
            $_SESSION['company_id_number'] = $user['company_id_number'];
            $_SESSION['logged_in'] = true;

            // Update last login timestamp
            $userModel->updateLastLogin($user['id']);

            // Log the login action
            $auditLog = new AuditLog();
            $auditLog->log([
                'user_id' => $user['id'],
                'action' => 'login',
                'description' => "User {$user['username']} logged in",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);

            return $user;
        }

        // Log failed login attempt (without user_id since we don't know which user)
        $auditLog = new AuditLog();
        $auditLog->log([
            'action' => 'failed_login',
            'description' => "Failed login attempt for identifier: {$identifier}",
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);

        return false;
    }

    /**
     * Log out the current user.
     *
     * @return void
     */
    public static function logout()
    {
        // Log the logout action if user is logged in
        if (!empty($_SESSION['user_id'])) {
            $auditLog = new AuditLog();
            $auditLog->log([
                'user_id' => $_SESSION['user_id'],
                'action' => 'logout',
                'description' => "User {$_SESSION['username']} logged out",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
        }

        // Destroy the session
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    /**
     * Check if the current user is logged in.
     *
     * @return bool
     */
    public static function check()
    {
        return !empty($_SESSION['user_id']) && $_SESSION['logged_in'] === true;
    }

    /**
     * Get the currently logged-in user.
     *
     * @return array|null
     */
    public static function user()
    {
        if (self::check()) {
            $userModel = new User();
            return $userModel->find($_SESSION['user_id']);
        }

        return null;
    }

    /**
     * Initiate password recovery (admin only).
     * Generates a temporary password and emails it (in a real system).
     * For this implementation, we'll just return the temp password.
     *
     * @param int $userId ID of user to reset password for
     * @param int $adminId ID of admin initiating the reset
     * @return string Temporary password
     */
    public static function initiatePasswordRecovery($userId, $adminId)
    {
        // Generate a temporary password
        $tempPassword = self::generateTempPassword();

        // Hash the temporary password
        $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

        // Update user's password
        $userModel = new User();
        $stmt = $userModel->getDb()->prepare(
            "UPDATE users SET password_hash = ? WHERE id = ?"
        );
        $stmt->execute([$hashedPassword, $userId]);

        // Log the password recovery action
        $auditLog = new AuditLog();
        $auditLog->log([
            'user_id' => $adminId,
            'action' => 'password_recovery',
            'table_affected' => 'users',
            'record_id' => $userId,
            'description' => "Admin initiated password recovery for user ID: {$userId}",
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);

        // In a real system, we would email this to the user
        // For this implementation, we return it so it can be displayed to the admin
        return $tempPassword;
    }

    /**
     * Generate a temporary password.
     *
     * @param int $length
     * @return string
     */
    private static function generateTempPassword($length = 12)
    {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $specialChars = '!@#$%^&*';

        $all = $uppercase . $lowercase . $numbers . $specialChars;
        $password = '';

        // Ensure at least one of each character type
        $password .= $uppercase[rand(0, strlen($uppercase) - 1)];
        $password .= $lowercase[rand(0, strlen($lowercase) - 1)];
        $password .= $numbers[rand(0, strlen($numbers) - 1)];
        $password .= $specialChars[rand(0, strlen($specialChars) - 1)];

        // Fill the rest with random characters
        for ($i = 4; $i < $length; $i++) {
            $password .= $all[rand(0, strlen($all) - 1)];
        }

        // Shuffle the password to avoid predictable patterns
        $password = str_shuffle($password);

        return $password;
    }

    /**
     * Change password for logged-in user.
     *
     * @param string $currentPassword
     * @param string $newPassword
     * @return bool
     */
    public static function changePassword($currentPassword, $newPassword)
    {
        if (!self::check()) {
            return false;
        }

        $user = self::user();
        if (!$user) {
            return false;
        }

        // Verify current password
        if (!password_verify($currentPassword, $user['password_hash'])) {
            return false;
        }

        // Hash new password
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update database
        $userModel = new User();
        $stmt = $userModel->getDb()->prepare(
            "UPDATE users SET password_hash = ? WHERE id = ?"
        );
        $result = $stmt->execute([$newPasswordHash, $user['id']]);

        if ($result) {
            // Log password change
            $auditLog = new AuditLog();
            $auditLog->log([
                'user_id' => $user['id'],
                'action' => 'password_change',
                'description' => "User {$user['username']} changed their password",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
        }

        return $result;
    }
}
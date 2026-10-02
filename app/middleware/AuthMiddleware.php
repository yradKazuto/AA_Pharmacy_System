<?php

namespace App\Middleware;

use App\Core\Controller;

class AuthMiddleware extends Controller
{
    /**
     * Ensure the user is logged in.
     *
     * @return bool
     */
    public static function requireAuth()
    {
        if (empty($_SESSION['user_id'])) {
            return false;
        }

        return true;
    }

    /**
     * Redirect to login if not authenticated.
     *
     * @param string $loginUrl
     * @return void
     */
    public static function guard($loginUrl = '/login')
    {
        if (!self::requireAuth()) {
            header("Location: " . $loginUrl);
            exit;
        }
    }

    /**
     * Redirect logged-in users away from auth pages (login, register).
     *
     * @param string $dashboardUrl
     * @return void
     */
    public static function guestOnly($dashboardUrl = '/dashboard')
    {
        if (!empty($_SESSION['user_id'])) {
            header("Location: " . $dashboardUrl);
            exit;
        }
    }
}
<?php

namespace App\Middleware;

use App\Core\Controller;

class GuestMiddleware extends Controller
{
    /**
     * Ensure the user is NOT logged in (guest only).
     *
     * @return bool
     */
    public static function requireGuest()
    {
        return empty($_SESSION['user_id']);
    }

    /**
     * Redirect to dashboard if user is logged in (for login/register pages).
     *
     * @param string $dashboardUrl
     * @return void
     */
    public static function redirectIfAuthenticated($dashboardUrl = '/dashboard')
    {
        if (!empty($_SESSION['user_id'])) {
            header("Location: " . $dashboardUrl);
            exit;
        }
    }
}
<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\Dashboard;

/**
 * DashboardController — real per-role dashboards replacing the Phase 1
 * placeholder closures. Each role's view is gated to that role.
 */
class DashboardController extends Controller
{
    /**
     * Admin dashboard.
     *
     * @return void
     */
    public function admin()
    {
        RoleMiddleware::requireRole('admin', '/unauthorized');

        $dm = new Dashboard();
        $this->view('dashboard/admin', [
            'kpis' => $dm->kpisForRole('admin'),
            'trend' => $dm->weeklySalesTrend(),
            'recentSales' => $dm->recentSales(),
            'recentMovements' => $dm->recentMovements(),
        ]);
    }

    /**
     * Pharmacist dashboard (inventory-focused).
     *
     * @return void
     */
    public function pharmacist()
    {
        RoleMiddleware::requireRole('pharmacist', '/unauthorized');

        $dm = new Dashboard();
        $this->view('dashboard/pharmacist', [
            'kpis' => $dm->kpisForRole('pharmacist'),
            'trend' => $dm->weeklySalesTrend(),
            'recentSales' => $dm->recentSales(),
            'recentMovements' => $dm->recentMovements(),
        ]);
    }

    /**
     * Cashier dashboard (POS-focused).
     *
     * @return void
     */
    public function cashier()
    {
        RoleMiddleware::requireRole('cashier', '/unauthorized');

        $dm = new Dashboard();
        $this->view('dashboard/cashier', [
            'kpis' => $dm->kpisForRole('cashier'),
            'recentSales' => $dm->recentSales(8),
        ]);
    }
}

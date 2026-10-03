<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\RoleMiddleware;
use App\Models\CustomerOrder;
use App\Models\Product;

/**
 * CustomerDashboardController — the customer portal landing page.
 * Replaces the Phase 1 placeholder. Gated to customers.
 */
class CustomerDashboardController extends Controller
{
    /**
     * Customer portal home: quick stats, catalog link, recent orders.
     *
     * @return void
     */
    public function index()
    {
        RoleMiddleware::requireRole('customer', '/unauthorized');

        $customerId = (int)$_SESSION['user_id'];
        $orderModel = new CustomerOrder();
        $orders = $orderModel->forCustomer($customerId);

        // Count in-progress (not terminal) orders for a quick badge.
        $activeOrders = 0;
        foreach ($orders as $o) {
            if (in_array($o['status'], ['pending_review', 'approved', 'fulfilled'], true)) {
                $activeOrders++;
            }
        }

        $productModel = new Product();
        $catalogCount = count(array_filter($productModel->getAllWithStock(), function ($p) {
            return (int)$p['total_qty'] > 0;
        }));

        $this->view('customer_dashboard/index', [
            'orders' => array_slice($orders, 0, 5),
            'activeOrders' => $activeOrders,
            'catalogCount' => $catalogCount,
        ]);
    }
}

<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\RoleMiddleware;
use App\Models\CustomerOrder;
use App\Models\Product;
use App\Services\CustomerOrderService;

/**
 * CustomerOrderController — the customer-side ordering flow.
 * Gated to customers (orders:create, orders:view_own). Rule 9: a customer can
 * only read/cancel their own orders.
 */
class CustomerOrderController extends Controller
{
    /**
     * Cart page.
     *
     * @return void
     */
    public function cart()
    {
        RoleMiddleware::requirePermission('orders:create', '/unauthorized');

        $productModel = new Product();
        $products = $productModel->getAllWithStock();
        $this->view('orders/cart', ['products' => $products]);
    }

    /**
     * Checkout page — delivery details + confirm.
     *
     * @return void
     */
    public function checkout()
    {
        RoleMiddleware::requirePermission('orders:create', '/unauthorized');

        $productModel = new Product();
        $products = $productModel->getAllWithStock();
        $this->view('orders/checkout', ['products' => $products]);
    }

    /**
     * Place an order (POST).
     *
     * @return void
     */
    public function place()
    {
        RoleMiddleware::requirePermission('orders:create', '/unauthorized');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/cart');
            return;
        }
        if (!self::verifyCsrfToken($_POST['_csrf_token'] ?? '')) {
            $this->redirect('/cart?error=Invalid CSRF token.');
            return;
        }

        $items = [];
        $productIds = $_POST['product_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        for ($i = 0; $i < count($productIds); $i++) {
            $items[] = ['product_id' => (int)$productIds[$i], 'qty' => (int)($quantities[$i] ?? 0)];
        }

        $service = new CustomerOrderService();
        $result = $service->placeOrder(
            (int)$_SESSION['user_id'],
            $items,
            trim($_POST['delivery_address'] ?? ''),
            trim($_POST['phone'] ?? ''),
            trim($_POST['notes'] ?? '') ?: null
        );

        if ($result['ok']) {
            $auditLog = new \App\Models\AuditLog();
            $auditLog->log([
                'user_id' => $_SESSION['user_id'],
                'action' => 'order_place',
                'table_affected' => 'customer_orders',
                'record_id' => $result['order_id'],
                'description' => "Order {$result['order_number']} placed",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
            $this->redirect('/orders/' . $result['order_id'] . '?success=' . urlencode('Order ' . $result['order_number'] . ' placed.'));
        } else {
            $this->redirect('/checkout?error=' . urlencode($result['message']));
        }
    }

    /**
     * "My orders" — only the logged-in customer's own orders (rule 9).
     *
     * @return void
     */
    public function myOrders()
    {
        RoleMiddleware::requirePermission('orders:view_own', '/unauthorized');

        $orderModel = new CustomerOrder();
        $this->view('orders/my_orders', [
            'orders' => $orderModel->forCustomer((int)$_SESSION['user_id'])
        ]);
    }

    /**
     * Order detail — only the owner's own order (rule 9).
     *
     * @param int $id
     * @return void
     */
    public function show($id)
    {
        RoleMiddleware::requirePermission('orders:view_own', '/unauthorized');

        $orderId = (int)$id;
        $orderModel = new CustomerOrder();
        $order = $orderModel->findWithItems($orderId);

        if (!$order || (int)$order['customer_id'] !== (int)$_SESSION['user_id']) {
            $this->redirect('/orders?error=Order not found.');
            return;
        }

        $this->view('orders/order_detail', ['order' => $order]);
    }

    /**
     * Customer cancels their own unfulfilled order (rule 9 + valid transition).
     *
     * @param int $id
     * @return void
     */
    public function cancel($id)
    {
        RoleMiddleware::requirePermission('orders:view_own', '/unauthorized');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/orders');
            return;
        }
        if (!self::verifyCsrfToken($_POST['_csrf_token'] ?? '')) {
            $this->redirect('/orders?error=Invalid CSRF token.');
            return;
        }

        $orderId = (int)$id;
        $orderModel = new CustomerOrder();
        $order = $orderModel->find($orderId);
        if (!$order || (int)$order['customer_id'] !== (int)$_SESSION['user_id']) {
            $this->redirect('/orders?error=Order not found.');
            return;
        }

        $service = new CustomerOrderService();
        $result = $service->cancel($orderId);

        if ($result['ok']) {
            $auditLog = new \App\Models\AuditLog();
            $auditLog->log([
                'user_id' => $_SESSION['user_id'],
                'action' => 'order_cancel',
                'table_affected' => 'customer_orders',
                'record_id' => $orderId,
                'description' => "Order {$order['order_number']} cancelled",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
            $this->redirect('/orders?success=' . urlencode($result['message']));
        } else {
            $this->redirect('/orders/' . $orderId . '?error=' . urlencode($result['message']));
        }
    }

    /**
     * Verify CSRF token (same pattern as other controllers).
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

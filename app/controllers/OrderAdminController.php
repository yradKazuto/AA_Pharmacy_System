<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\RoleMiddleware;
use App\Models\CustomerOrder;
use App\Services\CustomerOrderService;

/**
 * OrderAdminController — the pharmacist/admin order review + fulfillment
 * workflow (rule 8: prescription orders require pharmacist approval).
 */
class OrderAdminController extends Controller
{
    /**
     * Pending / in-progress orders for review & fulfillment.
     *
     * @return void
     */
    public function reviewQueue()
    {
        RoleMiddleware::requirePermission('orders:review', '/unauthorized');

        $orderModel = new CustomerOrder();
        $this->view('admin_orders/review', [
            'orders' => $orderModel->allForReview()
        ]);
    }

    /**
     * Approve a pending order.
     *
     * @param int $id
     * @return void
     */
    public function approve($id)
    {
        RoleMiddleware::requirePermission('orders:review', '/unauthorized');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !self::verifyCsrfToken($_POST['_csrf_token'] ?? '')) {
            $this->redirect('/orders/review?error=Invalid CSRF token.');
            return;
        }

        $orderModel = new CustomerOrder();
        $order = $orderModel->find((int)$id);
        if (!$order) {
            $this->redirect('/orders/review?error=Order not found.');
            return;
        }

        $service = new CustomerOrderService();
        $result = $service->approve((int)$id, $_SESSION['user_id'] ?? null);
        $this->logOrderAction($order, $result, 'order_approve', 'Order approved');
        if ($result['ok']) {
            $this->redirect('/orders/review?success=' . urlencode("Order {$order['order_number']} approved."));
        } else {
            $this->redirect('/orders/review?error=' . urlencode($result['message']));
        }
    }

    /**
     * Reject a pending order.
     *
     * @param int $id
     * @return void
     */
    public function reject($id)
    {
        RoleMiddleware::requirePermission('orders:review', '/unauthorized');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !self::verifyCsrfToken($_POST['_csrf_token'] ?? '')) {
            $this->redirect('/orders/review?error=Invalid CSRF token.');
            return;
        }

        $orderModel = new CustomerOrder();
        $order = $orderModel->find((int)$id);
        if (!$order) {
            $this->redirect('/orders/review?error=Order not found.');
            return;
        }

        $note = trim($_POST['reject_note'] ?? '');
        $service = new CustomerOrderService();
        $result = $service->reject((int)$id, $note !== '' ? $note : null, $_SESSION['user_id'] ?? null);
        $this->logOrderAction($order, $result, 'order_reject', 'Order rejected' . ($note !== '' ? ': ' . $note : ''));
        if ($result['ok']) {
            $this->redirect('/orders/review?success=' . urlencode("Order {$order['order_number']} rejected."));
        } else {
            $this->redirect('/orders/review?error=' . urlencode($result['message']));
        }
    }

    /**
     * Fulfill an approved order (deducts stock FEFO + records sale, one txn).
     *
     * @param int $id
     * @return void
     */
    public function fulfill($id)
    {
        RoleMiddleware::requirePermission('orders:review', '/unauthorized');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !self::verifyCsrfToken($_POST['_csrf_token'] ?? '')) {
            $this->redirect('/orders/review?error=Invalid CSRF token.');
            return;
        }

        $orderModel = new CustomerOrder();
        $order = $orderModel->find((int)$id);
        if (!$order) {
            $this->redirect('/orders/review?error=Order not found.');
            return;
        }

        $service = new CustomerOrderService();
        $result = $service->fulfill((int)$id, $_SESSION['user_id'] ?? null);
        $this->logOrderAction($order, $result, 'order_fulfill', 'Order fulfilled, stock deducted');
        if ($result['ok']) {
            $this->redirect('/orders/review?success=' . urlencode("Order {$order['order_number']} fulfilled."));
        } else {
            $this->redirect('/orders/review?error=' . urlencode($result['message']));
        }
    }

    /**
     * Mark a fulfilled order as delivered.
     *
     * @param int $id
     * @return void
     */
    public function deliver($id)
    {
        RoleMiddleware::requirePermission('orders:review', '/unauthorized');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !self::verifyCsrfToken($_POST['_csrf_token'] ?? '')) {
            $this->redirect('/orders/review?error=Invalid CSRF token.');
            return;
        }

        $orderModel = new CustomerOrder();
        $order = $orderModel->find((int)$id);
        if (!$order) {
            $this->redirect('/orders/review?error=Order not found.');
            return;
        }

        $service = new CustomerOrderService();
        $result = $service->markDelivered((int)$id);
        $this->logOrderAction($order, $result, 'order_deliver', 'Order marked delivered');
        if ($result['ok']) {
            $this->redirect('/orders/review?success=' . urlencode("Order {$order['order_number']} delivered."));
        } else {
            $this->redirect('/orders/review?error=' . urlencode($result['message']));
        }
    }

    /**
     * Audit-log an order action (only logs on success like other controllers).
     *
     * @param array $order
     * @param array $result
     * @param string $action
     * @param string $description
     * @return void
     */
    private function logOrderAction($order, $result, $action, $description)
    {
        if (!$result['ok']) {
            return;
        }
        $auditLog = new \App\Models\AuditLog();
        $auditLog->log([
            'user_id' => $_SESSION['user_id'],
            'action' => $action,
            'table_affected' => 'customer_orders',
            'record_id' => $order['id'],
            'description' => $description . " ({$order['order_number']})",
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
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
}

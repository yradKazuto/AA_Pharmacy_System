<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\RoleMiddleware;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Product;
use App\Services\PurchaseService;

class PurchaseOrderController extends Controller
{
    /**
     * List purchase orders.
     *
     * @return void
     */
    public function index()
    {
        RoleMiddleware::requirePermission('purchasing:view', '/unauthorized');

        $poModel = new PurchaseOrder();
        $purchaseOrders = $poModel->allWithDetails();

        $this->view('purchases/index', [
            'statuses' => PurchaseOrder::statuses(),
            'purchaseOrders' => $purchaseOrders
        ]);
    }

    /**
     * Show the form to create a purchase order.
     * With ?suggest=1 the form is pre-loaded with reorder suggestions (rules 6).
     *
     * @return void
     */
    public function create()
    {
        RoleMiddleware::requirePermission('purchasing:edit', '/unauthorized');

        $supplierModel = new Supplier();
        $productModel = new Product();

        $suggestedItems = [];
        if (isset($_GET['suggest']) && $_GET['suggest'] === '1') {
            $service = new PurchaseService();
            foreach ($service->getReorderSuggestions() as $s) {
                $suggestedItems[] = [
                    'product_id' => $s['id'],
                    'product_name' => $s['name'],
                    'unit' => $s['unit'],
                    'quantity' => $s['suggested_qty'],
                    'unit_cost' => round((float)$s['unit_price'] * 0.7, 2), // typical wholesale estimate
                ];
            }
        }

        $this->view('purchases/form', [
            'suppliers' => $supplierModel->allActive(),
            'products' => $productModel->getAllWithStock(),
            'suggestedItems' => $suggestedItems
        ]);
    }

    /**
     * Store a new purchase order (lines created in one transaction).
     *
     * @return void
     */
    public function store()
    {
        RoleMiddleware::requirePermission('purchasing:edit', '/unauthorized');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/purchases/create');
            return;
        }

        if (!self::verifyCsrfToken($_POST['_csrf_token'] ?? '')) {
            $this->redirect('/purchases/create?error=Invalid CSRF token.');
            return;
        }

        $supplierId = (int)($_POST['supplier_id'] ?? 0);
        $expectedDate = trim($_POST['expected_date'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        $items = [];
        $productIds = $_POST['product_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        $costs = $_POST['unit_cost'] ?? [];
        for ($i = 0; $i < count($productIds); $i++) {
            $items[] = [
                'product_id' => $productIds[$i],
                'quantity' => $quantities[$i],
                'unit_cost' => $costs[$i],
            ];
        }

        $service = new PurchaseService();
        $result = $service->createPurchaseOrder($supplierId, $items, $expectedDate, $notes, $_SESSION['user_id'] ?? null);

        if ($result['ok']) {
            $auditLog = new \App\Models\AuditLog();
            $auditLog->log([
                'user_id' => $_SESSION['user_id'],
                'action' => 'purchase_order_create',
                'table_affected' => 'purchase_orders',
                'record_id' => $result['po_id'],
                'description' => "Purchase order {$result['po_number']} created",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
            $this->redirect('/purchases?success=Purchase order ' . urlencode($result['po_number']) . ' created.');
        } else {
            $this->redirect('/purchases/create?error=' . urlencode($result['message']));
        }
    }

    /**
     * Show one purchase order with its line items.
     *
     * @param int $id
     * @return void
     */
    public function show($id)
    {
        RoleMiddleware::requirePermission('purchasing:view', '/unauthorized');

        $poId = (int)$id;
        $poModel = new PurchaseOrder();
        $po = $poModel->findWithDetails($poId);

        if (!$po) {
            $this->redirect('/purchases?error=Purchase order not found.');
            return;
        }

        $this->view('purchases/show', [
            'statuses' => PurchaseOrder::statuses(),
            'po' => $po
        ]);
    }

    /**
     * Show the receiving form for a purchase order.
     * Pre-fills lot/expiry placeholder and each line's remaining quantity.
     *
     * @param int $id
     * @return void
     */
    public function receive($id)
    {
        RoleMiddleware::requirePermission('purchasing:edit', '/unauthorized');

        $poId = (int)$id;
        $poModel = new PurchaseOrder();
        $po = $poModel->findWithDetails($poId);

        if (!$po) {
            $this->redirect('/purchases?error=Purchase order not found.');
            return;
        }

        if ($po['status'] === 'received') {
            $this->redirect("/purchases/{$poId}?error=This purchase order is already received.");
            return;
        }
        if ($po['status'] === 'cancelled') {
            $this->redirect("/purchases/{$poId}?error=A cancelled purchase order cannot be received.");
            return;
        }

        $this->view('purchases/receive', [
            'po' => $po
        ]);
    }

    /**
     * Process the receiving of a purchase order (one transaction).
     *
     * @param int $id
     * @return void
     */
    public function storeReceive($id)
    {
        RoleMiddleware::requirePermission('purchasing:edit', '/unauthorized');

        $poId = (int)$id;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect("/purchases/{$poId}/receive");
            return;
        }

        if (!self::verifyCsrfToken($_POST['_csrf_token'] ?? '')) {
            $this->redirect("/purchases/{$poId}/receive?error=Invalid CSRF token.");
            return;
        }

        $receivedItems = [];
        $itemIds = $_POST['item_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        $lots = $_POST['lot_number'] ?? [];
        $expiries = $_POST['expiry_date'] ?? [];
        $costs = $_POST['unit_cost'] ?? [];
        for ($i = 0; $i < count($itemIds); $i++) {
            $receivedItems[] = [
                'item_id' => $itemIds[$i],
                'qty' => $quantities[$i],
                'lot_number' => $lots[$i],
                'expiry_date' => $expiries[$i],
                'unit_cost' => $costs[$i],
            ];
        }

        $service = new PurchaseService();
        $result = $service->receivePurchaseOrder($poId, $receivedItems, $_SESSION['user_id'] ?? null);

        if ($result['ok']) {
            $auditLog = new \App\Models\AuditLog();
            $auditLog->log([
                'user_id' => $_SESSION['user_id'],
                'action' => 'purchase_order_receive',
                'table_affected' => 'purchase_orders',
                'record_id' => $poId,
                'description' => "Received PO {$poId}: " . $result['message'],
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
            $this->redirect("/purchases/{$poId}?success=" . urlencode($result['message']));
        } else {
            $this->redirect("/purchases/{$poId}/receive?error=" . urlencode($result['message']));
        }
    }

    /**
     * Cancel a purchase order (only while not yet received).
     *
     * @param int $id
     * @return void
     */
    public function cancel($id)
    {
        RoleMiddleware::requirePermission('purchasing:edit', '/unauthorized');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/purchases');
            return;
        }

        if (!self::verifyCsrfToken($_POST['_csrf_token'] ?? '')) {
            $this->redirect('/purchases?error=Invalid CSRF token.');
            return;
        }

        $poId = (int)$id;
        $poModel = new PurchaseOrder();
        $po = $poModel->find($poId);

        if (!$po) {
            $this->redirect('/purchases?error=Purchase order not found.');
            return;
        }

        if ($po['status'] !== 'ordered' && $po['status'] !== 'draft') {
            $this->redirect("/purchases/{$poId}?error=Only an open purchase order can be cancelled.");
            return;
        }

        $result = $poModel->cancel($poId);

        if ($result) {
            $auditLog = new \App\Models\AuditLog();
            $auditLog->log([
                'user_id' => $_SESSION['user_id'],
                'action' => 'purchase_order_cancel',
                'table_affected' => 'purchase_orders',
                'record_id' => $poId,
                'description' => "Purchase order {$po['po_number']} cancelled",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
            $this->redirect('/purchases?success=Purchase order cancelled.');
        } else {
            $this->redirect('/purchases?error=Failed to cancel purchase order.');
        }
    }

    /**
     * Reorder suggestions page (recommends only — rule 6).
     *
     * @return void
     */
    public function suggestions()
    {
        RoleMiddleware::requirePermission('purchasing:view', '/unauthorized');

        $service = new PurchaseService();
        $suggestions = $service->getReorderSuggestions();

        $this->view('purchases/suggestions', [
            'suggestions' => $suggestions
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

<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\RoleMiddleware;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleService;

class SaleController extends Controller
{
    /**
     * POS terminal — the point-of-sale cart page.
     * Requires pos:process (cashier, pharmacist, admin).
     *
     * @return void
     */
    public function index()
    {
        RoleMiddleware::requirePermission('pos:process', '/unauthorized');

        $productModel = new Product();
        $products = $productModel->getAllWithStock();

        $this->view('pos/terminal', [
            'products' => $products,
            'paymentMethods' => Sale::paymentMethods()
        ]);
    }

    /**
     * JSON product search for the POS cart (Fetch API).
     *
     * @return void
     */
    public function search()
    {
        RoleMiddleware::requirePermission('pos:process', '/unauthorized');

        $q = trim($_GET['q'] ?? '');
        $productModel = new Product();
        $stmt = $productModel->getDb()->prepare(
            "SELECT p.id, p.name, p.generic_name, p.unit, p.unit_price, p.requires_prescription,
                    COALESCE((SELECT SUM(b.quantity) FROM batches b
                              WHERE b.product_id = p.id AND b.is_active = 1
                                AND b.expiry_date > CURDATE() AND b.quantity > 0), 0) AS total_qty
             FROM products p
             WHERE p.is_active = 1 AND (p.name LIKE ? OR p.generic_name LIKE ?)
             ORDER BY p.name ASC
             LIMIT 20"
        );
        $like = '%' . $q . '%';
        $stmt->execute([$like, $like]);
        $this->json(['ok' => true, 'products' => $stmt->fetchAll()]);
    }

    /**
     * List recent sales (read: pos:process).
     *
     * @return void
     */
    public function list()
    {
        RoleMiddleware::requirePermission('pos:process', '/unauthorized');

        $saleModel = new Sale();
        $this->view('pos/sales', [
            'sales' => $saleModel->recent()
        ]);
    }

    /**
     * Store a new sale (POST /pos/checkout). Server-side compute, single
     * transaction in SaleService.
     *
     * @return void
     */
    public function store()
    {
        RoleMiddleware::requirePermission('pos:process', '/unauthorized');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/pos');
            return;
        }
        if (!self::verifyCsrfToken($_POST['_csrf_token'] ?? '')) {
            $this->redirect('/pos?error=Invalid CSRF token.');
            return;
        }

        $items = [];
        $productIds = $_POST['product_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        for ($i = 0; $i < count($productIds); $i++) {
            $items[] = [
                'product_id' => (int)$productIds[$i],
                'qty' => (int)($quantities[$i] ?? 0),
            ];
        }

        $discount = (float)($_POST['discount'] ?? 0);
        $paymentMethod = $_POST['payment_method'] ?? 'cash';
        $tendered = (float)($_POST['amount_tendered'] ?? 0);
        $customer = trim($_POST['customer_name'] ?? '');

        $service = new SaleService();
        $result = $service->createSale($items, $discount, $paymentMethod, $tendered, $customer !== '' ? $customer : null, $_SESSION['user_id'] ?? null);

        if ($result['ok']) {
            $auditLog = new \App\Models\AuditLog();
            $auditLog->log([
                'user_id' => $_SESSION['user_id'],
                'action' => 'sale_create',
                'table_affected' => 'sales',
                'record_id' => $result['sale_id'],
                'description' => "Sale {$result['sale_number']} completed, total PHP " . number_format($result['total'], 2) . ($paymentMethod === 'cash' ? ", change PHP " . number_format($result['change'], 2) : ''),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
            $this->redirect('/sales/' . $result['sale_id'] . '?success=' . urlencode('Sale ' . $result['sale_number'] . ' completed.'));
        } else {
            $this->redirect('/pos?error=' . urlencode($result['message']));
        }
    }

    /**
     * Show a sale receipt / detail.
     *
     * @param int $id
     * @return void
     */
    public function show($id)
    {
        RoleMiddleware::requirePermission('pos:process', '/unauthorized');

        $saleId = (int)$id;
        $saleModel = new Sale();
        $sale = $saleModel->findWithItems($saleId);

        if (!$sale) {
            $this->redirect('/pos/sales?error=Sale not found.');
            return;
        }

        $this->view('pos/receipt', [
            'sale' => $sale
        ]);
    }

    /**
     * Void a sale — restores stock via SaleService. Restricted to staff who can
     * edit inventory (pharmacist/admin), so cashiers can't reverse sales.
     *
     * @param int $id
     * @return void
     */
    public function void($id)
    {
        RoleMiddleware::requirePermission('inventory:edit', '/unauthorized');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/pos/sales');
            return;
        }
        if (!self::verifyCsrfToken($_POST['_csrf_token'] ?? '')) {
            $this->redirect('/pos/sales?error=Invalid CSRF token.');
            return;
        }

        $saleId = (int)$id;
        $service = new SaleService();
        $result = $service->voidSale($saleId, $_SESSION['user_id'] ?? null);

        if ($result['ok']) {
            $auditLog = new \App\Models\AuditLog();
            $auditLog->log([
                'user_id' => $_SESSION['user_id'],
                'action' => 'sale_void',
                'table_affected' => 'sales',
                'record_id' => $saleId,
                'description' => $result['message'],
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
            $this->redirect('/sales/' . $saleId . '?success=' . urlencode($result['message']));
        } else {
            $this->redirect('/pos/sales?error=' . urlencode($result['message']));
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

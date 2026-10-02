<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\RoleMiddleware;
use App\Models\Batch;
use App\Models\Product;
use App\Services\StockService;

class BatchController extends Controller
{
    /**
     * Show a product's batches and a receive-stock form.
     *
     * @param int $productId
     * @return void
     */
    public function index($productId)
    {
        RoleMiddleware::requirePermission('inventory:view', '/unauthorized');

        $productId = (int)$productId;
        $productModel = new Product();
        $product = $productModel->find($productId);

        if (!$product) {
            $this->redirect('/products?error=Product not found.');
            return;
        }

        $batchModel = new Batch();
        $batches = $batchModel->allForProduct($productId);

        $this->view('batches/index', [
            'product' => $product,
            'batches' => $batches
        ]);
    }

    /**
     * Receive stock into a new/existing batch for a product.
     *
     * @param int $productId
     * @return void
     */
    public function store($productId)
    {
        RoleMiddleware::requirePermission('inventory:edit', '/unauthorized');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect("/products/{$productId}/batches");
            return;
        }

        if (!self::verifyCsrfToken($_POST['_csrf_token'] ?? '')) {
            $this->redirect("/products/{$productId}/batches?error=Invalid CSRF token.");
            return;
        }

        $productId = (int)$productId;
        $product = (new Product())->find($productId);
        if (!$product) {
            $this->redirect('/products?error=Product not found.');
            return;
        }

        $svc = new StockService();
        $result = $svc->receiveStock(
            $productId,
            trim($_POST['lot_number'] ?? ''),
            $_POST['expiry_date'] ?? '',
            (int)($_POST['quantity'] ?? 0),
            (float)($_POST['unit_cost'] ?? 0),
            trim($_POST['supplier'] ?? ''),
            $_SESSION['user_id'] ?? null
        );

        if ($result['ok']) {
            $auditLog = new \App\Models\AuditLog();
            $auditLog->log([
                'user_id' => $_SESSION['user_id'],
                'action' => 'stock_receive',
                'table_affected' => 'batches',
                'record_id' => $result['batch_id'],
                'description' => "Received stock for {$product['name']} (lot " . trim($_POST['lot_number'] ?? '') . ")",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
            $this->redirect("/products/{$productId}/batches?success=" . urlencode($result['message']));
        } else {
            $this->redirect("/products/{$productId}/batches?error=" . urlencode($result['message']));
        }
    }

    /**
     * Retire (soft-deactivate) a batch.
     *
     * @param int $id
     * @return void
     */
    public function destroy($id)
    {
        RoleMiddleware::requirePermission('inventory:edit', '/unauthorized');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/products');
            return;
        }

        if (!self::verifyCsrfToken($_POST['_csrf_token'] ?? '')) {
            $this->redirect('/products?error=Invalid CSRF token.');
            return;
        }

        $batchId = (int)$id;
        $batchModel = new Batch();
        $batch = $batchModel->find($batchId);

        if (!$batch) {
            $this->redirect('/products?error=Batch not found.');
            return;
        }

        $result = $batchModel->retire($batchId);

        if ($result) {
            $auditLog = new \App\Models\AuditLog();
            $auditLog->log([
                'user_id' => $_SESSION['user_id'],
                'action' => 'batch_retire',
                'table_affected' => 'batches',
                'record_id' => $batchId,
                'description' => "Batch retired: lot {$batch['lot_number']}",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
            $this->redirect("/products/{$batch['product_id']}/batches?success=Batch retired successfully.");
        } else {
            $this->redirect('/products?error=Failed to retire batch.');
        }
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
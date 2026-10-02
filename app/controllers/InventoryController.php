<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\RoleMiddleware;
use App\Models\Batch;
use App\Models\Product;
use App\Services\StockService;

class InventoryController extends Controller
{
    /**
     * Stock overview: every product with available quantity + low-stock badge.
     *
     * @return void
     */
    public function index()
    {
        RoleMiddleware::requirePermission('inventory:view', '/unauthorized');

        $productModel = new Product();
        $products = $productModel->getAllWithStock();

        $this->view('inventory/index', [
            'products' => $products
        ]);
    }

    /**
     * Adjust a batch's quantity (POST). Uses StockService guards.
     *
     * @param int $batchId
     * @return void
     */
    public function adjust($batchId)
    {
        RoleMiddleware::requirePermission('inventory:edit', '/unauthorized');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/inventory');
            return;
        }

        if (!self::verifyCsrfToken($_POST['_csrf_token'] ?? '')) {
            $this->redirect('/inventory?error=Invalid CSRF token.');
            return;
        }

        $batchId = (int)$batchId;
        $batchModel = new Batch();
        $batch = $batchModel->find($batchId);

        if (!$batch) {
            $this->redirect('/inventory?error=Batch not found.');
            return;
        }

        $delta = (int)($_POST['delta'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');

        if (empty($reason)) {
            $this->redirect("/products/{$batch['product_id']}/batches?error=Reason is required for an adjustment.");
            return;
        }

        $svc = new StockService();
        $result = $svc->adjustStock($batchId, $delta, $reason, $_SESSION['user_id'] ?? null);

        if ($result['ok']) {
            $auditLog = new \App\Models\AuditLog();
            $auditLog->log([
                'user_id' => $_SESSION['user_id'],
                'action' => 'stock_adjust',
                'table_affected' => 'batches',
                'record_id' => $batchId,
                'description' => "Batch {$batch['lot_number']} adjusted by {$delta} ({$reason})",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
            $this->redirect("/products/{$batch['product_id']}/batches?success=" . urlencode($result['message']));
        } else {
            $this->redirect("/products/{$batch['product_id']}/batches?error=" . urlencode($result['message']));
        }
    }

    /**
     * Alerts page: expired / expiring / low-stock lists.
     *
     * @return void
     */
    public function alerts()
    {
        RoleMiddleware::requirePermission('inventory:view', '/unauthorized');

        $config = require __DIR__ . '/../../config/app.php';
        $svc = new StockService();

        $this->view('inventory/alerts', [
            'expired' => $svc->getExpired(),
            'expiring' => $svc->getExpiring((int)($config['near_expiry_days'] ?? 30)),
            'lowStock' => $svc->getLowStock(),
            'threshold' => (int)($config['low_stock_threshold'] ?? 10),
            'expiringDays' => (int)($config['near_expiry_days'] ?? 30),
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
<?php

/**
 * Phase 6 automated tests — reports + dashboards.
 * Run:  php tests/phase6_tests.php
 *
 * Approach mirrors earlier phase test files: uses the real aa_pharmacy DB with
 * clearly-marked test fixtures, then deletes them in a finally block so re-runs
 * stay clean. Exits non-zero on any failure.
 *
 * Business rules covered:
 *  10. Reports use ACTUAL transaction and inventory data only — every
 *      figure here is checked against the real rows it stems from.
 *  4. Movement report surfaces real inventory_movements rows (incl. filter).
 */

// --- Autoloader (same as public/index.php) ---
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $file = $base_dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use App\Core\Database;
use App\Models\Report;
use App\Models\Dashboard;
use App\Services\SaleService;

$db = Database::getInstance()->getConnection();

// --- Assertion helper ---
$GLOBALS['failures'] = 0;
$GLOBALS['count'] = 0;

function assertTrue($cond, $msg)
{
    $GLOBALS['count']++;
    if ($cond) {
        echo "  PASS: {$msg}\n";
    } else {
        $GLOBALS['failures']++;
        echo "  FAIL: {$msg}\n";
    }
}

// --- Fixture helpers ---
function makeProduct($name, $price)
{
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("INSERT INTO products (name, generic_name, category, unit, unit_price) VALUES (?, 'Test', 'Test', 'tablet', ?)");
    $stmt->execute([$name, $price]);
    return (int)$db->lastInsertId();
}

function makeBatch($productId, $lot, $expiry, $qty, $cost)
{
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("INSERT INTO batches (product_id, lot_number, expiry_date, quantity, unit_cost, received_date) VALUES (?, ?, ?, ?, ?, CURDATE())");
    $stmt->execute([$productId, $lot, $expiry, $qty, $cost]);
    return (int)$db->lastInsertId();
}

$day = function ($offset) {
    return date('Y-m-d', strtotime("{$offset} days"));
};

// Test fixtures to track for cleanup.
$createdProductIds = [];
$createdBatchIds = [];
$createdSaleIds = [];

// Seeded products exist? We don't delete those — we only assert against them.

$report = new Report();
$svc = new SaleService();

try {
    echo "\n== TEST: sales report math + date filter (rule 10) ==\n";

    // Known product: 10 units cost 8, sold qty 3 @ price 30.
    $p = makeProduct('TEST_RPT_PROD', 30.00);
    $createdProductIds[] = $p;
    $b = makeBatch($p, 'RPT-LOT', $day('+60'), 10, 8.00);
    $createdBatchIds[] = $b;

    // Sale 1: 3 units @30 = 90, discount 10 => total 80.
    $r1 = $svc->createSale([['product_id' => $p, 'qty' => 3]], 10.00, 'cash', 100, null, null);
    assertTrue($r1['ok'] === true, "sale to report created");
    $createdSaleIds[] = $r1['sale_id'];

    // Whole-history summary must include it.
    $today = date('Y-m-d');
    $from = date('Y-m-d', strtotime('-2 days'));
    $summary = $report->salesSummary($from, $today);
    assertTrue((int)$summary['count'] >= 1, "sales summary counts at least the created sale");
    assertTrue((float)$summary['net'] >= 80.00, "net revenue >= 80 (the created sale's net)");

    // Per-product: our product shows qty 3, revenue 90 (gross, line_total).
    $byProduct = $report->salesByProduct($from, $today);
    $found = null;
    foreach ($byProduct as $row) {
        if ((int)$row['qty_sold'] === 3 && abs((float)$row['revenue'] - 90.00) < 0.001) {
            $found = $row;
        }
    }
    assertTrue($found !== null, "sales-by-product lists the created sale line (qty 3, revenue 90)");

    // Date filter: a range ending yesterday cannot include today's sale
    // (checked deterministically via our unique product's per-product totals).
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $pastByProduct = $report->salesByProduct(date('Y-m-d', strtotime('-30 days')), $yesterday);
    $leaked = false;
    foreach ($pastByProduct as $row) {
        if ($row['name'] === 'TEST_RPT_PROD' && (int)$row['qty_sold'] > 0) {
            $leaked = true;
        }
    }
    assertTrue(!$leaked, "date range ending yesterday excludes today's sale of TEST_RPT_PROD");

    echo "\n== TEST: inventory valuation equals sum of valid batches ==\n";

    // Product with two valid batches + one expired batch (expired excluded).
    $pVal = makeProduct('TEST_RPT_VAL', 1.00);
    $createdProductIds[] = $pVal;
    $b1 = makeBatch($pVal, 'V-A', $day('+40'), 5, 10.00);   // valid: 5*10 = 50
    $b2 = makeBatch($pVal, 'V-B', $day('+90'), 2, 20.00);   // valid: 2*20 = 40
    $b3 = makeBatch($pVal, 'V-EXP', $day('-1'), 100, 99.00); // expired -> excluded
    $createdBatchIds[] = $b1;
    $createdBatchIds[] = $b2;
    $createdBatchIds[] = $b3;

    $valuation = $report->inventoryValuation();
    $valRow = null;
    foreach ($valuation['products'] as $row) {
        if ((int)$row['id'] === $pVal) {
            $valRow = $row;
        }
    }
    assertTrue($valRow !== null, "valuation includes the test product");
    assertTrue((float)$valRow['qty'] === 7.0, "valuation qty = 5 + 2 (expired batch excluded)");
    assertTrue((float)$valRow['value'] === 90.0, "valuation value = 50 + 40 (expired batch excluded)");
    // Grand total >= 90 (contains at least our product's value).
    assertTrue((float)$valuation['total'] >= 90.0, "valuation grand total >= product value");

    echo "\n== TEST: movement report surfaces real rows + type filter ==\n";

    // The created sale wrote a 'sale' movement; the valuation product batch writes nothing.
    $allMovements = $report->movements();
    $saleRef = $r1['sale_number'];
    $foundSaleMv = false;
    foreach ($allMovements as $m) {
        if ($m['movement_type'] === 'sale' && $m['reference'] === $saleRef) {
            $foundSaleMv = true;
        }
    }
    assertTrue($foundSaleMv, "movement log contains the real 'sale' movement from createSale");

    // Type filter to 'receive' should exclude the 'sale' movement.
    $recvOnly = $report->movements('receive');
    $leak = false;
    foreach ($recvOnly as $m) {
        if ($m['movement_type'] !== 'receive') {
            $leak = true;
        }
    }
    assertTrue(!$leak && count($recvOnly) > 0, "movement type filter returns only 'receive' rows");

    echo "\n== TEST: dashboard KPI cross-checks report (same query source) ==\n";

    $dash = new Dashboard();
    $adminKpis = $dash->kpisForRole('admin');
    // Today_revenue should equal today's completed sales total from the report.
    $todaySummary = $report->salesSummary($today, $today);
    assertTrue((float)$adminKpis['today_revenue'] === (float)$todaySummary['net'],
        "dashboard today_revenue equals report net for today");
    assertTrue((int)$adminKpis['today_sales_count'] === (int)$todaySummary['count'],
        "dashboard today_sales_count equals report count for today");

    // Open POs count is an integer and >= 0.
    assertTrue((int)$adminKpis['open_pos'] >= 0, "dashboard open_pos is a non-negative integer");

    echo "\n" . ($GLOBALS['failures'] === 0 ? "ALL TESTS PASSED ({$GLOBALS['count']} checks).\n" : "{$GLOBALS['failures']} TEST(S) FAILED.\n");
    exit($GLOBALS['failures'] === 0 ? 0 : 1);

} finally {
    // --- Cleanup: remove TEST_ fixtures ---
    foreach ($createdSaleIds as $sid) {
        if ($sid) {
            $db->prepare("DELETE FROM inventory_movements WHERE reference = (SELECT sale_number FROM sales WHERE id = ?)")->execute([$sid]);
            $db->prepare("DELETE FROM sale_items WHERE sale_id = ?")->execute([$sid]);
            $db->prepare("DELETE FROM sales WHERE id = ?")->execute([$sid]);
        }
    }
    foreach ($createdBatchIds as $bid) {
        if ($bid) {
            $db->prepare("DELETE FROM inventory_movements WHERE batch_id = ?")->execute([$bid]);
            $db->prepare("DELETE FROM batches WHERE id = ?")->execute([$bid]);
        }
    }
    foreach ($createdProductIds as $pid) {
        if ($pid) {
            $db->prepare("DELETE FROM inventory_movements WHERE product_id = ?")->execute([$pid]);
            $db->prepare("DELETE FROM batches WHERE product_id = ?")->execute([$pid]);
            $db->prepare("DELETE FROM sale_items WHERE product_id = ?")->execute([$pid]);
            $db->prepare("DELETE FROM products WHERE id = ?")->execute([$pid]);
        }
    }
    echo "\n[cleanup] TEST_ fixtures removed.\n";
}

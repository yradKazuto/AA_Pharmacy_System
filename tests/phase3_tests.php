<?php

/**
 * Phase 3 automated tests — FEFO, expiry blocking, receive, adjust, alerts.
 * Run:  php tests/phase3_tests.php
 *
 * Approach: uses the real aa_pharmacy DB with clearly-marked TEST_ fixtures,
 * then deletes those fixtures (in a finally block) so re-runs stay clean and
 * real seeded data is untouched. Exits non-zero on any failure.
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
use App\Services\StockService;

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
function makeProduct($name)
{
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("INSERT INTO products (name, generic_name, category, unit, unit_price) VALUES (?, 'Test', 'Test', 'tablet', 10.00)");
    $stmt->execute([$name]);
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

// --- Runner ---
$svc = new StockService();

// Named fixtures so we can clean them up in finally.
$createdProductIds = [];
$createdBatchIds = [];

try {
    echo "\n== TEST: Receive + FEFO selection with real StockService ==\n";

    // Fixture 1: product with three future batches (A < B < C expiries).
    $p1 = makeProduct('TEST_FEFO_PRODUCT');
    $createdProductIds[] = $p1;
    $bA = makeBatch($p1, 'T-A', $day('+90'), 10, 2.00);   // earliest (pick first)
    $bB = makeBatch($p1, 'T-B', $day('+120'), 20, 2.50);  // middle
    $bC = makeBatch($p1, 'T-C', $day('+150'), 30, 3.00);  // latest
    $createdBatchIds[] = $bA; $createdBatchIds[] = $bB; $createdBatchIds[] = $bC;

    // Available qty = 10+20+30 = 60
    assertTrue($svc->getAvailableQty($p1) === 60, "getAvailableQty returns 60 (got " . $svc->getAvailableQty($p1) . ")");

    // FEFO: pick 25 -> should take all 10 from A, then 15 from B. Order A then B.
    $picks = $svc->pickBatches($p1, 25);
    assertTrue(is_array($picks), "pickBatches(25) returns an array");
    assertTrue($picks[0]['batch_id'] === $bA && $picks[0]['quantity'] === 10, "FEFO picks earliest batch A first with qty 10");
    assertTrue($picks[1]['batch_id'] === $bB && $picks[1]['quantity'] === 15, "then picks batch B with remaining qty 15");
    assertTrue(count($picks) === 2, "only two batches picked (got " . count($picks) . ")");

    // Exact full pick.
    $picksFull = $svc->pickBatches($p1, 60);
    assertTrue(is_array($picksFull) && count($picksFull) === 3, "picking full 60 spans all 3 batches");

    // Insufficient -> no partial pick.
    assertTrue($svc->pickBatches($p1, 999) === false, "insufficient stock returns false (no partial pick)");

    // Drop the earliest-expiring batch A to make a controlled expired test.
    $db->prepare("DELETE FROM batches WHERE id = ?")->execute([$bA]);

    echo "\n== TEST: Expired batch is never picked ==\n";

    // Fixture 2: same product gains an EXPIRED batch (earliest date) — must be excluded.
    $bExp = makeBatch($p1, 'T-EXP', $day('-10'), 100, 1.00);
    $createdBatchIds[] = $bExp;
    // Now sellable = B(20) + C(30) = 50 (expired 100 excluded).
    assertTrue($svc->getAvailableQty($p1) === 50, "expired batch qty excluded from available (got " . $svc->getAvailableQty($p1) . ")");
    // FEFO should pick the expired batch only if wrong; it must pick B then C.
    $picks = $svc->pickBatches($p1, 25);
    assertTrue($picks[0]['batch_id'] === $bB, "FEFO skips expired batch, picks earliest valid (B) first");
    // fetch direct via model to confirm expired batch reported separately
    $expiredRows = $svc->getExpired();
    $foundExpired = false;
    foreach ($expiredRows as $r) {
        if ((int)$r['id'] === $bExp) { $foundExpired = true; }
    }
    assertTrue($foundExpired, "expired batch appears in getExpired() list");

    echo "\n== TEST: receiveStock logs movement + rejects expired receive ==\n";

    $rec = $svc->receiveStock($p1, 'T-RECV', $day('+30'), 5, 1.50, 'TestSup', null);
    $createdBatchIds[] = $rec['batch_id'] ?? 0;
    assertTrue($rec['ok'] === true, "receiveStock succeeds");
    $mov = $db->prepare("SELECT * FROM inventory_movements WHERE movement_type='receive' AND batch_id=?")->execute([$rec['batch_id']]);
    $mv = $db->prepare("SELECT * FROM inventory_movements WHERE movement_type='receive' AND batch_id=?");
    $mv->execute([$rec['batch_id']]);
    assertTrue((bool)$mv->fetch(), "receive created an inventory_movements 'receive' row");

    // Reject receiving an already-expired batch.
    $recExp = $svc->receiveStock($p1, 'T-BAD', $day('-5'), 5, 1.00, null, null);
    assertTrue($recExp['ok'] === false, "receiveStock rejects an already-expired batch");

    echo "\n== TEST: adjustStock guards ==\n";

    // Valid negative adjust on B (qty 20 -> 18), logs a movement.
    $adj = $svc->adjustStock($bB, -2, 'test write-off', null);
    assertTrue($adj['ok'] === true, "negative adjust succeeds");
    $b = $db->prepare("SELECT quantity FROM batches WHERE id=?");
    $b->execute([$bB]);
    assertTrue((int)$b->fetchColumn() === 18, "batch B quantity is now 18");
    $m = $db->prepare("SELECT * FROM inventory_movements WHERE batch_id=? AND quantity_change=-2");
    $m->execute([$bB]);
    assertTrue((bool)$m->fetch(), "negative adjust created a movement row");

    // Negative overflow guard.
    $guard = $svc->adjustStock($bB, -99999, 'should fail', null);
    assertTrue($guard['ok'] === false, "adjust that would go negative is rejected");

    // Cannot add stock to an expired batch.
    $expAdj = $svc->adjustStock($bExp, +5, 'should fail', null);
    assertTrue($expAdj['ok'] === false, "adding stock to expired batch is rejected");

    echo "\n== TEST: alerts ==\n";

    // Low stock fixture: product with fewer than threshold units sellable.
    $low = makeProduct('TEST_LOW_STOCK');
    $createdProductIds[] = $low;
    makeBatch($low, 'T-LOW', $day('+60'), 3, 1.00);

    $lowStock = $svc->getLowStock();
    $foundLow = false;
    foreach ($lowStock as $r) {
        if ((int)$r['id'] === $low) { $foundLow = true; }
    }
    assertTrue($foundLow, "low-stock product appears in getLowStock()");

    $expiring = $svc->getExpiring(30);
    $foundSoon = false;
    foreach ($expiring as $r) {
        if ((int)$r['id'] === ($rec['batch_id'] ?? -1)) { $foundSoon = true; } // T-RECV expires +30 -> within 30 days
    }
    assertTrue($foundSoon, "near-expiry batch (received +30d) appears in getExpiring(30)");

    echo "\n== TEST: HTTP home-route redirect fix (skipped if server not running) ==\n";
    $http = @file_get_contents('http://localhost:8000/', false, stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]));
    $httpCode = isset($http_header) ? 0 : (preg_match('/HTTP\/\S+ (\d+)/', $http_response_header[0] ?? '', $m) ? (int)$m[1] : 0);
    if (!$http) {
        echo "  SKIP: dev server not running.\n";
    } else {
        $headers = isset($http_response_header) ? implode("\n", $http_response_header) : '';
        assertTrue($httpCode === 302, "GET / returns 302 (got {$httpCode})");
        assertTrue(stripos($headers, '/login') !== false, "GET / redirects to /login (header: {$headers})");
    }

    echo "\n" . ($GLOBALS['failures'] === 0 ? "ALL TESTS PASSED ({$GLOBALS['count']} checks).\n" : "{$GLOBALS['failures']} TEST(S) FAILED.\n");
    exit($GLOBALS['failures'] === 0 ? 0 : 1);

} finally {
    // --- Cleanup: remove TEST fixtures (products, their batches, movements) ---
    foreach ($createdBatchIds as $bid) {
        if ($bid) {
            $db->prepare("DELETE FROM inventory_movements WHERE batch_id = ?")->execute([$bid]);
            $db->prepare("DELETE FROM batches WHERE id = ?")->execute([$bid]);
        }
    }
    foreach ($createdProductIds as $pid) {
        if ($pid) {
            // movements not tied to a captured batch
            $db->prepare("DELETE FROM inventory_movements WHERE product_id = ?")->execute([$pid]);
            $db->prepare("DELETE FROM batches WHERE product_id = ?")->execute([$pid]);
            $db->prepare("DELETE FROM products WHERE id = ?")->execute([$pid]);
        }
    }
    echo "\n[cleanup] TEST_ fixtures removed.\n";
}

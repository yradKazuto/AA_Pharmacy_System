<?php

/**
 * Phase 7 automated tests — online ordering portal.
 * Run:  php tests/phase7_tests.php
 *
 * Uses the real aa_pharmacy DB with marked TEST_ fixtures removed in a finally
 * block. Exits non-zero on any failure.
 *
 * Business rules covered:
 *   5. Fulfillment + inventory deduction happen in ONE transaction (stock
 *      only moves when the order is fulfilled).
 *   4. Every stock change writes an inventory_movements row.
 *   8. Prescription items force pharmacist review (requires_review / approve).
 *   9. Customers only ever see their own orders.
 *  10. Fulfilling an online order records a real sale so revenue flows into
 *      the same sales/reports data.
 *
 * Also verifies the FEFO boundary: fulfillment picks the EARLIEST-expiry valid
 * batch first, and never an expired batch.
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
use App\Models\CustomerOrder;
use App\Services\CustomerOrderService;

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

// Unique tag per process run so re-runs never collide with stale leftovers.
$TAG = substr(str_replace(['.', ' ', '-', ':'], '', uniqid('', true)), 0, 8);

// --- Fixture helpers (accept tag argument) ---
function roleIdByName($name)
{
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id FROM roles WHERE name = ?");
    $stmt->execute([$name]);
    return (int)$stmt->fetchColumn();
}

function makeUser($username, $roleName)
{
    global $TAG;
    $db = Database::getInstance()->getConnection();
    $role = roleIdByName($roleName);
    $stmt = $db->prepare(
        "INSERT INTO users (company_id_number, username, email, password_hash, first_name, last_name, role_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        'TEST-' . $username . '-' . $TAG,
        $username . $TAG,
        $username . $TAG . '@aa-pharmacy-test.local',
        password_hash('Test@1234', PASSWORD_DEFAULT),
        'Test',
        ucfirst($roleName),
        $role
    ]);
    return (int)$db->lastInsertId();
}

function makeProduct($name, $price, $rx = 0)
{
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare(
        "INSERT INTO products (name, generic_name, category, unit, unit_price, requires_prescription)
         VALUES (?, 'Test', 'Test', 'tablet', ?, ?)"
    );
    $stmt->execute([$name, $price, $rx]);
    return (int)$db->lastInsertId();
}

function makeBatch($productId, $lot, $expiry, $qty, $cost)
{
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare(
        "INSERT INTO batches (product_id, lot_number, expiry_date, quantity, unit_cost, received_date)
         VALUES (?, ?, ?, ?, ?, CURDATE())"
    );
    $stmt->execute([$productId, $lot, $expiry, $qty, $cost]);
    return (int)$db->lastInsertId();
}

$day = function ($offset) {
    return date('Y-m-d', strtotime("{$offset} days"));
};

// --- Tracked fixtures for cleanup ---
$customerId = null;
$otherCustomerId = null;
$staffId = null;
$productIds = [];
$batchIds = [];
$orderIds = [];
$saleIds = [];

$svc = new CustomerOrderService();
$orderModel = new CustomerOrder();

try {
    echo "\n== TEST: placeOrder creates order + lines, forces review for Rx ==\n";

    $staffId = makeUser('staff7', 'pharmacist');
    $customerId = makeUser('cust7a', 'customer');
    $otherCustomerId = makeUser('cust7b', 'customer');

    $pNormal = makeProduct('TEST_ORD_NORMAL', 25.00, 0);
    $productIds[] = $pNormal;
    $bNormal = makeBatch($pNormal, 'N-LOT', $day('+60'), 10, 8.00);
    $batchIds[] = $bNormal;

    $pRx = makeProduct('TEST_ORD_RX', 100.00, 1);
    $productIds[] = $pRx;
    $bRx = makeBatch($pRx, 'RX-LOT', $day('+45'), 5, 40.00);
    $batchIds[] = $bRx;

    $r = $svc->placeOrder($customerId, [
        ['product_id' => $pNormal, 'qty' => 4],
        ['product_id' => $pRx, 'qty' => 2],
    ], '123 Test St, Barangay, City', '09171234567', 'Please call on arrival');

    assertTrue($r['ok'] === true, "place order returns ok");
    $orderId = $r['order_id'];
    $orderIds[] = $orderId;

    $stmt = $db->prepare("SELECT * FROM customer_orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    assertTrue($order['status'] === 'pending_review', "order status is pending_review");
    assertTrue((int)$order['requires_review'] === 1, "requires_review=1 because of Rx item (rule 8)");
    assertTrue(abs((float)$order['items_total'] - 300.0) < 0.001, "items_total = 100 + 200 = 300");
    assertTrue(abs((float)$order['total_amount'] - 300.0) < 0.001, "total_amount = 300 (delivery_fee 0)");
    assertTrue($order['payment_method'] === 'cod', "payment method is cod");

    $countItems = $db->prepare("SELECT COUNT(*) FROM customer_order_items WHERE customer_order_id = ?");
    $countItems->execute([$orderId]);
    assertTrue((int)$countItems->fetchColumn() === 2, "two order line items created");

    $qtyLeft = $db->prepare("SELECT quantity FROM batches WHERE id = ?");
    $qtyLeft->execute([$bNormal]);
    assertTrue((int)$qtyLeft->fetchColumn() === 10, "no stock deducted at placeOrder (rule 5)");

    $rBad = $svc->placeOrder($customerId, [['product_id' => $pNormal, 'qty' => 99]], 'Addr', '0917', null);
    assertTrue($rBad['ok'] === false, "order over available stock is rejected");

    echo "\n== TEST: rule 9 — a customer only sees/acts on their own order ==\n";

    $mine = $orderModel->forCustomer($customerId);
    $theirs = $orderModel->forCustomer($otherCustomerId);
    assertTrue(count($mine) >= 1, "owner sees their order");
    assertTrue(count($theirs) === 0, "non-owner sees no orders (rule 9)");

    echo "\n== TEST: approve marks approved; invalid transitions blocked ==\n";

    $ap = $svc->approve($orderId, $staffId);
    assertTrue($ap['ok'] === true, "approve pending order ok");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    assertTrue($order['status'] === 'approved', "status now approved");
    assertTrue((int)$order['approved_by'] === $staffId, "approved_by recorded");

    $ap2 = $svc->approve($orderId, $staffId);
    assertTrue($ap2['ok'] === false, "re-approving is blocked (invalid transition)");

    echo "\n== TEST: FEFO fulfillment picks earliest valid batch, skips expired ==\n";

    $pFefo = makeProduct('TEST_ORD_FEFO', 5.00, 0);
    $productIds[] = $pFefo;
    $bA = makeBatch($pFefo, 'F-A', $day('+30'), 10, 2.00);
    $bB = makeBatch($pFefo, 'F-B', $day('+90'), 10, 2.00);
    $bC = makeBatch($pFefo, 'F-EXP', $day('-1'), 10, 2.00);
    $batchIds[] = $bA;
    $batchIds[] = $bB;
    $batchIds[] = $bC;

    $rf = $svc->placeOrder($customerId, [['product_id' => $pFefo, 'qty' => 18]], 'Addr2', '0917', null);
    assertTrue($rf['ok'] === true, "FEFO order placed");
    $orderIds[] = $rf['order_id'];
    assertTrue($svc->approve($rf['order_id'], $staffId)['ok'] === true, "approve FEFO order");

    $fl = $svc->fulfill($rf['order_id'], $staffId);
    assertTrue($fl['ok'] === true, "fulfill FEFO order ok");

    foreach ([[$bA, 'A', 0], [$bB, 'B', 2], [$bC, 'C', 10]] as $row) {
        $q = $db->prepare("SELECT quantity FROM batches WHERE id = ?");
        $q->execute([$row[0]]);
        assertTrue((int)$q->fetchColumn() === $row[2],
            "batch {$row[1]} ends at {$row[2]} (FEFO picks earliest, expired untouched)");
    }

    $stmt->execute([$rf['order_id']]);
    $orderF = $stmt->fetch();
    assertTrue($orderF['status'] === 'fulfilled', "FEFO order status fulfilled");

    $mv = $db->prepare("SELECT COUNT(*) FROM inventory_movements WHERE reference = ?");
    $mv->execute([$orderF['order_number']]);
    assertTrue((int)$mv->fetchColumn() === 2, "two 'sale' inventory_movements written (rule 4)");

    $sale = $db->prepare(
        "SELECT s.id, s.total_amount, s.status FROM sales s
         JOIN sale_items si ON si.sale_id = s.id
         WHERE si.product_id = ? AND si.batch_id = ?
         ORDER BY s.id DESC LIMIT 1"
    );
    $sale->execute([$pFefo, $bA]);
    $linked = $sale->fetch();
    assertTrue($linked !== false, "fulfillment recorded a linked sale (rule 10)");
    if ($linked) {
        $saleIds[] = (int)$linked['id'];
        assertTrue(abs((float)$linked['total_amount'] - 90.0) < 0.001, "linked sale total = 18 * 5 = 90");
        assertTrue($linked['status'] === 'completed', "linked sale status completed");
    }

    echo "\n== TEST: reject sets note; deliver transitions fulfilled -> delivered ==\n";

    // Reject requires a PENDING order (approved -> rejected is not allowed).
    $rp = $svc->placeOrder($customerId, [['product_id' => $pNormal, 'qty' => 1]], 'Addr4', '0917', null);
    assertTrue($rp['ok'] === true, "reject-flow order placed");
    $orderIds[] = $rp['order_id'];
    $rj = $svc->reject($rp['order_id'], 'No valid prescription on file', $staffId);
    assertTrue($rj['ok'] === true, "reject pending order ok");
    $stmt->execute([$rp['order_id']]);
    $order = $stmt->fetch();
    assertTrue($order['status'] === 'rejected', "status now rejected");
    assertTrue($order['rejected_note'] === 'No valid prescription on file', "reject note stored");

    // A separate order to test delivered flow.
    $rd = $svc->placeOrder($customerId, [['product_id' => $pNormal, 'qty' => 2]], 'Addr3', '0917', null);
    assertTrue($rd['ok'] === true, "deliver-flow order placed");
    $orderIds[] = $rd['order_id'];
    assertTrue($svc->approve($rd['order_id'], $staffId)['ok'] === true, "approve deliver-flow order");
    assertTrue($svc->fulfill($rd['order_id'], $staffId)['ok'] === true, "fulfill deliver-flow order");
    $dl = $svc->markDelivered($rd['order_id']);
    assertTrue($dl['ok'] === true, "mark delivered ok");
    $stmt->execute([$rd['order_id']]);
    assertTrue($stmt->fetch()['status'] === 'delivered', "deliver-flow order status delivered");

    // NOTE: no exit() here — in this PHP build exit() inside try skips finally,
    // so we let control fall through to the finally (which runs cleanup), then
    // print the summary and set the exit code AFTER the finally block.

} finally {
    // --- Cleanup: remove TEST_ fixtures (all keyed to our test batches/products) ---
    // 1. Order rows + their movement/sale side-effects for the tracked orders.
    foreach ($orderIds as $oid) {
        if (!$oid) {
            continue;
        }
        $on = $db->prepare("SELECT order_number FROM customer_orders WHERE id = ?");
        $on->execute([$oid]);
        $orderNumber = $on->fetchColumn();
        $db->prepare("DELETE FROM customer_order_items WHERE customer_order_id = ?")->execute([$oid]);
        $db->prepare("DELETE FROM customer_orders WHERE id = ?")->execute([$oid]);
        if ($orderNumber) {
            $db->prepare("DELETE FROM inventory_movements WHERE reference = ?")->execute([$orderNumber]);
        }
    }
    // 2. Any sales created by fulfillment reference our test batches as sale_items.
    foreach ($batchIds as $bid) {
        if (!$bid) {
            continue;
        }
        $linked = $db->prepare("SELECT DISTINCT sale_id FROM sale_items WHERE batch_id = ?");
        $linked->execute([$bid]);
        foreach ($linked->fetchAll() as $row) {
            $db->prepare("DELETE FROM sale_items WHERE sale_id = ?")->execute([$row['sale_id']]);
            $db->prepare("DELETE FROM sales WHERE id = ?")->execute([$row['sale_id']]);
        }
        $db->prepare("DELETE FROM inventory_movements WHERE batch_id = ?")->execute([$bid]);
        $db->prepare("DELETE FROM batches WHERE id = ?")->execute([$bid]);
    }
    foreach ($productIds as $pid) {
        if ($pid) {
            $db->prepare("DELETE FROM products WHERE id = ?")->execute([$pid]);
        }
    }
    // 3. User rows: clear FK refs and any residual orders/sales so deletes never
    //    fail regardless of tracking completeness.
    foreach ([$customerId, $otherCustomerId, $staffId] as $uid) {
        if (!$uid) {
            continue;
        }
        $db->prepare("UPDATE customer_orders SET approved_by = NULL, fulfilled_by = NULL WHERE approved_by = ? OR fulfilled_by = ?")->execute([$uid, $uid]);
        $residual = $db->prepare("SELECT id FROM sales WHERE sold_by = ?");
        $residual->execute([$uid]);
        foreach ($residual->fetchAll() as $row) {
            $db->prepare("DELETE FROM sale_items WHERE sale_id = ?")->execute([$row['id']]);
            $db->prepare("DELETE FROM sales WHERE id = ?")->execute([$row['id']]);
        }
        $theirOrders = $db->prepare("SELECT id FROM customer_orders WHERE customer_id = ? OR approved_by = ? OR fulfilled_by = ?");
        $theirOrders->execute([$uid, $uid, $uid]);
        foreach ($theirOrders->fetchAll() as $row) {
            $db->prepare("DELETE FROM customer_order_items WHERE customer_order_id = ?")->execute([$row['id']]);
            $db->prepare("DELETE FROM customer_orders WHERE id = ?")->execute([$row['id']]);
        }
        $db->prepare("DELETE FROM inventory_movements WHERE user_id = ?")->execute([$uid]);
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
    }
}

// Summary + exit code AFTER cleanup so fixtures never leak between runs.
echo "\n" . ($GLOBALS['failures'] === 0 ? "ALL TESTS PASSED ({$GLOBALS['count']} checks).\n" : "{$GLOBALS['failures']} TEST(S) FAILED.\n");
exit($GLOBALS['failures'] === 0 ? 0 : 1);

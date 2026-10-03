<?php

/**
 * Phase 4 automated tests — suppliers, purchase orders, receiving.
 * Run:  php tests/phase4_tests.php
 *
 * Approach mirrors tests/phase3_tests.php: uses the real aa_pharmacy DB with
 * clearly-marked TEST_ fixtures, then deletes those fixtures (in a finally
 * block) so re-runs stay clean. Exits non-zero on any failure.
 *
 * Business rules covered:
 *   4. Every stock change creates an inventory_movements row.
 *   5. Receiving a PO + all stock movements happen in ONE transaction.
 *   6. Purchasing only recommends reorders (getReorderSuggestions is advice).
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
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Services\PurchaseService;

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

function makeSupplier($name)
{
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES (?, 'T Person', '123', 't@example.com', 'Test')");
    $stmt->execute([$name]);
    return (int)$db->lastInsertId();
}

$day = function ($offset) {
    return date('Y-m-d', strtotime("{$offset} days"));
};

// --- Runner ---
$svc = new PurchaseService();

// Track fixtures for cleanup.
$createdProductIds = [];
$createdSupplierIds = [];
$createdPoIds = [];
$createdBatchIds = [];

try {
    echo "\n== TEST: Supplier model ==\n";

    $supName = 'TEST_SUP_' . uniqid();
    $supId = makeSupplier($supName);
    $createdSupplierIds[] = $supId;
    $sModel = new Supplier();

    $found = $sModel->findByName($supName);
    assertTrue($found && (int)$found['id'] === $supId, "findByName locates the new supplier");
    assertTrue((bool)$sModel->deactivate($supId), "supplier can be deactivated (soft)");
    $sup = Database::getInstance()->getConnection()->query("SELECT is_active FROM suppliers WHERE id = $supId")->fetch();
    assertTrue((int)$sup['is_active'] === 0, "deactivated supplier has is_active = 0");
    // Re-activate for later PO tests.
    Database::getInstance()->getConnection()->exec("UPDATE suppliers SET is_active = 1 WHERE id = $supId");

    echo "\n== TEST: createPurchaseOrder (one transaction) ==\n";

    $p1 = makeProduct('TEST_PO_P1');
    $p2 = makeProduct('TEST_PO_P2');
    $createdProductIds[] = $p1;
    $createdProductIds[] = $p2;

    // Empty items -> rejected.
    $r0 = $svc->createPurchaseOrder($supId, [], '', null, null);
    assertTrue($r0['ok'] === false, "PO with no items is rejected");

    // Invalid supplier -> rejected.
    $r1 = $svc->createPurchaseOrder(99999999, [['product_id' => $p1, 'quantity' => 5, 'unit_cost' => 2.00]], '', null, null);
    assertTrue($r1['ok'] === false, "PO with unknown supplier is rejected");

    // Valid PO with two lines.
    $r2 = $svc->createPurchaseOrder($supId, [
        ['product_id' => $p1, 'quantity' => 10, 'unit_cost' => 2.00],
        ['product_id' => $p2, 'quantity' => 20, 'unit_cost' => 3.50],
    ], $day('+7'), 'Test order', null);
    assertTrue($r2['ok'] === true, "valid PO creates successfully");
    $poId = $r2['po_id'];
    $createdPoIds[] = $poId;
    assertTrue(strpos($r2['po_number'], 'PO-') === 0, "PO number is generated with PO- prefix");

    $detail = (new PurchaseOrder())->findWithDetails($poId);
    assertTrue($detail !== null && count($detail['items']) === 2, "findWithDetails loads both line items");
    assertTrue((float)$detail['total_amount'] === (10 * 2.00 + 20 * 3.50), "total_amount = sum(line totals)");
    assertTrue($detail['status'] === 'ordered', "new PO starts in 'ordered' status");

    echo "\n== TEST: getReorderSuggestions (rule 6, recommendation only) ==\n";

    // Low-stock product (3 units < threshold 10) -> suggested.
    $low = makeProduct('TEST_SUG_LOW');
    $createdProductIds[] = $low;
    $db->prepare("INSERT INTO batches (product_id, lot_number, expiry_date, quantity, unit_cost, received_date) VALUES (?, 'S-L', ?, 3, 1.00, CURDATE())")
       ->execute([$low, $day('+60')]);

    // High-stock product (50 units) -> not suggested.
    $hi = makeProduct('TEST_SUG_HI');
    $createdProductIds[] = $hi;
    $db->prepare("INSERT INTO batches (product_id, lot_number, expiry_date, quantity, unit_cost, received_date) VALUES (?, 'S-H', ?, 50, 1.00, CURDATE())")
       ->execute([$hi, $day('+60')]);

    $sugs = $svc->getReorderSuggestions();
    $foundLow = false; $foundHi = false;
    foreach ($sugs as $s) {
        if ((int)$s['id'] === $low) { $foundLow = true; }
        if ((int)$s['id'] === $hi) { $foundHi = true; }
    }
    assertTrue($foundLow, "low-stock product appears in reorder suggestions");
    assertTrue(!$foundHi, "well-stocked product is NOT suggested");
    foreach ($sugs as $s) {
        if ((int)$s['id'] === $low) {
            assertTrue((int)$s['suggested_qty'] >= 1, "a suggested quantity is provided for the low-stock product");
        }
    }

    echo "\n== TEST: receivePurchaseOrder (whole PO, one transaction) ==\n";

    $p1ItemId = null; $p2ItemId = null;
    foreach ($detail['items'] as $it) {
        if ((int)$it['product_id'] === $p1) { $p1ItemId = (int)$it['id']; }
        if ((int)$it['product_id'] === $p2) { $p2ItemId = (int)$it['id']; }
    }

    // Expired lot -> whole receipt rejected.
    $rExp = $svc->receivePurchaseOrder($poId, [
        ['item_id' => $p1ItemId, 'qty' => 10, 'lot_number' => 'T-BAD-EXP', 'expiry_date' => $day('-1'), 'unit_cost' => 2.00],
        ['item_id' => $p2ItemId, 'qty' => 20, 'lot_number' => 'T-OK', 'expiry_date' => $day('+60'), 'unit_cost' => 3.50],
    ], null);
    assertTrue($rExp['ok'] === false, "receiving an expired lot is rejected");

    // Over-receive -> rejected (qty 25 > 20 ordered on p2).
    $rOver = $svc->receivePurchaseOrder($poId, [
        ['item_id' => $p1ItemId, 'qty' => 10, 'lot_number' => 'T-OK', 'expiry_date' => $day('+60'), 'unit_cost' => 2.00],
        ['item_id' => $p2ItemId, 'qty' => 25, 'lot_number' => 'T-OK', 'expiry_date' => $day('+60'), 'unit_cost' => 3.50],
    ], null);
    assertTrue($rOver['ok'] === false, "over-receiving a line is rejected");

    // Atomicity: an invalid item_id rejects the WHOLE receipt with no partial apply.
    $thatDetail = (new PurchaseOrder())->findWithDetails($poId);
    assertTrue((string)$thatDetail['status'] === 'ordered', "PO still 'ordered' after rejected receipts (no partial apply)");
    $mvCount = $db->prepare("SELECT COUNT(*) FROM inventory_movements WHERE reference = ?");
    $mvCount->execute([$r2['po_number']]);
    assertTrue((int)$mvCount->fetchColumn() === 0, "no inventory movements were written by rejected receipts");

    // Valid full receipt.
    $rOk = $svc->receivePurchaseOrder($poId, [
        ['item_id' => $p1ItemId, 'qty' => 10, 'lot_number' => 'T-RECV-1', 'expiry_date' => $day('+60'), 'unit_cost' => 2.00],
        ['item_id' => $p2ItemId, 'qty' => 20, 'lot_number' => 'T-RECV-2', 'expiry_date' => $day('+90'), 'unit_cost' => 3.50],
    ], null);
    assertTrue($rOk['ok'] === true, "valid whole-PO receipt succeeds");

    // Movements written (rule 4) referenced to the PO.
    $mvCount->execute([$r2['po_number']]);
    assertTrue((int)$mvCount->fetchColumn() === 2, "two 'receive' movements written, referenced to the PO number");

    // Batches created for both products.
    $b1 = $db->prepare("SELECT id, quantity, unit_cost, supplier FROM batches WHERE product_id = ? AND lot_number = ? AND is_active = 1");
    $b1->execute([$p1, 'T-RECV-1']); $row1 = $b1->fetch();
    $b2 = $db->prepare("SELECT id, quantity, unit_cost, supplier FROM batches WHERE product_id = ? AND lot_number = ? AND is_active = 1");
    $b2->execute([$p2, 'T-RECV-2']); $row2 = $b2->fetch();
    assertTrue($row1 && (int)$row1['quantity'] === 10, "batch created for p1 with received qty 10");
    assertTrue($row2 && (int)$row2['quantity'] === 20, "batch created for p2 with received qty 20");
    if ($row1) { $createdBatchIds[] = (int)$row1['id']; }
    if ($row2) { $createdBatchIds[] = (int)$row2['id']; }

    $doneDetail = (new PurchaseOrder())->findWithDetails($poId);
    assertTrue($doneDetail['status'] === 'received', "PO status is now 'received'");
    $recQty = (int)$doneDetail['items'][0]['quantity_received'] + (int)$doneDetail['items'][1]['quantity_received'];
    assertTrue($recQty === 30, "line quantity_received totals equal ordered (10 + 20)");

    // Receiving an already-received PO is rejected.
    $rAgain = $svc->receivePurchaseOrder($poId, [
        ['item_id' => $p1ItemId, 'qty' => 1, 'lot_number' => 'T-X', 'expiry_date' => $day('+60'), 'unit_cost' => 2.00],
    ], null);
    assertTrue($rAgain['ok'] === false, "receiving an already-received PO is rejected");
    echo "\n== TEST: batch top-up on same product + lot ==\n";

    // A second PO for p1 with the SAME lot number should top up the existing batch.
    $r3 = $svc->createPurchaseOrder($supId, [
        ['product_id' => $p1, 'quantity' => 5, 'unit_cost' => 2.00],
    ], '', null, null);
    assertTrue($r3['ok'] === true, "second PO created");
    $createdPoIds[] = $r3['po_id'];
    $d3 = (new PurchaseOrder())->findWithDetails($r3['po_id']);
    $p1Line2 = (int)$d3['items'][0]['id'];

    $rTop = $svc->receivePurchaseOrder($r3['po_id'], [
        ['item_id' => $p1Line2, 'qty' => 5, 'lot_number' => 'T-RECV-1', 'expiry_date' => $day('+60'), 'unit_cost' => 2.00],
    ], null);
    assertTrue($rTop['ok'] === true, "second receipt succeeds");
    $b1->execute([$p1, 'T-RECV-1']); $row1b = $b1->fetch();
    assertTrue($row1b && (int)$row1b['quantity'] === 15, "same lot topped up: batch qty 10 -> 15 (not a new batch)");

    echo "\n== TEST: cancel ==\n";

    $rCancel = $svc->createPurchaseOrder($supId, [
        ['product_id' => $p2, 'quantity' => 5, 'unit_cost' => 3.00],
    ], '', null, null);
    assertTrue($rCancel['ok'] === true, "PO to cancel created");
    $createdPoIds[] = $rCancel['po_id'];
    assertTrue((bool)(new PurchaseOrder())->cancel($rCancel['po_id']), "open PO can be cancelled");
    $cc = (new PurchaseOrder())->findWithDetails($rCancel['po_id']);
    assertTrue($cc['status'] === 'cancelled', "cancelled PO status is 'cancelled'");
    $ci = (int)$cc['items'][0]['id'];
    $rc = $svc->receivePurchaseOrder($rCancel['po_id'], [
        ['item_id' => $ci, 'qty' => 5, 'lot_number' => 'T-C', 'expiry_date' => $day('+60'), 'unit_cost' => 3.00],
    ], null);
    assertTrue($rc['ok'] === false, "receiving a cancelled PO is rejected");

    echo "\n" . ($GLOBALS['failures'] === 0 ? "ALL TESTS PASSED ({$GLOBALS['count']} checks).\n" : "{$GLOBALS['failures']} TEST(S) FAILED.\n");
    exit($GLOBALS['failures'] === 0 ? 0 : 1);

} finally {
    // --- Cleanup: remove TEST_ fixtures ---
    foreach ($createdBatchIds as $bid) {
        if ($bid) {
            $db->prepare("DELETE FROM inventory_movements WHERE batch_id = ?")->execute([$bid]);
            $db->prepare("DELETE FROM batches WHERE id = ?")->execute([$bid]);
        }
    }
    foreach ($createdPoIds as $pid) {
        if ($pid) {
            $db->prepare("DELETE FROM purchase_order_items WHERE purchase_order_id = ?")->execute([$pid]);
            $db->prepare("DELETE FROM purchase_orders WHERE id = ?")->execute([$pid]);
        }
    }
    foreach ($createdProductIds as $pid) {
        if ($pid) {
            $db->prepare("DELETE FROM inventory_movements WHERE product_id = ?")->execute([$pid]);
            $db->prepare("DELETE FROM batches WHERE product_id = ?")->execute([$pid]);
            $db->prepare("DELETE FROM purchase_order_items WHERE product_id = ?")->execute([$pid]);
            $db->prepare("DELETE FROM products WHERE id = ?")->execute([$pid]);
        }
    }
    foreach ($createdSupplierIds as $sid) {
        if ($sid) {
            $db->prepare("DELETE FROM purchase_orders WHERE supplier_id = ?")->execute([$sid]);
            $db->prepare("DELETE FROM suppliers WHERE id = ?")->execute([$sid]);
        }
    }
    echo "\n[cleanup] TEST_ fixtures removed.\n";
}

<?php

/**
 * Phase 5 automated tests — POS sales, FEFO deduction, void.
 * Run:  php tests/phase5_tests.php
 *
 * Approach mirrors tests/phase4_tests.php: uses the real aa_pharmacy DB with
 * clearly-marked TEST_ fixtures, then deletes those fixtures (in a finally
 * block) so re-runs stay clean. Exits non-zero on any failure.
 *
 * Business rules covered:
 *   1. Expired batches are never sold (FEFO selection excludes them).
 *   3. FEFO: the earliest valid expiry is drawn first.
 *   4. Every stock change creates an inventory_movements row.
 *   5. A completed sale and all inventory deductions happen in ONE transaction.
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
use App\Models\Sale;
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

// --- Test state / fixture tracking ---
$svc = new SaleService();
$createdProductIds = [];
$createdBatchIds = [];
$createdSaleIds = [];

try {
    echo "\n== TEST: FEFO selection + expired never sold (rules 1, 3) ==\n";

    // Product with three batches: one EXPIRED, one soon, one later.
    $pFefo = makeProduct('TEST_FEFO', 10.00);
    $createdProductIds[] = $pFefo;
    $bExp = makeBatch($pFefo, 'F-EXP', $day('-5'), 50, 1.00);   // expired
    $bSoon = makeBatch($pFefo, 'F-SOON', $day('+10'), 5, 2.00); // near
    $bLate = makeBatch($pFefo, 'F-LATE', $day('+100'), 20, 3.00);
    $createdBatchIds[] = $bExp;
    $createdBatchIds[] = $bSoon;
    $createdBatchIds[] = $bLate;

    // FEFO: selling 5 draws entirely from the soon batch (not the expired one).
    $r1 = $svc->createSale([['product_id' => $pFefo, 'qty' => 5]], 0, 'cash', 50, null, null);
    assertTrue($r1['ok'] === true, "sale of 5 units succeeds");
    $createdSaleIds[] = $r1['sale_id'];
    assertTrue(strpos($r1['sale_number'], 'S-') === 0, "sale number generated with S- prefix");

    $qSoon = $db->prepare("SELECT quantity FROM batches WHERE id = ?");
    $qSoon->execute([$bSoon]);
    assertTrue((int)$qSoon->fetchColumn() === 0, "FEFO: soon batch (exp +10) fully depleted first");
    $qLate = $db->prepare("SELECT quantity FROM batches WHERE id = ?");
    $qLate->execute([$bLate]);
    assertTrue((int)$qLate->fetchColumn() === 20, "later batch untouched (FEFO)");
    $qExp = $db->prepare("SELECT quantity FROM batches WHERE id = ?");
    $qExp->execute([$bExp]);
    assertTrue((int)$qExp->fetchColumn() === 50, "EXPIRED batch never drawn from");

    // The sale_item records which batch was picked (the FEFO first pick).
    $line = $db->prepare("SELECT batch_id, unit_cost FROM sale_items WHERE sale_id = ?");
    $line->execute([$r1['sale_id']]);
    $lineRow = $line->fetch();
    assertTrue((int)$lineRow['batch_id'] === $bSoon, "sale_item records the FEFO-picked batch_id");

    echo "\n== TEST: deduction totals + movements (rule 4) ==\n";
    $mvCountS = $db->prepare("SELECT COUNT(*) FROM inventory_movements WHERE reference = ?");
    $mvCountS->execute([$r1['sale_number']]);
    assertTrue((int)$mvCountS->fetchColumn() === 1, "one 'sale' movement logged for the sale");
    $mvSumStmt = $db->prepare("SELECT COALESCE(SUM(quantity_change), 0) FROM inventory_movements WHERE reference = ?");
    $mvSumStmt->execute([$r1['sale_number']]);
    assertTrue((int)$mvSumStmt->fetchColumn() === -5, "movement quantity_change is -5 (deduction)");

    echo "\n== TEST: sale pricing math ==\n";

    $pMath = makeProduct('TEST_MATH', 20.00);
    $createdProductIds[] = $pMath;
    $bMath = makeBatch($pMath, 'M-1', $day('+50'), 10, 8.00);
    $createdBatchIds[] = $bMath;

    // 3 units @ 20 = 60; discount 5 => total 55; cash 100 => change 45.
    $r2 = $svc->createSale([['product_id' => $pMath, 'qty' => 3]], 5.00, 'cash', 100, 'Walk-In Test', null);
    assertTrue($r2['ok'] === true, "cash sale with discount succeeds");
    $createdSaleIds[] = $r2['sale_id'];
    assertTrue((float)$r2['total'] === 55.00, "total = subtotal(60) - discount(5)");
    assertTrue((float)$r2['change'] === 45.00, "change = tendered(100) - total(55)");
    $save = (new Sale())->findWithItems($r2['sale_id']);
    assertTrue((float)$save['subtotal'] === 60.00, "subtotal stored = 60");
    assertTrue((float)$save['total_amount'] === 55.00, "total_amount stored = 55");
    assertTrue((float)$save['change_due'] === 45.00, "change_due stored = 45");
    assertTrue($save['customer_name'] === 'Walk-In Test', "optional customer name stored");

    // GCash: no tendered/change.
    $r3 = $svc->createSale([['product_id' => $pMath, 'qty' => 1]], 0, 'gcash', 0, null, null);
    assertTrue($r3['ok'] === true, "gcash sale succeeds");
    $createdSaleIds[] = $r3['sale_id'];
    assertTrue((float)$r3['total'] === 20.00, "gcash total = 20");
    assertTrue($r3['change'] === null, "non-cash has no change_due");
    $g3 = (new Sale())->find($r3['sale_id']);
    assertTrue($g3['amount_tendered'] === null, "non-cash stores no tendered amount");

    echo "\n== TEST: insufficient stock & one-transaction rollback (rule 5) ==\n";

    // Product with limited stock: selling more than available must fail wholly.
    $pShort = makeProduct('TEST_SHORT', 15.00);
    $createdProductIds[] = $pShort;
    $bN = makeBatch($pShort, 'S-1', $day('+40'), 4, 4.00);
    $createdBatchIds[] = $bN;

    // Two-line sale: product A ok, product B over stock => whole sale rejected.
    $pOther = makeProduct('TEST_OTHER', 25.00);
    $createdProductIds[] = $pOther;
    $bO = makeBatch($pOther, 'O-1', $day('+40'), 10, 10.00);
    $createdBatchIds[] = $bO;

    // Insufficient on a single product alone.
    $rShort = $svc->createSale([['product_id' => $pShort, 'qty' => 9]], 0, 'cash', 100, null, null);
    assertTrue($rShort['ok'] === false, "sale exceeding stock is rejected");

    // Multi-line where one product is short => NO partial deduction (rule 5).
    $rMulti = $svc->createSale([
        ['product_id' => $pOther, 'qty' => 9], // would succeed alone
        ['product_id' => $pShort, 'qty' => 9], // over stock => abort whole
    ], 0, 'cash', 100, null, null);
    assertTrue($rMulti['ok'] === false, "multi-line sale with any short line is rejected");

    // Verify nothing was deducted for the OK line (atomicity).
    $qO = $db->prepare("SELECT quantity FROM batches WHERE id = ?");
    $qO->execute([$bO]);
    assertTrue((int)$qO->fetchColumn() === 10, "no partial deduction: well-stocked line untouched on rejection");
    $qN = $db->prepare("SELECT quantity FROM batches WHERE id = ?");
    $qN->execute([$bN]);
    assertTrue((int)$qN->fetchColumn() === 4, "short line untouched on rejection");

    // No sales row and no movements were persisted for the rejected sale.
    $mvBad = $db->query("SELECT COUNT(*) FROM inventory_movements WHERE notes LIKE '%rejected%'")->fetchColumn();
    $salesRejected = $db->query("SELECT COUNT(*) FROM sales WHERE sale_number = 'S-PENDING'")->fetchColumn();
    assertTrue((int)$salesRejected === 0, "rejected sale leaves no sales row (rolled back)");

    echo "\n== TEST: void restores stock + logs movement, double-void rejected ==\n";

    $pVoid = makeProduct('TEST_VOID', 30.00);
    $createdProductIds[] = $pVoid;
    $bV1 = makeBatch($pVoid, 'V-1', $day('+60'), 10, 12.00);
    $createdBatchIds[] = $bV1;

    $rV = $svc->createSale([['product_id' => $pVoid, 'qty' => 4]], 0, 'cash', 200, null, null);
    assertTrue($rV['ok'] === true, "sale to void created");
    $createdSaleIds[] = $rV['sale_id'];

    $qV1 = $db->prepare("SELECT quantity FROM batches WHERE id = ?");
    $qV1->execute([$bV1]);
    assertTrue((int)$qV1->fetchColumn() === 6, "batch qty 10 -> 6 after sale of 4");

    $rv = $svc->voidSale($rV['sale_id'], null);
    assertTrue($rv['ok'] === true, "completed sale can be voided");

    $qV1b = $db->prepare("SELECT quantity FROM batches WHERE id = ?");
    $qV1b->execute([$bV1]);
    assertTrue((int)$qV1b->fetchColumn() === 10, "void restores batch qty 6 -> 10");

    $sV = (new Sale())->find($rV['sale_id']);
    assertTrue($sV['status'] === 'voided', "voided sale status = 'voided'");

    // Void movement logged with positive restore, referenced to the sale.
    $mvV = $db->prepare("SELECT COUNT(*), SUM(quantity_change) FROM inventory_movements WHERE movement_type = 'void' AND reference = ?");
    $mvV->execute([$rV['sale_number']]);
    assertTrue((int)$mvV->fetchColumn() === 1, "one 'void' movement logged for the sale");
    $mvVSum = $db->prepare("SELECT COALESCE(SUM(quantity_change), 0) FROM inventory_movements WHERE movement_type = 'void' AND reference = ?");
    $mvVSum->execute([$rV['sale_number']]);
    assertTrue((int)$mvVSum->fetchColumn() === 4, "void movement quantity_change is +4 (restore)");

    // Double-void is rejected and must not double-restore.
    $rv2 = $svc->voidSale($rV['sale_id'], null);
    assertTrue($rv2['ok'] === false, "voiding an already-voided sale is rejected");
    $qV1c = $db->prepare("SELECT quantity FROM batches WHERE id = ?");
    $qV1c->execute([$bV1]);
    assertTrue((int)$qV1c->fetchColumn() === 10, "no double-restore on rejected re-void");

    echo "\n== TEST: void of unknown / validation ==\n";
    $rUnknown = $svc->voidSale(99999999, null);
    assertTrue($rUnknown['ok'] === false, "voiding a non-existent sale is rejected");

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


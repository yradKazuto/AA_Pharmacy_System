<?php

namespace App\Services;

use App\Core\Database;
use App\Models\InventoryMovement;
use App\Models\PurchaseOrder;

/**
 * PurchaseService — purchasing / receiving business logic lives here
 * (not in controllers), matching StockService.
 *
 * Enforces the project's core pharmacy rules:
 *   6. Purchasing only RECOMMENDS reorders; it never auto-purchases.
 *   5. Receiving a purchase order and all its stock movements happen in
 *      ONE database transaction; roll back on any failure.
 *   4. Every stock change creates an inventory_movements row.
 *
 * EXPIRY BOUNDARY (same as StockService):
 *   Receiving an already-expired lot is always rejected.
 */
class PurchaseService
{
    private $po;
    private $movement;

    public function __construct()
    {
        $this->po = new PurchaseOrder();
        $this->movement = new InventoryMovement();
    }

    /**
     * Reorder recommendations — ADVICE ONLY (rule 6). Returns active products
     * whose current sellable stock is at or below the low-stock threshold.
     * The suggested quantity is a multiple of the threshold so the pharmacist
     * gets a concrete number, but nothing is ever ordered automatically.
     *
     * @return array each row: id, name, unit, unit_price, total_qty,
     *               suggested_qty
     */
    public function getReorderSuggestions()
    {
        $config = require __DIR__ . '/../../config/app.php';
        $threshold = (int)($config['low_stock_threshold'] ?? 10);

        $sql = "SELECT p.id, p.name, p.unit, p.unit_price,
                       COALESCE(SUM(CASE WHEN b.is_active = 1 AND b.expiry_date > CURDATE() THEN b.quantity ELSE 0 END), 0) AS total_qty
                FROM products p
                LEFT JOIN batches b ON b.product_id = p.id
                WHERE p.is_active = 1
                GROUP BY p.id
                HAVING total_qty < ?
                ORDER BY total_qty ASC, p.name ASC";
        $stmt = Database::getInstance()->getConnection()->prepare($sql);
        $stmt->execute([$threshold]);
        $rows = $stmt->fetchAll();

        // Suggest enough to restock above the threshold (rounded up to a unit of $threshold).
        foreach ($rows as &$row) {
            $shortage = $threshold - (int)$row['total_qty'];
            $row['suggested_qty'] = (int)ceil($shortage / $threshold) * $threshold;
        }

        return $rows;
    }

    /**
     * Create a purchase order with its line items in ONE transaction.
     *
     * @param int   $supplierId
     * @param array $items      list of ['product_id' => int, 'quantity' => int, 'unit_cost' => float]
     * @param string $expectedDate  Y-m-d (optional)
     * @param string|null $notes
     * @param int|null $userId      ordering user
     * @return array ['ok' => bool, 'message' => string, 'po_id' => ?int]
     */
    public function createPurchaseOrder($supplierId, array $items, $expectedDate = '', $notes = null, $userId = null)
    {
        $supplierId = (int)$supplierId;
        if ($supplierId <= 0) {
            return ['ok' => false, 'message' => 'A supplier is required.'];
        }

        // Validate we got at least one good line item.
        $cleanItems = [];
        foreach ($items as $item) {
            $productId = (int)($item['product_id'] ?? 0);
            $qty = (int)($item['quantity'] ?? 0);
            $cost = (float)($item['unit_cost'] ?? 0);
            if ($productId <= 0 || $qty <= 0) {
                continue; // skip empty/blank rows
            }
            if ($cost < 0) {
                return ['ok' => false, 'message' => 'Unit cost cannot be negative.'];
            }
            $cleanItems[] = ['product_id' => $productId, 'quantity' => $qty, 'unit_cost' => $cost];
        }

        if (empty($cleanItems)) {
            return ['ok' => false, 'message' => 'Order must contain at least one item with a quantity.'];
        }

        $expectedDate = trim((string)$expectedDate);
        if ($expectedDate !== '' && strtotime($expectedDate) === false) {
            return ['ok' => false, 'message' => 'Invalid expected delivery date.'];
        }
        if ($expectedDate === '') {
            $expectedDate = null;
        }

        $db = Database::getInstance()->getConnection();
        $supplierExists = $db->prepare("SELECT id FROM suppliers WHERE id = ? AND is_active = 1");
        $supplierExists->execute([$supplierId]);
        if (!$supplierExists->fetch()) {
            return ['ok' => false, 'message' => 'Supplier not found.'];
        }

        try {
            $db->beginTransaction();

            $insertPo = $db->prepare(
                "INSERT INTO purchase_orders (po_number, supplier_id, status, order_date, expected_date, notes, created_by)
                 VALUES (?, ?, 'ordered', CURDATE(), ?, ?, ?)"
            );
            $insertPo->execute(['PO-PENDING', $supplierId, $expectedDate, $notes, $userId]);
            $poId = (int)$db->lastInsertId();

            // Assign the human-readable PO number (unique because ids are unique).
            $poNumber = 'PO-' . date('Ymd') . '-' . str_pad($poId, 4, '0', STR_PAD_LEFT);
            $setNumber = $db->prepare("UPDATE purchase_orders SET po_number = ? WHERE id = ?");
            $setNumber->execute([$poNumber, $poId]);

            $insertItem = $db->prepare(
                "INSERT INTO purchase_order_items (purchase_order_id, product_id, quantity_ordered, unit_cost)
                 VALUES (?, ?, ?, ?)"
            );
            foreach ($cleanItems as $ci) {
                $insertItem->execute([$poId, $ci['product_id'], $ci['quantity'], $ci['unit_cost']]);
            }

            $db->commit();
            return ['ok' => true, 'message' => 'Purchase order created.', 'po_id' => $poId, 'po_number' => $poNumber];
        } catch (\Exception $e) {
            $db->rollBack();
            return ['ok' => false, 'message' => 'Failed to create purchase order: ' . $e->getMessage()];
        }
    }

    /**
     * Receive a whole purchase order in ONE transaction (rule 5).
     *
     * For each line item with a positive received quantity, a batch is created
     * (or topped up for the same product + lot) and an inventory_movements row
     * (type 'receive', reference = PO number) is written. The line's
     * quantity_received and the PO status are updated. On any failure the whole
     * receipt rolls back.
     *
     * Guards:
     *   - cannot receive a cancelled order,
     *   - received quantity cannot exceed the ordered quantity,
     *   - an already-expired lot is rejected.
     *
     * @param int   $poId
     * @param array $receivedItems list of each submitted line:
     *               ['item_id' => int, 'qty' => int, 'lot_number' => string, 'expiry_date' => string Y-m-d, 'unit_cost' => float]
     * @param int|null $userId
     * @return array ['ok' => bool, 'message' => string]
     */
    public function receivePurchaseOrder($poId, array $receivedItems, $userId = null)
    {
        $poId = (int)$poId;
        $db = Database::getInstance()->getConnection();

        try {
            $db->beginTransaction();

            // Load and lock the PO.
            $poStmt = $db->prepare("SELECT * FROM purchase_orders WHERE id = ? FOR UPDATE");
            $poStmt->execute([$poId]);
            $po = $poStmt->fetch();
            if (!$po) {
                $db->rollBack();
                return ['ok' => false, 'message' => 'Purchase order not found.'];
            }
            if ($po['status'] === 'cancelled') {
                $db->rollBack();
                return ['ok' => false, 'message' => 'Cannot receive a cancelled purchase order.'];
            }
            if ($po['status'] === 'received') {
                $db->rollBack();
                return ['ok' => false, 'message' => 'This purchase order is already fully received.'];
            }

            // Load the order's line items keyed by id.
            $itemsStmt = $db->prepare(
                "SELECT * FROM purchase_order_items WHERE purchase_order_id = ? FOR UPDATE"
            );
            $itemsStmt->execute([$poId]);
            $existing = [];
            foreach ($itemsStmt->fetchAll() as $row) {
                $existing[(int)$row['id']] = $row;
            }

            // --- Validate the whole receipt before mutating anything ---
            $prepared = [];
            foreach ($receivedItems as $ri) {
                $itemId = (int)($ri['item_id'] ?? 0);
                if (!isset($existing[$itemId])) {
                    $db->rollBack();
                    return ['ok' => false, 'message' => 'Invalid line item in receipt.'];
                }
                $line = $existing[$itemId];

                $qty = (int)($ri['qty'] ?? 0);
                $lot = trim((string)($ri['lot_number'] ?? ''));
                $expiry = trim((string)($ri['expiry_date'] ?? ''));
                $cost = (float)($ri['unit_cost'] ?? $line['unit_cost']);

                if ($qty < 0) {
                    $db->rollBack();
                    return ['ok' => false, 'message' => 'Received quantity cannot be negative.'];
                }
                if ($qty === 0) {
                    continue; // line unchanged (nothing to receive)
                }
                $remaining = (int)$line['quantity_ordered'] - (int)$line['quantity_received'];
                if ($qty > $remaining) {
                    $db->rollBack();
                    return ['ok' => false, 'message' => "Received quantity exceeds order for {$line['product_id']} (max {$remaining})."];
                }
                if ($lot === '') {
                    $db->rollBack();
                    return ['ok' => false, 'message' => 'Lot number is required for received stock.'];
                }
                if (strtotime($expiry) === false || $expiry === '') {
                    $db->rollBack();
                    return ['ok' => false, 'message' => 'Invalid expiry date for received stock.'];
                }
                // Guard: never receive an already-expired lot (rule 1).
                if ($expiry <= date('Y-m-d')) {
                    $db->rollBack();
                    return ['ok' => false, 'message' => "Cannot receive already-expired lot '{$lot}'."];
                }

                $prepared[] = [
                    'line'         => $line,
                    'qty'          => $qty,
                    'lot_number'   => $lot,
                    'expiry_date'  => $expiry,
                    'unit_cost'    => $cost,
                ];
            }

            if (empty($prepared)) {
                $db->rollBack();
                return ['ok' => false, 'message' => 'Enter a quantity to receive.'];
            }

            // --- Apply the receipt ---
            // Resolve the supplier name once so received batches carry it.
            $supplierStmt = $db->prepare(
                "SELECT s.name FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id WHERE po.id = ?"
            );
            $supplierStmt->execute([$poId]);
            $supplierName = $supplierStmt->fetchColumn() ?: null;

            $updateItem = $db->prepare(
                "UPDATE purchase_order_items SET quantity_received = quantity_received + ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
            );
            foreach ($prepared as $p) {
                $line = $p['line'];

                // Create or top up the batch for this product + lot.
                $batchId = $this->findOrCreateBatch($db, $line['product_id'], $p['lot_number'], $p['expiry_date'], $p['qty'], $p['unit_cost'], $supplierName);

                // Rule 4: log the movement, referenced to the PO number.
                $this->movement->record([
                    'product_id'      => $line['product_id'],
                    'batch_id'        => $batchId,
                    'movement_type'   => 'receive',
                    'quantity_change' => $p['qty'],
                    'unit_cost'       => $p['unit_cost'],
                    'reference'       => $po['po_number'],
                    'user_id'         => $userId,
                    'notes'           => 'Received PO ' . $po['po_number'] . ' lot ' . $p['lot_number'],
                ]);

                $updateItem->execute([$p['qty'], $line['id']]);
            }

            // Mark the PO received.
            $markReceived = $db->prepare(
                "UPDATE purchase_orders SET status = 'received', received_by = ?, received_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
            );
            $markReceived->execute([$userId, $poId]);

            $db->commit();
            return ['ok' => true, 'message' => 'Purchase order received and stock updated.'];
        } catch (\Exception $e) {
            $db->rollBack();
            return ['ok' => false, 'message' => 'Failed to receive purchase order: ' . $e->getMessage()];
        }
    }

    /**
     * Insert a new batch, or top up quantity if an active batch already exists
     * for the same product + lot number. Mirrors StockService::findOrCreateBatch
     * but does NOT manage its own transaction (caller owns it).
     *
     * @param \PDO   $db
     * @param int    $productId
     * @param string $lotNumber
     * @param string $expiryDate
     * @param int    $qty
     * @param float  $unitCost
     * @param string|null $supplierName
     * @return int The batch id used.
     */
    private function findOrCreateBatch($db, $productId, $lotNumber, $expiryDate, $qty, $unitCost, $supplierName = null)
    {
        $stmt = $db->prepare(
            "SELECT id FROM batches
             WHERE product_id = ? AND lot_number = ? AND is_active = 1
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$productId, $lotNumber]);
        $existing = $stmt->fetch();

        if ($existing) {
            $update = $db->prepare(
                "UPDATE batches SET quantity = quantity + ?, unit_cost = ?, received_date = CURDATE()
                 WHERE id = ?"
            );
            $update->execute([$qty, $unitCost, $existing['id']]);
            return (int)$existing['id'];
        }

        $insert = $db->prepare(
            "INSERT INTO batches (product_id, lot_number, expiry_date, quantity, unit_cost, supplier, received_date)
             VALUES (?, ?, ?, ?, ?, ?, CURDATE())"
        );
        $insert->execute([$productId, $lotNumber, $expiryDate, $qty, $unitCost, $supplierName]);
        return (int)$db->lastInsertId();
    }
}

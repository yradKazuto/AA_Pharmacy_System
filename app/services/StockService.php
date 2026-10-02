<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Batch;
use App\Models\InventoryMovement;

/**
 * StockService — all inventory business logic lives here (not in controllers).
 *
 * Enforces the project's core pharmacy rules:
 *   1. Expired batches are never sold or reserved.
 *   3. FEFO: always pick the earliest valid expiry first.
 *   4. Every stock change creates an inventory_movements row.
 *
 * EXPIRY BOUNDARY (used consistently across the app):
 *   - Sellable    = expiry_date > CURDATE()   (strictly in the future)
 *   - Expired     = expiry_date <= CURDATE()
 * A batch expiring tomorrow is sellable today but is flagged by getExpiring().
 */
class StockService
{
    private $batch;
    private $movement;

    public function __construct()
    {
        $this->batch = new Batch();
        $this->movement = new InventoryMovement();
    }

    /**
     * Get the total available (sellable) quantity for a product.
     *
     * @param int $productId
     * @return int
     */
    public function getAvailableQty($productId)
    {
        $rows = $this->batch->validForProduct($productId);
        $total = 0;
        foreach ($rows as $row) {
            $total += (int)$row['quantity'];
        }
        return $total;
    }

    /**
     * Whether a product is low stock (available qty <= configured threshold).
     *
     * @param int $productId
     * @return bool
     */
    public function isLowStock($productId)
    {
        $config = require __DIR__ . '/../../config/app.php';
        $threshold = (int)($config['low_stock_threshold'] ?? 10);
        return $this->getAvailableQty($productId) <= $threshold;
    }

    /**
     * FEFO batch selection: pick the requested quantity from the earliest
     * valid expiries first. Expired batches are always excluded.
     *
     * If total available stock is less than $qty, returns FALSE (no partial
     * pick — a sale must never be partly fulfilled).
     *
     * @param int $productId
     * @param int $qty
     * @return array|false List of ['batch_id','quantity','unit_cost'] picks
     */
    public function pickBatches($productId, $qty)
    {
        $qty = (int)$qty;
        if ($qty <= 0) {
            return false;
        }

        $batches = $this->batch->validForProduct($productId); // FEFO-ordered, non-expired only
        if (empty($batches)) {
            return false;
        }

        // Do a read-only calculation first to guarantee no partial pick.
        $totalAvailable = 0;
        foreach ($batches as $row) {
            $totalAvailable += (int)$row['quantity'];
        }
        if ($totalAvailable < $qty) {
            return false;
        }

        $picks = [];
        $remaining = $qty;
        foreach ($batches as $row) {
            if ($remaining <= 0) {
                break;
            }
            $take = min((int)$row['quantity'], $remaining);
            if ($take > 0) {
                $picks[] = [
                    'batch_id'  => (int)$row['id'],
                    'quantity'  => $take,
                    'unit_cost' => $row['unit_cost'],
                ];
                $remaining -= $take;
            }
        }

        return $picks;
    }

    /**
     * Receive stock: create a new batch (or top up an existing batch for the
     * same product + lot) and log a 'receive' movement. Runs in a transaction.
     *
     * Rejects receiving an already-expired batch.
     *
     * @param int    $productId
     * @param string $lotNumber
     * @param string $expiryDate  Y-m-d
     * @param int    $qty
     * @param float  $unitCost
     * @param string|null $supplier
     * @param int|null $userId
     * @return array ['ok' => bool, 'message' => string]
     */
    public function receiveStock($productId, $lotNumber, $expiryDate, $qty, $unitCost, $supplier = null, $userId = null)
    {
        $qty = (int)$qty;
        $unitCost = (float)$unitCost;

        if ($qty <= 0) {
            return ['ok' => false, 'message' => 'Quantity must be greater than zero.'];
        }
        if ($unitCost < 0) {
            return ['ok' => false, 'message' => 'Unit cost cannot be negative.'];
        }
        if (strtotime($expiryDate) === false) {
            return ['ok' => false, 'message' => 'Invalid expiry date.'];
        }
        // Rule 1 (guard): never receive an already-expired batch.
        if ($expiryDate <= date('Y-m-d')) {
            return ['ok' => false, 'message' => 'Cannot receive an already-expired batch.'];
        }

        $db = Database::getInstance()->getConnection();

        try {
            $db->beginTransaction();

            $batchId = $this->findOrCreateBatch($db, $productId, $lotNumber, $expiryDate, $qty, $unitCost, $supplier);

            // Rule 4: every stock change creates a movement row.
            $this->movement->record([
                'product_id'     => $productId,
                'batch_id'       => $batchId,
                'movement_type'  => 'receive',
                'quantity_change' => $qty,
                'unit_cost'      => $unitCost,
                'user_id'        => $userId,
                'notes'          => 'Received lot ' . $lotNumber . ($supplier ? ' from ' . $supplier : ''),
            ]);

            $db->commit();
            return ['ok' => true, 'message' => 'Stock received.', 'batch_id' => $batchId];
        } catch (\Exception $e) {
            $db->rollBack();
            return ['ok' => false, 'message' => 'Failed to receive stock: ' . $e->getMessage()];
        }
    }

    /**
     * Insert a new batch, or top up quantity if an active batch already exists
     * for the same product + lot number.
     *
     * @return int The batch id used.
     */
    private function findOrCreateBatch($db, $productId, $lotNumber, $expiryDate, $qty, $unitCost, $supplier)
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
        $insert->execute([$productId, $lotNumber, $expiryDate, $qty, $unitCost, $supplier]);
        return (int)$db->lastInsertId();
    }

    /**
     * Adjust a batch's quantity by a signed delta and log a movement.
     * Runs in a transaction.
     *
     * Guards:
     *   - Result quantity can never go negative.
     *   - An expired batch can only be written off (negative delta); it can
     *     never be increased back to sellable stock.
     *
     * @param int $batchId
     * @param int $delta  signed change
     * @param string $reason
     * @param int|null $userId
     * @return array ['ok' => bool, 'message' => string]
     */
    public function adjustStock($batchId, $delta, $reason, $userId = null)
    {
        $delta = (int)$delta;
        if ($delta === 0) {
            return ['ok' => false, 'message' => 'Adjustment delta cannot be zero.'];
        }

        $db = Database::getInstance()->getConnection();

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT * FROM batches WHERE id = ? FOR UPDATE");
            $stmt->execute([$batchId]);
            $batch = $stmt->fetch();

            if (!$batch) {
                $db->rollBack();
                return ['ok' => false, 'message' => 'Batch not found.'];
            }

            $current = (int)$batch['quantity'];
            $newQty = $current + $delta;

            // Never go negative.
            if ($newQty < 0) {
                $db->rollBack();
                return ['ok' => false, 'message' => "Adjustment would make quantity negative (current {$current}, delta {$delta})."];
            }

            // An expired batch can only be written down (record as waste), never increased.
            $isExpired = ($batch['expiry_date'] <= date('Y-m-d'));
            if ($isExpired && $delta > 0) {
                $db->rollBack();
                return ['ok' => false, 'message' => 'Cannot add stock to an expired batch; it may only be written off.'];
            }

            $movementType = ($newQty < $current) ? 'waste' : 'adjustment';

            $update = $db->prepare("UPDATE batches SET quantity = ? WHERE id = ?");
            $update->execute([$newQty, $batchId]);

            // Rule 4: log the movement.
            $this->movement->record([
                'product_id'      => $batch['product_id'],
                'batch_id'        => $batchId,
                'movement_type'   => $movementType,
                'quantity_change'  => $delta,
                'unit_cost'       => $batch['unit_cost'],
                'user_id'         => $userId,
                'notes'           => $reason,
            ]);

            $db->commit();
            return ['ok' => true, 'message' => 'Stock adjusted.'];
        } catch (\Exception $e) {
            $db->rollBack();
            return ['ok' => false, 'message' => 'Adjustment failed: ' . $e->getMessage()];
        }
    }

    /**
     * Products at or below the low-stock threshold.
     *
     * @return array
     */
    public function getLowStock()
    {
        $config = require __DIR__ . '/../../config/app.php';
        $threshold = (int)($config['low_stock_threshold'] ?? 10);

        $sql = "SELECT p.id, p.name, p.unit, p.unit_price,
                       COALESCE(SUM(CASE WHEN b.is_active = 1 AND b.expiry_date > CURDATE() THEN b.quantity ELSE 0 END), 0) AS total_qty
                FROM products p
                LEFT JOIN batches b ON b.product_id = p.id
                WHERE p.is_active = 1
                GROUP BY p.id
                HAVING total_qty <= ?
                ORDER BY total_qty ASC";
        $stmt = Database::getInstance()->getConnection()->prepare($sql);
        $stmt->execute([$threshold]);
        return $stmt->fetchAll();
    }

    /**
     * Expired batches (delegate to model).
     *
     * @return array
     */
    public function getExpired()
    {
        return $this->batch->getExpired();
    }

    /**
     * Batches expiring within $days (delegate to model).
     *
     * @param int $days
     * @return array
     */
    public function getExpiring($days = 30)
    {
        return $this->batch->getExpiring($days);
    }
}
<?php

namespace App\Services;

use App\Core\Database;
use App\Models\InventoryMovement;
use App\Models\Sale;
use App\Models\Product;

/**
 * SaleService — POS / sales business logic lives here (not in controllers),
 * matching StockService and PurchaseService.
 *
 * Enforces the project's core pharmacy rules:
 *   1. Expired batches are never sold (delegated to StockService::pickBatches).
 *   2. Stock is tracked by batch (each sale line records its batch_id).
 *   3. FEFO selection (delegated to StockService::pickBatches).
 *   4. Every stock change creates an inventory_movements row.
 *   5. A completed sale and all its inventory deductions happen in ONE
 *      database transaction; roll back on any failure.
 *
 * EXPIRY BOUNDARY:
 *   Sellable = expiry_date > CURDATE(). pickBatches only ever returns
 *   valid (non-expired) batches, so expired stock can never be sold.
 */
class SaleService
{
    private $stock;
    private $sale;
    private $movement;
    private $product;

    public function __construct()
    {
        $this->stock = new StockService();
        $this->sale = new Sale();
        $this->movement = new InventoryMovement();
        $this->product = new Product();
    }

    /**
     * Create a sale and deduct its stock in ONE transaction (rule 5).
     *
     * For each line item the service picks batches FEFO; every batch drawn is
     * decremented and logged as a 'sale' movement referenced to the sale
     * number; the sale + its items are inserted. Any failure rolls the whole
     * sale back (no partial deductions).
     *
     * Guards:
     *   - at least one line with a positive quantity,
     *   - product must exist and be active,
     *   - insufficient stock for any product => whole sale rejected,
     *   - discount/tendered cannot be negative.
     *
     * @param array $items    list of ['product_id' => int, 'qty' => int]
     * @param float $discount
     * @param string $paymentMethod  cash|gcash|card
     * @param float $tendered  only used for cash
     * @param string|null $customer  optional walk-in customer name
     * @param int|null $userId selling user
     * @return array ['ok' => bool, 'message' => string, 'sale_id' => ?int,
     *                'sale_number' => ?string, 'total' => ?float, 'change' => ?float]
     */
    public function createSale(array $items, $discount = 0.0, $paymentMethod = 'cash', $tendered = 0.0, $customer = null, $userId = null)
    {
        $discount = (float)$discount;
        if ($discount < 0) {
            return ['ok' => false, 'message' => 'Discount cannot be negative.'];
        }

        $paymentMethod = in_array($paymentMethod, Sale::paymentMethods()) ? $paymentMethod : 'cash';
        $tendered = (float)$tendered;
        if ($tendered < 0) {
            return ['ok' => false, 'message' => 'Amount tendered cannot be negative.'];
        }

        // Validate / normalize line items.
        $cleanItems = [];
        foreach ($items as $item) {
            $productId = (int)($item['product_id'] ?? 0);
            $qty = (int)($item['qty'] ?? 0);
            if ($productId <= 0 || $qty <= 0) {
                continue; // skip blank rows
            }
            $cleanItems[] = ['product_id' => $productId, 'qty' => $qty];
        }
        if (empty($cleanItems)) {
            return ['ok' => false, 'message' => 'A sale must contain at least one item with a quantity.'];
        }

        // Load product price for each line; reject unknown/inactive products.
        foreach ($cleanItems as &$line) {
            $product = $this->product->find($line['product_id']);
            if (!$product || (int)$product['is_active'] !== 1) {
                return ['ok' => false, 'message' => 'Product not found or inactive.'];
            }
            $line['unit_price'] = (float)$product['unit_price'];
        }
        unset($line);

        $db = Database::getInstance()->getConnection();

        try {
            $db->beginTransaction();

            // Insert the sale header first (child rows + movements need nothing from it yet).
            $insertSale = $db->prepare(
                "INSERT INTO sales (sale_number, customer_name, subtotal, discount, total_amount,
                                    payment_method, amount_tendered, change_due, sold_by, status, sale_date)
                 VALUES ('S-PENDING', ?, ?, ?, ?, ?, ?, ?, ?, 'completed', CURDATE())"
            );
            $customer = ($customer !== null) ? trim((string)$customer) : null;
            $insertSale->execute([
                $customer,
                0, 0, 0,
                $paymentMethod,
                $paymentMethod === 'cash' ? $tendered : null,
                null,
                $userId,
            ]);
            $saleId = (int)$db->lastInsertId();

            // Human-readable sale number (unique because sale ids are unique).
            $saleNumber = 'S-' . date('Ymd') . '-' . str_pad($saleId, 4, '0', STR_PAD_LEFT);
            $setNumber = $db->prepare("UPDATE sales SET sale_number = ? WHERE id = ?");
            $setNumber->execute([$saleNumber, $saleId]);

            // Reserve & deduct stock per line, inserting sale_items + movements.
            $subtotal = 0.0;
            $insertItem = $db->prepare(
                "INSERT INTO sale_items (sale_id, product_id, batch_id, quantity, unit_cost, unit_price, line_total)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $deductBatch = $db->prepare("UPDATE batches SET quantity = quantity - ? WHERE id = ?");

            foreach ($cleanItems as $line) {
                $picks = $this->stock->pickBatches($line['product_id'], $line['qty']);
                if ($picks === false) {
                    $db->rollBack();
                    return ['ok' => false, 'message' => 'Insufficient stock for one or more items.'];
                }

                $lineTotal = (float)$line['unit_price'] * $line['qty'];
                $subtotal += $lineTotal;

                foreach ($picks as $pick) {
                    // Deduct the batch (rule 4): log a negative 'sale' movement.
                    $deductBatch->execute([$pick['quantity'], $pick['batch_id']]);
                    $this->movement->record([
                        'product_id'      => $line['product_id'],
                        'batch_id'        => $pick['batch_id'],
                        'movement_type'   => 'sale',
                        'quantity_change' => -$pick['quantity'],
                        'unit_cost'       => $pick['unit_cost'],
                        'reference'       => $saleNumber,
                        'user_id'         => $userId,
                        'notes'           => 'Sale ' . $saleNumber . ' — batch deducted FEFO',
                    ]);
                }

                // Store the line; batch_id is the first (earliest-expiry) pick.
                $firstBatch = $picks[0]['batch_id'];
                $insertItem->execute([
                    $saleId,
                    $line['product_id'],
                    $firstBatch,
                    $line['qty'],
                    $picks[0]['unit_cost'],
                    $line['unit_price'],
                    $lineTotal,
                ]);
            }

            $total = max(0.0, round($subtotal - $discount, 2));
            $change = $paymentMethod === 'cash' ? max(0.0, round($tendered - $total, 2)) : null;
            $storedTendered = $paymentMethod === 'cash' ? $tendered : null;

            $finalize = $db->prepare(
                "UPDATE sales
                 SET customer_name = ?, subtotal = ?, discount = ?, total_amount = ?,
                     amount_tendered = ?, change_due = ?
                 WHERE id = ?"
            );
            $finalize->execute([$customer, round($subtotal, 2), $discount, $total, $storedTendered, $change, $saleId]);

            $db->commit();

            return [
                'ok' => true, 'message' => 'Sale completed.',
                'sale_id' => $saleId, 'sale_number' => $saleNumber,
                'total' => $total, 'change' => $change,
            ];
        } catch (\Exception $e) {
            $db->rollBack();
            return ['ok' => false, 'message' => 'Failed to complete sale: ' . $e->getMessage()];
        }
    }

    /**
     * Void a completed sale and restore its stock in ONE transaction.
     *
     * Reverses every deduction recorded against the sale: for each sale_item
     * the drawn batch (or the same-lot active batch) has its quantity restored
     * and a positive 'void' movement is logged. The sale is marked voided.
     * A sale can only be voided once.
     *
     * @param int $saleId
     * @param int|null $userId user performing the void (pharmacist/admin)
     * @return array ['ok' => bool, 'message' => string]
     */
    public function voidSale($saleId, $userId = null)
    {
        $saleId = (int)$saleId;
        $db = Database::getInstance()->getConnection();

        try {
            $db->beginTransaction();

            $saleStmt = $db->prepare("SELECT * FROM sales WHERE id = ? FOR UPDATE");
            $saleStmt->execute([$saleId]);
            $sale = $saleStmt->fetch();
            if (!$sale) {
                $db->rollBack();
                return ['ok' => false, 'message' => 'Sale not found.'];
            }
            if ($sale['status'] !== 'completed') {
                $db->rollBack();
                return ['ok' => false, 'message' => 'Only a completed sale can be voided.'];
            }

            $itemsStmt = $db->prepare("SELECT * FROM sale_items WHERE sale_id = ? FOR UPDATE");
            $itemsStmt->execute([$saleId]);
            $items = $itemsStmt->fetchAll();

            $updateItem = $db->prepare("UPDATE sale_items SET batch_id = ? WHERE id = ?");

            foreach ($items as $it) {
                // Restore to the drawn batch if still active; otherwise fall back
                // to the active batch for the same product (stock moved on since).
                $batchId = $this->resolveRestockBatch($db, $it);
                if (!$batchId) {
                    $db->rollBack();
                    return ['ok' => false, 'message' => 'Cannot void: no active batch to restore stock into for one item.'];
                }
                $restore = $db->prepare("UPDATE batches SET quantity = quantity + ? WHERE id = ?");
                $restore->execute([(int)$it['quantity'], $batchId]);

                // Rule 4: log a positive 'void' movement referenced to the sale.
                $this->movement->record([
                    'product_id'      => $it['product_id'],
                    'batch_id'        => $batchId,
                    'movement_type'   => 'void',
                    'quantity_change' => (int)$it['quantity'],
                    'unit_cost'       => $it['unit_cost'],
                    'reference'       => $sale['sale_number'],
                    'user_id'         => $userId,
                    'notes'           => 'Void sale ' . $sale['sale_number'],
                ]);

                // Point the line at the batch we actually restored into.
                $updateItem->execute([$batchId, $it['id']]);
            }

            $markVoid = $db->prepare(
                "UPDATE sales SET status = 'voided', voided_by = ?, voided_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
            );
            $markVoid->execute([$userId, $saleId]);

            $db->commit();
            return ['ok' => true, 'message' => 'Sale voided and stock restored.'];
        } catch (\Exception $e) {
            $db->rollBack();
            return ['ok' => false, 'message' => 'Failed to void sale: ' . $e->getMessage()];
        }
    }

    /**
     * Choose which batch a voided line's stock returns to: the original drawn
     * batch if it is still active, else the earliest-expiry active batch for
     * the same product. Caller owns the transaction.
     *
     * @param \PDO $db
     * @param array $item a sale_items row
     * @return int batch id
     */
    private function resolveRestockBatch($db, $item)
    {
        if (!empty($item['batch_id'])) {
            $stmt = $db->prepare("SELECT id FROM batches WHERE id = ? AND is_active = 1");
            $stmt->execute([$item['batch_id']]);
            if ($stmt->fetch()) {
                return (int)$item['batch_id'];
            }
        }
        // Fall back to FEFO-ordered active batch (prefer non-expired, then earliest expiry).
        $stmt = $db->prepare(
            "SELECT id FROM batches
             WHERE product_id = ? AND is_active = 1
             ORDER BY (expiry_date > CURDATE()) DESC, expiry_date ASC, id ASC
             LIMIT 1"
        );
        $stmt->execute([$item['product_id']]);
        $row = $stmt->fetch();
        return $row ? (int)$row['id'] : null;
    }
}

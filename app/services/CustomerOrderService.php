<?php

namespace App\Services;

use App\Core\Database;
use App\Models\CustomerOrder;
use App\Models\InventoryMovement;
use App\Models\Product;

/**
 * CustomerOrderService — online-ordering business logic, mirroring
 * SaleService / PurchaseService.
 *
 * Enforces business rules:
 *   5. Fulfilling an order and its inventory deduction happen in ONE
 *      database transaction; roll back on any failure.
 *   4. Every stock change creates an inventory_movements row.
 *   8. Orders containing prescription items must be APPROVED by the
 *      pharmacist before fulfillment; a flag is set at order time.
 *   9. Customers can only see/act on their own orders.
 *  10. Online revenue flows into the same sales/reports data.
 *
 * EXPIRY BOUNDARY: fulfillment uses StockService FEFO selection on valid
 * (non-expired) batches only, so expired stock is never shipped.
 */
class CustomerOrderService
{
    private $stock;
    private $order;
    private $movement;
    private $product;

    public function __construct()
    {
        $this->stock = new StockService();
        $this->order = new CustomerOrder();
        $this->movement = new InventoryMovement();
        $this->product = new Product();
    }

    /**
     * Place a new online order in ONE transaction.
     *
     * Validates each line (active product, available stock) and prices it from
     * the current unit_price (snapshot). Sets requires_review = 1 if any line
     * requires a prescription (rule 8). No stock is deducted here — stock is
     * deducted at fulfillment. Payment is COD only for v1.
     *
     * @param int $customerId
     * @param array $items   list of ['product_id' => int, 'qty' => int]
     * @param string $address
     * @param string $phone
     * @param string|null $notes
     * @return array ['ok' => bool, 'message' => string, 'order_id' => ?int, 'order_number' => ?string]
     */
    public function placeOrder($customerId, array $items, $address, $phone, $notes = null)
    {
        $customerId = (int)$customerId;
        if ($customerId <= 0) {
            return ['ok' => false, 'message' => 'Customer is required.'];
        }

        // Normalize lines and validate product existence/price.
        $clean = [];
        foreach ($items as $item) {
            $productId = (int)($item['product_id'] ?? 0);
            $qty = (int)($item['qty'] ?? 0);
            if ($productId <= 0 || $qty <= 0) {
                continue;
            }
            $product = $this->product->find($productId);
            if (!$product || (int)$product['is_active'] !== 1) {
                return ['ok' => false, 'message' => 'A product in your order is unavailable.'];
            }
            $clean[] = [
                'product_id' => $productId,
                'product_name' => $product['name'],
                'qty' => $qty,
                'unit_price' => (float)$product['unit_price'],
                'requires_prescription' => (int)$product['requires_prescription'] === 1,
            ];
        }
        if (empty($clean)) {
            return ['ok' => false, 'message' => 'Your order must contain at least one item with a quantity.'];
        }

        // Ensure enough available stock for every line (no reservation yet).
        foreach ($clean as $line) {
            if ($this->stock->getAvailableQty($line['product_id']) < $line['qty']) {
                return ['ok' => false, 'message' => "Not enough stock for {$line['product_name']}."];
            }
        }

        $address = trim((string)$address);
        $phone = trim((string)$phone);
        if ($address === '') {
            return ['ok' => false, 'message' => 'Delivery address is required.'];
        }

        $db = Database::getInstance()->getConnection();
        $requiresReview = 0;
        foreach ($clean as $line) {
            if ($line['requires_prescription']) {
                $requiresReview = 1;
                break;
            }
        }

        try {
            $db->beginTransaction();

            $customerStmt = $db->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
            $customerStmt->execute([$customerId]);
            $customer = $customerStmt->fetch();
            $customerName = $customer ? trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) : null;

            $itemsTotal = 0.0;
            foreach ($clean as $line) {
                $itemsTotal += $line['unit_price'] * $line['qty'];
            }
            $deliveryFee = 0.0;
            $total = round($itemsTotal + $deliveryFee, 2);

            $insert = $db->prepare(
                "INSERT INTO customer_orders
                    (order_number, customer_id, customer_name, delivery_address, phone,
                     status, items_total, delivery_fee, total_amount, payment_method,
                     notes, requires_review)
                 VALUES ('CO-PENDING', ?, ?, ?, ?, 'pending_review', ?, ?, ?, 'cod', ?, ?)"
            );
            $insert->execute([
                $customerId, $customerName, $address, $phone,
                round($itemsTotal, 2), $deliveryFee, $total,
                $notes !== null ? trim((string)$notes) : null,
                $requiresReview,
            ]);
            $orderId = (int)$db->lastInsertId();

            $orderNumber = 'CO-' . date('Ymd') . '-' . str_pad($orderId, 4, '0', STR_PAD_LEFT);
            $db->prepare("UPDATE customer_orders SET order_number = ? WHERE id = ?")
                ->execute([$orderNumber, $orderId]);

            $insertItem = $db->prepare(
                "INSERT INTO customer_order_items
                    (customer_order_id, product_id, product_name, quantity, unit_price, line_total)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            foreach ($clean as $line) {
                $insertItem->execute([
                    $orderId, $line['product_id'], $line['product_name'],
                    $line['qty'], $line['unit_price'],
                    round($line['unit_price'] * $line['qty'], 2),
                ]);
            }

            $db->commit();
            return ['ok' => true, 'message' => 'Order placed.', 'order_id' => $orderId, 'order_number' => $orderNumber];
        } catch (\Exception $e) {
            $db->rollBack();
            return ['ok' => false, 'message' => 'Failed to place order: ' . $e->getMessage()];
        }
    }

    /**
     * Approve a pending order (pharmacist review — rule 8). No stock changes.
     *
     * @param int $orderId
     * @param int|null $userId
     * @return array ['ok' => bool, 'message' => string]
     */
    public function approve($orderId, $userId = null)
    {
        $db = Database::getInstance()->getConnection();
        try {
            $db->beginTransaction();
            $result = $this->transition($db, $orderId, 'approved');
            if (!$result['ok']) {
                $db->rollBack();
                return $result;
            }
            $db->prepare("UPDATE customer_orders SET approved_by = ?, approved_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$userId, $orderId]);
            $db->commit();
            return ['ok' => true, 'message' => 'Order approved.'];
        } catch (\Exception $e) {
            $db->rollBack();
            return ['ok' => false, 'message' => 'Failed to approve order: ' . $e->getMessage()];
        }
    }

    /**
     * Reject a pending order (pharmacist review — rule 8).
     *
     * @param int $orderId
     * @param string|null $note
     * @param int|null $userId
     * @return array ['ok' => bool, 'message' => string]
     */
    public function reject($orderId, $note = null, $userId = null)
    {
        $db = Database::getInstance()->getConnection();
        try {
            $db->beginTransaction();
            $result = $this->transition($db, $orderId, 'rejected');
            if (!$result['ok']) {
                $db->rollBack();
                return $result;
            }
            $db->prepare("UPDATE customer_orders SET rejected_note = ?, approved_by = ?, approved_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$note !== null ? trim((string)$note) : null, $userId, $orderId]);
            $db->commit();
            return ['ok' => true, 'message' => 'Order rejected.'];
        } catch (\Exception $e) {
            $db->rollBack();
            return ['ok' => false, 'message' => 'Failed to reject order: ' . $e->getMessage()];
        }
    }

    /**
     * Fulfill an approved order: deduct stock FEFO, log movements, insert a
     * linked sale (so online revenue flows into reports), all in ONE
     * transaction (rule 5). Roll back on any short stock (no partial).
     *
     * @param int $orderId
     * @param int|null $userId
     * @return array ['ok' => bool, 'message' => string]
     */
    public function fulfill($orderId, $userId = null)
    {
        $orderId = (int)$orderId;
        $db = Database::getInstance()->getConnection();

        try {
            $db->beginTransaction();

            $orderStmt = $db->prepare("SELECT * FROM customer_orders WHERE id = ? FOR UPDATE");
            $orderStmt->execute([$orderId]);
            $order = $orderStmt->fetch();
            if (!$order) {
                $db->rollBack();
                return ['ok' => false, 'message' => 'Order not found.'];
            }
            if ($order['status'] !== 'approved') {
                $db->rollBack();
                return ['ok' => false, 'message' => 'Only an approved order can be fulfilled.'];
            }

            $itemsStmt = $db->prepare("SELECT * FROM customer_order_items WHERE customer_order_id = ? FOR UPDATE");
            $itemsStmt->execute([$orderId]);
            $items = $itemsStmt->fetchAll();

            // Pre-compute FEFO picks so we can reject before mutating anything.
            $prepared = [];
            foreach ($items as $it) {
                $picks = $this->stock->pickBatches($it['product_id'], (int)$it['quantity']);
                if ($picks === false) {
                    $db->rollBack();
                    return ['ok' => false, 'message' => "Insufficient stock to fulfill {$it['product_name']}."];
                }
                $prepared[] = ['item' => $it, 'picks' => $picks];
            }

            // Deduct batch quantities, write movements, and record sale lines.
            $deductBatch = $db->prepare("UPDATE batches SET quantity = quantity - ? WHERE id = ?");
            $updateItem = $db->prepare("UPDATE customer_order_items SET batch_id = ? WHERE id = ?");

            $insertSale = $db->prepare(
                "INSERT INTO sales (sale_number, customer_name, subtotal, discount, total_amount,
                                    payment_method, amount_tendered, change_due, sold_by, status, sale_date)
                 VALUES ('S-PENDING', ?, ?, 0, ?, 'cod', NULL, NULL, ?, 'completed', CURDATE())"
            );
            $insertSale->execute([
                $order['customer_name'], $order['items_total'], $order['total_amount'], $userId,
            ]);
            $saleId = (int)$db->lastInsertId();
            $saleNumber = 'S-' . date('Ymd') . '-' . str_pad($saleId, 4, '0', STR_PAD_LEFT);
            $db->prepare("UPDATE sales SET sale_number = ? WHERE id = ?")->execute([$saleNumber, $saleId]);

            $insertSaleItem = $db->prepare(
                "INSERT INTO sale_items (sale_id, product_id, batch_id, quantity, unit_cost, unit_price, line_total)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            foreach ($prepared as $p) {
                $it = $p['item'];
                $firstBatch = $p['picks'][0]['batch_id'];

                foreach ($p['picks'] as $pick) {
                    $deductBatch->execute([$pick['quantity'], $pick['batch_id']]);
                    $this->movement->record([
                        'product_id'      => $it['product_id'],
                        'batch_id'        => $pick['batch_id'],
                        'movement_type'   => 'sale',
                        'quantity_change' => -$pick['quantity'],
                        'unit_cost'       => $pick['unit_cost'],
                        'reference'       => $order['order_number'],
                        'user_id'         => $userId,
                        'notes'           => 'Fulfilled order ' . $order['order_number'],
                    ]);
                }

                $updateItem->execute([$firstBatch, $it['id']]);
                $insertSaleItem->execute([
                    $saleId, $it['product_id'], $firstBatch, (int)$it['quantity'],
                    $p['picks'][0]['unit_cost'], $it['unit_price'], $it['line_total'],
                ]);
            }

            $db->prepare(
                "UPDATE customer_orders SET status = 'fulfilled', fulfilled_by = ?, fulfilled_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
            )->execute([$userId, $orderId]);

            $db->commit();
            return ['ok' => true, 'message' => 'Order fulfilled and stock deducted.'];
        } catch (\Exception $e) {
            $db->rollBack();
            return ['ok' => false, 'message' => 'Failed to fulfill order: ' . $e->getMessage()];
        }
    }

    /**
     * Mark a fulfilled order as delivered.
     *
     * @param int $orderId
     * @return array ['ok' => bool, 'message' => string]
     */
    public function markDelivered($orderId)
    {
        $db = Database::getInstance()->getConnection();
        try {
            $db->beginTransaction();
            $result = $this->transition($db, $orderId, 'delivered');
            if (!$result['ok']) {
                $db->rollBack();
                return $result;
            }
            $db->commit();
            return ['ok' => true, 'message' => 'Order marked as delivered.'];
        } catch (\Exception $e) {
            $db->rollBack();
            return ['ok' => false, 'message' => 'Failed to mark order delivered: ' . $e->getMessage()];
        }
    }

    /**
     * Cancel an order that has not been fulfilled (customer or staff).
     *
     * @param int $orderId
     * @return array ['ok' => bool, 'message' => string]
     */
    public function cancel($orderId)
    {
        $db = Database::getInstance()->getConnection();
        try {
            $db->beginTransaction();
            $result = $this->transition($db, $orderId, 'cancelled');
            if (!$result['ok']) {
                $db->rollBack();
                return $result;
            }
            $db->commit();
            return ['ok' => true, 'message' => 'Order cancelled.'];
        } catch (\Exception $e) {
            $db->rollBack();
            return ['ok' => false, 'message' => 'Failed to cancel order: ' . $e->getMessage()];
        }
    }

    /**
     * Apply a status transition if it is allowed. Caller owns the transaction.
     *
     * @param \PDO $db
     * @param int $orderId
     * @param string $target
     * @return array ['ok' => bool, 'message' => string]
     */
    private function transition($db, $orderId, $target)
    {
        $stmt = $db->prepare("SELECT status FROM customer_orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            return ['ok' => false, 'message' => 'Order not found.'];
        }
        $transitions = CustomerOrder::transitions();
        $allowed = $transitions[$order['status']] ?? [];
        if (!in_array($target, $allowed, true)) {
            return ['ok' => false, 'message' => "Cannot move an order from '{$order['status']}' to '{$target}'."];
        }
        $db->prepare("UPDATE customer_orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$target, $orderId]);
        return ['ok' => true, 'message' => 'Status updated.'];
    }
}

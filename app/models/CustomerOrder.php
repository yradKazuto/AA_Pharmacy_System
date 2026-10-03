<?php

namespace App\Models;

use App\Core\Model;

class CustomerOrder extends Model
{
    protected $table = 'customer_orders';

    /**
     * Valid statuses and the transitions allowed between them.
     *
     * @return array ['STATUS' => ['allowed_next', ...]]
     */
    public static function transitions()
    {
        return [
            'pending_review' => ['approved', 'cancelled', 'rejected'],
            'approved'       => ['fulfilled', 'cancelled'],
            'fulfilled'      => ['delivered'],
            'delivered'      => [],
            'cancelled'      => [],
            'rejected'       => [],
        ];
    }

    /**
     * Get a single order with its line items and snapshot customer info,
     * plus the staff members who acted on it.
     *
     * @param int $id
     * @return array|null
     */
    public function findWithItems($id)
    {
        $stmt = $this->db->prepare(
            "SELECT o.*, c.username AS customer_username,
                    au.username AS approved_by_name,
                    fu.username AS fulfilled_by_name
             FROM {$this->table} o
             LEFT JOIN users c ON c.id = o.customer_id
             LEFT JOIN users au ON au.id = o.approved_by
             LEFT JOIN users fu ON fu.id = o.fulfilled_by
             WHERE o.id = ?"
        );
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        if (!$order) {
            return null;
        }

        $items = $this->db->prepare(
            "SELECT i.*, b.lot_number AS batch_lot, b.expiry_date AS batch_expiry
             FROM customer_order_items i
             LEFT JOIN batches b ON b.id = i.batch_id
             WHERE i.customer_order_id = ?
             ORDER BY i.id ASC"
        );
        $items->execute([$id]);
        $order['items'] = $items->fetchAll();

        return $order;
    }

    /**
     * Orders visible to the review workflow (all non-terminal, newest first).
     *
     * @return array
     */
    public function allForReview()
    {
        $orders = $this->db->query(
            "SELECT o.*, c.username AS customer_username
             FROM customer_orders o
             LEFT JOIN users c ON c.id = o.customer_id
             WHERE o.status IN ('pending_review','approved','fulfilled')
             ORDER BY o.created_at DESC"
        )->fetchAll();

        // Attach line items to each order for the review queue display.
        foreach ($orders as &$order) {
            $items = $this->db->prepare(
                "SELECT i.product_name, i.quantity, i.unit_price
                 FROM customer_order_items i
                 WHERE i.customer_order_id = ?
                 ORDER BY i.id ASC"
            );
            $items->execute([$order['id']]);
            $order['items'] = $items->fetchAll();
        }
        unset($order);

        return $orders;
    }

    /**
     * All orders belonging to one customer (authorization — rule 9).
     *
     * @param int $customerId
     * @return array
     */
    public function forCustomer($customerId)
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE customer_id = ?
             ORDER BY created_at DESC"
        );
        $stmt->execute([$customerId]);
        return $stmt->fetchAll();
    }
}

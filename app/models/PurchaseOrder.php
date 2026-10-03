<?php

namespace App\Models;

use App\Core\Model;

class PurchaseOrder extends Model
{
    protected $table = 'purchase_orders';

    /**
     * The statuses a purchase order can be in.
     *
     * @return array
     */
    public static function statuses()
    {
        return [
            'draft'     => 'Draft',
            'ordered'   => 'Ordered',
            'received'  => 'Received',
            'cancelled' => 'Cancelled',
        ];
    }

    /**
     * Get all purchase orders with supplier name and item summary.
     *
     * @return array
     */
    public function allWithDetails()
    {
        $sql = "SELECT po.*, s.name AS supplier_name,
                       COUNT(poi.id) AS item_count,
                       COALESCE(SUM(poi.quantity_ordered * poi.unit_cost), 0) AS total_amount
                FROM {$this->table} po
                JOIN suppliers s ON s.id = po.supplier_id
                LEFT JOIN purchase_order_items poi ON poi.purchase_order_id = po.id
                GROUP BY po.id
                ORDER BY po.created_at DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get one purchase order with supplier name and the full line-item list
     * (each with product name/unit, ordered/received qty, and unit cost).
     *
     * @param int $id
     * @return array|null keys: po fields, supplier_name, items, item_count, received_qty, total_amount
     */
    public function findWithDetails($id)
    {
        $stmt = $this->db->prepare(
            "SELECT po.*, s.name AS supplier_name,
                    cb.username AS created_by_name,
                    rb.username AS received_by_name
             FROM {$this->table} po
             JOIN suppliers s ON s.id = po.supplier_id
             LEFT JOIN users cb ON cb.id = po.created_by
             LEFT JOIN users rb ON rb.id = po.received_by
             WHERE po.id = ?"
        );
        $stmt->execute([$id]);
        $po = $stmt->fetch();

        if (!$po) {
            return null;
        }

        $itemStmt = $this->db->prepare(
            "SELECT poi.*, p.name AS product_name, p.unit
             FROM purchase_order_items poi
             JOIN products p ON p.id = poi.product_id
             WHERE poi.purchase_order_id = ?
             ORDER BY p.name ASC"
        );
        $itemStmt->execute([$id]);
        $items = $itemStmt->fetchAll();

        $totalAmount = 0;
        foreach ($items as $item) {
            $totalAmount += (float)$item['quantity_ordered'] * (float)$item['unit_cost'];
        }

        $po['items']         = $items;
        $po['item_count']    = count($items);
        $po['total_amount']  = $totalAmount;

        return $po;
    }

    /**
     * Mark the order as cancelled (only while not yet received).
     *
     * @param int $id
     * @return bool
     */
    public function cancel($id)
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }
}

<?php

namespace App\Models;

use App\Core\Model;

class Sale extends Model
{
    protected $table = 'sales';

    /**
     * Get a single sale with its line items (receipt data).
     * Each line carries the batch/lot it was drawn from for the audit trail.
     *
     * @param int $id
     * @return array|null
     */
    public function findWithItems($id)
    {
        $sale = $this->find($id);
        if (!$sale) {
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT si.*, p.name AS product_name, p.unit, p.requires_prescription,
                    b.lot_number AS batch_lot, b.expiry_date AS batch_expiry
             FROM sale_items si
             JOIN products p ON p.id = si.product_id
             LEFT JOIN batches b ON b.id = si.batch_id
             WHERE si.sale_id = ?
             ORDER BY si.id ASC"
        );
        $stmt->execute([$id]);
        $sale['items'] = $stmt->fetchAll();

        return $sale;
    }

    /**
     * Get recent sales with the selling user's name.
     *
     * @param int $limit
     * @return array
     */
    public function recent($limit = 50)
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, u.username AS sold_by_name, u.first_name, u.last_name
             FROM sales s
             LEFT JOIN users u ON u.id = s.sold_by
             ORDER BY s.created_at DESC
             LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    /**
     * Payment method options for the POS terminal.
     *
     * @return array
     */
    public static function paymentMethods()
    {
        return ['cash', 'gcash', 'card'];
    }

    /**
     * Sale statuses.
     *
     * @return array
     */
    public static function statuses()
    {
        return ['completed', 'voided'];
    }
}

<?php

namespace App\Models;

use App\Core\Model;

class Batch extends Model
{
    protected $table = 'batches';

    /**
     * Get all batches for a product, with an expired-status flag.
     * Includes active and inactive batches.
     *
     * @param int $productId
     * @return array
     */
    public function allForProduct($productId)
    {
        $stmt = $this->db->prepare(
            "SELECT b.*,
                    CASE WHEN b.expiry_date <= CURDATE() THEN 1 ELSE 0 END AS is_expired
             FROM {$this->table} b
             WHERE b.product_id = ?
             ORDER BY b.expiry_date ASC"
        );
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }

    /**
     * Get valid (sellable) batches for a product — FEFO source.
     * Filters: active, quantity > 0, expiry strictly in the future.
     * Ordered by earliest expiry first (FEFO).
     *
     * @param int $productId
     * @return array
     */
    public function validForProduct($productId)
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE product_id = ?
               AND is_active = 1
               AND quantity > 0
               AND expiry_date > CURDATE()
             ORDER BY expiry_date ASC"
        );
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }

    /**
     * Get all expired batches (expiry <= today) with product info.
     *
     * @return array
     */
    public function getExpired()
    {
        $sql = "SELECT b.*, p.name AS product_name, p.unit
                FROM {$this->table} b
                JOIN products p ON p.id = b.product_id
                WHERE b.expiry_date <= CURDATE()
                  AND b.is_active = 1
                  AND b.quantity > 0
                ORDER BY b.expiry_date ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get batches expiring within $days from today.
     *
     * @param int $days
     * @return array
     */
    public function getExpiring($days = 30)
    {
        $stmt = $this->db->prepare(
            "SELECT b.*, p.name AS product_name, p.unit
             FROM {$this->table} b
             JOIN products p ON p.id = b.product_id
             WHERE b.expiry_date > CURDATE()
               AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
               AND b.is_active = 1
               AND b.quantity > 0
             ORDER BY b.expiry_date ASC"
        );
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    }

    /**
     * Soft-retire a batch (set is_active = 0).
     *
     * @param int $id
     * @return bool
     */
    public function retire($id)
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }
}
<?php

namespace App\Models;

use App\Core\Model;

class Product extends Model
{
    protected $table = 'products';

    /**
     * Get all active products with their current available stock and low-stock flag.
     * Joins batches to compute total quantity from non-expired, active batches only.
     *
     * @return array
     */
    public function getAllWithStock()
    {
        // SUBQUERY: total available (non-expired, active batches with qty > 0)
        $sql = "SELECT p.id, p.name, p.generic_name, p.category, p.unit,
                       p.unit_price, p.requires_prescription, p.is_active,
                       p.created_at, p.updated_at,
                       COALESCE(
                           (SELECT SUM(b.quantity)
                            FROM batches b
                            WHERE b.product_id = p.id
                              AND b.is_active = 1
                              AND b.expiry_date > CURDATE()
                              AND b.quantity > 0), 0
                       ) AS total_qty,
                       COALESCE(
                           (SELECT SUM(b.quantity * b.unit_cost)
                            FROM batches b
                            WHERE b.product_id = p.id
                              AND b.is_active = 1
                              AND b.expiry_date > CURDATE()), 0
                       ) AS total_cost_value
                FROM products p
                WHERE p.is_active = 1
                ORDER BY p.name ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Find a product by its name.
     *
     * @param string $name
     * @return array|null
     */
    public function findByName($name)
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE name = ? AND is_active = 1"
        );
        $stmt->execute([$name]);
        return $stmt->fetch();
    }

    /**
     * Soft-deactivate a product (set is_active = 0).
     *
     * @param int $id
     * @return bool
     */
    public function deactivate($id)
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }
}
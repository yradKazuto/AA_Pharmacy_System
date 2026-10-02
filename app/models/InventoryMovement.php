<?php

namespace App\Models;

use App\Core\Model;

class InventoryMovement extends Model
{
    protected $table = 'inventory_movements';

    /**
     * Record an inventory movement. Mirrors AuditLog::log() pattern.
     * Keys expected: product_id, batch_id (nullable), movement_type,
     *               quantity_change, unit_cost (nullable), reference (nullable),
     *               user_id (nullable), notes (nullable).
     *
     * @param array $data
     * @return bool
     */
    public function record(array $data)
    {
        $columns = [];
        $placeholders = [];
        $values = [];

        foreach ($data as $key => $value) {
            $columns[] = $key;
            $placeholders[] = '?';
            $values[] = $value;
        }

        $columns[] = 'created_at';
        $placeholders[] = '?';
        $values[] = date('Y-m-d H:i:s');

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ")
                 VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Get recent inventory movements, optionally filtered by batch.
     *
     * @param int|null $batchId
     * @param int $limit
     * @return array
     */
    public function getByBatch($batchId, $limit = 50)
    {
        $stmt = $this->db->prepare(
            "SELECT m.*, p.name AS product_name, u.username
             FROM {$this->table} m
             LEFT JOIN products p ON p.id = m.product_id
             LEFT JOIN users u ON u.id = m.user_id
             WHERE m.batch_id = ?
             ORDER BY m.created_at DESC
             LIMIT ?"
        );
        $stmt->execute([$batchId, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Get the most recent movements across all products.
     *
     * @param int $limit
     * @return array
     */
    public function getRecent($limit = 50)
    {
        $stmt = $this->db->prepare(
            "SELECT m.*, p.name AS product_name, u.username
             FROM {$this->table} m
             LEFT JOIN products p ON p.id = m.product_id
             LEFT JOIN users u ON u.id = m.user_id
             ORDER BY m.created_at DESC
             LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
}
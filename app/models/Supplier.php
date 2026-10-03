<?php

namespace App\Models;

use App\Core\Model;

class Supplier extends Model
{
    protected $table = 'suppliers';

    /**
     * Get all active suppliers, ordered by name.
     *
     * @return array
     */
    public function allActive()
    {
        $stmt = $this->db->query(
            "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY name ASC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Find a supplier by its name (duplicate check / lookup).
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
     * Soft-retire a supplier (set is_active = 0).
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

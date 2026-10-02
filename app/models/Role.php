<?php

namespace App\Models;

use App\Core\Model;

class Role extends Model
{
    protected $table = 'roles';

    /**
     * Find a role by name.
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
     * Get all active roles.
     *
     * @return array
     */
    public function getActiveRoles()
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY name ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
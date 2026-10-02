<?php

namespace App\Models;

use App\Core\Model;

class User extends Model
{
    protected $table = 'users';

    /**
     * Find a user by username or email.
     *
     * @param string $identifier Username or email
     * @return array|null
     */
    public function findByUsernameOrEmail($identifier)
    {
        $stmt = $this->db->prepare(
            "SELECT u.*, r.name as role_name FROM {$this->table} u
            JOIN roles r ON u.role_id = r.id
            WHERE (u.username = ? OR u.email = ? OR u.company_id_number = ?) AND u.is_active = 1"
        );
        $stmt->execute([$identifier, $identifier, $identifier]);
        return $stmt->fetch();
    }

    /**
     * Find a user by company ID number.
     *
     * @param string $companyIdNumber
     * @return array|null
     */
    public function findByCompanyIdNumber($companyIdNumber)
    {
        $stmt = $this->db->prepare(
            "SELECT u.*, r.name as role_name FROM {$this->table} u
            JOIN roles r ON u.role_id = r.id
            WHERE u.company_id_number = ? AND u.is_active = 1"
        );
        $stmt->execute([$companyIdNumber]);
        return $stmt->fetch();
    }

    /**
     * Get all users with their roles.
     *
     * @return array
     */
    public function getAllWithRoles()
    {
        $stmt = $this->db->prepare(
            "SELECT u.*, r.name as role_name FROM {$this->table} u
            JOIN roles r ON u.role_id = r.id
            WHERE u.is_active = 1
            ORDER BY u.last_name ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Update last login timestamp.
     *
     * @param int $userId
     * @return bool
     */
    public function updateLastLogin($userId)
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?"
        );
        return $stmt->execute([$userId]);
    }
}
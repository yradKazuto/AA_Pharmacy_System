<?php

namespace App\Models;

use App\Core\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    /**
     * Log an action to the audit log.
     *
     * @param array $data
     * @return bool
     */
    public function log(array $data)
    {
        $columns = [];
        $values = [];
        $placeholders = [];

        foreach ($data as $key => $value) {
            $columns[] = $key;
            $values[] = $value;
            $placeholders[] = '?';
        }

        $columns[] = 'created_at';
        $values[] = date('Y-m-d H:i:s');
        $placeholders[] = '?';

        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $placeholders) . ")"
        );

        return $stmt->execute($values);
    }

    /**
     * Get audit logs with optional filters.
     *
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getLogs($filters = [], $limit = 50, $offset = 0)
    {
        $where = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = ?';
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['action'])) {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
        }

        if (!empty($filters['table_affected'])) {
            $where[] = 'table_affected = ?';
            $params[] = $filters['table_affected'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(created_at) >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(created_at) <= ?';
            $params[] = $filters['date_to'];
        }

        $sql = "SELECT a.*, u.username, u.company_id_number
                FROM {$this->table} a
                LEFT JOIN users u ON a.user_id = u.id";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY a.created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
<?php

namespace App\Models;

use App\Core\Model;

/**
 * Report — read-only queries backing the Reports section.
 * All figures are computed from ACTUAL transaction/inventory rows (rule 10):
 * sales, sale_items, batches, inventory_movements, purchase_orders.
 */
class Report extends Model
{
    protected $table = 'sales';

    /**
     * Sales summary over a date range (inclusive, on sale_date).
     * Only completed sales are counted (voided sales are excluded).
     *
     * @param string $from Y-m-d (optional)
     * @param string $to   Y-m-d (optional)
     * @return array ['count' => int, 'gross' => float, 'discounts' => float, 'net' => float, 'items' => int]
     */
    public function salesSummary($from = null, $to = null)
    {
        $sql = "SELECT COUNT(*) AS cnt,
                       COALESCE(SUM(subtotal), 0) AS gross,
                       COALESCE(SUM(discount), 0) AS discounts,
                       COALESCE(SUM(total_amount), 0) AS net,
                       COALESCE((SELECT SUM(quantity) FROM sale_items si JOIN sales s ON s.id = si.sale_id
                                 WHERE s.status = 'completed' AND s.sale_date BETWEEN ? AND ?), 0) AS items
                FROM sales
                WHERE status = 'completed' AND sale_date BETWEEN ? AND ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$from, $to, $from, $to]);
        $row = $stmt->fetch();
        return [
            'count' => (int)$row['cnt'],
            'gross' => (float)$row['gross'],
            'discounts' => (float)$row['discounts'],
            'net' => (float)$row['net'],
            'items' => (int)$row['items'],
        ];
    }

    /**
     * Per-product sales totals over a date range (completed sales only).
     *
     * @param string $from
     * @param string $to
     * @return array
     */
    public function salesByProduct($from, $to)
    {
        $sql = "SELECT p.name, p.generic_name, p.unit,
                       SUM(si.quantity) AS qty_sold,
                       COALESCE(SUM(si.line_total), 0) AS revenue
                FROM sale_items si
                JOIN sales s ON s.id = si.sale_id
                JOIN products p ON p.id = si.product_id
                WHERE s.status = 'completed' AND s.sale_date BETWEEN ? AND ?
                GROUP BY si.product_id, p.name, p.generic_name, p.unit
                ORDER BY revenue DESC, p.name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$from, $to]);
        return $stmt->fetchAll();
    }

    /**
     * Daily sales totals for a date range — used for the Chart.js trend.
     * Returns every day in the range (zero-filled) for a clean line chart.
     *
     * @param string $from
     * @param string $to
     * @return array list of ['sale_date' => Y-m-d, 'revenue' => float] ascending
     */
    public function dailySales($from, $to)
    {
        $stmt = $this->db->prepare(
            "SELECT sale_date, COALESCE(SUM(total_amount), 0) AS revenue
             FROM sales
             WHERE status = 'completed' AND sale_date BETWEEN ? AND ?
             GROUP BY sale_date
             ORDER BY sale_date ASC"
        );
        $stmt->execute([$from, $to]);
        $rows = $stmt->fetchAll();
        $byDate = [];
        foreach ($rows as $r) {
            $byDate[$r['sale_date']] = (float)$r['revenue'];
        }

        // Zero-fill every day in the range.
        $out = [];
        $cur = strtotime($from);
        $end = strtotime($to);
        while ($cur <= $end) {
            $d = date('Y-m-d', $cur);
            $out[] = ['sale_date' => $d, 'revenue' => $byDate[$d] ?? 0.0];
            $cur = strtotime('+1 day', $cur);
        }
        return $out;
    }

    /**
     * Inventory valuation — current stock value per product from valid
     * (active, non-expired) batches: SUM(quantity * unit_cost), plus grand total.
     *
     * @return array ['products' => array, 'total' => float]
     */
    public function inventoryValuation()
    {
        $sql = "SELECT p.id, p.name, p.generic_name, p.unit,
                       COALESCE(SUM(b.quantity), 0) AS qty,
                       COALESCE(SUM(b.quantity * b.unit_cost), 0) AS value
                FROM products p
                LEFT JOIN batches b
                       ON b.product_id = p.id AND b.is_active = 1 AND b.expiry_date > CURDATE()
                WHERE p.is_active = 1
                GROUP BY p.id, p.name, p.generic_name, p.unit
                ORDER BY value DESC, p.name ASC";
        $products = $this->db->query($sql)->fetchAll();

        $total = 0.0;
        foreach ($products as &$p) {
            $p['value'] = (float)$p['value'];
            $total += $p['value'];
        }
        unset($p);

        return ['products' => $products, 'total' => $total];
    }

    /**
     * Inventory movement log, filterable by type and date range.
     *
     * @param string|null $type
     * @param string|null $from
     * @param string|null $to
     * @return array
     */
    public function movements($type = null, $from = null, $to = null)
    {
        $where = [];
        $params = [];

        if ($type !== null && $type !== '') {
            $where[] = 'm.movement_type = ?';
            $params[] = $type;
        }
        if ($from !== null && $from !== '') {
            $where[] = 'DATE(m.created_at) >= ?';
            $params[] = $from;
        }
        if ($to !== null && $to !== '') {
            $where[] = 'DATE(m.created_at) <= ?';
            $params[] = $to;
        }

        $sql = "SELECT m.id, m.movement_type, m.quantity_change, m.reference, m.notes,
                       m.created_at, p.name AS product_name, u.username
                FROM inventory_movements m
                LEFT JOIN products p ON p.id = m.product_id
                LEFT JOIN users u ON u.id = m.user_id";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY m.created_at DESC LIMIT 500";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Distinct movement types for the filter dropdown.
     *
     * @return array
     */
    public function movementTypes()
    {
        return ['receive', 'sale', 'void', 'waste', 'adjustment'];
    }
}

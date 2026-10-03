<?php

namespace App\Models;

use App\Core\Model;

/**
 * Dashboard — read-only KPI + trend queries per staff role.
 * All numbers come from ACTUAL transaction/inventory rows (rule 10).
 */
class Dashboard extends Model
{
    protected $table = 'sales';

    /**
     * KPIs for a given staff role.
     *
     * @param string $roleName lowercased role ('admin', 'pharmacist', 'cashier')
     * @return array
     */
    public function kpisForRole($roleName)
    {
        $config = require __DIR__ . '/../../config/app.php';
        $svc = new \App\Services\StockService();

        $today = date('Y-m-d');

        // Today's sales (completed).
        $todayStmt = $this->db->prepare(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS revenue
             FROM sales WHERE status = 'completed' AND sale_date = ?"
        );
        $todayStmt->execute([$today]);
        $todayRow = $todayStmt->fetch();

        $base = [
            'today_sales_count' => (int)$todayRow['cnt'],
            'today_revenue' => (float)$todayRow['revenue'],
            'low_stock_count' => count($svc->getLowStock()),
            'expiring_count' => count($svc->getExpiring((int)($config['near_expiry_days'] ?? 30))),
            'expired_count' => count($svc->getExpired()),
        ];

        // Admin: add total revenue, open POs, and supplier count.
        if ($roleName === 'admin') {
            $base['total_revenue'] = (float)$this->db->query(
                "SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE status = 'completed'"
            )->fetchColumn();
            $base['open_pos'] = (int)$this->db->query(
                "SELECT COUNT(*) FROM purchase_orders WHERE status = 'ordered' OR status = 'draft'"
            )->fetchColumn();
            $base['supplier_count'] = (int)$this->db->query(
                "SELECT COUNT(*) FROM suppliers WHERE is_active = 1"
            )->fetchColumn();
        }

        // Pharmacist: add open PO count + suggestion count (inventory focus).
        if ($roleName === 'pharmacist') {
            $base['open_pos'] = (int)$this->db->query(
                "SELECT COUNT(*) FROM purchase_orders WHERE status IN ('ordered','draft')"
            )->fetchColumn();
            $base['suggestion_count'] = count((new \App\Services\PurchaseService())->getReorderSuggestions());
        }

        return $base;
    }

    /**
     * Weekly sales trend — last 7 days, completed sales only (Chart.js data).
     *
     * @return array list of ['sale_date' => Y-m-d, 'revenue' => float]
     */
    public function weeklySalesTrend()
    {
        $from = date('Y-m-d', strtotime('-6 days'));
        $to = date('Y-m-d');
        return (new Report())->dailySales($from, $to);
    }

    /**
     * Recent completed sales (small list for the dashboard).
     *
     * @param int $limit
     * @return array
     */
    public function recentSales($limit = 5)
    {
        $stmt = $this->db->prepare(
            "SELECT s.id, s.sale_number, s.sale_date, s.customer_name, s.payment_method,
                    s.total_amount, s.status, u.username AS sold_by_name
             FROM sales s
             LEFT JOIN users u ON u.id = s.sold_by
             ORDER BY s.created_at DESC
             LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    /**
     * Recent inventory movements (small list for the dashboard).
     *
     * @param int $limit
     * @return array
     */
    public function recentMovements($limit = 6)
    {
        $stmt = $this->db->prepare(
            "SELECT m.movement_type, m.quantity_change, m.created_at,
                    p.name AS product_name, u.username
             FROM inventory_movements m
             LEFT JOIN products p ON p.id = m.product_id
             LEFT JOIN users u ON u.id = m.user_id
             ORDER BY m.created_at DESC
             LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
}

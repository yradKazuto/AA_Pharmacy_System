<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\RoleMiddleware;
use App\Models\Report;

/**
 * ReportController — read-only report pages (rule 10: actual data only).
 * All gated on reports:view (admin, pharmacist, cashier all have it).
 */
class ReportController extends Controller
{
    /**
     * Reports index — links to each report.
     *
     * @return void
     */
    public function index()
    {
        RoleMiddleware::requirePermission('reports:view', '/unauthorized');
        $this->view('reports/index');
    }

    /**
     * Sales report with an optional date-range filter.
     *
     * @return void
     */
    public function sales()
    {
        RoleMiddleware::requirePermission('reports:view', '/unauthorized');

        $from = trim($_GET['from'] ?? '');
        $to = trim($_GET['to'] ?? '');
        // Default: current month so the page is never empty.
        if ($from === '' || $to === '') {
            $from = date('Y-m-01');
            $to = date('Y-m-d');
        }

        $report = new Report();
        $this->view('reports/sales', [
            'from' => $from,
            'to' => $to,
            'summary' => $report->salesSummary($from, $to),
            'byProduct' => $report->salesByProduct($from, $to),
        ]);
    }

    /**
     * Inventory valuation report.
     *
     * @return void
     */
    public function valuation()
    {
        RoleMiddleware::requirePermission('reports:view', '/unauthorized');

        $report = new Report();
        $this->view('reports/valuation', $report->inventoryValuation());
    }

    /**
     * Stock movement report with type + date-range filters.
     *
     * @return void
     */
    public function movements()
    {
        RoleMiddleware::requirePermission('reports:view', '/unauthorized');

        $type = trim($_GET['type'] ?? '');
        $from = trim($_GET['from'] ?? '');
        $to = trim($_GET['to'] ?? '');

        $report = new Report();
        $this->view('reports/movements', [
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'types' => $report->movementTypes(),
            'movements' => $report->movements($type, $from, $to),
        ]);
    }
}

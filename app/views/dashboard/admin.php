<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - AA Pharmacy System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .stat-card { border-radius: .5rem; }
        .card-title { font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; }
        .kpi-num { font-size: 1.6rem; font-weight: 600; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin/dashboard">AA Pharmacy System</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="/admin/dashboard">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="/users">Users</a></li>
                    <li class="nav-item"><a class="nav-link" href="/roles">Roles</a></li>
                    <li class="nav-item"><a class="nav-link" href="/reports">Reports</a></li>
                    <li class="nav-item"><a class="nav-link" href="/logout">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (!empty($_GET['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_GET['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Admin Dashboard</h2>
            <a href="/reports" class="btn">Reports</a>
        </div>

        <!-- KPI cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card shadow-sm">
                    <div class="card-body">
                        <div class="card-title">Today Sales</div>
                        <div class="kpi-num"><?php echo (int)$kpis['today_sales_count']; ?></div>
                        <small class="text-muted">PHP <?php echo number_format((float)$kpis['today_revenue'], 2); ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card shadow-sm">
                    <div class="card-body">
                        <div class="card-title">Total Revenue</div>
                        <div class="kpi-num">PHP <?php echo number_format((float)$kpis['total_revenue'], 2); ?></div>
                        <small class="text-muted">all time, completed sales</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card shadow-sm">
                    <div class="card-body">
                        <div class="card-title">Low Stock</div>
                        <div class="kpi-num" <?php echo (int)$kpis['low_stock_count'] > 0 ? 'class="text-warning"' : ''; ?>><?php echo (int)$kpis['low_stock_count']; ?></div>
                        <a href="/inventory/alerts" class="small">view alerts &raquo;</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card shadow-sm">
                    <div class="card-body">
                        <div class="card-title">Expiring Soon</div>
                        <div class="kpi-num"><?php echo (int)$kpis['expiring_count']; ?></div>
                        <a href="/inventory/alerts" class="small">view alerts &raquo;</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- sales trend chart -->
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header">Sales Trend (last 7 days)</div>
                    <div class="card-body">
                        <canvas id="salesChart" height="110"></canvas>
                    </div>
                </div>
            </div>
            <!-- right column: open POs / recent movements -->
            <div class="col-lg-4">
                <div class="card shadow-sm mb-3">
                    <div class="card-header">Quick Stats</div>
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between border-bottom py-1">
                            <span>Open purchase orders</span>
                            <strong><?php echo (int)$kpis['open_pos']; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between border-bottom py-1">
                            <span>Active suppliers</span>
                            <strong><?php echo (int)$kpis['supplier_count']; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span>Expired batches</span>
                            <strong class="text-danger"><?php echo (int)$kpis['expired_count']; ?></strong>
                        </div>
                        <div class="mt-2 d-grid gap-2">
                            <a href="/reports" class="btn btn-sm btn-outline-primary">Reports</a>
                            <a href="/pos" class="btn btn-sm btn-success">Open POS</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header">Recent Sales</div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr><th>Sale#</th><th>Date</th><th>Total</th><th>Cashier</th></tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentSales)): ?>
                                    <?php foreach ($recentSales as $s): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($s['sale_number']); ?></td>
                                            <td><?php echo htmlspecialchars($s['sale_date']); ?></td>
                                            <td>PHP <?php echo number_format((float)$s['total_amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($s['sold_by_name'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted">No sales yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header">Recent Inventory Movements</div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr><th>Type</th><th>Product</th><th>Qty</th><th>When</th></tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentMovements)): ?>
                                    <?php foreach ($recentMovements as $m): ?>
                                        <tr>
                                            <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($m['movement_type']); ?></span></td>
                                            <td><?php echo htmlspecialchars($m['product_name'] ?? '-'); ?></td>
                                            <td><?php echo (int)$m['quantity_change'] > 0 ? '+' : ''; ?><?php echo (int)$m['quantity_change']; ?></td>
                                            <td><?php echo htmlspecialchars($m['created_at']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted">No movements.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <script>
        const trend = <?php echo json_encode($trend); ?>;
        new Chart(document.getElementById('salesChart'), {
            type: 'line',
            data: {
                labels: trend.map(function(d) { return d.sale_date; }),
                datasets: [{
                    label: 'Revenue (PHP)',
                    data: trend.map(function(d) { return d.revenue; }),
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13,110,253,.12)',
                    fill: true,
                    tension: .3
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } }
            }
        });
    </script>
</body>
</html>

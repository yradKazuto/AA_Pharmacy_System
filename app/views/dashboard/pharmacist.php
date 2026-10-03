<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacist Dashboard - AA Pharmacy System</title>
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
            <a class="navbar-brand" href="/">AA Pharmacy System</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="/pharmacist/dashboard">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="/inventory">Inventory</a></li>
                    <li class="nav-item"><a class="nav-link" href="/products">Products</a></li>
                    <li class="nav-item"><a class="nav-link" href="/purchases">Purchases</a></li>
                    <li class="nav-item"><a class="nav-link" href="/suppliers">Suppliers</a></li>
                    <li class="nav-item"><a class="nav-link" href="/reports">Reports</a></li>
                    <li class="nav-item"><a class="nav-link" href="/inventory/alerts">Alerts</a></li>
                    <li class="nav-item"><a class="nav-link" href="/logout">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Pharmacist Dashboard</h2>
            <a href="/purchases/suggestions" class="btn btn-outline-secondary">Reorder Suggestions</a>
        </div>

        <!-- KPI cards -->
        <div class="row g-3 mb-4">
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
            <div class="col-md-3">
                <div class="card stat-card shadow-sm">
                    <div class="card-body">
                        <div class="card-title">Expired Stock</div>
                        <div class="kpi-num" <?php echo (int)$kpis['expired_count'] > 0 ? 'style="color:#dc3545"' : ''; ?>><?php echo (int)$kpis['expired_count']; ?></div>
                        <a href="/inventory/alerts" class="small">view alerts &raquo;</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card shadow-sm">
                    <div class="card-body">
                        <div class="card-title">Reorder Suggestions</div>
                        <div class="kpi-num"><?php echo (int)$kpis['suggestion_count']; ?></div>
                        <a href="/purchases/suggestions" class="small">recommend-only (rule 6) &raquo;</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card shadow-sm">
                    <div class="card-body">
                        <div class="card-title">Open POs</div>
                        <div class="kpi-num"><?php echo (int)$kpis['open_pos']; ?></div>
                        <a href="/purchases" class="small">view purchase orders &raquo;</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card shadow-sm">
                    <div class="card-body">
                        <div class="card-title">Today Sales</div>
                        <div class="kpi-num"><?php echo (int)$kpis['today_sales_count']; ?></div>
                        <small class="text-muted">PHP <?php echo number_format((float)$kpis['today_revenue'], 2); ?></small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card shadow-sm">
                    <div class="card-header">Sales Trend (last 7 days)</div>
                    <div class="card-body">
                        <canvas id="salesChart" height="110"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card shadow-sm mb-3">
                    <div class="card-header">Recent Inventory Movements</div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr><th>Type</th><th>Product</th><th>Qty</th></tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentMovements)): ?>
                                    <?php foreach ($recentMovements as $m): ?>
                                        <tr>
                                            <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($m['movement_type']); ?></span></td>
                                            <td><?php echo htmlspecialchars($m['product_name'] ?? '-'); ?></td>
                                            <td><?php echo (int)$m['quantity_change'] > 0 ? '+' : ''; ?><?php echo (int)$m['quantity_change']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center text-muted">No movements.</td></tr>
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
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25,135,84,.12)',
                    fill: true,
                    tension: .3
                }]
            }
        });
    </script>
</body>
</html>

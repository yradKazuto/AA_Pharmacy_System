<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier Dashboard - AA Pharmacy System</title>
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
            <a class="navbar-brand" href="/cashier/dashboard">AA Pharmacy System</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="/cashier/dashboard">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="/pos">POS Terminal</a></li>
                    <li class="nav-item"><a class="nav-link" href="/pos/sales">Sales</a></li>
                    <li class="nav-item"><a class="nav-link" href="/inventory">Inventory</a></li>
                    <li class="nav-item"><a class="nav-link" href="/reports">Reports</a></li>
                    <li class="nav-item"><a class="nav-link" href="/logout">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Cashier Dashboard</h2>
            <a href="/pos" class="btn btn-success btn-lg">Start New Sale</a>
        </div>

        <!-- KPI cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card shadow-sm">
                    <div class="card-body">
                        <div class="card-title">Today's Sales</div>
                        <div class="kpi-num"><?php echo (int)$kpis['today_sales_count']; ?></div>
                        <small class="text-muted">completed today</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card shadow-sm">
                    <div class="card-body">
                        <div class="card-title">Today's Revenue</div>
                        <div class="kpi-num">PHP <?php echo number_format((float)$kpis['today_revenue'], 2); ?></div>
                        <small class="text-muted">from completed sales</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card shadow-sm">
                    <div class="card-body">
                        <div class="card-title">Low Stock</div>
                        <div class="kpi-num" <?php echo (int)$kpis['low_stock_count'] > 0 ? 'class="text-warning"' : ''; ?>><?php echo (int)$kpis['low_stock_count']; ?></div>
                        <a href="/inventory/alerts" class="small">view alerts &raquo;</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Recent Sales</span>
                <a href="/pos/sales" class="small">view all &raquo;</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Sale#</th><th>Date</th><th>Customer</th><th>Payment</th><th>Total</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentSales)): ?>
                            <?php foreach ($recentSales as $s): ?>
                                <tr>
                                    <td><a href="/sales/<?php echo (int)$s['id'] ?? ''; ?>"><?php echo htmlspecialchars($s['sale_number']); ?></a></td>
                                    <td><?php echo htmlspecialchars($s['sale_date']); ?></td>
                                    <td><?php echo htmlspecialchars($s['customer_name'] ?? 'Walk-in'); ?></td>
                                    <td><?php echo ucfirst(htmlspecialchars($s['payment_method'])); ?></td>
                                    <td>PHP <?php echo number_format((float)$s['total_amount'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center text-muted">No sales yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report - {app}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .stat-num { font-size: 1.5rem; font-weight: 600; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="/">AA Pharmacy System</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="/">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="/reports">Reports</a></li>
                    <li class="nav-item"><a class="nav-link" href="/logout">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Sales Report</h2>
            <a href="/reports" class="btn btn-outline-secondary">&larr; All Reports</a>
        </div>

        <!-- Date-range filter -->
        <form method="GET" class="row g-2 align-items-end mb-4">
            <div class="col-auto">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($from); ?>">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($to); ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm">Apply</button>
            </div>
            <div class="col-auto">
                <a href="/reports/sales" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>

        <!-- Summary cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card shadow-sm">
                    <div class="card-body"><div class="card-title small text-uppercase text-muted">Sales</div><div class="stat-num"><?php echo (int)$summary['count']; ?></div></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm">
                    <div class="card-body"><div class="card-title small text-uppercase text-muted">Gross Revenue</div><div class="stat-num">PHP <?php echo number_format((float)$summary['gross'], 2); ?></div></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm">
                    <div class="card-body"><div class="card-title small text-uppercase text-muted">Discounts</div><div class="stat-num text-danger">- PHP <?php echo number_format((float)$summary['discounts'], 2); ?></div></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm">
                    <div class="card-body"><div class="card-title small text-uppercase text-muted">Net Revenue</div><div class="stat-num">PHP <?php echo number_format((float)$summary['net'], 2); ?></div></div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header">Sales by Product (<?php echo htmlspecialchars($from); ?> → <?php echo htmlspecialchars($to); ?>)</div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th>Generic</th>
                            <th>Unit</th>
                            <th class="text-end">Qty Sold</th>
                            <th class="text-end">Revenue (PHP)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($byProduct)): ?>
                            <?php foreach ($byProduct as $b): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($b['name']); ?></td>
                                    <td><?php echo htmlspecialchars($b['generic_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($b['unit']); ?></td>
                                    <td class="text-end"><?php echo (int)$b['qty_sold']; ?></td>
                                    <td class="text-end"><?php echo number_format((float)$b['revenue'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center text-muted">No completed sales in this range.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Valuation - AA Pharmacy System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body { background-color: #f8f9fa; }</style>
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
            <h2>Inventory Valuation</h2>
            <a href="/reports" class="btn btn-outline-secondary">&larr; All Reports</a>
        </div>
        <p class="text-muted">Current stock value (qty × cost) from active, non-expired batches only. Updated live.</p>

        <div class="card shadow-sm mb-4">
            <div class="card-body text-center">
                <div class="small text-uppercase text-muted">Total Portfolio Value</div>
                <div class="display-6">PHP <?php echo number_format((float)$total, 2); ?> </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header">Per-Product Valuation</div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th>Generic</th>
                            <th>Unit</th>
                            <th class="text-end">Qty (valid)</th>
                            <th class="text-end">Value (PHP)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($products)): ?>
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($p['name']); ?></td>
                                    <td><?php echo htmlspecialchars($p['generic_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($p['unit']); ?></td>
                                    <td class="text-end"><?php echo (int)$p['qty']; ?></td>
                                    <td class="text-end"><?php echo number_format((float)$p['value'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center text-muted">No active products with stock.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

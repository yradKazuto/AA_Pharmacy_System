<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Movement Log - AA Pharmacy System</title>
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
            <h2>Stock Movement Log</h2>
            <a href="/reports" class="btn btn-outline-secondary">&larr; All Reports</a>
        </div>

        <!-- Filters -->
        <form method="GET" class="row g-2 align-items-end mb-4">
            <div class="col-auto">
                <label class="form-label small mb-1">Type</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">All types</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?php echo $t; ?>" <?php echo $type === $t ? 'selected' : ''; ?>><?php echo ucfirst($t); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
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
                <a href="/reports/movements" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>

        <div class="card shadow-sm">
            <div class="card-header"><?php echo (int)count($movements); ?> movement(s)</div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Product</th>
                            <th class="text-end">Qty Change</th>
                            <th>Reference</th>
                            <th>User</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($movements)): ?>
                            <?php foreach ($movements as $m): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($m['created_at']); ?></td>
                                    <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($m['movement_type']); ?></span></td>
                                    <td><?php echo htmlspecialchars($m['product_name'] ?? '-'); ?></td>
                                    <td class="text-end"><?php echo (int)$m['quantity_change'] > 0 ? '+' : ''; ?><?php echo (int)$m['quantity_change']; ?></td>
                                    <td><?php echo htmlspecialchars($m['reference'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($m['username'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($m['notes'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center text-muted">No movements match the filters.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

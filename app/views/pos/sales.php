<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales - AA Pharmacy System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .table-container { margin-top: 20px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="/dashboard">AA Pharmacy System</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="/pos">POS Terminal</a></li>
                    <li class="nav-item"><a class="nav-link" href="/pos/sales">Sales</a></li>
                    <li class="nav-item"><a class="nav-link" href="/inventory">Inventory</a></li>
                    <li class="nav-item"><a class="nav-link" href="/inventory/alerts">Alerts</a></li>
                    <li class="nav-item"><a class="nav-link" href="/purchases">Purchases</a></li>
                    <li class="nav-item"><a class="nav-link" href="/suppliers">Suppliers</a></li>
                    <li class="nav-item">
                        <span class="nav-link text-white"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></span>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="/logout">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Recent Sales</h2>
            <a href="/pos" class="btn btn-success">New Sale</a>
        </div>

        <?php if (!empty($_GET['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_GET['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <div class="table-container">
            <table class="table table-hover table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Sale#</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Payment</th>
                        <th>Total (PHP)</th>
                        <th>Cashier</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($sales)): ?>
                        <?php foreach ($sales as $s): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($s['sale_number']); ?></td>
                                <td><?php echo htmlspecialchars($s['sale_date']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($s['created_at']); ?></small></td>
                                <td><?php echo htmlspecialchars($s['customer_name'] ?? 'Walk-in'); ?></td>
                                <td><?php echo ucfirst(htmlspecialchars($s['payment_method'])); ?>
                                    <?php if ($s['payment_method'] === 'cash' && $s['status'] === 'completed'): ?>
                                        <br><small class="text-muted">Change: PHP <?php echo number_format((float)$s['change_due'], 2); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format((float)$s['total_amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($s['sold_by_name'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($s['status'] === 'voided'): ?>
                                        <span class="badge bg-secondary"><?php echo ucfirst(htmlspecialchars($s['status'])); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Completed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="/sales/<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-outline-info" title="Receipt">View</a>
                                        <?php if ($s['status'] === 'completed'): ?>
                                            <form action="/sales/<?php echo (int)$s['id']; ?>/void" method="POST"
                                                  onsubmit="return confirm('Void this sale and restore its stock?');">
                                                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Void sale">Void</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center">No sales yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - AA Pharmacy System</title>
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
                    <li class="nav-item"><a class="nav-link" href="/products">Products</a></li>
                    <li class="nav-item"><a class="nav-link" href="/inventory/alerts">Alerts</a></li>
                    <li class="nav-item"><span class="nav-link text-white"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></span></li>
                    <li class="nav-item"><a class="nav-link" href="/logout">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Inventory Overview</h2>
            <a href="/inventory/alerts" class="btn btn-outline-warning">View Alerts</a>
        </div>

        <div class="table-container">
            <table class="table table-hover table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th>Price (PHP)</th>
                        <th>Available</th>
                        <th>Stock Value</th>
                        <th>Batches</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $i => $p): ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($p['name']); ?>
                                    <?php if ($p['requires_prescription']): ?><span class="badge bg-warning text-dark">Rx</span><?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($p['category'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($p['unit']); ?></td>
                                <td><?php echo number_format((float)$p['unit_price'], 2); ?></td>
                                <td>
                                    <?php echo (int)$p['total_qty']; ?>
                                    <?php if ((int)$p['total_qty'] <= 10): ?>
                                        <span class="badge bg-danger">Low</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format((float)$p['total_cost_value'], 2); ?></td>
                                <td><a href="/products/<?php echo $p['id']; ?>/batches" class="btn btn-sm btn-outline-info">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center">No active products.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Alerts - AA Pharmacy System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .section { margin-top: 25px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="/dashboard">AA Pharmacy System</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="/products">Products</a></li>
                    <li class="nav-item"><a class="nav-link" href="/inventory">Inventory</a></li>
                    <li class="nav-item"><span class="nav-link text-white"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></span></li>
                    <li class="nav-item"><a class="nav-link" href="/logout">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <a href="/inventory" class="btn btn-sm btn-outline-secondary">&larr; Back to Inventory</a>
        <h2 class="mt-2">Inventory Alerts</h2>

        <!-- EXPIRED -->
        <div class="section">
            <h5 class="text-danger">Expired Batches (must NOT be sold)</h5>
            <?php if (!empty($expired)): ?>
                <table class="table table-sm table-bordered table-striped">
                    <thead class="table-danger">
                        <tr><th>Product</th><th>Lot</th><th>Expiry</th><th>Qty</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expired as $e): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($e['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($e['lot_number']); ?></td>
                                <td><?php echo htmlspecialchars($e['expiry_date']); ?></td>
                                <td><?php echo (int)$e['quantity']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-muted">No expired batches.</p>
            <?php endif; ?>
        </div>

        <!-- EXPIRING -->
        <div class="section">
            <h5 class="text-warning">Expiring within <?php echo (int)$expiringDays; ?> days</h5>
            <?php if (!empty($expiring)): ?>
                <table class="table table-sm table-bordered table-striped">
                    <thead class="table-warning">
                        <tr><th>Product</th><th>Lot</th><th>Expiry</th><th>Qty</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expiring as $x): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($x['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($x['lot_number']); ?></td>
                                <td><?php echo htmlspecialchars($x['expiry_date']); ?></td>
                                <td><?php echo (int)$x['quantity']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-muted">No batches expiring soon.</p>
            <?php endif; ?>
        </div>

        <!-- LOW STOCK -->
        <div class="section">
            <h5 class="text-danger">Low Stock (&le; <?php echo (int)$threshold; ?> units available)</h5>
            <?php if (!empty($lowStock)): ?>
                <table class="table table-sm table-bordered table-striped">
                    <thead class="table-dark">
                        <tr><th>Product</th><th>Unit</th><th>Price</th><th>Available</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowStock as $l): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($l['name']); ?>
                                    <a href="/products/<?php echo $l['id']; ?>/batches" class="btn btn-sm btn-outline-info ms-2">Receive</a>
                                </td>
                                <td><?php echo htmlspecialchars($l['unit']); ?></td>
                                <td><?php echo number_format((float)$l['unit_price'], 2); ?></td>
                                <td><span class="badge bg-danger"><?php echo (int)$l['total_qty']; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-muted">No low-stock products.</p>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

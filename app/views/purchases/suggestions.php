<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reorder Suggestions - AA Pharmacy System</title>
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
                    <li class="nav-item"><a class="nav-link" href="/purchases">Purchase Orders</a></li>
                    <li class="nav-item"><a class="nav-link" href="/suppliers">Suppliers</a></li>
                    <li class="nav-item"><a class="nav-link" href="/inventory">Inventory</a></li>
                    <li class="nav-item"><a class="nav-link" href="/logout">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
                <h2 class="mb-0">Reorder Suggestions</h2>
                <small class="text-muted">Recommendation only — ordering is never automatic.</small>
            </div>
            <a href="/purchases/create?suggest=1" class="btn btn-success">Create Order from Suggestions</a>
        </div>

        <div class="table-container">
            <table class="table table-hover table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>Unit</th>
                        <th>Selling Price (PHP)</th>
                        <th>Current Stock</th>
                        <th>Recommended Qty</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($suggestions)): ?>
                        <?php foreach ($suggestions as $i => $s): ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td><?php echo htmlspecialchars($s['name']); ?></td>
                                <td><?php echo htmlspecialchars($s['unit']); ?></td>
                                <td><?php echo number_format((float)$s['unit_price'], 2); ?></td>
                                <td>
                                    <?php echo (int)$s['total_qty']; ?>
                                    <span class="badge bg-danger">Low Stock</span>
                                </td>
                                <td><?php echo (int)$s['suggested_qty']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center">No products need reordering right now.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

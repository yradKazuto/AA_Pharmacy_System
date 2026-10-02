<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - AA Pharmacy System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .table-container { margin-top: 20px; }
        .btn-action { margin-right: 5px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="/dashboard">AA Pharmacy System</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="/inventory">Inventory</a></li>
                    <li class="nav-item"><a class="nav-link" href="/inventory/alerts">Alerts</a></li>
                    <li class="nav-item">
                        <span class="nav-link text-white">Logged in as:
                            <strong><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>
                            (<?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?>)</strong>
                        </span>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="/logout">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Products</h2>
            <a href="/products/create" class="btn btn-success">Add New Product</a>
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
                        <th>#</th>
                        <th>Name</th>
                        <th>Generic</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th>Price (PHP)</th>
                        <th>Stock</th>
                        <th>Rx</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $i => $p): ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td><?php echo htmlspecialchars($p['name']); ?></td>
                                <td><?php echo htmlspecialchars($p['generic_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($p['category'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($p['unit']); ?></td>
                                <td><?php echo number_format((float)$p['unit_price'], 2); ?></td>
                                <td>
                                    <?php echo (int)$p['total_qty']; ?>
                                    <?php if ((int)$p['total_qty'] <= 10): ?>
                                        <span class="badge bg-danger">Low Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $p['requires_prescription'] ? '<span class="badge bg-warning text-dark">Rx</span>' : '<span class="badge bg-secondary">OTC</span>'; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="/products/<?php echo $p['id']; ?>/batches" class="btn btn-sm btn-outline-info" title="Batches / Receive">Batches</a>
                                        <a href="/products/<?php echo $p['id']; ?>/edit" class="btn btn-sm btn-outline-primary" title="Edit">Edit</a>
                                        <form action="/products/<?php echo $p['id']; ?>/delete" method="POST"
                                              onsubmit="return confirm('Deactivate this product? Existing batches are kept.');">
                                            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Deactivate">Del</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="text-center">No products found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

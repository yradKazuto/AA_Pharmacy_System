<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Orders - AA Pharmacy System</title>
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
                    <li class="nav-item"><a class="nav-link" href="/inventory">Inventory</a></li>
                    <li class="nav-item"><a class="nav-link" href="/suppliers">Suppliers</a></li>
                    <li class="nav-item"><a class="nav-link" href="/purchases/suggestions">Reorder Suggestions</a></li>
                    <li class="nav-item"><a class="nav-link" href="/inventory/alerts">Alerts</a></li>
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
            <h2>Purchase Orders</h2>
            <div>
                <a href="/purchases/create?suggest=1" class="btn btn-outline-warning me-2">New Order from Suggestions</a>
                <a href="/purchases/create" class="btn btn-success">New Purchase Order</a>
            </div>
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
                        <th>PO#</th>
                        <th>Supplier</th>
                        <th>Status</th>
                        <th>Order Date</th>
                        <th>Expected</th>
                        <th>Items</th>
                        <th>Total (PHP)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($purchaseOrders)): ?>
                        <?php foreach ($purchaseOrders as $po): ?>
                            <?php $status = $po['status']; ?>
                            <tr>
                                <td><?php echo htmlspecialchars($po['po_number']); ?></td>
                                <td><?php echo htmlspecialchars($po['supplier_name']); ?></td>
                                <td>
                                    <?php if ($status === 'received'): ?>
                                        <span class="badge bg-success">Received</span>
                                    <?php elseif ($status === 'cancelled'): ?>
                                        <span class="badge bg-secondary">Cancelled</span>
                                    <?php elseif ($status === 'ordered'): ?>
                                        <span class="badge bg-primary">Ordered</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Draft</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($po['order_date'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($po['expected_date'] ?? ''); ?></td>
                                <td><?php echo (int)$po['item_count']; ?></td>
                                <td><?php echo number_format((float)$po['total_amount'], 2); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="/purchases/<?php echo $po['id']; ?>" class="btn btn-sm btn-outline-info" title="View">View</a>
                                        <?php if ($status === 'ordered' || $status === 'draft'): ?>
                                            <a href="/purchases/<?php echo $po['id']; ?>/receive" class="btn btn-sm btn-outline-success" title="Receive">Receive</a>
                                            <form action="/purchases/<?php echo $po['id']; ?>/cancel" method="POST"
                                                  onsubmit="return confirm('Cancel this purchase order?');">
                                                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel">Cancel</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center">No purchase orders found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Portal - AA Pharmacy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .brand { font-size: 1.9rem; font-weight: 700; color: #0d6efd; }
        .stat-num { font-size: 1.6rem; font-weight: 600; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="/customer/dashboard">AA Pharmacy</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="/shop">Shop</a></li>
                    <li class="nav-item"><a class="nav-link" href="/orders">My Orders</a></li>
                    <li class="nav-item"><a class="nav-link" href="/logout">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (!empty($_GET['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_GET['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <div class="text-center mb-4">
            <div class="brand">AA Pharmacy</div>
            <p class="text-muted">Zamboanga City — order online, pick up or delivered.</p>
            <a href="/shop" class="btn btn-primary btn-lg">Browse the Catalog</a>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <div class="small text-uppercase text-muted">Active Orders</div>
                        <div class="stat-num"><?php echo (int)$activeOrders; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <div class="small text-uppercase text-muted">Items Available</div>
                        <div class="stat-num"><?php echo (int)$catalogCount; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <div class="small text-uppercase text-muted">Payment</div>
                        <div class="stat-num small mt-2">Cash on Delivery</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Recent Orders</span>
                <a href="/orders" class="small">view all &raquo;</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Order#</th><th>Date</th><th>Total</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($orders)): ?>
                            <?php foreach ($orders as $o): ?>
                                <tr>
                                    <td><a href="/orders/<?php echo (int)$o['id']; ?>"><?php echo htmlspecialchars($o['order_number']); ?></a></td>
                                    <td><?php echo htmlspecialchars($o['created_at']); ?></td>
                                    <td>PHP <?php echo number_format((float)$o['total_amount'], 2); ?></td>
                                    <td><span class="badge bg-<?php echo $o['status'] === 'delivered' ? 'success' : ($o['status'] === 'cancelled' || $o['status'] === 'rejected' ? 'secondary' : 'primary'); ?>"><?php echo htmlspecialchars(str_replace('_', ' ', $o['status'])); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center text-muted">No orders yet. <a href="/shop">Start shopping</a>.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

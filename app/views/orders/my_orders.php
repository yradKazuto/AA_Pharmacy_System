<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - AA Pharmacy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .status-pending_review { background-color: #ffc107; }
        .status-approved { background-color: #0d6efd; }
        .status-fulfilled { background-color: #6f42c1; }
        .status-delivered { background-color: #198754; }
        .status-rejected, .status-cancelled { background-color: #6c757d; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="/customer/dashboard">AA Pharmacy</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="/shop">Shop</a></li>
                    <li class="nav-item"><a class="nav-link" href="/cart">Cart</a></li>
                    <li class="nav-item"><a class="nav-link active" href="/orders">My Orders</a></li>
                    <li class="nav-item"><a class="nav-link" href="/logout">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>My Orders</h2>
            <a href="/shop" class="btn btn-outline-primary">New Order</a>
        </div>

        <?php if (!empty($_GET['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_GET['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <?php if (!empty($orders)): ?>
            <div class="list-group">
                <?php foreach ($orders as $o): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <a href="/orders/<?php echo (int)$o['id']; ?>" class="fw-bold text-decoration-none">
                                <?php echo htmlspecialchars($o['order_number']); ?>
                            </a>
                            <div class="small text-muted">
                                <?php echo date('M j, Y g:i A', strtotime($o['created_at'])); ?> &middot;
                                <?php echo (int)$o['item_count']; ?> item(s)
                            </div>
                        </div>
                        <div class="text-end">
                            <div>PHP <?php echo number_format((float)$o['total_amount'], 2); ?></div>
                            <span class="badge status-<?php echo htmlspecialchars($o['status']); ?> text-white">
                                <?php echo htmlspecialchars(str_replace('_', ' ', $o['status'])); ?>
                            </span>
                            <?php if (in_array($o['status'], ['pending_review', 'approved'], true)): ?>
                                <form method="POST" action="/orders/<?php echo (int)$o['id']; ?>/cancel" class="d-inline ms-2"
                                      onsubmit="return confirm('Cancel this order?');">
                                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <button class="btn btn-sm btn-outline-danger">Cancel</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center text-muted py-5">
                <p>You have no orders yet.</p>
                <a href="/shop" class="btn btn-primary">Browse the catalog</a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

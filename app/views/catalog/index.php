<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalog - AA Pharmacy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card :hover { cursor: pointer; }
        .rx-badge { position: absolute; top: .5rem; right: .5rem; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="/customer/dashboard">AA Pharmacy</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="/customer/dashboard">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="/shop">Shop</a></li>
                    <li class="nav-item"><a class="nav-link" href="/orders">My Orders</a></li>
                    <li class="nav-item"><a class="nav-link" href="/logout">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Catalog</h2>
            <a href="/cart" class="btn btn-outline-primary">View Cart</a>
        </div>

        <?php if (!empty($_GET['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_GET['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <div class="row g-3">
            <?php if (!empty($products)): ?>
                <?php foreach ($products as $p): ?>
                    <?php $rx = (int)$p['requires_prescription'] === 1; ?>
                    <div class="col-md-3 col-sm-6">
                        <div class="card h-100 shadow-sm position-relative">
                            <?php if ($rx): ?>
                                <span class="rx-badge badge bg-danger">Rx</span>
                            <?php endif; ?>
                            <div class="card-body text-center">
                                <div class="fw-bold"><?php echo htmlspecialchars($p['name']); ?></div>
                                <div class="small text-muted"><?php echo htmlspecialchars($p['generic_name'] ?? ''); ?></div>
                                <div class="mt-2 fs-5">PHP <?php echo number_format((float)$p['unit_price'], 2); ?></div>
                                <div class="small text-muted"><?php echo htmlspecialchars($p['unit']); ?> · in stock: <?php echo (int)$p['total_qty']; ?></div>
                                <?php if ($rx): ?>
                                    <div class="small text-danger mt-1">Prescription item — pharmacist will review your order.</div>
                                <?php endif; ?>
                                <form action="/checkout" method="GET" class="mt-2">
                                    <input type="hidden" name="add" value="<?php echo (int)$p['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">Add to Cart &amp; Checkout</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center text-muted">No items available right now.</div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

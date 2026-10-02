<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batches: <?php echo htmlspecialchars($product['name']); ?> - AA Pharmacy System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .table-container { margin-top: 20px; }
        .form-card { max-width: 600px; margin: 20px 0; padding: 20px; background: white; border-radius: 10px; box-shadow: 0 0 15px rgba(0,0,0,0.08); }
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
        <a href="/products" class="btn btn-sm btn-outline-secondary">&larr; Back to Products</a>
        <h2 class="mt-2"><?php echo htmlspecialchars($product['name']); ?> — Batches</h2>

        <?php if (!empty($_GET['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_GET['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <!-- Receive Stock form -->
        <div class="form-card">
            <h5>Receive Stock</h5>
            <form action="/products/<?php echo $product['id']; ?>/batches" method="POST">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label">Lot Number *</label>
                        <input type="text" name="lot_number" class="form-control" maxlength="50" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Expiry Date *</label>
                        <input type="date" name="expiry_date" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Quantity *</label>
                        <input type="number" name="quantity" min="1" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Unit Cost (PHP)</label>
                        <input type="number" name="unit_cost" step="0.01" min="0" value="0" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Supplier</label>
                        <input type="text" name="supplier" class="form-control" maxlength="100">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100">Receive Stock</button>
                    </div>
                </div>
            </form>
            <div class="form-text mt-2">Rule: an already-expired batch cannot be received.</div>
        </div>

        <div class="table-container">
            <table class="table table-hover table-striped align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Lot</th>
                        <th>Expiry</th>
                        <th>Qty</th>
                        <th>Unit Cost</th>
                        <th>Supplier</th>
                        <th>Received</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($batches)): ?>
                        <?php foreach ($batches as $b): ?>
                            <?php $isExpired = ($b['expiry_date'] <= date('Y-m-d')); ?>
                            <tr>
                                <td><?php echo htmlspecialchars($b['lot_number']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($b['expiry_date']); ?>
                                    <?php if ($isExpired): ?>
                                        <span class="badge bg-danger">Expired</span>
                                    <?php elseif (strtotime($b['expiry_date']) <= strtotime('+30 days')): ?>
                                        <span class="badge bg-warning text-dark">Near</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo (int)$b['quantity']; ?></td>
                                <td><?php echo number_format((float)$b['unit_cost'], 2); ?></td>
                                <td><?php echo htmlspecialchars($b['supplier'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($b['received_date'] ?? ''); ?></td>
                                <td>
                                    <?php if ($b['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Retired</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($b['is_active']): ?>
                                        <?php if (!$isExpired): ?>
                                        <!-- Adjust form (inline, collapse style) -->
                                        <form action="/inventory/batches/<?php echo $b['id']; ?>/adjust" method="POST" class="d-inline">
                                            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <input type="number" name="delta" class="form-control form-control-sm d-inline-block w-auto" placeholder="+/- qty" required>
                                            <input type="text" name="reason" class="form-control form-control-sm d-inline-block w-auto" placeholder="Reason" required>
                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Adjust qty">Adj</button>
                                        </form>
                                        <?php endif; ?>
                                        <form action="/batches/<?php echo $b['id']; ?>/delete" method="POST" class="d-inline"
                                              onsubmit="return confirm('Retire this batch? Existing stock is preserved but no longer sellable.');">
                                            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Retire">Retire</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center">No batches for this product yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

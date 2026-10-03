<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order <?php echo htmlspecialchars($po['po_number']); ?> - AA Pharmacy System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .box { max-width: 900px; margin: 0 auto; }
        .meta { border: 1px solid #dee2e6; border-radius: .5rem; padding: 1rem; background: #fff; }
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
                    <li class="nav-item"><a class="nav-link" href="/logout">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="box">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h3 class="mb-0"><?php echo htmlspecialchars($po['po_number']); ?></h3>
                    <small class="text-muted"><?php echo htmlspecialchars($po['supplier_name']); ?></small>
                </div>
                <div>
                    <?php if ($po['status'] === 'received'): ?>
                        <span class="badge bg-success" style="font-size:1rem">Received</span>
                    <?php elseif ($po['status'] === 'cancelled'): ?>
                        <span class="badge bg-secondary" style="font-size:1rem">Cancelled</span>
                    <?php elseif ($po['status'] === 'ordered'): ?>
                        <span class="badge bg-primary" style="font-size:1rem">Ordered</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark" style="font-size:1rem">Draft</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($_GET['success'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
            <?php endif; ?>
            <?php if (!empty($_GET['error'])): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>

            <div class="meta mb-4">
                <div class="row">
                    <div class="col-md-3"><strong>Order Date:</strong><br><?php echo htmlspecialchars($po['order_date'] ?? ''); ?></div>
                    <div class="col-md-3"><strong>Expected:</strong><br><?php echo htmlspecialchars($po['expected_date'] ?? ''); ?></div>
                    <div class="col-md-3"><strong>Created By:</strong><br><?php echo htmlspecialchars($po['created_by_name'] ?? '-'); ?></div>
                    <div class="col-md-3"><strong>Received By:</strong><br><?php echo htmlspecialchars($po['received_by_name'] ?? '-'); ?></div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-12"><strong>Notes:</strong><br><?php echo htmlspecialchars($po['notes'] ?? ''); ?></div>
                </div>
            </div>

            <table class="table table-hover table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Product</th>
                        <th>Unit</th>
                        <th>Qty Ordered</th>
                        <th>Qty Received</th>
                        <th>Unit Cost (PHP)</th>
                        <th>Line Total (PHP)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($po['items'])): ?>
                        <?php foreach ($po['items'] as $it): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($it['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($it['unit']); ?></td>
                                <td><?php echo (int)$it['quantity_ordered']; ?></td>
                                <td>
                                    <?php echo (int)$it['quantity_received']; ?>
                                    <?php if ((int)$it['quantity_received'] < (int)$it['quantity_ordered'] && $po['status'] === 'received'): ?>
                                        <span class="badge bg-warning text-dark">Short</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format((float)$it['unit_cost'], 2); ?></td>
                                <td><?php echo number_format((float)$it['quantity_ordered'] * (float)$it['unit_cost'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center">No items.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="table-light">
                        <td colspan="5" class="text-end"><strong>Total</strong></td>
                        <td><strong><?php echo number_format((float)$po['total_amount'], 2); ?></strong></td>
                    </tr>
                </tfoot>
            </table>

            <?php if ($po['status'] === 'ordered' || $po['status'] === 'draft'): ?>
                <div class="d-flex gap-2">
                    <a href="/purchases/<?php echo $po['id']; ?>/receive" class="btn btn-success">Receive Stock</a>
                    <form action="/purchases/<?php echo $po['id']; ?>/cancel" method="POST"
                          onsubmit="return confirm('Cancel this purchase order?');">
                        <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <button type="submit" class="btn btn-outline-danger">Cancel Order</button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="mt-4">
                <a href="/purchases" class="btn btn-secondary">&larr; Back to Purchase Orders</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

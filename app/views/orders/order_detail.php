<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order <?php echo htmlspecialchars($order['order_number']); ?> - AA Pharmacy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .status-pending_review { background-color: #ffc107; }
        .status-approved { background-color: #0d6efd; }
        .status-fulfilled { background-color: #6f42c1; }
        .status-delivered { background-color: #198754; }
        .status-rejected, .status-cancelled { background-color: #6c757d; }
        .total-row td { font-weight: 700; font-size: 1.1rem; border-top: 2px solid #dee2e6; }
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
        <?php if (!empty($_GET['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_GET['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0">Order <?php echo htmlspecialchars($order['order_number']); ?></h2>
                <div class="text-muted">
                    Placed <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?>
                    <span class="badge status-<?php echo htmlspecialchars($order['status']); ?> text-white ms-1">
                        <?php echo htmlspecialchars(str_replace('_', ' ', $order['status'])); ?>
                    </span>
                </div>
            </div>
            <a href="/orders" class="btn btn-outline-primary">&larr; My Orders</a>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card shadow-sm">
                    <div class="card-header"><strong>Items</strong></div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr><th>Item</th><th>Price</th><th>Qty</th><th>Amount</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order['items'] as $it): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($it['product_name']); ?>
                                            <?php if (!empty($it['batch_lot'])): ?>
                                                <div class="small text-muted">Batch <?php echo htmlspecialchars($it['batch_lot']); ?> (exp <?php echo htmlspecialchars($it['batch_expiry']); ?>)</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>PHP <?php echo number_format((float)$it['unit_price'], 2); ?></td>
                                        <td><?php echo (int)$it['quantity']; ?></td>
                                        <td>PHP <?php echo number_format((float)$it['unit_price'] * (int)$it['quantity'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr><td colspan="3" class="text-end">Items subtotal:</td>
                                    <td>PHP <?php echo number_format((float)$order['items_total'], 2); ?></td></tr>
                                <tr><td colspan="3" class="text-end">Delivery fee:</td>
                                    <td>PHP <?php echo number_format((float)$order['delivery_fee'], 2); ?></td></tr>
                                <tr class="total-row"><td colspan="3" class="text-end">Total (COD):</td>
                                    <td>PHP <?php echo number_format((float)$order['total_amount'], 2); ?></td></tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card shadow-sm mb-3">
                    <div class="card-header"><strong>Delivery &amp; Contact</strong></div>
                    <div class="card-body">
                        <div class="mb-1"><strong>Address:</strong> <?php echo htmlspecialchars($order['delivery_address'] ?? '—'); ?></div>
                        <div class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($order['phone'] ?? '—'); ?></div>
                        <?php if (!empty($order['notes'])): ?>
                            <div class="mb-1"><strong>Notes:</strong> <?php echo htmlspecialchars($order['notes']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card shadow-sm mb-3">
                    <div class="card-header"><strong>Progress</strong></div>
                    <div class="card-body">
                        <ul class="mb-0 ps-3">
                            <li><strong>Placed</strong> — <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></li>
                            <?php if (!empty($order['approved_at'])): ?>
                                <li><strong>Approved</strong> by <?php echo htmlspecialchars($order['approved_by_name'] ?? 'staff'); ?>
                                    — <?php echo date('M j, Y g:i A', strtotime($order['approved_at'])); ?></li>
                            <?php endif; ?>
                            <?php if (!empty($order['fulfilled_at'])): ?>
                                <li><strong>Packed/Fulfilled</strong> by <?php echo htmlspecialchars($order['fulfilled_by_name'] ?? 'staff'); ?>
                                    — <?php echo date('M j, Y g:i A', strtotime($order['fulfilled_at'])); ?></li>
                            <?php endif; ?>
                            <?php if ($order['status'] === 'delivered'): ?>
                                <li><strong>Delivered</strong> — <?php echo date('M j, Y g:i A', strtotime($order['updated_at'])); ?></li>
                            <?php elseif ($order['status'] === 'rejected'): ?>
                                <li><strong>Rejected</strong>:
                                    <span class="text-danger"><?php echo htmlspecialchars($order['rejected_note'] ?? 'No reason given.'); ?></span></li>
                            <?php elseif ($order['status'] === 'cancelled'): ?>
                                <li><strong>Cancelled</strong></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <?php if (in_array($order['status'], ['pending_review', 'approved'], true)): ?>
                    <form method="POST" action="/orders/<?php echo (int)$order['id']; ?>/cancel"
                          onsubmit="return confirm('Cancel this order? This cannot be undone.');">
                        <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <button class="btn btn-outline-danger w-100">Cancel Order</button>
                    </form>
                <?php endif; ?>

                <?php if ($order['status'] === 'rejected'): ?>
                    <div class="alert alert-warning small mb-0">
                        Your order was not approved. If you believe this is a mistake, please contact the pharmacy.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

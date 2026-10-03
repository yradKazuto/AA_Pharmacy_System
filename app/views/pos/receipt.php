<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt <?php echo htmlspecialchars($sale['sale_number']); ?> - AA Pharmacy System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .box { max-width: 720px; margin: 0 auto; }
        .receipt { background: #fff; border: 1px solid #dee2e6; border-radius: .5rem; padding: 1.5rem; }
        @media print {
            .no-print { display: none; }
            .box { max-width: 100%; } body { background: #fff; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary no-print">
        <div class="container-fluid">
            <a class="navbar-brand" href="/dashboard">AA Pharmacy System</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="/pos">POS Terminal</a></li>
                    <li class="nav-item"><a class="nav-link" href="/pos/sales">Sales</a></li>
                    <li class="nav-item"><a class="nav-link" href="/logout">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="box">
            <?php if (!empty($_GET['success'])): ?>
                <div class="alert alert-success no-print"><?php echo htmlspecialchars($_GET['success']); ?></div>
            <?php endif; ?>
            <?php if (!empty($_GET['error'])): ?>
                <div class="alert alert-danger no-print"><?php echo htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>

            <div class="receipt">
                <div class="text-center mb-3">
                    <h4 class="mb-0">AA Pharmacy</h4>
                    <small class="text-muted">Zamboanga City</small>
                    <div class="mt-1">
                        <?php if ($sale['status'] === 'voided'): ?>
                            <span class="badge bg-secondary">VOIDED</span>
                        <?php else: ?>
                            <span class="badge bg-success">SALE RECEIPT</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="small mb-3">
                    <div><strong>Receipt #:</strong> <?php echo htmlspecialchars($sale['sale_number']); ?></div>
                    <div><strong>Date:</strong> <?php echo htmlspecialchars($sale['sale_date']); ?> (<?php echo htmlspecialchars($sale['created_at']); ?>)</div>
                    <div><strong>Cashier:</strong> <?php echo htmlspecialchars($sale['sold_by_name'] ?? '-'); ?></div>
                    <div><strong>Customer:</strong> <?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in'); ?></div>
                    <?php if ($sale['status'] === 'voided' && !empty($sale['voided_at'])): ?>
                        <div class="text-danger"><strong>Voided:</strong> <?php echo htmlspecialchars($sale['voided_at']); ?></div>
                    <?php endif; ?>
                </div>

                <table class="table table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th class="text-end">Price</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sale['items'] as $it): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($it['product_name']); ?>
                                    <br><small class="text-muted">Lot <?php echo htmlspecialchars($it['batch_lot'] ?? '-'); ?>
                                    <?php if (!empty($it['batch_expiry'])): ?>(exp <?php echo htmlspecialchars($it['batch_expiry']); ?>)<?php endif; ?></small>
                                </td>
                                <td><?php echo (int)$it['quantity']; ?></td>
                                <td class="text-end"><?php echo number_format((float)$it['unit_price'], 2); ?></td>
                                <td class="text-end"><?php echo number_format((float)$it['line_total'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-light"><td colspan="3" class="text-end">Subtotal</td><td class="text-end"><?php echo number_format((float)$sale['subtotal'], 2); ?></td></tr>
                        <?php if ((float)$sale['discount'] > 0): ?>
                            <tr class="table-light"><td colspan="3" class="text-end text-danger">Discount</td><td class="text-end text-danger">- <?php echo number_format((float)$sale['discount'], 2); ?></td></tr>
                        <?php endif; ?>
                        <tr class="table-light"><td colspan="3" class="text-end"><strong>Total</strong></td><td class="text-end"><strong><?php echo number_format((float)$sale['total_amount'], 2); ?></strong></td></tr>
                        <?php if ($sale['status'] === 'completed'): ?>
                            <tr class="table-light"><td colspan="3" class="text-end">Payment (<?php echo ucfirst(htmlspecialchars($sale['payment_method'])); ?>)</td><td class="text-end"><?php echo number_format((float)$sale['amount_tendered'], 2); ?></td></tr>
                            <?php if ($sale['payment_method'] === 'cash'): ?>
                                <tr class="table-light"><td colspan="3" class="text-end">Change</td><td class="text-end"><?php echo number_format((float)$sale['change_due'], 2); ?></td></tr>
                            <?php endif; ?>
                        <?php endif; ?>
                    </tfoot>
                </table>

                <div class="text-center text-muted small mt-3">Thank you for your purchase!</div>
            </div>

            <div class="d-flex gap-2 mt-3 no-print">
                <a href="/pos" class="btn btn-secondary">New Sale</a>
                <a href="/pos/sales" class="btn btn-outline-secondary">&larr; Back to Sales</a>
                <button class="btn btn-primary" onclick="window.print()">Print Receipt</button>
                <?php if ($sale['status'] === 'completed'): ?>
                    <form action="/sales/<?php echo (int)$sale['id']; ?>/void" method="POST"
                          onsubmit="return confirm('Void this sale and restore its stock?');" class="ms-auto">
                        <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <button type="submit" class="btn btn-outline-danger">Void Sale</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

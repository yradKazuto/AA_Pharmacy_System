<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receive PO <?php echo htmlspecialchars($po['po_number']); ?> - AA Pharmacy System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .box { max-width: 1000px; margin: 0 auto; }
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
                    <h3 class="mb-0">Receive Stock</h3>
                    <small class="text-muted">
                        PO <?php echo htmlspecialchars($po['po_number']); ?> from <?php echo htmlspecialchars($po['supplier_name']); ?>
                    </small>
                </div>
                <a href="/purchases/<?php echo $po['id']; ?>" class="btn btn-secondary">&larr; Back</a>
            </div>

            <?php if (!empty($_GET['error'])): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>

            <form action="/purchases/<?php echo $po['id']; ?>/receive" method="POST">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <table class="table table-bordered align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Product</th>
                            <th style="width:90px">Ordered</th>
                            <th style="width:90px">Already</th>
                            <th style="width:100px">Receive Qty</th>
                            <th style="width:140px">Lot Number</th>
                            <th style="width:140px">Expiry</th>
                            <th style="width:120px">Unit Cost (PHP)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($po['items'] as $it): ?>
                            <?php
                                $remaining = (int)$it['quantity_ordered'] - (int)$it['quantity_received'];
                                $editable = ($remaining > 0 && $po['status'] !== 'received' && $po['status'] !== 'cancelled');
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($it['product_name']); ?></td>
                                <td><?php echo (int)$it['quantity_ordered']; ?></td>
                                <td><?php echo (int)$it['quantity_received']; ?></td>
                                <?php if ($editable): ?>
                                    <td>
                                        <input type="number" name="quantity[]" class="form-control" min="0" max="<?php echo $remaining; ?>"
                                               value="<?php echo $remaining; ?>">
                                        <small class="text-muted">max <?php echo $remaining; ?></small>
                                    </td>
                                    <td><input type="text" name="lot_number[]" class="form-control" maxlength="50"></td>
                                    <td><input type="date" name="expiry_date[]" class="form-control"></td>
                                    <td><input type="number" name="unit_cost[]" step="0.01" min="0" class="form-control" value="<?php echo (float)$it['unit_cost']; ?>"></td>
                                <?php else: ?>
                                    <td colspan="4" class="text-center text-muted">Fully received</td>
                                <?php endif; ?>
                                <input type="hidden" name="item_id[]" value="<?php echo (int)$it['id']; ?>">
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="alert alert-warning">
                    <strong>Receiving whole PO:</strong> this will be applied as one transaction. Every received line
                    creates a new batch (or tops up an existing one for the same product + lot) and logs an
                    inventory movement. You cannot receive this order again afterwards.
                </div>

                <button type="submit" class="btn btn-success">Receive Stock</button>
                <a href="/purchases/<?php echo $po['id']; ?>" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

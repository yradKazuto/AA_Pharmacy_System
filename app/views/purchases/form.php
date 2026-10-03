<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Purchase Order - AA Pharmacy System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .form-container { max-width: 900px; }
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
        <div class="form-container mx-auto">
            <h3 class="mb-4">New Purchase Order</h3>
            <?php if (!empty($suggestedItems)): ?>
                <div class="alert alert-info">
                    Pre-loaded with <strong>reorder suggestions</strong> (low-stock products). Adjust quantities, then choose a supplier and save.
                </div>
            <?php endif; ?>

            <form action="/purchases" method="POST">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="mb-3">
                    <label class="form-label">Supplier *</label>
                    <select name="supplier_id" class="form-select" required>
                        <option value="">-- Select supplier --</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Expected Delivery</label>
                        <input type="date" name="expected_date" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Notes</label>
                        <input type="text" name="notes" class="form-control" maxlength="255">
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label mb-0">Order Items</label>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRow()">+ Add Item</button>
                </div>

                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th style="width:120px">Qty</th>
                            <th style="width:140px">Unit Cost (PHP)</th>
                            <th style="width:60px"></th>
                        </tr>
                    </thead>
                    <tbody id="items-body">
                    </tbody>
                </table>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Create Purchase Order</button>
                    <a href="/purchases" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Product options map for building <select> rows on the client.
        const PRODUCT_OPTIONS = [
            <?php foreach ($products as $p): ?>
                { id: <?php echo (int)$p['id']; ?>,
                  name: <?php echo json_encode($p['name']); ?>,
                  unit: <?php echo json_encode($p['unit']); ?>,
                  price: <?php echo (float)$p['unit_price']; ?> },
            <?php endforeach; ?>
        ];

        function productOptionHtml(selectedId) {
            let opts = '<option value="">-- Select product --</option>';
            PRODUCT_OPTIONS.forEach(p => {
                const unit = p.unit ? ' (' + p.unit + ')' : '';
                opts += '<option value="' + p.id + '"' + (p.id === selectedId ? ' selected' : '') + '>'
                        + p.name.replace(/</g, '&lt;') + unit + '</option>';
            });
            return opts;
        }

        function addRow(productId, qty, unitCost) {
            const tbody = document.getElementById('items-body');
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' +
                    '<select name="product_id[]" class="form-select">' + productOptionHtml(productId || null) + '</select>' +
                '</td>' +
                '<td><input type="number" name="quantity[]" class="form-control" min="1" value="' + (qty || '') + '"></td>' +
                '<td><input type="number" name="unit_cost[]" step="0.01" min="0" class="form-control" value="' + (unitCost || '') + '"></td>' +
                '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest(\'tr\').remove()">X</button></td>';
            tbody.appendChild(tr);
        }

        // Pre-load suggested rows then add one blank editable row.
        <?php if (!empty($suggestedItems)): ?>
            <?php foreach ($suggestedItems as $si): ?>
                addRow(<?php echo (int)$si['product_id']; ?>,
                       <?php echo (int)$si['quantity']; ?>,
                       <?php echo (float)$si['unit_cost']; ?>);
            <?php endforeach; ?>
        <?php endif; ?>
        addRow();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart - AA Pharmacy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body { background-color: #f8f9fa; }</style>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Your Cart</h2>
            <a href="/shop" class="btn btn-outline-primary">&larr; Keep Shopping</a>
        </div>

        <?php if (!empty($_GET['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_GET['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Item</th><th>Price</th><th style="width:110px">Qty</th><th>Amount</th><th></th></tr>
                    </thead>
                    <tbody id="cartBody"></tbody>
                </table>
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <strong>Total:</strong> <span id="cartTotal" class="fs-4">PHP 0.00</span>
                        <span class="text-muted small">(Cash on Delivery)</span>
                    </div>
                    <a href="/checkout" class="btn btn-success btn-lg" id="checkoutBtn">Proceed to Checkout</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Server-rendered product catalog (id => structured) for cart math + display.
        const PRODUCTS = <?php echo json_encode(array_column($products, null, 'id'), JSON_NUMERIC_CHECK | JSON_PARTIAL_OUTPUT_ON_ERROR); ?>;

        function loadCart() {
            try { return JSON.parse(localStorage.getItem('aa_cart') || '{}'); }
            catch (e) { return {}; }
        }
        function saveCart(c) { localStorage.setItem('aa_cart', JSON.stringify(c)); }

        function render() {
            const cart = loadCart();
            const tbody = document.getElementById('cartBody');
            tbody.innerHTML = '';
            let total = 0;
            const ids = Object.keys(cart);
            ids.forEach(function(id) {
                const line = cart[id];
                const p = PRODUCTS[id];
                if (!p) {
                    // Product no longer available -> drop silently.
                    delete cart[id];
                    return;
                }
                const amt = line.qty * Number(p.unit_price);
                total += amt;
                const tr = document.createElement('tr');
                tr.innerHTML =
                    '<td><div class="fw-bold">' + p.name + '</div>' +
                    '<small class="text-muted">' + (p.generic_name || '') + ' ' + p.unit + '</small>' +
                    (p.requires_prescription == 1 ? '<div><span class="badge bg-danger">Rx — pharmacist review</span></div>' : '') +
                    '</td>' +
                    '<td>PHP ' + Number(p.unit_price).toFixed(2) + '</td>' +
                    '<td><input type="number" class="form-control" min="1" max="' + Number(p.total_qty) + '" value="' + line.qty + '" onchange="setQty(\'' + id + '\', this.value)"></td>' +
                    '<td>PHP ' + amt.toFixed(2) + '</td>' +
                    '<td><button class="btn btn-sm btn-outline-danger" onclick="remove(\'' + id + '\')">&times;</button></td>';
                tbody.appendChild(tr);
            });
            if (ids.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Your cart is empty. <a href="/shop">Browse the catalog</a>.</td></tr>';
            }
            document.getElementById('cartTotal').textContent = 'PHP ' + total.toFixed(2);
            document.getElementById('checkoutBtn').style.display = ids.length ? '' : 'none';
            saveCart(cart);
        }

        function setQty(id, val) {
            const cart = loadCart();
            const p = PRODUCTS[id];
            val = parseInt(val, 10) || 1;
            if (val < 1) val = 1;
            if (p) {
                const max = Number(p.total_qty);
                if (val > max) { alert('Only ' + max + ' in stock.'); val = max; }
            }
            cart[id].qty = val;
            saveCart(cart);
            render();
        }

        function remove(id) {
            const cart = loadCart();
            delete cart[id];
            saveCart(cart);
            render();
        }

        render();
    </script>
</body>
</html>

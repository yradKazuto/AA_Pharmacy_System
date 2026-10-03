<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Point of Sale - AA Pharmacy System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .product-card { cursor: pointer; }
        .product-card:hover { border-color: #0d6efd; background: #eef4ff; }
        .out-of-stock { opacity: .5; }
        .cart-qty { width: 70px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="/dashboard">AA Pharmacy System</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="/pos">POS Terminal</a></li>
                    <li class="nav-item"><a class="nav-link" href="/pos/sales">Sales</a></li>
                    <li class="nav-item"><a class="nav-link" href="/inventory">Inventory</a></li>
                    <li class="nav-item"><a class="nav-link" href="/inventory/alerts">Alerts</a></li>
                    <li class="nav-item">
                        <span class="nav-link text-white"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></span>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="/logout">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Point of Sale</h2>
            <a href="/pos/sales" class="btn btn-outline-secondary">View Sales</a>
        </div>

        <?php if (!empty($_GET['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_GET['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <div class="row g-3">
            <!-- Product picker -->
            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-header">
                        <input type="text" id="searchInput" class="form-control"
                               placeholder="Search product by name or generic name..."
                               autocomplete="off">
                    </div>
                    <div class="card-body" style="max-height: 460px; overflow-y: auto;">
                        <div class="row g-2" id="productGrid">
                            <?php foreach ($products as $p): ?>
                                <?php $avail = (int)$p['total_qty']; ?>
                                <div class="col-md-4 product-card <?php echo $avail <= 0 ? 'out-of-stock' : ''; ?>"
                                     data-id="<?php echo $p['id']; ?>"
                                     data-name="<?php echo htmlspecialchars($p['name']); ?>"
                                     data-price="<?php echo (float)$p['unit_price']; ?>"
                                     data-stock="<?php echo $avail; ?>"
                                     onclick="addToCart(this)">
                                    <div class="card h-100">
                                        <div class="card-body p-2">
                                            <div class="fw-bold small"><?php echo htmlspecialchars($p['name']); ?></div>
                                            <div class="small text-muted">PHP <?php echo number_format((float)$p['unit_price'], 2); ?></div>
                                            <div class="small">Stock: <?php echo $avail; ?></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cart -->
            <div class="col-lg-5">
                <form action="/pos/checkout" method="POST" id="checkoutForm">
                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>Cart</strong>
                            <span class="badge bg-secondary" id="cartCount">0</span>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0" id="cartTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Item</th>
                                        <th style="width:70px">Qty</th>
                                        <th>Amt</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="card-body border-top">
                            <div class="row g-2 align-items-end">
                                <div class="col-6">
                                    <label class="form-label small mb-1">Customer (optional)</label>
                                    <input type="text" name="customer_name" class="form-control form-control-sm" placeholder="Walk-in">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1">Payment</label>
                                    <select name="payment_method" id="payMethod" class="form-select form-select-sm" onchange="toggleTendered()">
                                        <?php foreach ($paymentMethods as $m): ?>
                                            <option value="<?php echo $m; ?>"><?php echo ucfirst($m); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6" id="tenderedRow">
                                    <label class="form-label small mb-1">Tendered (PHP)</label>
                                    <input type="number" name="amount_tendered" id="tenderedInput" step="0.01" min="0"
                                           class="form-control form-control-sm" value="0">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1">Discount (PHP)</label>
                                    <input type="number" name="discount" id="discountInput" step="0.01" min="0" value="0"
                                           class="form-control form-control-sm" oninput="recalc()">
                                </div>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between"><span>Subtotal</span><strong id="subtotal">PHP 0.00</strong></div>
                            <div class="d-flex justify-content-between text-danger"><span>Discount</span><strong id="discountAmt">- PHP 0.00</strong></div>
                            <div class="d-flex justify-content-between fs-5"><span>Total</span><strong id="grandTotal">PHP 0.00</strong></div>
                            <div class="d-flex justify-content-between text-success"><span>Change</span><strong id="changeAmt">PHP 0.00</strong></div>
                            <button type="submit" class="btn btn-success btn-lg w-100 mt-3" id="checkoutBtn" disabled>
                                Complete Sale
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Cart stored as an object keyed by product id.
        const cart = {};
        const PRICES = <?php echo json_encode(array_column($products, 'unit_price', 'id'), JSON_NUMERIC_CHECK); ?>;
        const NAMES = <?php echo json_encode(array_column($products, 'name', 'id')); ?>;

        function fmt(n) { return 'PHP ' + Number(n).toFixed(2); }

        function refreshProducts(results) {
            const grid = document.getElementById('productGrid');
            grid.innerHTML = '';
            results.forEach(function(p) {
                const avail = Number(p.total_qty || 0);
                const div = document.createElement('div');
                div.className = 'col-md-4 product-card ' + (avail <= 0 ? 'out-of-stock' : '');
                div.dataset.id = p.id;
                div.dataset.name = p.name;
                div.dataset.price = p.unit_price;
                div.dataset.stock = avail;
                div.onclick = function() { addToCart(div); };
                div.innerHTML =
                    '<div class="card h-100"><div class="card-body p-2">' +
                    '<div class="fw-bold small">' + p.name + '</div>' +
                    '<div class="small text-muted">PHP ' + Number(p.unit_price).toFixed(2) + '</div>' +
                    '<div class="small">Stock: ' + avail + '</div>' +
                    '</div></div>';
                grid.appendChild(div);
            });
        }

        function addToCart(el, forceQty) {
            const id = el.dataset.id;
            const stock = Number(el.dataset.stock);
            const current = cart[id] ? cart[id].qty : 0;
            if (current + 1 > stock) { alert('Not enough stock.'); return; }
            if (!cart[id]) {
                cart[id] = { id: id, name: el.dataset.name, price: Number(el.dataset.price), qty: forceQty || 1, stock: stock };
            } else {
                cart[id].qty = (forceQty) ? forceQty : cart[id].qty + 1;
            }
            renderCart();
        }

        function renderCart() {
            const tbody = document.querySelector('#cartTable tbody');
            tbody.innerHTML = '';
            let count = 0;
            Object.values(cart).forEach(function(line) {
                count += line.qty;
                const tr = document.createElement('tr');
                const total = line.qty * line.price;
                tr.innerHTML =
                    '<td>' + line.name + '</td>' +
                    '<td><input type="number" class="form-control form-control-sm cart-qty" min="1" max="' + line.stock +
                    '" value="' + line.qty + '" onchange="setQty(\'' + line.id + '\', this.value)"></td>' +
                    '<td>' + fmt(total) + '</td>' +
                    '<td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLine(\'' + line.id + '\')">&times;</button></td>';
                tbody.appendChild(tr);
            });
            document.getElementById('cartCount').textContent = count;
            recalc();
        }

        function setQty(id, val) {
            val = parseInt(val, 10) || 1;
            if (val < 1) val = 1;
            if (val > cart[id].stock) { alert('Not enough stock.'); val = cart[id].stock; }
            cart[id].qty = val;
            renderCart();
        }

        function removeLine(id) { delete cart[id]; renderCart(); }

        function recalc() {
            let subtotal = 0;
            Object.values(cart).forEach(function(line) { subtotal += line.qty * line.price; });
            const discount = Math.max(0, Number(document.getElementById('discountInput').value) || 0);
            const total = Math.max(0, subtotal - discount);
            const tendered = Number(document.getElementById('tenderedInput').value) || 0;
            const change = document.getElementById('payMethod').value === 'cash' ? Math.max(0, tendered - total) : 0;

            document.getElementById('subtotal').textContent = fmt(subtotal);
            document.getElementById('discountAmt').textContent = '- ' + fmt(discount);
            document.getElementById('grandTotal').textContent = fmt(total);
            document.getElementById('changeAmt').textContent = fmt(change);
            document.getElementById('checkoutBtn').disabled = subtotal <= 0;
            document.getElementById('checkoutBtn').textContent =
                (document.getElementById('payMethod').value === 'cash')
                    ? 'Complete Sale — ' + fmt(total)
                    : ('Charge ' + fmt(total));
        }

        function toggleTendered() {
            const isCash = document.getElementById('payMethod').value === 'cash';
            document.getElementById('tenderedRow').style.display = isCash ? '' : 'none';
            recalc();
        }

        // Form submit: serialize cart lines.
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const ids = Object.keys(cart);
            if (ids.length === 0) { e.preventDefault(); return; }
            ids.forEach(function(id) {
                const hiddenId = document.createElement('input');
                hiddenId.type = 'hidden'; hiddenId.name = 'product_id[]'; hiddenId.value = id;
                const hiddenQty = document.createElement('input');
                hiddenQty.type = 'hidden'; hiddenQty.name = 'quantity[]'; hiddenQty.value = cart[id].qty;
                this.appendChild(hiddenId); this.appendChild(hiddenQty);
            }, this);
        });

        // Live search via Fetch API.
        let timer = null;
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(timer);
            const q = this.value.trim();
            timer = setTimeout(function() {
                if (!q) {
                    // restore original grid — simplest: reload grid from initially rendered data.
                    location.reload();
                    return;
                }
                fetch('/pos/search?q=' + encodeURIComponent(q))
                    .then(function(r) { return r.json(); })
                    .then(function(data) { if (data.ok) refreshProducts(data.products); });
            }, 250);
        });

        toggleTendered();
    </script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - AA Pharmacy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .total-row td { font-weight: 700; font-size: 1.1rem; border-top: 2px solid #dee2e6; }
        .rx-note { font-size: 0.85rem; color: #dc3545; }
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
                    <li class="nav-item"><a class="nav-link" href="/orders">My Orders</a></li>
                    <li class="nav-item"><a class="nav-link" href="/logout">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2 class="mb-4">Checkout</h2>

        <?php if (!empty($_GET['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <form method="POST" action="/checkout" id="checkoutForm" novalidate>
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="row g-4">
                <!-- Left: order summary -->
                <div class="col-lg-7">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header"><strong>Order Summary</strong></div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr><th>Item</th><th>Price</th><th>Qty</th><th>Amount</th></tr>
                                </thead>
                                <tbody id="cartRows"></tbody>
                                <tfoot>
                                    <tr class="total-row">
                                        <td colspan="3" class="text-end">Total:</td>
                                        <td id="cartTotal">PHP 0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                            <div class="card-body py-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="codAgree" required>
                                    <label class="form-check-label small" for="codAgree">
                                        I understand payment is <strong>Cash on Delivery (COD)</strong>.
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: delivery details -->
                <div class="col-lg-5">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header"><strong>Delivery Details</strong></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label" for="delivery_address">Delivery Address <span class="text-danger">*</span></label>
                                <textarea name="delivery_address" id="delivery_address" class="form-control" rows="3"
                                    placeholder="Unit/House #, Street, Barangay, City" required></textarea>
                                <div class="invalid-feedback">Please enter your delivery address.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="phone">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" name="phone" id="phone" class="form-control"
                                    placeholder="09XX XXX XXXX" required>
                                <div class="invalid-feedback">Please enter a valid phone number.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="notes">Notes <span class="text-muted">(optional)</span></label>
                                <textarea name="notes" id="notes" class="form-control" rows="2"
                                    placeholder="E.g., leave at gate, contact me first..."></textarea>
                            </div>
                            <div class="alert alert-info small mb-0">
                                <strong>Prescription items:</strong> A pharmacist will review your order before approval.
                                You may be asked to present a valid prescription upon delivery.
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success btn-lg w-100" id="placeBtn" disabled>
                        Place Order
                    </button>
                    <p class="text-muted small text-center mt-2 mb-0">Cash on Delivery &middot; No payment needed now</p>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const PRODUCTS = <?php echo json_encode(array_column($products, null, 'id'), JSON_NUMERIC_CHECK | JSON_PARTIAL_OUTPUT_ON_ERROR); ?>;

        function loadCart() {
            try { return JSON.parse(localStorage.getItem('aa_cart') || '{}'); }
            catch (e) { return {}; }
        }

        function render() {
            const cart = loadCart();
            const tbody = document.getElementById('cartRows');
            tbody.innerHTML = '';
            let total = 0;
            let hasItems = false;

            Object.keys(cart).forEach(function(id) {
                const line = cart[id];
                const p = PRODUCTS[id];
                if (!p) { delete cart[id]; localStorage.setItem('aa_cart', JSON.stringify(cart)); return; }
                hasItems = true;
                const amt = line.qty * Number(p.unit_price);
                total += amt;
                const rx = p.requires_prescription == 1;
                const tr = document.createElement('tr');
                tr.innerHTML =
                    '<td><div class="fw-semibold">' + p.name + '</div>' +
                    (rx ? '<div class="rx-note">Rx — requires pharmacist review</div>' : '') +
                    '</td>' +
                    '<td>PHP ' + Number(p.unit_price).toFixed(2) + '</td>' +
                    '<td>' + line.qty + '</td>' +
                    '<td>PHP ' + amt.toFixed(2) + '</td>';
                tbody.appendChild(tr);
            });

            if (!hasItems) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">Your cart is empty.</td></tr>';
                document.getElementById('placeBtn').disabled = true;
            } else {
                document.getElementById('placeBtn').disabled = false;
            }

            document.getElementById('cartTotal').textContent = 'PHP ' + total.toFixed(2);
            document.getElementById('placeBtn').disabled = !hasItems || !document.getElementById('codAgree').checked;
        }

        function syncForm() {
            // Build hidden inputs so POST carries the cart.
            const cart = loadCart();
            const form = document.getElementById('checkoutForm');

            // Remove stale inputs
            form.querySelectorAll('.cart-item').forEach(el => el.remove());

            Object.keys(cart).forEach(function(id) {
                const p = PRODUCTS[id];
                if (!p) return;

                ['product_id', 'quantity'].forEach(function(name) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name + '[]';
                    input.value = name === 'product_id' ? id : cart[id].qty;
                    input.className = 'cart-item';
                    form.appendChild(input);
                });
            });
        }

        // Bootstrap validation
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            syncForm();
            if (!this.checkValidity()) {
                e.preventDefault();
                this.classList.add('was-validated');
            }
        });

        document.getElementById('codAgree').addEventListener('change', function() {
            document.getElementById('placeBtn').disabled = !this.checked || Object.keys(loadCart()).length === 0;
        });

        render();
    </script>
</body>
</html>

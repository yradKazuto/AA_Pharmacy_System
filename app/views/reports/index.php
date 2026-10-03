<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - AA Pharmacy System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body { background-color: #f8f9fa; }</style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="/">AA Pharmacy System</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="/">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="/reports">Reports</a></li>
                    <li class="nav-item"><a class="nav-link" href="/logout">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Reports</h2>
        <p class="text-muted">All figures are computed from actual transaction and inventory data.</p>

        <div class="row g-4 mt-1">
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">Sales Report</h5>
                        <p class="card-text">Sales count, revenue, discounts, and per-product totals over a date range.</p>
                        <a href="/reports/sales" class="btn btn-primary">Open Sales Report</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">Inventory Valuation</h5>
                        <p class="card-text">Current stock value per product (qty × cost) from valid batches, plus the total portfolio value.</p>
                        <a href="/reports/valuation" class="btn btn-primary">Open Valuation</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">Stock Movement Log</h5>
                        <p class="card-text">Every inventory movement (receive, sale, void, waste, adjustment), filterable by type and date.</p>
                        <a href="/reports/movements" class="btn btn-primary">Open Movement Log</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

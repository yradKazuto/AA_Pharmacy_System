<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Review - AA Pharmacy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .status-pending_review { background-color: #ffc107; }
        .status-approved { background-color: #0d6efd; }
        .status-fulfilled { background-color: #6f42c1; }
        .status-delivered { background-color: #198754; }
        .status-rejected, .status-cancelled { background-color: #6c757d; }
        .rx-badge { font-size: 0.7rem; vertical-align: middle; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/dashboard">AA Pharmacy</a>
            <span class="navbar-text me-auto ms-3">Order Review &amp; Fulfillment</span>
            <a class="nav-link text-white" href="/dashboard">Dashboard</a>
            <a class="nav-link text-white" href="/logout">Logout</a>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Order Review &amp; Fulfillment</h2>
        <p class="text-muted">Review, approve, fulfill and deliver online customer orders.</p>

        <?php if (!empty($_GET['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_GET['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <?php if (empty($orders)): ?>
            <div class="text-center text-muted py-5">No orders awaiting action right now.</div>
        <?php else: ?>
            <?php foreach ($orders as $o): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <span class="fw-bold"><?php echo htmlspecialchars($o['order_number']); ?></span>
                            <span class="text-muted ms-2"><?php echo htmlspecialchars($o['customer_username'] ?? 'customer'); ?></span>
                            <span class="badge status-<?php echo htmlspecialchars($o['status']); ?> text-white ms-2">
                                <?php echo htmlspecialchars(str_replace('_', ' ', $o['status'])); ?>
                            </span>
                        </div>
                        <div class="text-muted small">
                            <?php echo date('M j, Y g:i A', strtotime($o['created_at'])); ?> &middot;
                            PHP <?php echo number_format((float)$o['total_amount'], 2); ?> COD
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-lg-7">
                                <h6 class="text-muted">
                                    Items
                                    <?php if ((int)$o['requires_review'] === 1): ?>
                                        <span class="badge bg-danger rx-badge">Rx — requires pharmacist review</span>
                                    <?php endif; ?>
                                </h6>
                                <ul class="list-unstyled mb-0">
                                    <?php foreach ($o['items'] as $it): ?>
                                        <li>
                                            <?php echo htmlspecialchars($it['product_name']); ?>
                                            &times; <?php echo (int)$it['quantity']; ?>
                                            <span class="text-muted small">/ PHP <?php echo number_format((float)$it['unit_price'], 2); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="col-lg-5">
                                <div class="small">
                                    <div><strong>Deliver to:</strong> <?php echo htmlspecialchars($o['delivery_address'] ?? '—'); ?></div>
                                    <div><strong>Phone:</strong> <?php echo htmlspecialchars($o['phone'] ?? '—'); ?></div>
                                    <?php if (!empty($o['notes'])): ?>
                                        <div><strong>Notes:</strong> <?php echo htmlspecialchars($o['notes']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($o['rejected_note'])): ?>
                                        <div class="text-danger"><strong>Reject note:</strong> <?php echo htmlspecialchars($o['rejected_note']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="d-flex gap-2 mt-3 flex-wrap">
                                    <?php if ($o['status'] === 'pending_review'): ?>
                                        <form method="POST" action="/orders/<?php echo (int)$o['id']; ?>/approve">
                                            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <button class="btn btn-sm btn-success">Approve</button>
                                        </form>
                                        <button class="btn btn-sm btn-outline-danger"
                                                data-bs-toggle="collapse" data-bs-target="#reject<?php echo (int)$o['id']; ?>">Reject</button>
                                        <div class="collapse w-100 pt-2" id="reject<?php echo (int)$o['id']; ?>">
                                            <form method="POST" action="/orders/<?php echo (int)$o['id']; ?>/reject" class="d-flex gap-2">
                                                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="text" name="reject_note" class="form-control form-control-sm"
                                                       placeholder="Reason (optional)" maxlength="255">
                                                <button class="btn btn-sm btn-danger">Confirm Reject</button>
                                            </form>
                                        </div>
                                    <?php elseif ($o['status'] === 'approved'): ?>
                                        <form method="POST" action="/orders/<?php echo (int)$o['id']; ?>/fulfill"
                                              onsubmit="return confirm('Fulfill this order now? Stock will be deducted (FEFO) and a sale recorded.');">
                                            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <button class="btn btn-sm btn-primary">Fulfill (deduct stock)</button>
                                        </form>
                                    <?php elseif ($o['status'] === 'fulfilled'): ?>
                                        <form method="POST" action="/orders/<?php echo (int)$o['id']; ?>/deliver">
                                            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <button class="btn btn-sm btn-outline-primary">Mark Delivered</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product ? 'Edit Product' : 'Add New Product'; ?> - AA Pharmacy System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .form-card { max-width: 600px; margin: 50px auto; padding: 30px; background: white; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="/dashboard">AA Pharmacy System</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="/products">Products</a></li>
                    <li class="nav-item"><span class="nav-link text-white"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></span></li>
                    <li class="nav-item"><a class="nav-link" href="/logout">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="form-card">
            <h2><?php echo $product ? 'Edit Product' : 'Add New Product'; ?></h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger"><ul class="mb-0">
                    <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
                </ul></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="<?php echo $product ? "/products/{$product['id']}" : '/products'; ?>" method="POST">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <?php if ($product): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>

                <div class="mb-3">
                    <label class="form-label">Product Name *</label>
                    <input type="text" name="name" class="form-control" maxlength="100"
                           value="<?php echo htmlspecialchars($product['name'] ?? $_POST['name'] ?? ''); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Generic Name</label>
                    <input type="text" name="generic_name" class="form-control" maxlength="100"
                           value="<?php echo htmlspecialchars($product['generic_name'] ?? $_POST['generic_name'] ?? ''); ?>">
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Category</label>
                        <input type="text" name="category" class="form-control" maxlength="50"
                               value="<?php echo htmlspecialchars($product['category'] ?? $_POST['category'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Unit *</label>
                        <select name="unit" class="form-select" required>
                            <?php
                                $units = ['pc', 'tablet', 'capsule', 'bottle', 'box', 'sachet', 'inhaler', 'tube'];
                                $currentUnit = $product['unit'] ?? $_POST['unit'] ?? 'pc';
                                foreach ($units as $u) {
                                    $sel = ($currentUnit === $u) ? 'selected' : '';
                                    echo "<option value=\"{$u}\" {$sel}>" . htmlspecialchars($u) . "</option>";
                                }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Unit Price (PHP) *</label>
                        <input type="number" step="0.01" min="0" name="unit_price" class="form-control"
                               value="<?php echo htmlspecialchars(($product['unit_price'] ?? $_POST['unit_price'] ?? 0)); ?>" required>
                    </div>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="rx" name="requires_prescription" value="1"
                           <?php echo (!empty($product['requires_prescription']) || !empty($_POST['requires_prescription'])) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="rx">Requires prescription (Rx)</label>
                </div>

                <div class="d-grid gap-2">
                    <?php if ($product): ?><a href="/products" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
                    <button type="submit" class="btn btn-primary"><?php echo $product ? 'Update Product' : 'Create Product'; ?></button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

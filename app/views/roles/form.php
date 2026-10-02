<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $role ? 'Edit Role' : 'Add New Role'; ?> - AA Pharmacy System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .form-card {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="/dashboard">AA Pharmacy System</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link text-white">Logged in as:
                            <strong><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>
                            (<?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?>)</strong>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/logout">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="form-card">
            <h2><?php echo $role ? 'Edit Role' : 'Add New Role'; ?></h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="<?php echo $role ? "/roles/{$role['id']}" : '/roles'; ?>"
                  method="POST">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <?php if ($role): ?>
                    <input type="hidden" name="_method" value="PUT">
                <?php endif; ?>

                <div class="mb-3">
                    <label for="name" class="form-label">Role Name *</label>
                    <input type="text" class="form-control" id="name" name="name"
                           value="<?php echo htmlspecialchars($role['name'] ?? ''); ?>"
                           minlength="3" maxlength="30" required>
                    <div class="form-text">Role name (admin, pharmacist, cashier, customer, etc.)</div>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description"
                              rows="3"><?php echo htmlspecialchars($role['description'] ?? ''); ?></textarea>
                    <div class="form-text">Brief description of role permissions and responsibilities.</div>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active"
                           value="1" <?php echo (!empty($role['is_active']) && $role['is_active'] == 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="is_active">Role is Active</label>
                </div>

                <div class="d-grid gap-2">
                    <?php if ($role): ?>
                        <a href="/roles" class="btn btn-outline-secondary">Cancel</a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $role ? 'Update Role' : 'Create Role'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
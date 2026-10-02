<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $user ? 'Edit User' : 'Add New User'; ?> - AA Pharmacy System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .form-card {
            max-width: 600px;
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
            <h2><?php echo $user ? 'Edit User' : 'Add New User'; ?></h2>

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

            <form action="<?php echo $user ? "/users/{$user['id']}" : '/users'; ?>"
                  method="POST">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <?php if ($user): ?>
                    <input type="hidden" name="_method" value="PUT">
                <?php endif; ?>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="company_id_number" class="form-label">Company ID Number *</label>
                        <input type="text" class="form-control" id="company_id_number" name="company_id_number"
                               value="<?php echo htmlspecialchars($user['company_id_number'] ?? ''); ?>"
                               maxlength="20" required>
                        <div class="form-text">Unique identifier for audit tracking.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="username" class="form-label">Username *</label>
                        <input type="text" class="form-control" id="username" name="username"
                               value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>"
                               minlength="3" maxlength="50" required>
                        <div class="form-text">Used for login. Must be at least 3 characters.</div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email Address *</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                               maxlength="100" required>
                    </div>
                    <div class="col-md-6">
                        <label for="role_id" class="form-label">Role *</label>
                        <select class="form-select" id="role_id" name="role_id" required>
                            <option value="">-- Select Role --</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>"
                                        <?php echo (isset($user['role_id']) && $user['role_id'] == $role['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucfirst($role['name'])); ?>
                                    <?php if (!empty($role['description'])): ?>
                                        <small class="text-muted d-block"> - <?php echo htmlspecialchars($role['description']); ?></small>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <?php if (!$user): ?>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="password" name="password"
                                   minlength="8" required>
                            <div class="form-text">Must be at least 8 characters.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label">Confirm Password *</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                   minlength="8" required>
                            <div class="form-text">Enter the same password as above for verification.</div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">New Password (leave blank to keep current)</label>
                            <input type="password" class="form-control" id="password" name="password"
                                   minlength="8">
                            <div class="form-text">If filled, must be at least 8 characters.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                   minlength="8">
                            <div class="form-text">Enter the same new password as above for verification.</div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="first_name" class="form-label">First Name *</label>
                        <input type="text" class="form-control" id="first_name" name="first_name"
                               value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="last_name" class="form-label">Last Name *</label>
                        <input type="text" class="form-control" id="last_name" name="last_name"
                               value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                    </div>
                </div>

                <?php if ($user): ?>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active"
                               value="1" <?php echo (!empty($user['is_active']) && $user['is_active'] == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">Account is Active</label>
                    </div>
                <?php endif; ?>

                <div class="d-grid gap-2">
                    <?php if ($user): ?>
                        <a href="/users" class="btn btn-outline-secondary">Cancel</a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $user ? 'Update User' : 'Create User'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
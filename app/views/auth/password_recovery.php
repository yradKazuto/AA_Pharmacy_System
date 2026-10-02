<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Recovery - AA Pharmacy System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .recovery-card {
            max-width: 500px;
            margin: 100px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .temp-password {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 18px;
            letter-spacing: 1px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="recovery-card">
            <div class="logo">
                <h2>AA Pharmacy System</h2>
                <p class="text-muted">Password Recovery (Admin Only)</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <strong><?php echo htmlspecialchars($message); ?></strong>
                </div>
                <div class="temp-password">
                    Temporary Password: <strong><?php echo htmlspecialchars($temp_password); ?></strong>
                </div>
                <p class="text-muted small">
                    Please provide this temporary password to the user securely.
                    The user will be prompted to change it on first login.
                </p>
            <?php endif; ?>

            <form action="/password-recovery" method="POST">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <h4>Select User to Reset Password</h4>
                <div class="mb-3">
                    <select class="form-select" name="user_id" required>
                        <option value="">-- Select User --</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo (isset($_POST['user_id']) && $_POST['user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username'] . ' (' . $user['email'] . ') - [' . $user['role_name'] . ']'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-warning btn-lg">Generate Temporary Password</button>
                </div>
            </form>

            <div class="text-center mt-4">
                <p class="small text-muted">
                    <a href="/login">Back to Login</a>
                </p>
                <p class="small text-muted">
                    <em>Note: Only administrators can initiate password recovery.</em>
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
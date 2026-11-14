<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } elseif (attempt_login($username, $password)) {
        flash('success', 'Welcome back, ' . current_user()['full_name'] . '!');
        redirect('dashboard.php');
    } else {
        $error = 'Invalid username/email or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In | AgriTrack Agriculture Information System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#15803d">
    <meta name="mobile-web-app-capable" content="yes">
</head>
<body class="login-body">
<div class="login-card">
    <div class="text-center mb-4">
        <div class="brand-icon-box mx-auto mb-3" style="width: 52px; height: 52px; font-size: 1.6rem;">
            <i class="bi bi-flower1"></i>
        </div>
        <h4 class="fw-bold mb-1" style="color: #0f2316;">AgriTrack</h4>
        <div class="text-muted small">Cebu Technological University · AP3 Midterm Project</div>
        <div class="badge bg-success-subtle text-success border border-success-subtle mt-2 px-3 py-1">
            Agriculture Information System
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= h($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php foreach (get_flashes() as $f): ?>
        <div class="alert alert-<?= $f['type'] === 'error' ? 'danger' : ($f['type'] === 'warning' ? 'warning' : 'success') ?> py-2 small" role="alert">
            <?= h($f['message']) ?>
        </div>
    <?php endforeach; ?>

    <form method="post" action="login.php" class="needs-validation">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

        <div class="mb-3">
            <label for="username" class="form-label small fw-semibold text-secondary">Username or Email</label>
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                <input type="text" class="form-control" id="username" name="username" placeholder="e.g. admin or staff" required autofocus>
            </div>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label small fw-semibold text-secondary">Password</label>
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required>
            </div>
        </div>

        <button type="submit" class="btn btn-agri w-100 py-2 fw-semibold shadow-sm mt-2">
            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to System
        </button>
    </form>

    <div class="mt-4 pt-3 border-top">
        <div class="text-muted small fw-semibold mb-2 text-center">Quick Demo Accounts (1-Click Fill):</div>
        <div class="row g-2">
            <div class="col-6">
                <div class="demo-account-pill text-center" onclick="fillCredentials('admin', 'admin123')">
                    <div class="fw-bold text-dark"><i class="bi bi-shield-lock-fill text-danger me-1"></i>Admin</div>
                    <div class="text-muted" style="font-size: 0.72rem;">admin / admin123</div>
                </div>
            </div>
            <div class="col-6">
                <div class="demo-account-pill text-center" onclick="fillCredentials('staff', 'staff123')">
                    <div class="fw-bold text-dark"><i class="bi bi-person-workspace text-success me-1"></i>Staff</div>
                    <div class="text-muted" style="font-size: 0.72rem;">staff / staff123</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function fillCredentials(user, pass) {
    document.getElementById('username').value = user;
    document.getElementById('password').value = pass;
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Redirect if already logged in
if (Auth::check()) {
    redirect(BASE_URL . '/admin/');
}

$error = '';

if (isPost()) {
    // Validate CSRF
    if (!verifyCsrf()) {
        $error = 'Request tidak valid. Silakan coba lagi.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Username dan password harus diisi.';
        } else {
            $result = Auth::login($username, $password);
            if ($result['success']) {
                $redirect = $_GET['redirect'] ?? '';
                // Security: only allow relative redirects
                if ($redirect && strpos($redirect, '/') === 0 && strpos($redirect, '//') !== 0) {
                    redirect(BASE_URL . $redirect);
                }
                redirect(BASE_URL . '/admin/');
            } else {
                $error = $result['message'];
            }
        }
    }
}

$appName = getSetting('app_name', APP_NAME);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login &mdash; <?= e($appName) ?></title>
    <?php if ($favicon = getSetting('app_favicon')): ?>
    <link rel="icon" href="<?= e($favicon) ?>">
    <?php else: ?>
    <link rel="icon" href="<?= BASE_URL ?>/assets/images/favicon.png" type="image/png">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #1e2a3b 0%, #0d6efd 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .login-card {
            background: #fff;
            border-radius: 1.25rem;
            box-shadow: 0 16px 48px rgba(0,0,0,0.2);
            overflow: hidden;
            width: 100%;
            max-width: 420px;
        }
        .login-header {
            background: linear-gradient(135deg, #1e2a3b, #0d6efd);
            padding: 2.5rem 2rem;
            text-align: center;
            color: white;
        }
        .login-body { padding: 2rem; }
        .login-icon { font-size: 3rem; opacity: 0.9; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="login-header">
        <div class="login-icon">
            <i class="bi bi-building-fill-check"></i>
        </div>
        <h4 class="mt-3 mb-1 fw-bold"><?= e($appName) ?></h4>
        <p class="mb-0 opacity-75 small">Panel Admin &amp; Manajemen</p>
    </div>
    <div class="login-body">
        <h5 class="fw-bold mb-4 text-center">Masuk ke Sistem</h5>

        <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 py-2">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span><?= e($error) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($flash = getFlash()): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'info' ?> py-2">
            <?= e($flash['message']) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm" novalidate>
            <?= csrfField() ?>

            <div class="mb-3">
                <label for="username" class="form-label">Username / Email</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-person"></i>
                    </span>
                    <input type="text" class="form-control" id="username" name="username"
                           placeholder="Masukkan username atau email"
                           value="<?= e($_POST['username'] ?? '') ?>"
                           required autofocus autocomplete="username">
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-lock"></i>
                    </span>
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="Masukkan password"
                           required autocomplete="current-password">
                    <button class="btn btn-outline-secondary" type="button" id="togglePass">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg fw-semibold" id="submitBtn">
                    <i class="bi bi-box-arrow-in-right me-2"></i> Masuk
                </button>
            </div>
        </form>

        <div class="text-center mt-3">
            <a href="<?= BASE_URL ?>" class="text-muted small text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Halaman Booking
            </a>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle password visibility
document.getElementById('togglePass')?.addEventListener('click', function() {
    const pwd = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        pwd.type = 'password';
        icon.className = 'bi bi-eye';
    }
});

// Prevent double submit
document.getElementById('loginForm')?.addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Memproses...';
});
</script>
</body>
</html>

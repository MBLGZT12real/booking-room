<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>403 - Akses Ditolak</title>
<?php if ($favicon = getSetting('app_favicon')): ?>
<link rel="icon" href="<?= e($favicon) ?>">
<?php else: ?>
<link rel="icon" href="<?= BASE_URL ?>/assets/images/favicon.png" type="image/png">
<?php endif; ?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh">
<div class="text-center">
    <div class="display-1 text-danger fw-bold">403</div>
    <h3 class="mt-2">Akses Ditolak</h3>
    <p class="text-muted">Anda tidak memiliki izin untuk mengakses halaman ini.</p>
    <a href="<?= BASE_URL ?>/" class="btn btn-primary">
        <i class="bi bi-house me-1"></i> Kembali ke Dashboard
    </a>
</div>
</body>
</html>

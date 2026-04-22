<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Dashboard') ?> &mdash; <?= e(getSetting('app_name', APP_NAME)) ?></title>

    <!-- Favicon -->
    <?php if ($favicon = getSetting('app_favicon')): ?>
    <link rel="icon" href="<?= e($favicon) ?>">
    <?php else: ?>
    <link rel="icon" href="<?= BASE_URL ?>/assets/images/favicon.png" type="image/png">
    <?php endif; ?>

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap-icons.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/datatables.min.css">
    <!-- Select2 -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/select2.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/select2-bootstrap-5-theme.min.css">
    <!-- Custom Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

    <?php if (isset($extraCss)) echo $extraCss; ?>

    <style>
        :root {
            --bs-primary: <?= e(getSetting('app_color_primary', '#0d6efd')) ?>;
            --sidebar-width: 260px;
        }
    </style>
</head>
<body class="admin-body">

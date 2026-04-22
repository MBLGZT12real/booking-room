<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requirePermission('settings.manage');

$pageTitle = 'Template Email';
$db = db();

$templates = $db->fetchAll(
    "SELECT * FROM email_templates ORDER BY id ASC"
);

$typeLabels = [
    'booking_confirmation' => ['label' => 'Konfirmasi Booking Baru', 'icon' => 'bi-calendar-plus', 'class' => 'primary'],
    'booking_approved'     => ['label' => 'Booking Disetujui',       'icon' => 'bi-check-circle',  'class' => 'success'],
    'booking_rejected'     => ['label' => 'Booking Ditolak',         'icon' => 'bi-x-circle',      'class' => 'danger'],
    'checkin_confirmation' => ['label' => 'Konfirmasi Check-in',     'icon' => 'bi-box-arrow-in-right', 'class' => 'info'],
];
?>
<?php include dirname(__DIR__) . '/layout/header.php'; ?>
<div class="admin-wrapper">
<?php include dirname(__DIR__) . '/layout/sidebar.php'; ?>
<div class="main-content">
<?php include dirname(__DIR__) . '/layout/navbar.php'; ?>
<div class="page-content">

    <div class="page-header d-flex align-items-center justify-content-between">
        <div>
            <h5><i class="bi bi-envelope-paper me-2 text-primary"></i><?= e($pageTitle) ?></h5>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/">Dashboard</a></li>
                    <li class="breadcrumb-item active">Template Email</li>
                </ol>
            </nav>
        </div>
    </div>

    <?= showFlash() ?>

    <div class="alert alert-info d-flex gap-2 align-items-start">
        <i class="bi bi-info-circle-fill mt-1 flex-shrink-0"></i>
        <div class="small">
            <strong>Cara menggunakan variabel:</strong> Gunakan format <code>&#123;&#123;nama_variabel&#125;&#125;</code> di subject maupun body email.
            Setiap template memiliki variabel berbeda — lihat daftar variabel di halaman edit masing-masing template.
        </div>
    </div>

    <div class="row g-3">
        <?php foreach ($templates as $tpl): ?>
        <?php $info = $typeLabels[$tpl['type']] ?? ['label' => $tpl['type'], 'icon' => 'bi-envelope', 'class' => 'secondary']; ?>
        <div class="col-12 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="p-2 rounded bg-<?= $info['class'] ?>-subtle text-<?= $info['class'] ?> fs-4">
                            <i class="bi <?= $info['icon'] ?>"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0"><?= e($tpl['name']) ?></h6>
                            <span class="badge bg-<?= $info['class'] ?>-subtle text-<?= $info['class'] ?> small">
                                <?= e($info['label']) ?>
                            </span>
                        </div>
                        <div class="ms-auto">
                            <?php if ($tpl['is_active']): ?>
                            <span class="badge bg-success">Aktif</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Nonaktif</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="small text-muted mb-2">
                        <strong>Subject:</strong> <?= e($tpl['subject']) ?>
                    </div>
                    <?php if ($tpl['updated_at']): ?>
                    <div class="small text-muted">
                        <i class="bi bi-clock me-1"></i>Diperbarui: <?= formatDatetimeId($tpl['updated_at']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-transparent d-flex gap-2">
                    <a href="<?= BASE_URL ?>/admin/email-templates/edit.php?id=<?= $tpl['id'] ?>"
                       class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-pencil me-1"></i> Edit Template
                    </a>
                    <a href="<?= BASE_URL ?>/admin/email-templates/preview.php?id=<?= $tpl['id'] ?>"
                       class="btn btn-sm btn-outline-secondary" target="_blank">
                        <i class="bi bi-eye me-1"></i> Preview
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (empty($templates)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5 text-muted">
                    <i class="bi bi-envelope-x display-4 d-block mb-3 opacity-25"></i>
                    <p>Belum ada template email.</p>
                    <p class="small">Jalankan file <code>database/migration_email_templates.sql</code> terlebih dahulu.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>
<?php include dirname(__DIR__) . '/layout/footer.php'; ?>

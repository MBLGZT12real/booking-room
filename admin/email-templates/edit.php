<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requirePermission('settings.manage');

$db  = db();
$id  = (int) ($_GET['id'] ?? 0);
$tpl = $db->fetch("SELECT * FROM email_templates WHERE id = ?", [$id]);

if (!$tpl) {
    flash('error', 'Template tidak ditemukan.');
    redirect(BASE_URL . '/admin/email-templates/');
}

$pageTitle = 'Edit Template: ' . $tpl['name'];
$errors    = [];

// Variable hints per type
$varHints = [
    'booking_confirmation' => [
        'app_name'        => 'Nama aplikasi',
        'booker_name'     => 'Nama pemesan',
        'booking_code'    => 'Kode booking',
        'room_name'       => 'Nama ruangan',
        'booking_date'    => 'Tanggal booking (format panjang)',
        'booking_time'    => 'Rentang waktu (mis: 08:00 - 10:00)',
        'status_label'    => 'Status booking (✅ DIKONFIRMASI / ⏳ MENUNGGU)',
        'checkin_section' => 'Blok HTML tombol check-in (otomatis)',
        'checkin_url'     => 'URL link check-in',
    ],
    'booking_approved' => [
        'app_name'     => 'Nama aplikasi',
        'booker_name'  => 'Nama pemesan',
        'booking_code' => 'Kode booking',
        'room_name'    => 'Nama ruangan',
        'booking_date' => 'Tanggal booking',
        'booking_time' => 'Rentang waktu',
        'checkin_url'  => 'URL link check-in',
    ],
    'booking_rejected' => [
        'app_name'       => 'Nama aplikasi',
        'booker_name'    => 'Nama pemesan',
        'booking_code'   => 'Kode booking',
        'room_name'      => 'Nama ruangan',
        'booking_date'   => 'Tanggal booking',
        'booking_time'   => 'Rentang waktu',
        'reason_section' => 'Blok HTML alasan penolakan (otomatis jika ada)',
    ],
    'checkin_confirmation' => [
        'app_name'     => 'Nama aplikasi',
        'booker_name'  => 'Nama pemesan',
        'booking_code' => 'Kode booking',
        'room_name'    => 'Nama ruangan',
        'booking_date' => 'Tanggal booking',
        'booking_time' => 'Rentang waktu',
        'checkin_time' => 'Jam check-in (mis: 08:35)',
    ],
];

$hints = $varHints[$tpl['type']] ?? [];

if (isPost() && verifyCsrf()) {
    $name      = sanitize($_POST['name'] ?? '');
    $subject   = trim($_POST['subject'] ?? '');
    $bodyHtml  = $_POST['body_html'] ?? '';
    $isActive  = isset($_POST['is_active']) ? 1 : 0;

    if (!$name)    $errors[] = 'Nama template wajib diisi.';
    if (!$subject) $errors[] = 'Subject email wajib diisi.';
    if (!$bodyHtml) $errors[] = 'Isi template wajib diisi.';

    if (empty($errors)) {
        $db->update('email_templates', [
            'name'      => $name,
            'subject'   => $subject,
            'body_html' => $bodyHtml,
            'is_active' => $isActive,
        ], ['id' => $id]);

        auditLog('update', 'email_templates', "Edit template: {$tpl['type']}");
        flash('success', 'Template email berhasil disimpan.');
        redirect(BASE_URL . '/admin/email-templates/');
    }

    // Re-populate untuk re-render form dengan nilai baru
    $tpl = array_merge($tpl, [
        'name'      => $name,
        'subject'   => $subject,
        'body_html' => $bodyHtml,
        'is_active' => $isActive,
    ]);
}
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
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/email-templates/">Template Email</a></li>
                    <li class="breadcrumb-item active">Edit</li>
                </ol>
            </nav>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-pencil me-2"></i>Edit Template</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrfField() ?>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nama Template</label>
                            <input type="text" class="form-control" name="name"
                                   value="<?= e($tpl['name']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Subject Email <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="subject"
                                   value="<?= e($tpl['subject']) ?>" required>
                            <div class="form-text">Variabel dapat digunakan di subject, contoh: <code>&#123;&#123;booking_code&#125;&#125;</code></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Isi Email (HTML) <span class="text-danger">*</span></label>
                            <textarea class="form-control font-monospace"
                                      name="body_html" id="bodyHtml" rows="18"
                                      style="font-size:0.82rem;resize:vertical;"
                                      required><?= htmlspecialchars($tpl['body_html'], ENT_QUOTES, 'UTF-8') ?></textarea>
                            <div class="form-text">HTML diizinkan. Template ini dimasukkan ke dalam kerangka email otomatis (header & footer sudah otomatis ditambahkan).</div>
                        </div>

                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active"
                                       id="isActive" value="1" <?= $tpl['is_active'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="isActive">Template aktif</label>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i> Simpan Template
                            </button>
                            <a href="<?= BASE_URL ?>/admin/email-templates/preview.php?id=<?= $id ?>"
                               class="btn btn-outline-secondary" target="_blank">
                                <i class="bi bi-eye me-1"></i> Preview
                            </a>
                            <a href="<?= BASE_URL ?>/admin/email-templates/"
                               class="btn btn-outline-secondary ms-auto">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Variables Hint Panel -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-braces me-2 text-warning"></i>Variabel Tersedia</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($hints as $varName => $desc): ?>
                        <div class="list-group-item px-3 py-2">
                            <div class="d-flex align-items-start gap-2">
                                <button type="button"
                                        class="btn btn-sm btn-outline-warning py-0 px-1 font-monospace flex-shrink-0"
                                        style="font-size:0.75rem;"
                                        onclick="insertVar('{{<?= $varName ?>}}')"
                                        title="Klik untuk sisipkan ke editor">
                                    &#123;&#123;<?= e($varName) ?>&#125;&#125;
                                </button>
                                <span class="small text-muted"><?= e($desc) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="card-footer bg-transparent small text-muted">
                    <i class="bi bi-lightbulb me-1"></i>
                    Klik tombol variabel untuk menyisipkan ke posisi kursor di editor.
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-3">
                <div class="card-body small">
                    <h6 class="fw-bold"><i class="bi bi-info-circle me-2 text-info"></i>Tips</h6>
                    <ul class="mb-0 ps-3 text-muted">
                        <li>Gunakan tag HTML standar untuk styling.</li>
                        <li>Variabel <code>_section</code> sudah berupa blok HTML siap pakai.</li>
                        <li>Test dengan Preview sebelum menyimpan.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</div>
<?php include dirname(__DIR__) . '/layout/footer.php'; ?>
<script>
function insertVar(variable) {
    const ta = document.getElementById('bodyHtml');
    const start = ta.selectionStart;
    const end   = ta.selectionEnd;
    ta.value = ta.value.substring(0, start) + variable + ta.value.substring(end);
    ta.selectionStart = ta.selectionEnd = start + variable.length;
    ta.focus();
}
</script>

<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requirePermission('settings.manage');

$pageTitle = 'Pengaturan Sistem';
$db = db();

// -------------------------------------------------------
// Helper: Upload logo / favicon ke assets/images/
// -------------------------------------------------------
function uploadAppImage(string $fieldName, string $baseName): array
{
    if (empty($_FILES[$fieldName]['name'])) {
        return ['success' => true, 'skipped' => true]; // tidak diupload, skip
    }

    $file     = $_FILES[$fieldName];
    $allowed  = ['jpg', 'jpeg', 'png', 'webp'];
    $allowedMime = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload gagal (kode: ' . $file['error'] . ').'];
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'Ukuran file terlalu besar (maks 2MB).'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        return ['success' => false, 'message' => 'Format tidak didukung. Gunakan JPG, PNG, atau WEBP.'];
    }

    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowedMime)) {
        return ['success' => false, 'message' => 'File bukan gambar yang valid.'];
    }

    // Hapus file lama dengan base name yang sama
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $oldExt) {
        $oldPath = BASE_PATH . '/assets/images/' . $baseName . '.' . $oldExt;
        if (file_exists($oldPath)) @unlink($oldPath);
    }

    $newName  = $baseName . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    $destPath = BASE_PATH . '/assets/images/' . $newName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['success' => false, 'message' => 'Gagal menyimpan file ke server.'];
    }

    return ['success' => true, 'skipped' => false, 'url' => BASE_URL . '/assets/images/' . $newName];
}

if (isPost() && verifyCsrf()) {
    $uploadErrors = [];

    // Handle logo upload
    $logoResult = uploadAppImage('logo_upload', 'logo');
    if (!$logoResult['success']) {
        $uploadErrors[] = 'Logo: ' . $logoResult['message'];
    } elseif (empty($logoResult['skipped'])) {
        $db->update('system_settings', ['value' => $logoResult['url']], ['key_name' => 'app_logo']);
    }

    // Handle favicon upload
    $faviconResult = uploadAppImage('favicon_upload', 'favicon');
    if (!$faviconResult['success']) {
        $uploadErrors[] = 'Favicon: ' . $faviconResult['message'];
    } elseif (empty($faviconResult['skipped'])) {
        $db->update('system_settings', ['value' => $faviconResult['url']], ['key_name' => 'app_favicon']);
    }

    if (!empty($uploadErrors)) {
        flash('error', implode('<br>', $uploadErrors));
        redirect(BASE_URL . '/admin/master/settings.php');
    }

    // Save regular (non-file) settings
    $settings = $_POST['settings'] ?? [];
    foreach ($settings as $key => $value) {
        $key   = sanitize($key);
        $value = trim($value);
        $setting = $db->fetch("SELECT * FROM system_settings WHERE key_name = ?", [$key]);
        if ($setting) {
            // Enkripsi SMTP password sebelum disimpan ke DB
            if ($key === 'smtp_password' && $value !== '') {
                $value = encryptValue($value);
            }
            $db->update('system_settings', ['value' => $value], ['key_name' => $key]);
        }
    }
    auditLog('update', 'settings', 'Update system settings');
    flash('success', 'Pengaturan berhasil disimpan.');
    redirect(BASE_URL . '/admin/master/settings.php');
}

// Get all settings grouped
$rawSettings = $db->fetchAll("SELECT * FROM system_settings WHERE is_active = 1 ORDER BY group_name, sort_order ASC");
$settingsByGroup = [];
foreach ($rawSettings as $s) {
    $settingsByGroup[$s['group_name']][] = $s;
}

$groupLabels = [
    'general'      => ['label' => 'Umum', 'icon' => 'bi-gear'],
    'booking'      => ['label' => 'Booking', 'icon' => 'bi-calendar-check'],
    'email'        => ['label' => 'Email (SMTP)', 'icon' => 'bi-envelope'],
    'notification' => ['label' => 'Notifikasi', 'icon' => 'bi-bell'],
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
            <h5><i class="bi bi-gear me-2 text-primary"></i><?= e($pageTitle) ?></h5>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/">Dashboard</a></li>
                    <li class="breadcrumb-item active">Pengaturan Sistem</li>
                </ol>
            </nav>
        </div>
    </div>

    <?= showFlash() ?>

    <!-- Tab Navigation -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <ul class="nav nav-pills gap-1 flex-wrap" id="settingsTabs">
                <?php $first = true; ?>
                <?php foreach ($groupLabels as $group => $info): ?>
                <?php if (isset($settingsByGroup[$group])): ?>
                <li class="nav-item flex-shrink-0">
                    <button class="nav-link <?= $first ? 'active' : '' ?> px-3 py-2 text-nowrap"
                            data-bs-toggle="tab" data-bs-target="#tab_<?= $group ?>">
                        <i class="bi <?= $info['icon'] ?> me-1"></i><?= $info['label'] ?>
                        <span class="badge bg-<?= $first ? 'white text-primary' : 'secondary-subtle text-secondary' ?> ms-1 fw-normal"><?= count($settingsByGroup[$group]) ?></span>
                    </button>
                </li>
                <?php $first = false; endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <form method="POST" action="" enctype="multipart/form-data">
        <?= csrfField() ?>
        <div class="tab-content">
            <?php $first = true; ?>
            <?php foreach ($groupLabels as $group => $info): ?>
            <?php if (!isset($settingsByGroup[$group])) continue; ?>
            <div class="tab-pane fade <?= $first ? 'show active' : '' ?>" id="tab_<?= $group ?>">
                <div class="card border-0 shadow-sm">

                    <!-- Card Header -->
                    <div class="card-header bg-transparent border-bottom py-3 px-3 px-md-4">
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle bg-primary-subtle d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:40px;height:40px;">
                                <i class="bi <?= $info['icon'] ?> text-primary fs-5"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0">Pengaturan <?= $info['label'] ?></h6>
                                <span class="text-muted small"><?= count($settingsByGroup[$group]) ?> item pengaturan</span>
                            </div>
                        </div>
                    </div>

                    <!-- Settings Rows -->
                    <div class="card-body p-0">
                        <?php foreach ($settingsByGroup[$group] as $idx => $s): ?>
                        <div class="px-3 px-md-4 py-3 <?= $idx < count($settingsByGroup[$group]) - 1 ? 'border-bottom' : '' ?>">
                            <div class="row g-3 align-items-start">

                                <!-- Label & Description -->
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold mb-0 text-dark" for="s_<?= $s['id'] ?>">
                                        <?= e($s['label'] ?? $s['key_name']) ?>
                                    </label>
                                    <?php if ($s['description']): ?>
                                    <div class="text-muted mt-1" style="font-size:0.78rem;line-height:1.4;">
                                        <?= e($s['description']) ?>
                                    </div>
                                    <?php endif; ?>
                                    <code class="text-muted d-block mt-1" style="font-size:0.68rem;"><?= e($s['key_name']) ?></code>
                                </div>

                                <!-- Input -->
                                <div class="col-12 col-md-8">

                                    <?php if ($s['type'] === 'boolean'): ?>
                                    <!-- Boolean Switch -->
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input setting-toggle" type="checkbox"
                                                   name="settings[<?= e($s['key_name']) ?>]"
                                                   value="1" id="s_<?= $s['id'] ?>"
                                                   <?= $s['value'] === '1' ? 'checked' : '' ?>
                                                   style="width:2.5em;height:1.3em;cursor:pointer;">
                                        </div>
                                        <span class="badge bg-<?= $s['value'] === '1' ? 'success' : 'secondary' ?> px-2 py-1"
                                              id="badge_<?= $s['id'] ?>" style="font-size:0.75rem;">
                                            <i class="bi bi-<?= $s['value'] === '1' ? 'check-circle' : 'x-circle' ?> me-1"></i>
                                            <?= $s['value'] === '1' ? 'Aktif' : 'Nonaktif' ?>
                                        </span>
                                    </div>

                                    <?php elseif ($s['type'] === 'file'): ?>
                                    <!-- File Upload -->
                                    <?php
                                    $uploadFieldMap = [
                                        'app_logo'    => ['field' => 'logo_upload',    'hint' => 'logo.png / logo.jpg / logo.webp'],
                                        'app_favicon' => ['field' => 'favicon_upload', 'hint' => 'favicon.png — ideal 32×32 atau 64×64 px'],
                                    ];
                                    $uMap = $uploadFieldMap[$s['key_name']] ?? null;
                                    ?>
                                    <?php if ($uMap): ?>
                                    <div class="d-flex flex-column gap-2">
                                        <!-- Current image preview -->
                                        <div class="d-flex align-items-center gap-3 p-2 border rounded-2 bg-light">
                                            <?php if (!empty($s['value'])): ?>
                                            <img src="<?= e($s['value']) ?>?v=<?= time() ?>"
                                                 alt="Current" style="max-height:48px;max-width:120px;border-radius:4px;object-fit:contain;flex-shrink:0;"
                                                 onerror="this.nextElementSibling.classList.remove('d-none');this.style.display='none';">
                                            <span class="d-none text-muted"><i class="bi bi-image fs-4"></i></span>
                                            <?php else: ?>
                                            <span class="text-muted"><i class="bi bi-image fs-4"></i></span>
                                            <?php endif; ?>
                                            <div class="small min-w-0">
                                                <div class="fw-semibold text-muted text-truncate"><?= empty($s['value']) ? 'Belum ada gambar' : e(basename($s['value'])) ?></div>
                                                <div class="text-muted" style="font-size:0.7rem;">Gambar saat ini</div>
                                            </div>
                                        </div>
                                        <!-- Upload input -->
                                        <input type="file" class="form-control" name="<?= $uMap['field'] ?>"
                                               accept=".jpg,.jpeg,.png,.webp">
                                        <div class="text-muted" style="font-size:0.75rem;">
                                            <i class="bi bi-info-circle me-1"></i>
                                            Format: <strong>JPG, PNG, WEBP</strong> &bull; Maks 2MB &bull;
                                            Disimpan sebagai <code><?= $uMap['hint'] ?></code>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <input type="text" class="form-control"
                                           name="settings[<?= e($s['key_name']) ?>]"
                                           value="<?= e($s['value'] ?? '') ?>">
                                    <?php endif; ?>

                                    <?php elseif ($s['type'] === 'textarea'): ?>
                                    <!-- Textarea -->
                                    <textarea class="form-control" name="settings[<?= e($s['key_name']) ?>]"
                                              id="s_<?= $s['id'] ?>" rows="3" style="resize:vertical;"><?= e($s['value'] ?? '') ?></textarea>

                                    <?php elseif ($s['type'] === 'color'): ?>
                                    <!-- Color Picker -->
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <input type="color" class="form-control form-control-color flex-shrink-0"
                                               name="settings[<?= e($s['key_name']) ?>]"
                                               value="<?= e($s['value'] ?? '#0d6efd') ?>"
                                               id="s_<?= $s['id'] ?>"
                                               style="width:52px;height:38px;padding:2px 4px;cursor:pointer;">
                                        <input type="text" class="form-control text-uppercase"
                                               value="<?= e($s['value'] ?? '#0d6efd') ?>"
                                               readonly style="max-width:110px;font-family:monospace;font-size:0.85rem;"
                                               id="colorText_<?= $s['id'] ?>">
                                        <div class="rounded-2 border" style="width:38px;height:38px;background:<?= e($s['value'] ?? '#0d6efd') ?>;"
                                             id="colorPreview_<?= $s['id'] ?>"></div>
                                    </div>

                                    <?php elseif ($s['type'] === 'number'): ?>
                                    <!-- Number -->
                                    <div class="input-group" style="max-width:200px;">
                                        <input type="number" class="form-control"
                                               name="settings[<?= e($s['key_name']) ?>]"
                                               id="s_<?= $s['id'] ?>"
                                               value="<?= e($s['value'] ?? '') ?>">
                                    </div>

                                    <?php else: ?>
                                    <!-- Text / Password -->
                                    <?php $isPassword = $s['key_name'] === 'smtp_password'; ?>
                                    <div class="input-group">
                                        <input type="<?= $isPassword ? 'password' : 'text' ?>"
                                               class="form-control"
                                               name="settings[<?= e($s['key_name']) ?>]"
                                               id="s_<?= $s['id'] ?>"
                                               value="<?= e($s['value'] ?? '') ?>"
                                               autocomplete="<?= $isPassword ? 'new-password' : 'off' ?>">
                                        <?php if ($isPassword): ?>
                                        <button class="btn btn-outline-secondary" type="button"
                                                onclick="togglePwd('s_<?= $s['id'] ?>', this)" title="Tampilkan/Sembunyikan">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>

                                    <?php endif; ?>

                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Card Footer: Save Button -->
                    <div class="card-footer bg-transparent border-top py-3 px-3 px-md-4">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i> Simpan Pengaturan
                            </button>
                            <span class="text-muted small">
                                <i class="bi bi-info-circle me-1"></i>Perubahan hanya berlaku setelah disimpan.
                            </span>
                        </div>
                    </div>

                </div>
            </div>
            <?php $first = false; ?>
            <?php endforeach; ?>
        </div>
    </form>

</div>
<?php include dirname(__DIR__) . '/layout/footer.php'; ?>
<script>
// --- Color picker sync ---
document.querySelectorAll('input[type="color"]').forEach(input => {
    const textEl   = document.getElementById('colorText_' + input.id.replace('s_', ''));
    const previewEl = document.getElementById('colorPreview_' + input.id.replace('s_', ''));
    input.addEventListener('input', function() {
        if (textEl)    textEl.value = this.value.toUpperCase();
        if (previewEl) previewEl.style.background = this.value;
    });
});

// --- Boolean toggle badge ---
document.querySelectorAll('.setting-toggle').forEach(cb => {
    const id = cb.id.replace('s_', '');
    const badge = document.getElementById('badge_' + id);
    cb.addEventListener('change', function() {
        if (!badge) return;
        if (this.checked) {
            badge.className = 'badge bg-success px-2 py-1';
            badge.innerHTML = '<i class="bi bi-check-circle me-1"></i>Aktif';
        } else {
            badge.className = 'badge bg-secondary px-2 py-1';
            badge.innerHTML = '<i class="bi bi-x-circle me-1"></i>Nonaktif';
        }
    });
});

// --- Tab badge color update ---
document.querySelectorAll('#settingsTabs .nav-link').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('#settingsTabs .nav-link').forEach(b => {
            const badge = b.querySelector('.badge');
            if (badge) badge.className = 'badge bg-secondary-subtle text-secondary ms-1 fw-normal';
        });
        const activeBadge = this.querySelector('.badge');
        if (activeBadge) activeBadge.className = 'badge bg-white text-primary ms-1 fw-normal';
    });
});

// --- Password show/hide ---
function togglePwd(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>

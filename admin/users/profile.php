<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requireLogin();

$pageTitle = 'Profil Saya';
$db = db();
$userId = Auth::id();
$me = $db->fetch("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?", [$userId]);

$errors = [];
$success = false;

if (isPost() && verifyCsrf()) {
    $action = $_POST['action'] ?? 'update_profile';

    if ($action === 'update_profile') {
        $name  = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');

        if (!$name) $errors[] = 'Nama wajib diisi.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email tidak valid.';

        // Check email uniqueness
        $existingEmail = $db->fetch("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId]);
        if ($existingEmail) $errors[] = 'Email sudah digunakan.';

        if (empty($errors)) {
            $updateData = ['name' => $name, 'email' => $email, 'phone' => $phone];

            // Handle avatar upload
            if (!empty($_FILES['avatar']['name'])) {
                $avatarResult = uploadImage($_FILES['avatar'], 'avatars', 400);
                if ($avatarResult['success']) {
                    // Delete old avatar
                    if ($me['avatar']) deleteFile('avatars/' . $me['avatar']);
                    $updateData['avatar'] = $avatarResult['filename'];
                } else {
                    $errors[] = $avatarResult['message'];
                }
            }

            if (empty($errors)) {
                $db->update('users', $updateData, ['id' => $userId]);
                auditLog('update', 'users', 'Update profil sendiri');
                // Reload session user data
                Auth::reloadPermissions();
                flash('success', 'Profil berhasil diperbarui.');
                redirect(BASE_URL . '/admin/users/profile.php');
            }
        }

    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!Auth::verifyPassword($currentPassword, $me['password'])) {
            $errors[] = 'Password saat ini tidak benar.';
        }
        if (strlen($newPassword) < 6) $errors[] = 'Password baru minimal 6 karakter.';
        if ($newPassword !== $confirmPassword) $errors[] = 'Konfirmasi password tidak cocok.';

        if (empty($errors)) {
            $hashed = Auth::hashPassword($newPassword);
            $db->update('users', ['password' => $hashed], ['id' => $userId]);
            auditLog('update', 'users', 'Ganti password sendiri');
            flash('success', 'Password berhasil diubah.');
            redirect(BASE_URL . '/admin/users/profile.php');
        }
    }

    // Re-fetch after potential updates
    $me = $db->fetch("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?", [$userId]);
}
?>
<?php include dirname(__DIR__) . '/layout/header.php'; ?>
<div class="admin-wrapper">
<?php include dirname(__DIR__) . '/layout/sidebar.php'; ?>
<div class="main-content">
<?php include dirname(__DIR__) . '/layout/navbar.php'; ?>
<div class="page-content">

    <div class="page-header">
        <h5><i class="bi bi-person-circle me-2 text-primary"></i><?= e($pageTitle) ?></h5>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/">Dashboard</a></li>
                <li class="breadcrumb-item active">Profil Saya</li>
            </ol>
        </nav>
    </div>

    <?= showFlash() ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="row g-3">
        <!-- Profile Info -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm text-center p-4">
                <div class="mb-3">
                    <img src="<?= avatarUrl($me['avatar']) ?>"
                         class="rounded-circle border"
                         style="width:100px;height:100px;object-fit:cover;"
                         id="avatarPreview" alt="Avatar">
                </div>
                <h5 class="fw-bold mb-1"><?= e($me['name']) ?></h5>
                <p class="text-muted small mb-2"><?= e($me['username']) ?></p>
                <span class="badge bg-primary"><?= e($me['role_name']) ?></span>
                <hr>
                <div class="text-start small text-muted">
                    <div class="mb-1"><i class="bi bi-envelope me-2"></i><?= e($me['email']) ?></div>
                    <div class="mb-1"><i class="bi bi-phone me-2"></i><?= e($me['phone'] ?? '-') ?></div>
                    <div><i class="bi bi-clock me-2"></i>Login terakhir:
                        <?= $me['last_login'] ? formatDatetimeId($me['last_login']) : 'Belum pernah' ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <!-- Update Profile -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-person me-2"></i>Edit Profil</h6>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_profile">
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" value="<?= e($me['name']) ?>" required>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" value="<?= e($me['username']) ?>" disabled>
                                <div class="form-text">Username tidak bisa diubah.</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" value="<?= e($me['email']) ?>" required>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label">No. HP</label>
                                <input type="text" class="form-control" name="phone" value="<?= e($me['phone'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Foto Profil</label>
                                <input type="file" class="form-control" name="avatar" id="avatarInput"
                                       accept="image/jpeg,image/png,image/webp">
                                <div class="form-text">Format: JPG, PNG, WebP. Maks. 2MB.</div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i> Simpan Profil
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-key me-2"></i>Ganti Password</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="change_password">
                        <div class="mb-3">
                            <label class="form-label">Password Saat Ini <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="current_password" required autocomplete="current-password">
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Password Baru <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="new_password" required minlength="6" autocomplete="new-password">
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="confirm_password" required autocomplete="new-password">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-key me-1"></i> Ganti Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>
<?php include dirname(__DIR__) . '/layout/footer.php'; ?>
<script>
// Avatar preview
document.getElementById('avatarInput')?.addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('avatarPreview').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
});
</script>

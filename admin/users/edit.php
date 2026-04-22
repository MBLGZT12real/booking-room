<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requirePermission('users.edit');

$pageTitle = 'Edit User';
$db = db();
$id = intVal(qParam('id'));
if (!$id) redirect(BASE_URL . '/admin/users/');

$user = $db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
if (!$user) { flash('error', 'User tidak ditemukan.'); redirect(BASE_URL . '/admin/users/'); }

$errors = [];
$data = $user;

if (isPost() && verifyCsrf()) {
    $data = [
        'name'      => trim(pParam('name')),
        'username'  => trim(pParam('username')),
        'email'     => trim(pParam('email')),
        'phone'     => trim(pParam('phone')),
        'role_id'   => intVal(pParam('role_id')),
        'is_active' => (int) (pParam('is_active') === '1'),
    ];
    $password  = pParam('password');
    $password2 = pParam('password_confirm');

    if (empty($data['name'])) $errors[] = 'Nama lengkap harus diisi.';
    if (empty($data['username'])) $errors[] = 'Username harus diisi.';
    if (empty($data['email']) || !isValidEmail($data['email'])) $errors[] = 'Email tidak valid.';
    if (!$data['role_id']) $errors[] = 'Role harus dipilih.';
    if ($password && strlen($password) < 6) $errors[] = 'Password minimal 6 karakter.';
    if ($password && $password !== $password2) $errors[] = 'Konfirmasi password tidak cocok.';

    if (!$errors) {
        // Check unique excluding self
        if ($db->fetch("SELECT id FROM users WHERE username = ? AND id != ?", [$data['username'], $id])) {
            $errors[] = 'Username sudah digunakan.';
        }
        if ($db->fetch("SELECT id FROM users WHERE email = ? AND id != ?", [$data['email'], $id])) {
            $errors[] = 'Email sudah digunakan.';
        }
    }

    if (!$errors) {
        if ($password) $data['password'] = Auth::hashPassword($password);
        $db->update('users', $data, ['id' => $id]);
        auditLog('update', 'users', 'Edit user: ' . $data['username'], $user, $data);
        flash('success', 'User berhasil diperbarui.');
        redirect(BASE_URL . '/admin/users/');
    }
    $data = array_merge($user, $data);
}

$roles = $db->fetchAll("SELECT id, name FROM roles WHERE is_active = 1 ORDER BY id ASC");
?>
<?php include dirname(__DIR__) . '/layout/header.php'; ?>
<div class="admin-wrapper">
<?php include dirname(__DIR__) . '/layout/sidebar.php'; ?>
<div class="main-content">
<?php include dirname(__DIR__) . '/layout/navbar.php'; ?>
<div class="page-content">

    <div class="page-header d-flex align-items-center justify-content-between">
        <div>
            <h5><i class="bi bi-person-gear me-2 text-warning"></i>Edit User</h5>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/users/">User</a></li>
                    <li class="breadcrumb-item active">Edit</li>
                </ol>
            </nav>
        </div>
        <a href="<?= BASE_URL ?>/admin/users/" class="btn btn-outline-secondary btn-md">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-12 col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="POST" action="">
                        <?= csrfField() ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" value="<?= e($data['name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="username" value="<?= e($data['username']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" value="<?= e($data['email']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nomor HP</label>
                                <input type="text" class="form-control" name="phone" value="<?= e($data['phone'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <div class="alert alert-info py-2 small">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Kosongkan field password jika tidak ingin mengubah password.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password Baru</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="password" id="pwd" autocomplete="new-password">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('pwd')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Konfirmasi Password Baru</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="password_confirm" id="pwd2" autocomplete="new-password">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('pwd2')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Role <span class="text-danger">*</span></label>
                                <select class="form-select" name="role_id" required>
                                    <option value="">-- Pilih Role --</option>
                                    <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>" <?= (int)$data['role_id'] === $r['id'] ? 'selected' : '' ?>>
                                        <?= e($r['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="is_active">
                                    <option value="1" <?= $data['is_active'] ? 'selected' : '' ?>>Aktif</option>
                                    <option value="0" <?= !$data['is_active'] ? 'selected' : '' ?>>Nonaktif</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-warning text-dark">
                                <i class="bi bi-save me-1"></i> Perbarui User
                            </button>
                            <a href="<?= BASE_URL ?>/admin/users/" class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include dirname(__DIR__) . '/layout/footer.php'; ?>
<script>
function togglePwd(id) {
    const el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
}
</script>

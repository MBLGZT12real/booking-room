<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requirePermission('users.create');

$pageTitle = 'Tambah User';
$db = db();
$errors = [];
$data = ['name' => '', 'username' => '', 'email' => '', 'phone' => '', 'role_id' => '', 'is_active' => 1];

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
    if (empty($password)) $errors[] = 'Password harus diisi.';
    if (strlen($password) < 8) $errors[] = 'Password minimal 8 karakter.';
    if ($password !== $password2) $errors[] = 'Konfirmasi password tidak cocok.';
    if (!$data['role_id']) $errors[] = 'Role harus dipilih.';

    if (!$errors) {
        // Check unique
        if ($db->exists('users', ['username' => $data['username']])) $errors[] = 'Username sudah digunakan.';
        if ($db->exists('users', ['email' => $data['email']])) $errors[] = 'Email sudah digunakan.';
    }

    if (!$errors) {
        $data['password'] = Auth::hashPassword($password);
        $userId = $db->insert('users', $data);
        auditLog('create', 'users', 'Tambah user: ' . $data['username']);
        flash('success', 'User berhasil ditambahkan.');
        redirect(BASE_URL . '/admin/users/');
    }
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
            <h5><i class="bi bi-person-plus me-2 text-primary"></i><?= e($pageTitle) ?></h5>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/users/">User</a></li>
                    <li class="breadcrumb-item active">Tambah</li>
                </ol>
            </nav>
        </div>
        <a href="<?= BASE_URL ?>/admin/users/" class="btn btn-outline-secondary btn-md">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <?php if ($errors): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <ul class="mb-0 mt-1">
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
                                <input type="text" class="form-control" name="username" value="<?= e($data['username']) ?>" required autocomplete="off">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" value="<?= e($data['email']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nomor HP</label>
                                <input type="text" class="form-control" name="phone" value="<?= e($data['phone']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="password" id="pwd" required minlength="8" autocomplete="new-password">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('pwd')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Minimal 6 karakter</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="password_confirm" id="pwd2" required autocomplete="new-password">
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
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i> Simpan User
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

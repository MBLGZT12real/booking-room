<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requirePermission('roles.create');

$db = db();
$id = intVal(qParam('id'));
$isEdit = $id > 0;
$pageTitle = $isEdit ? 'Edit Role' : 'Tambah Role';

if ($isEdit) {
    Auth::requirePermission('roles.edit');
    $role = $db->fetch("SELECT * FROM roles WHERE id = ?", [$id]);
    if (!$role) { flash('error', 'Role tidak ditemukan.'); redirect(BASE_URL . '/admin/roles/'); }
} else {
    $role = ['name' => '', 'slug' => '', 'description' => '', 'is_active' => 1];
}

$errors = [];

if (isPost() && verifyCsrf()) {
    $data = [
        'name'        => trim(pParam('name')),
        'slug'        => strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', pParam('slug')))),
        'description' => trim(pParam('description')),
        'is_active'   => (int) (pParam('is_active') === '1'),
    ];

    if (empty($data['name'])) $errors[] = 'Nama role harus diisi.';
    if (empty($data['slug'])) $errors[] = 'Slug role harus diisi.';

    // Check unique slug
    if (!$errors) {
        $existingSlug = $db->fetch(
            "SELECT id FROM roles WHERE slug = ? AND id != ?",
            [$data['slug'], $id ?: 0]
        );
        if ($existingSlug) $errors[] = 'Slug sudah digunakan role lain.';
    }

    if (!$errors) {
        if ($isEdit) {
            // Protect superadmin slug
            if ($role['slug'] === 'superadmin') {
                $data['slug'] = 'superadmin';
            }
            $db->update('roles', $data, ['id' => $id]);
            auditLog('update', 'roles', 'Edit role: ' . $data['name']);
            flash('success', 'Role berhasil diperbarui.');
        } else {
            $db->insert('roles', $data);
            auditLog('create', 'roles', 'Tambah role: ' . $data['name']);
            flash('success', 'Role berhasil ditambahkan.');
        }
        redirect(BASE_URL . '/admin/roles/');
    }
    $role = array_merge($role, $data);
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
            <h5><i class="bi bi-person-badge me-2 text-primary"></i><?= e($pageTitle) ?></h5>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/roles/">Role</a></li>
                    <li class="breadcrumb-item active"><?= e($pageTitle) ?></li>
                </ol>
            </nav>
        </div>
        <a href="<?= BASE_URL ?>/admin/roles/" class="btn btn-outline-secondary btn-md">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <?php if ($errors): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Terjadi kesalahan:</strong>
        <ul class="mb-0 mt-1">
            <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h6 class="fw-bold mb-0"><?= e($pageTitle) ?></h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <?= csrfField() ?>

                        <div class="mb-3">
                            <label class="form-label">Nama Role <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name"
                                   value="<?= e($role['name']) ?>" required
                                   placeholder="Contoh: Admin">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Slug <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?= $isEdit && $role['slug'] === 'superadmin' ? 'bg-light' : '' ?>"
                                   name="slug" id="slugInput"
                                   value="<?= e($role['slug']) ?>"
                                   <?= $isEdit && $role['slug'] === 'superadmin' ? 'readonly' : '' ?>
                                   placeholder="contoh: admin" required>
                            <div class="form-text">Huruf kecil, angka, dan underscore saja. Digunakan sebagai identifier internal.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="form-control" name="description" rows="3"
                                      placeholder="Deskripsi singkat tentang role ini..."><?= e($role['description'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Status</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="is_active"
                                           id="statusActive" value="1" <?= $role['is_active'] ? 'checked' : '' ?>>
                                    <label class="form-check-label text-success" for="statusActive">
                                        <i class="bi bi-check-circle me-1"></i> Aktif
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="is_active"
                                           id="statusInactive" value="0" <?= !$role['is_active'] ? 'checked' : '' ?>>
                                    <label class="form-check-label text-secondary" for="statusInactive">
                                        <i class="bi bi-x-circle me-1"></i> Nonaktif
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i> Simpan
                            </button>
                            <a href="<?= BASE_URL ?>/admin/roles/" class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>
<?php include dirname(__DIR__) . '/layout/footer.php'; ?>
<script>
// Auto-generate slug from name
document.querySelector('[name="name"]')?.addEventListener('input', function() {
    const slugInput = document.getElementById('slugInput');
    if (slugInput && !slugInput.readOnly) {
        slugInput.value = this.value.toLowerCase()
            .replace(/\s+/g, '_')
            .replace(/[^a-z0-9_]/g, '')
            .replace(/_+/g, '_');
    }
});
</script>

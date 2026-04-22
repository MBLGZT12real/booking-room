<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requirePermission('departments.view');

$pageTitle = 'Departemen';
$db = db();

// Handle POST actions (AJAX or form)
if (isPost() && verifyCsrf()) {
    $action = pParam('action');

    if ($action === 'create' && Auth::can('departments.manage')) {
        $data = [
            'name'        => trim(pParam('name')),
            'code'        => strtoupper(trim(pParam('code'))) ?: null,
            'description' => trim(pParam('description')) ?: null,
            'is_active'   => (int) (pParam('is_active') === '1'),
        ];
        if (empty($data['name'])) {
            flash('error', 'Nama departemen harus diisi.');
        } else {
            $db->insert('departments', $data);
            auditLog('create', 'departments', 'Tambah departemen: ' . $data['name']);
            flash('success', 'Departemen berhasil ditambahkan.');
        }
    } elseif ($action === 'update' && Auth::can('departments.manage')) {
        $id = intVal(pParam('id'));
        $data = [
            'name'        => trim(pParam('name')),
            'code'        => strtoupper(trim(pParam('code'))) ?: null,
            'description' => trim(pParam('description')) ?: null,
            'is_active'   => (int) (pParam('is_active') === '1'),
        ];
        if (empty($data['name'])) {
            flash('error', 'Nama departemen harus diisi.');
        } else {
            $db->update('departments', $data, ['id' => $id]);
            auditLog('update', 'departments', 'Edit departemen ID: ' . $id);
            flash('success', 'Departemen berhasil diperbarui.');
        }
    } elseif ($action === 'toggle' && Auth::can('departments.manage')) {
        $id = intVal(pParam('id'));
        $dept = $db->fetch("SELECT * FROM departments WHERE id = ?", [$id]);
        if ($dept) {
            $db->update('departments', ['is_active' => $dept['is_active'] ? 0 : 1], ['id' => $id]);
            flash('success', 'Status departemen diubah.');
        }
    } elseif ($action === 'delete' && Auth::can('departments.manage')) {
        $id = intVal(pParam('id'));
        // Check if used in bookings
        $usedCount = (int) $db->fetchColumn("SELECT COUNT(*) FROM bookings WHERE department_id = ?", [$id]);
        if ($usedCount > 0) {
            flash('error', "Departemen tidak dapat dihapus karena digunakan di $usedCount booking.");
        } else {
            $db->delete('departments', ['id' => $id]);
            auditLog('delete', 'departments', 'Hapus departemen ID: ' . $id);
            flash('success', 'Departemen berhasil dihapus.');
        }
    }
    redirect(BASE_URL . '/admin/master/departments.php');
}

$departments = $db->fetchAll(
    "SELECT d.*,
     (SELECT COUNT(*) FROM bookings b WHERE b.department_id = d.id) as booking_count
     FROM departments d ORDER BY d.id ASC"
);
?>
<?php include dirname(__DIR__) . '/layout/header.php'; ?>
<div class="admin-wrapper">
<?php include dirname(__DIR__) . '/layout/sidebar.php'; ?>
<div class="main-content">
<?php include dirname(__DIR__) . '/layout/navbar.php'; ?>
<div class="page-content">

    <div class="page-header d-flex align-items-center justify-content-between">
        <div>
            <h5><i class="bi bi-building me-2 text-primary"></i><?= e($pageTitle) ?></h5>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/">Dashboard</a></li>
                    <li class="breadcrumb-item active">Departemen</li>
                </ol>
            </nav>
        </div>
        <?php if (Auth::can('departments.manage')): ?>
        <button type="button" class="btn btn-primary btn-md" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-circle me-1"></i><span class="d-none d-sm-inline">Tambah</span> Departemen
        </button>
        <?php endif; ?>
    </div>

    <?= showFlash() ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover <?= !empty($departments) ? 'datatable' : '' ?> align-middle">
                    <thead>
                        <tr>
                            <th class="px-4" width="50">#</th>
                            <th style="min-width:180px;">Departemen</th>
                            <th>Kode</th>
                            <th style="min-width:160px;">Deskripsi</th>
                            <th class="text-center">Booking</th>
                            <th class="text-center">Status</th>
                            <th class="text-center no-sort" style="min-width:90px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departments as $i => $dept): ?>
                        <tr>
                            <td class="px-3 text-muted small"><?= $i + 1 ?></td>
                            <td class="fw-semibold"><?= e($dept['name']) ?></td>
                            <td><code><?= e($dept['code'] ?? '-') ?></code></td>
                            <td class="small text-muted"><?= e(truncate($dept['description'] ?? '-', 60)) ?></td>
                            <td class="text-center">
                                <span class="badge bg-secondary-subtle text-secondary"><?= numId($dept['booking_count']) ?></span>
                            </td>
                            <td class="text-center">
                                <?php if (Auth::can('departments.manage')): ?>
                                <form method="POST" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $dept['id'] ?>">
                                    <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent">
                                        <span class="badge bg-<?= $dept['is_active'] ? 'success' : 'secondary' ?>">
                                            <?= $dept['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                                        </span>
                                    </button>
                                </form>
                                <?php else: ?>
                                <span class="badge bg-<?= $dept['is_active'] ? 'success' : 'secondary' ?>">
                                    <?= $dept['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (Auth::can('departments.manage')): ?>
                                <div class="d-flex justify-content-center gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-warning"
                                            onclick="openEditModal(<?= htmlspecialchars(json_encode($dept)) ?>)"
                                            title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            onclick="confirmDelete('<?= BASE_URL ?>/admin/master/departments.php?_action=delete&id=<?= $dept['id'] ?>', '<?= e($dept['name']) ?>')"
                                            title="Hapus">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($departments)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada data departemen</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
<?php include dirname(__DIR__) . '/layout/footer.php'; ?>

<?php if (Auth::can('departments.manage')): ?>
<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-building me-2"></i>Tambah Departemen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Departemen <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required placeholder="Contoh: Finance">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kode</label>
                        <input type="text" class="form-control" name="code" placeholder="Contoh: FIN" maxlength="20">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="is_active">
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Departemen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Departemen <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="editName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kode</label>
                        <input type="text" class="form-control" name="code" id="editCode" maxlength="20">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea class="form-control" name="description" id="editDesc" rows="2"></textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="is_active" id="editStatus">
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning text-dark"><i class="bi bi-save me-1"></i> Perbarui</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditModal(data) {
    document.getElementById('editId').value = data.id;
    document.getElementById('editName').value = data.name;
    document.getElementById('editCode').value = data.code || '';
    document.getElementById('editDesc').value = data.description || '';
    document.getElementById('editStatus').value = data.is_active;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

// Handle delete via GET with CSRF
function confirmDelete(url, name) {
    Swal.fire({
        title: 'Hapus Departemen?',
        html: 'Departemen <strong>' + name + '</strong> akan dihapus.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            // Submit as POST to avoid CSRF issues
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?= BASE_URL ?>/admin/master/departments.php';
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="${new URL(url).searchParams.get('id')}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>
<?php endif; ?>

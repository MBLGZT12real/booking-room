<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requirePermission('meeting_types.view');

$pageTitle = 'Tipe Meeting';
$db = db();

if (isPost() && verifyCsrf()) {
    $action = pParam('action');

    if ($action === 'create' && Auth::can('meeting_types.manage')) {
        $data = [
            'name'        => trim(pParam('name')),
            'description' => trim(pParam('description')) ?: null,
            'is_active'   => (int) (pParam('is_active') === '1'),
        ];
        if (empty($data['name'])) {
            flash('error', 'Nama tipe meeting harus diisi.');
        } else {
            $db->insert('meeting_types', $data);
            flash('success', 'Tipe meeting berhasil ditambahkan.');
        }
    } elseif ($action === 'update' && Auth::can('meeting_types.manage')) {
        $id = intVal(pParam('id'));
        $data = [
            'name'        => trim(pParam('name')),
            'description' => trim(pParam('description')) ?: null,
            'is_active'   => (int) (pParam('is_active') === '1'),
        ];
        $db->update('meeting_types', $data, ['id' => $id]);
        flash('success', 'Tipe meeting berhasil diperbarui.');
    } elseif ($action === 'toggle' && Auth::can('meeting_types.manage')) {
        $id = intVal(pParam('id'));
        $mt = $db->fetch("SELECT * FROM meeting_types WHERE id = ?", [$id]);
        if ($mt) $db->update('meeting_types', ['is_active' => $mt['is_active'] ? 0 : 1], ['id' => $id]);
        flash('success', 'Status diubah.');
    } elseif ($action === 'delete' && Auth::can('meeting_types.manage')) {
        $id = intVal(pParam('id'));
        $used = (int) $db->fetchColumn("SELECT COUNT(*) FROM bookings WHERE meeting_type_id = ?", [$id]);
        if ($used > 0) {
            flash('error', "Tidak dapat dihapus karena digunakan di $used booking.");
        } else {
            $db->delete('meeting_types', ['id' => $id]);
            flash('success', 'Tipe meeting berhasil dihapus.');
        }
    }
    redirect(BASE_URL . '/admin/master/meeting-types.php');
}

$meetingTypes = $db->fetchAll(
    "SELECT mt.*,
     (SELECT COUNT(*) FROM bookings b WHERE b.meeting_type_id = mt.id) as booking_count
     FROM meeting_types mt ORDER BY mt.id ASC"
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
            <h5><i class="bi bi-calendar-event me-2 text-primary"></i><?= e($pageTitle) ?></h5>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item fs-8"><a href="<?= BASE_URL ?>/admin/">Dashboard</a></li>
                    <li class="breadcrumb-item fs-8 active">Tipe Meeting</li>
                </ol>
            </nav>
        </div>
        <?php if (Auth::can('meeting_types.manage')): ?>
        <button type="button" class="btn btn-primary btn-md" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-circle me-1"></i><span class="d-none d-sm-inline">Tambah</span> Tipe Meeting
        </button>
        <?php endif; ?>
    </div>

    <?= showFlash() ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover <?= !empty($meetingTypes) ? 'datatable' : '' ?> align-middle">
                    <thead>
                        <tr>
                            <th class="px-4" width="50">#</th>
                            <th style="min-width:180px;">Tipe Meeting</th>
                            <th style="min-width:160px;">Deskripsi</th>
                            <th class="text-center">Booking</th>
                            <th class="text-center">Status</th>
                            <th class="text-center no-sort" style="min-width:90px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($meetingTypes as $i => $mt): ?>
                        <tr>
                            <td class="px-3 text-muted small"><?= $i + 1 ?></td>
                            <td class="fw-semibold"><?= e($mt['name']) ?></td>
                            <td class="small text-muted"><?= e(truncate($mt['description'] ?? '-', 70)) ?></td>
                            <td class="text-center">
                                <span class="badge bg-secondary-subtle text-secondary"><?= numId($mt['booking_count']) ?></span>
                            </td>
                            <td class="text-center">
                                <?php if (Auth::can('meeting_types.manage')): ?>
                                <form method="POST" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $mt['id'] ?>">
                                    <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent">
                                        <span class="badge bg-<?= $mt['is_active'] ? 'success' : 'secondary' ?>">
                                            <?= $mt['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                                        </span>
                                    </button>
                                </form>
                                <?php else: ?>
                                <span class="badge bg-<?= $mt['is_active'] ? 'success' : 'secondary' ?>">
                                    <?= $mt['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (Auth::can('meeting_types.manage')): ?>
                                <div class="d-flex justify-content-center gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-warning"
                                            onclick="openEditModal(<?= htmlspecialchars(json_encode($mt)) ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            onclick="doDelete(<?= $mt['id'] ?>, '<?= e($mt['name']) ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($meetingTypes)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada data tipe meeting</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include dirname(__DIR__) . '/layout/footer.php'; ?>

<?php if (Auth::can('meeting_types.manage')): ?>
<form id="deleteForm" method="POST" action="">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Tipe Meeting</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required>
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
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editId">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Tipe Meeting</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="editName" required>
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
                    <button type="submit" class="btn btn-warning text-dark">Perbarui</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditModal(d) {
    document.getElementById('editId').value = d.id;
    document.getElementById('editName').value = d.name;
    document.getElementById('editDesc').value = d.description || '';
    document.getElementById('editStatus').value = d.is_active;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
function doDelete(id, name) {
    Swal.fire({
        title: 'Hapus?', html: 'Tipe <strong>' + name + '</strong> akan dihapus.',
        icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d',
        confirmButtonText: 'Hapus', cancelButtonText: 'Batal',
    }).then(r => { if (r.isConfirmed) { document.getElementById('deleteId').value = id; document.getElementById('deleteForm').submit(); }});
}
</script>
<?php endif; ?>

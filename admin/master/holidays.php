<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requirePermission('holidays.manage');

$pageTitle = 'Hari Libur / Day Off';
$db = db();

if (isPost() && verifyCsrf()) {
    $action = pParam('action');

    if ($action === 'create') {
        $data = [
            'date'        => pParam('date'),
            'name'        => trim(pParam('name')),
            'description' => trim(pParam('description')) ?: null,
            'is_recurring'=> (int) (pParam('is_recurring') === '1'),
            'is_active'   => 1,
        ];
        if (empty($data['date']) || empty($data['name'])) {
            flash('error', 'Tanggal dan nama hari libur harus diisi.');
        } else {
            $db->insert('holidays', $data);
            flash('success', 'Hari libur berhasil ditambahkan.');
        }
    } elseif ($action === 'update') {
        $id = intVal(pParam('id'));
        $data = [
            'date'        => pParam('date'),
            'name'        => trim(pParam('name')),
            'description' => trim(pParam('description')) ?: null,
            'is_recurring'=> (int) (pParam('is_recurring') === '1'),
            'is_active'   => (int) (pParam('is_active') === '1'),
        ];
        $db->update('holidays', $data, ['id' => $id]);
        flash('success', 'Hari libur berhasil diperbarui.');
    } elseif ($action === 'toggle') {
        $id = intVal(pParam('id'));
        $h = $db->fetch("SELECT * FROM holidays WHERE id = ?", [$id]);
        if ($h) $db->update('holidays', ['is_active' => $h['is_active'] ? 0 : 1], ['id' => $id]);
        flash('success', 'Status diubah.');
    } elseif ($action === 'delete') {
        $id = intVal(pParam('id'));
        $db->delete('holidays', ['id' => $id]);
        flash('success', 'Hari libur berhasil dihapus.');
    }
    redirect(BASE_URL . '/admin/master/holidays.php');
}

$year = intVal(qParam('year', date('Y')));
$holidays = $db->fetchAll(
    "SELECT * FROM holidays
     WHERE (YEAR(date) = ? AND is_recurring = 0) OR is_recurring = 1
     ORDER BY DATE_FORMAT(date, '%m-%d') ASC, date ASC",
    [$year]
);
// Untuk hari libur berulang, ganti tahun ke tahun yang dipilih agar tampilan sesuai
foreach ($holidays as &$h) {
    if ($h['is_recurring']) {
        $h['date'] = $year . substr($h['date'], 4);
    }
}
unset($h);
$years = range(date('Y') - 1, date('Y') + 3);
?>
<?php include dirname(__DIR__) . '/layout/header.php'; ?>
<div class="admin-wrapper">
<?php include dirname(__DIR__) . '/layout/sidebar.php'; ?>
<div class="main-content">
<?php include dirname(__DIR__) . '/layout/navbar.php'; ?>
<div class="page-content">

    <div class="page-header d-flex align-items-center justify-content-between">
        <div>
            <h5><i class="bi bi-calendar-x me-2 text-primary"></i><?= e($pageTitle) ?></h5>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/">Dashboard</a></li>
                    <li class="breadcrumb-item active">Hari Libur</li>
                </ol>
            </nav>
        </div>
        <button type="button" class="btn btn-primary btn-md" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-circle me-1"></i><span class="d-none d-sm-inline">Tambah</span> Hari Libur
        </button>
    </div>

    <?= showFlash() ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-bottom py-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-calendar-x text-primary fs-5"></i>
                    <div>
                        <h6 class="fw-bold mb-0">Daftar Hari Libur</h6>
                        <span class="text-muted small"><?= count($holidays) ?> hari libur ditemukan untuk tahun <?= $year ?></span>
                    </div>
                </div>
                <form method="GET" class="d-flex align-items-center gap-2">
                    <label class="small text-muted mb-0 text-nowrap">Filter Tahun:</label>
                    <select name="year" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                        <?php foreach ($years as $y): ?>
                        <option value="<?= $y ?>" <?= $year === $y ? 'selected' : '' ?>>Tahun <?= $y ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover <?= !empty($holidays) ? 'datatable' : '' ?> align-middle">
                    <thead>
                        <tr>
                            <th class="px-4" width="50">#</th>
                            <th style="min-width:250px;">Tanggal</th>
                            <th style="min-width:150px;">Hari Libur</th>
                            <th style="min-width:150px;">Deskripsi</th>
                            <th class="text-center">Berulang</th>
                            <th class="text-center">Status</th>
                            <th class="text-center no-sort" style="min-width:90px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($holidays as $i => $h): ?>
                        <tr>
                            <td class="px-3 text-muted small"><?= $i + 1 ?></td>
                            <td>
                                <div class="d-inline-flex align-items-center gap-2">
                                    <div class="text-center bg-primary-subtle rounded-2 px-2 py-1" style="min-width:44px;">
                                        <div class="text-primary fw-bold" style="font-size:1rem;line-height:1.1;"><?= date('d', strtotime($h['date'])) ?></div>
                                        <div class="text-primary" style="font-size:0.65rem;text-transform:uppercase;"><?= date('M', strtotime($h['date'])) ?></div>
                                    </div>
                                    <div>
                                        <div class="fw-semibold small"><?= formatDateId($h['date']) ?></div>
                                        <div class="text-muted" style="font-size:0.72rem;"><?= date('l', strtotime($h['date'])) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="fw-semibold"><?= e($h['name']) ?></td>
                            <td class="small text-muted"><?= e(truncate($h['description'] ?? '-', 60)) ?></td>
                            <td class="text-center">
                                <?php if ($h['is_recurring']): ?>
                                <span class="badge bg-info-subtle text-info">
                                    <i class="bi bi-arrow-repeat me-1"></i>Tiap Tahun
                                </span>
                                <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary">Sekali</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <form method="POST" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $h['id'] ?>">
                                    <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent">
                                        <span class="badge bg-<?= $h['is_active'] ? 'success' : 'secondary' ?>">
                                            <?= $h['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                                        </span>
                                    </button>
                                </form>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-warning"
                                            onclick="openEditModal(<?= htmlspecialchars(json_encode($h)) ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            onclick="doDelete(<?= $h['id'] ?>, '<?= e($h['name']) ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($holidays)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada data hari libur</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include dirname(__DIR__) . '/layout/footer.php'; ?>

<form id="deleteForm" method="POST">
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
                    <h5 class="modal-title"><i class="bi bi-calendar-x me-2"></i>Tambah Hari Libur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="date" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Hari Libur <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required placeholder="Contoh: Hari Kemerdekaan RI">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                    <div class="mb-0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_recurring" value="1" id="addRecurring">
                            <label class="form-check-label" for="addRecurring">
                                Berulang setiap tahun (pada tanggal yang sama)
                            </label>
                        </div>
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
                    <h5 class="modal-title">Edit Hari Libur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="date" id="editDate" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Hari Libur <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="editName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea class="form-control" name="description" id="editDesc" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_recurring" value="1" id="editRecurring">
                            <label class="form-check-label" for="editRecurring">Berulang setiap tahun</label>
                        </div>
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
    document.getElementById('editDate').value = d.date;
    document.getElementById('editName').value = d.name;
    document.getElementById('editDesc').value = d.description || '';
    document.getElementById('editRecurring').checked = d.is_recurring == 1;
    document.getElementById('editStatus').value = d.is_active;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
function doDelete(id, name) {
    Swal.fire({ title:'Hapus?', html:'Hari libur <strong>'+name+'</strong> akan dihapus.',
        icon:'warning', showCancelButton:true, confirmButtonColor:'#dc3545',
        confirmButtonText:'Hapus', cancelButtonText:'Batal'
    }).then(r => { if (r.isConfirmed) { document.getElementById('deleteId').value=id; document.getElementById('deleteForm').submit(); }});
}
</script>

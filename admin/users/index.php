<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requirePermission('users.view');

$pageTitle = 'Manajemen User';
$db = db();

// Handle toggle status
if (isPost() && verifyCsrf()) {
    $action = pParam('action');
    $id = intVal(pParam('id'));

    if ($action === 'toggle' && Auth::can('users.edit') && $id) {
        // Cannot deactivate self
        if ($id === Auth::id()) {
            flash('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
        } else {
            $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
            if ($user) {
                $newStatus = $user['is_active'] ? 0 : 1;
                $db->update('users', ['is_active' => $newStatus], ['id' => $id]);
                flash('success', 'Status user berhasil diubah.');
            }
        }
    } elseif ($action === 'delete' && Auth::can('users.delete') && $id) {
        if ($id === Auth::id()) {
            flash('error', 'Anda tidak dapat menghapus akun sendiri.');
        } else {
            $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
            if ($user) {
                $db->delete('users', ['id' => $id]);
                auditLog('delete', 'users', 'Hapus user: ' . $user['username']);
                flash('success', 'User berhasil dihapus.');
            }
        }
    }
    redirect(BASE_URL . '/admin/users/');
}

// Filters
$search = trim(qParam('search'));
$roleFilter = intVal(qParam('role'));
$statusFilter = qParam('status');

$params = [];
$where = ['1=1'];

if ($search) {
    $where[] = "(u.name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($roleFilter) {
    $where[] = "u.role_id = ?";
    $params[] = $roleFilter;
}
if ($statusFilter !== '') {
    $where[] = "u.is_active = ?";
    $params[] = (int) $statusFilter;
}

$whereStr = implode(' AND ', $where);
$users = $db->fetchAll(
    "SELECT u.*, r.name as role_name, r.slug as role_slug
     FROM users u JOIN roles r ON u.role_id = r.id
     WHERE $whereStr ORDER BY u.id ASC",
    $params
);

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
            <h5><i class="bi bi-people me-2 text-primary"></i><?= e($pageTitle) ?></h5>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/">Dashboard</a></li>
                    <li class="breadcrumb-item active">User</li>
                </ol>
            </nav>
        </div>
        <?php if (Auth::can('users.create')): ?>
        <a href="<?= BASE_URL ?>/admin/users/create.php" class="btn btn-primary btn-md">
            <i class="bi bi-plus-circle me-1"></i><span class="d-none d-sm-inline">Tambah </span>User
        </a>
        <?php endif; ?>
    </div>

    <?= showFlash() ?>

    <?php
    $roleBadge = [
        'superadmin' => 'danger',
        'admin'      => 'primary',
        'operator'   => 'success',
    ];
    // Find active role name for display
    $activeRoleName = '';
    foreach ($roles as $r) {
        if ($r['id'] === $roleFilter) { $activeRoleName = $r['name']; break; }
    }
    $hasFilter = ($search || $roleFilter || $statusFilter !== '');
    ?>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-transparent border-bottom py-2 px-3">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-funnel text-primary"></i>
                <h6 class="fw-bold mb-0 me-auto">Filter & Pencarian</h6>
                <?php if ($hasFilter): ?>
                <a href="<?= BASE_URL ?>/admin/users/" class="btn btn-sm btn-outline-secondary py-0">
                    <i class="bi bi-x-lg me-1"></i><span class="d-none d-sm-inline">Reset</span>
                </a>
                <?php endif; ?>
            </div>
            <?php if ($hasFilter): ?>
            <div class="d-flex flex-wrap gap-1 mt-2">
                <?php if ($search): ?>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-normal">
                    <i class="bi bi-search me-1"></i><?= e(mb_strimwidth($search, 0, 25, '…')) ?>
                </span>
                <?php endif; ?>
                <?php if ($roleFilter && $activeRoleName): ?>
                <span class="badge bg-info-subtle text-info border border-info-subtle fw-normal">
                    <i class="bi bi-person-badge me-1"></i><?= e($activeRoleName) ?>
                </span>
                <?php endif; ?>
                <?php if ($statusFilter !== ''): ?>
                <span class="badge bg-<?= $statusFilter === '1' ? 'success' : 'secondary' ?>-subtle text-<?= $statusFilter === '1' ? 'success' : 'secondary' ?> border fw-normal">
                    <?= $statusFilter === '1' ? 'Aktif' : 'Nonaktif' ?>
                </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="card-body py-3 px-3">
            <form method="GET">
                <div class="row g-2">

                    <!-- Search -->
                    <div class="col-12 col-md-5">
                        <label class="form-label small fw-semibold text-muted mb-1">Cari User</label>
                        <div class="input-group">
                            <span class="input-group-text bg-body border-end-0 text-muted">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" class="form-control border-start-0 ps-1"
                                   name="search" id="searchInput"
                                   placeholder="Nama, username, atau email..."
                                   value="<?= e($search) ?>">
                            <?php if ($search): ?>
                            <button type="button" class="btn btn-outline-secondary border-start-0"
                                    onclick="document.getElementById('searchInput').value='';this.form.submit();"
                                    title="Hapus pencarian">
                                <i class="bi bi-x"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Role -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Role</label>
                        <select name="role" class="form-select">
                            <option value="">Semua Role</option>
                            <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= $roleFilter === $r['id'] ? 'selected' : '' ?>>
                                <?= e($r['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Status -->
                    <div class="col-12 col-sm-6 col-md-2">
                        <label class="form-label small fw-semibold text-muted mb-1">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Semua Status</option>
                            <option value="1" <?= $statusFilter === '1' ? 'selected' : '' ?>>Aktif</option>
                            <option value="0" <?= $statusFilter === '0' ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
                    </div>

                    <!-- Button -->
                    <div class="col-12 col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="bi bi-funnel me-1"></i>Filter
                        </button>
                        <?php if ($hasFilter): ?>
                        <a href="<?= BASE_URL ?>/admin/users/" class="btn btn-outline-secondary" title="Reset">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                        <?php endif; ?>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <!-- User Table Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-bottom py-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-people text-primary fs-5"></i>
                    <div>
                        <h6 class="fw-bold mb-0">Daftar User</h6>
                        <span class="text-muted small">
                            <?= count($users) ?> user ditemukan
                            <?php if ($hasFilter): ?>
                            <span class="text-primary">(difilter)</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <!-- Role summary badges (hidden on xs) -->
                <?php
                $roleCounts = [];
                foreach ($users as $u) {
                    $roleCounts[$u['role_name']] = ($roleCounts[$u['role_name']] ?? 0) + 1;
                }
                ?>
                <?php if ($roleCounts): ?>
                <div class="d-none d-sm-flex gap-1 flex-wrap">
                    <?php foreach ($roleCounts as $rName => $rCount): ?>
                    <span class="badge bg-secondary-subtle text-secondary fw-normal">
                        <?= e($rName) ?>: <strong><?= $rCount ?></strong>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover <?= !empty($users) ? 'datatable' : '' ?> align-middle" data-no-filter="true">
                    <thead>
                        <tr>
                            <th class="px-4" width="50">#</th>
                            <th style="min-width:160px;">User</th>
                            <th style="min-width:100px;">Role</th>
                            <th style="min-width:180px;">Kontak</th>
                            <th style="min-width:150px;">Login Terakhir</th>
                            <th class="text-center" style="min-width:90px;">Status</th>
                            <th class="text-center no-sort" style="min-width:90px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $i => $u): ?>
                        <tr>
                            <td class="px-3 text-muted small"><?= $i + 1 ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="position-relative flex-shrink-0">
                                        <img src="<?= avatarUrl($u['avatar']) ?>" class="rounded-circle"
                                             width="36" height="36" alt="Avatar"
                                             style="object-fit:cover;border:2px solid #e9ecef;">
                                        <?php if ($u['id'] === Auth::id()): ?>
                                        <span class="position-absolute bottom-0 end-0 translate-middle-x badge rounded-pill bg-primary"
                                              style="font-size:0.55rem;padding:2px 4px;">You</span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="fw-semibold small"><?= e($u['name']) ?></div>
                                        <div class="text-muted" style="font-size:0.72rem;">
                                            <i class="bi bi-at"></i><?= e($u['username']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php $badgeColor = $roleBadge[$u['role_slug']] ?? 'secondary'; ?>
                                <span class="badge bg-<?= $badgeColor ?>-subtle text-<?= $badgeColor ?> px-2">
                                    <?= e($u['role_name']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="small"><i class="bi bi-envelope me-1 text-muted"></i><?= e($u['email']) ?></div>
                                <?php if ($u['phone']): ?>
                                <div class="small text-muted"><i class="bi bi-phone me-1"></i><?= e($u['phone']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted">
                                <?php if ($u['last_login']): ?>
                                <div><?= formatDatetimeId($u['last_login']) ?></div>
                                <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary">Belum pernah</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (Auth::can('users.edit') && $u['id'] !== Auth::id()): ?>
                                <form method="POST" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent">
                                        <span class="badge bg-<?= $u['is_active'] ? 'success' : 'secondary' ?>">
                                            <?= $u['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                                        </span>
                                    </button>
                                </form>
                                <?php else: ?>
                                <span class="badge bg-<?= $u['is_active'] ? 'success' : 'secondary' ?>">
                                    <?= $u['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <?php if (Auth::can('users.edit')): ?>
                                    <a href="<?= BASE_URL ?>/admin/users/edit.php?id=<?= $u['id'] ?>"
                                       class="btn btn-sm btn-outline-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (Auth::can('users.delete') && $u['id'] !== Auth::id()): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            onclick="deleteUser(<?= $u['id'] ?>, '<?= e($u['name']) ?>')"
                                            title="Hapus">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-people display-6 d-block mb-2 opacity-25"></i>
                                <?= $hasFilter ? 'Tidak ada user yang cocok dengan filter.' : 'Belum ada data user.' ?>
                                <?php if ($hasFilter): ?>
                                <div class="mt-2">
                                    <a href="<?= BASE_URL ?>/admin/users/" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Filter
                                    </a>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
<?php include dirname(__DIR__) . '/layout/footer.php'; ?>

<form id="userDeleteForm" method="POST" action="">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="userDeleteId">
</form>

<script>
function deleteUser(id, name) {
    Swal.fire({
        title: 'Hapus User?',
        html: 'User <strong>' + name + '</strong> akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-trash me-1"></i> Ya, Hapus',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('userDeleteId').value = id;
            document.getElementById('userDeleteForm').submit();
        }
    });
}
</script>

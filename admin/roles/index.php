<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requirePermission('roles.view');

$pageTitle = 'Manajemen Role';
$db = db();

// Handle quick actions
if (isPost() && verifyCsrf()) {
    $action = pParam('action');
    $id = intVal(pParam('id'));

    if ($action === 'toggle' && Auth::can('roles.edit') && $id) {
        $role = $db->fetch("SELECT * FROM roles WHERE id = ?", [$id]);
        if ($role && $role['slug'] !== 'superadmin') {
            $newStatus = $role['is_active'] ? 0 : 1;
            $db->update('roles', ['is_active' => $newStatus], ['id' => $id]);
            flash('success', 'Status role berhasil diubah.');
        } else {
            flash('error', 'Role Superadmin tidak dapat dinonaktifkan.');
        }
    } elseif ($action === 'delete' && Auth::can('roles.delete') && $id) {
        $role = $db->fetch("SELECT * FROM roles WHERE id = ?", [$id]);
        if ($role && !in_array($role['slug'], ['superadmin', 'admin'])) {
            // Check if role has users
            $userCount = $db->fetchColumn("SELECT COUNT(*) FROM users WHERE role_id = ?", [$id]);
            if ($userCount > 0) {
                flash('error', "Role tidak dapat dihapus karena masih digunakan oleh $userCount user.");
            } else {
                $db->delete('roles', ['id' => $id]);
                auditLog('delete', 'roles', 'Hapus role: ' . $role['name']);
                flash('success', 'Role berhasil dihapus.');
            }
        } else {
            flash('error', 'Role default sistem tidak dapat dihapus.');
        }
    }
    redirect(BASE_URL . '/admin/roles/');
}

$roles = $db->fetchAll(
    "SELECT r.*,
     (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id AND u.is_active = 1) as user_count,
     (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) as perm_count
     FROM roles r ORDER BY r.id ASC"
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
            <h5><i class="bi bi-person-badge me-2 text-primary"></i><?= e($pageTitle) ?></h5>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/">Dashboard</a></li>
                    <li class="breadcrumb-item active">Role</li>
                </ol>
            </nav>
        </div>
        <?php if (Auth::can('roles.create')): ?>
        <a href="<?= BASE_URL ?>/admin/roles/form.php" class="btn btn-primary btn-md">
            <i class="bi bi-plus-circle me-1"></i><span class="d-none d-sm-inline">Tambah </span>Role
        </a>
        <?php endif; ?>
    </div>

    <?= showFlash() ?>

    <?php
    $roleStyle = [
        'superadmin' => ['color' => 'danger',  'icon' => 'bi-patch-check-fill',   'bg' => '#dc3545'],
        'admin'      => ['color' => 'primary',  'icon' => 'bi-person-badge-fill',  'bg' => '#0d6efd'],
        'operator'   => ['color' => 'success',  'icon' => 'bi-person-workspace',   'bg' => '#198754'],
        'staff'      => ['color' => 'warning',  'icon' => 'bi-person-workspace',   'bg' => '#e3f91a']
    ];
    $defaultStyle = ['color' => 'secondary', 'icon' => 'bi-person-fill', 'bg' => '#6c757d'];
    ?>

    <!--<div class="row g-3 mt-1 mb-3">-->
    <div class="row g-3 mb-4">
        <?php foreach ($roles as $role):
            $style = $roleStyle[$role['slug']] ?? $defaultStyle;
        ?>
        <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
            <div class="card border-0 shadow-sm h-100 rounded-3 overflow-hidden">

                <!-- Card Header: colored strip -->
                <div class="px-3 py-3 d-flex align-items-center justify-content-between"
                     style="background: linear-gradient(135deg, <?= $style['bg'] ?>cc, <?= $style['bg'] ?>88);">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-white d-flex align-items-center justify-content-center"
                             style="width:40px;height:40px;flex-shrink:0;">
                            <i class="bi <?= $style['icon'] ?> fs-5 text-<?= $style['color'] ?>"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-white" style="font-size:0.95rem;"><?= e($role['name']) ?></div>
                            <code class="text-muted" style="font-size:0.72rem;"><?= e($role['slug']) ?></code>
                        </div>
                    </div>
                    <span class="badge bg-white text-<?= $role['is_active'] ? 'success' : 'secondary' ?> fw-semibold">
                        <?= $role['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                    </span>
                </div>

                <!-- Card Body -->
                <div class="card-body">
                    <p class="small text-muted mb-3" style="min-height:36px;">
                        <?= e($role['description'] ?? 'Tidak ada deskripsi.') ?>
                    </p>

                    <!-- Stats -->
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="rounded-2 bg-primary-subtle text-center py-2">
                                <div class="fw-bold text-primary fs-5 lh-1"><?= numId($role['user_count']) ?></div>
                                <div class="text-muted" style="font-size:0.7rem;">User Aktif</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="rounded-2 bg-info-subtle text-center py-2">
                                <div class="fw-bold text-info fs-5 lh-1"><?= numId($role['perm_count']) ?></div>
                                <div class="text-muted" style="font-size:0.7rem;">Permission</div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex flex-wrap gap-1">
                        <?php if (Auth::can('roles.permissions')): ?>
                        <a href="<?= BASE_URL ?>/admin/roles/permissions.php?id=<?= $role['id'] ?>"
                           class="btn btn-sm btn-info flex-fill" title="Kelola Permission">
                            <i class="bi bi-shield-check me-1"></i>Permission
                        </a>
                        <?php endif; ?>
                        <?php if (Auth::can('roles.edit')): ?>
                        <a href="<?= BASE_URL ?>/admin/roles/form.php?id=<?= $role['id'] ?>"
                           class="btn btn-sm btn-warning" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php endif; ?>
                        <?php if (Auth::can('roles.edit') && $role['slug'] !== 'superadmin'): ?>
                        <form method="POST" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $role['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-<?= $role['is_active'] ? 'success' : 'secondary' ?>"
                                    title="<?= $role['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                <i class="bi bi-toggle-<?= $role['is_active'] ? 'on' : 'off' ?>"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                        <?php if (Auth::can('roles.delete') && !in_array($role['slug'], ['superadmin', 'admin'])): ?>
                        <button type="button" class="btn btn-sm btn-danger"
                                onclick="deleteRole(<?= $role['id'] ?>, '<?= e($role['name']) ?>')"
                                title="Hapus">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
        <?php endforeach; ?>

        <?php if (empty($roles)): ?>
        <div class="col-12">
            <div class="text-center py-5 text-muted">
                <i class="bi bi-person-badge display-3 d-block mb-3 opacity-25"></i>
                <p>Belum ada data role.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>
<?php include dirname(__DIR__) . '/layout/footer.php'; ?>

<form id="roleDeleteForm" method="POST" action="">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="roleDeleteId">
</form>

<script>
function deleteRole(id, name) {
    Swal.fire({
        title: 'Hapus Role?',
        html: 'Role <strong>' + name + '</strong> akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-trash me-1"></i> Ya, Hapus',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('roleDeleteId').value = id;
            document.getElementById('roleDeleteForm').submit();
        }
    });
}
</script>

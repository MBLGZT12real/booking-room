<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requirePermission('roles.permissions');

$db = db();
$id = intVal(qParam('id'));
if (!$id) { redirect(BASE_URL . '/admin/roles/'); }

$role = $db->fetch("SELECT * FROM roles WHERE id = ?", [$id]);
if (!$role) { flash('error', 'Role tidak ditemukan.'); redirect(BASE_URL . '/admin/roles/'); }

$pageTitle = 'Permission: ' . $role['name'];

if (isPost() && verifyCsrf()) {
    $selectedPermissions = $_POST['permissions'] ?? [];
    $selectedPermissions = array_map('intval', $selectedPermissions);

    // Protect superadmin: always has all permissions
    if ($role['slug'] !== 'superadmin') {
        $db->beginTransaction();
        try {
            // Remove all existing
            $db->query("DELETE FROM role_permissions WHERE role_id = ?", [$id]);
            // Insert selected
            foreach ($selectedPermissions as $permId) {
                if ($permId > 0) {
                    $db->insert('role_permissions', ['role_id' => $id, 'permission_id' => $permId]);
                }
            }
            $db->commit();
            auditLog('update', 'role_permissions', "Update permission role: {$role['name']}");
            flash('success', 'Permission role berhasil diperbarui.');
        } catch (Exception $e) {
            $db->rollback();
            flash('error', 'Gagal menyimpan permission: ' . $e->getMessage());
        }
    } else {
        flash('warning', 'Permission Superadmin tidak dapat diubah.');
    }
    redirect(BASE_URL . '/admin/roles/permissions.php?id=' . $id);
}

// Get all permissions grouped by module
$allPermissions = $db->fetchAll("SELECT * FROM permissions WHERE is_active = 1 ORDER BY module, id ASC");

// Group by module
$permsByModule = [];
foreach ($allPermissions as $perm) {
    $permsByModule[$perm['module']][] = $perm;
}

// Get current role permissions
$currentPerms = $db->fetchAll(
    "SELECT permission_id FROM role_permissions WHERE role_id = ?",
    [$id]
);
$currentPermIds = array_column($currentPerms, 'permission_id');

$moduleLabels = [
    'dashboard'    => ['label' => 'Dashboard', 'icon' => 'bi-speedometer2'],
    'roles'        => ['label' => 'Manajemen Role', 'icon' => 'bi-person-badge'],
    'users'        => ['label' => 'Manajemen User', 'icon' => 'bi-people'],
    'menus'        => ['label' => 'Manajemen Menu', 'icon' => 'bi-list-nested'],
    'settings'     => ['label' => 'Pengaturan Sistem', 'icon' => 'bi-gear'],
    'departments'  => ['label' => 'Departemen', 'icon' => 'bi-building'],
    'meeting_types'=> ['label' => 'Tipe Meeting', 'icon' => 'bi-calendar-event'],
    'rooms'        => ['label' => 'Ruangan', 'icon' => 'bi-door-open'],
    'working_hours'=> ['label' => 'Jam Kerja', 'icon' => 'bi-clock'],
    'holidays'     => ['label' => 'Hari Libur', 'icon' => 'bi-calendar-x'],
    'bookings'     => ['label' => 'Booking', 'icon' => 'bi-journal-check'],
    'checkin'      => ['label' => 'Check-in/out', 'icon' => 'bi-box-arrow-in-right'],
    'notifications'=> ['label' => 'Notifikasi', 'icon' => 'bi-bell'],
    'maillog'      => ['label' => 'Mail Log', 'icon' => 'bi-envelope-check'],
    'reports'      => ['label' => 'Laporan', 'icon' => 'bi-bar-chart-line'],
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
            <h5><i class="bi bi-shield-check me-2 text-info"></i><?= e($pageTitle) ?></h5>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/roles/">Role</a></li>
                    <li class="breadcrumb-item active">Permission</li>
                </ol>
            </nav>
        </div>
        <a href="<?= BASE_URL ?>/admin/roles/" class="btn btn-outline-secondary btn-md">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <?= showFlash() ?>

    <?php if ($role['slug'] === 'superadmin'): ?>
    <div class="alert alert-info d-flex align-items-center gap-2">
        <i class="bi bi-info-circle-fill"></i>
        Superadmin secara otomatis memiliki semua permission. Permission tidak perlu diatur manual.
    </div>
    <?php endif; ?>

    <form method="POST" action="">
        <?= csrfField() ?>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="text-muted small">
                Total permission dipilih: <strong id="selectedCount"><?= count($currentPermIds) ?></strong> / <?= count($allPermissions) ?>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAll()">
                    <span class="d-none d-sm-inline">Pilih</span> Semua
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearAll()">
                    Hapus<span class="d-none d-sm-inline"> Semua</span> 
                </button>
                <?php if ($role['slug'] !== 'superadmin'): ?>
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-save me-1"></i> Simpan <span class="d-none d-sm-inline">Permission</span>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-3">
            <?php foreach ($permsByModule as $module => $perms): ?>
            <?php $modInfo = $moduleLabels[$module] ?? ['label' => ucfirst($module), 'icon' => 'bi-circle']; ?>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent border-0 py-2 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi <?= $modInfo['icon'] ?> text-primary"></i>
                            <span class="fw-semibold small"><?= e($modInfo['label']) ?></span>
                        </div>
                        <button type="button" class="btn btn-link btn-sm p-0 text-muted"
                                onclick="toggleModule('mod_<?= $module ?>')">
                            All
                        </button>
                    </div>
                    <div class="card-body py-2">
                        <?php foreach ($perms as $perm): ?>
                        <div class="form-check mb-1">
                            <input class="form-check-input perm-check"
                                   type="checkbox" name="permissions[]"
                                   value="<?= $perm['id'] ?>"
                                   id="perm_<?= $perm['id'] ?>"
                                   data-module="mod_<?= $module ?>"
                                   <?= in_array($perm['id'], $currentPermIds) ? 'checked' : '' ?>
                                   <?= $role['slug'] === 'superadmin' ? 'disabled checked' : '' ?>
                                   onchange="updateCount()">
                            <label class="form-check-label small" for="perm_<?= $perm['id'] ?>">
                                <?= e($perm['name']) ?>
                                <?php if ($perm['description']): ?>
                                <span class="text-muted" data-bs-toggle="tooltip" title="<?= e($perm['description']) ?>">
                                    <i class="bi bi-question-circle"></i>
                                </span>
                                <?php endif; ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($role['slug'] !== 'superadmin'): ?>
        <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i> Simpan Permission
            </button>
            <a href="<?= BASE_URL ?>/admin/roles/" class="btn btn-outline-secondary">Batal</a>
        </div>
        <?php endif; ?>
    </form>

</div>
<?php include dirname(__DIR__) . '/layout/footer.php'; ?>
<script>
function updateCount() {
    const count = document.querySelectorAll('.perm-check:checked').length;
    document.getElementById('selectedCount').textContent = count;
}

function selectAll() {
    document.querySelectorAll('.perm-check:not([disabled])').forEach(cb => cb.checked = true);
    updateCount();
}

function clearAll() {
    document.querySelectorAll('.perm-check:not([disabled])').forEach(cb => cb.checked = false);
    updateCount();
}

function toggleModule(moduleId) {
    const checks = document.querySelectorAll(`.perm-check[data-module="${moduleId}"]:not([disabled])`);
    const allChecked = Array.from(checks).every(cb => cb.checked);
    checks.forEach(cb => cb.checked = !allChecked);
    updateCount();
}

// Init tooltips
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
</script>

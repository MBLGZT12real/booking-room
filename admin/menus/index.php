<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requirePermission('menus.manage');

$pageTitle = 'Kelola Menu';
$db = db();

// Handle actions
if (isPost() && verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'edit') {
        $id       = (int)($_POST['id'] ?? 0);
        $name     = sanitize($_POST['name'] ?? '');
        $url      = sanitize($_POST['url'] ?? '');
        $icon     = sanitize($_POST['icon'] ?? '');
        $parentId = (int)($_POST['parent_id'] ?? 0) ?: null;
        $permId   = (int)($_POST['permission_id'] ?? 0) ?: null;
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive  = isset($_POST['is_active']) ? 1 : 0;

        if (!$name) {
            flash('error', 'Nama menu wajib diisi.');
        } else {
            $data = [
                'name'          => $name,
                'url'           => $url,
                'icon'          => $icon,
                'parent_id'     => $parentId,
                'permission_id' => $permId,
                'sort_order'    => $sortOrder,
                'is_active'     => $isActive,
            ];

            if ($action === 'create') {
                $db->insert('menus', $data);
                auditLog('create', 'menus', "Tambah menu: $name");
                flash('success', "Menu \"$name\" berhasil ditambahkan.");
            } else {
                $db->update('menus', $data, ['id' => $id]);
                auditLog('update', 'menus', "Update menu: $name");
                flash('success', "Menu \"$name\" berhasil diperbarui.");
            }
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $menu = $db->fetch("SELECT name FROM menus WHERE id = ?", [$id]);
        if ($menu) {
            // Move children to parent
            $db->query("UPDATE menus SET parent_id = NULL WHERE parent_id = ?", [$id]);
            $db->delete('menus', ['id' => $id]);
            auditLog('delete', 'menus', "Hapus menu: {$menu['name']}");
            flash('success', "Menu berhasil dihapus.");
        }

    } elseif ($action === 'sort') {
        $order = $_POST['order'] ?? [];
        foreach ($order as $sortOrder => $menuId) {
            $db->update('menus', ['sort_order' => (int)$sortOrder], ['id' => (int)$menuId]);
        }
        jsonResponse(['success' => true]);
    }

    redirect(BASE_URL . '/admin/menus/');
}

// Get all menus with permission info
$menus = $db->fetchAll(
    "SELECT m.*, p.name as perm_name, p.slug as perm_slug,
            pm.name as parent_name
     FROM menus m
     LEFT JOIN permissions p ON m.permission_id = p.id
     LEFT JOIN menus pm ON m.parent_id = pm.id
     ORDER BY m.parent_id ASC, m.sort_order ASC, m.id ASC"
);

// Build hierarchy for display
$parents  = array_filter($menus, fn($m) => $m['parent_id'] === null);
$children = array_filter($menus, fn($m) => $m['parent_id'] !== null);

// Get permissions for dropdown
$permissions = $db->fetchAll("SELECT * FROM permissions WHERE is_active = 1 ORDER BY module, name ASC");

// Get parent menus for dropdown
$parentMenus = $db->fetchAll("SELECT id, name FROM menus WHERE parent_id IS NULL AND is_active = 1 ORDER BY sort_order ASC");
?>
<?php include dirname(__DIR__) . '/layout/header.php'; ?>
<div class="admin-wrapper">
<?php include dirname(__DIR__) . '/layout/sidebar.php'; ?>
<div class="main-content">
<?php include dirname(__DIR__) . '/layout/navbar.php'; ?>
<div class="page-content">

    <div class="page-header d-flex align-items-center justify-content-between">
        <div>
            <h5><i class="bi bi-list-nested me-2 text-primary"></i><?= e($pageTitle) ?></h5>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/">Dashboard</a></li>
                    <li class="breadcrumb-item active">Kelola Menu</li>
                </ol>
            </nav>
        </div>
        <button class="btn btn-primary btn-md" data-bs-toggle="modal" data-bs-target="#menuModal">
            <i class="bi bi-plus-circle me-1"></i><span class="d-none d-sm-inline">Tambah</span> Menu
        </button>
    </div>

    <?= showFlash() ?>

    <!-- Summary -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex gap-2">
            <span class="badge bg-primary-subtle text-primary px-3 py-2">
                <i class="bi bi-list me-1"></i><?= count($parents) ?> menu utama
            </span>
            <span class="badge bg-secondary-subtle text-secondary px-3 py-2">
                <i class="bi bi-diagram-3 me-1"></i><?= count($children) ?> submenu
            </span>
        </div>
    </div>

    <!-- Menu Hierarchy Cards -->
    <div class="d-flex flex-column gap-3 mb-3">
        <?php foreach ($parents as $parent):
            $myChildren = array_values(array_filter($children, fn($c) => $c['parent_id'] == $parent['id']));
        ?>
        <div class="card border-0 shadow-sm rounded-3 overflow-hidden">

            <!-- Parent Header -->
            <div class="card-header py-3 bg-white"
                 style="border-left:4px solid <?= $parent['is_active'] ? '#0d6efd' : '#adb5bd' ?>;">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-2 bg-primary-subtle d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:38px;height:38px;">
                            <i class="bi <?= e($parent['icon'] ?: 'bi-circle') ?> text-primary"></i>
                        </div>
                        <div>
                            <div class="fw-bold"><?= e($parent['name']) ?></div>
                            <div class="d-flex align-items-center gap-2 flex-wrap mt-1">
                                <?php if ($parent['url']): ?>
                                <code class="text-muted" style="font-size:0.72rem;"><?= e($parent['url']) ?></code>
                                <?php endif; ?>
                                <?php if ($parent['perm_slug']): ?>
                                <span class="badge bg-warning-subtle text-warning" style="font-size:0.68rem;">
                                    <i class="bi bi-shield me-1"></i><?= e($parent['perm_slug']) ?>
                                </span>
                                <?php endif; ?>
                                <span class="badge bg-secondary-subtle text-secondary" style="font-size:0.68rem;">
                                    <i class="bi bi-sort-numeric-down me-1"></i>urutan <?= $parent['sort_order'] ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge bg-<?= count($myChildren) > 0 ? 'info' : 'secondary' ?>-subtle text-<?= count($myChildren) > 0 ? 'info' : 'secondary' ?>">
                            <?= count($myChildren) ?> submenu
                        </span>
                        <span class="badge bg-<?= $parent['is_active'] ? 'success' : 'secondary' ?>">
                            <?= $parent['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                        </span>
                        <button class="btn btn-sm btn-outline-warning" title="Edit"
                                onclick="editMenu(<?= htmlspecialchars(json_encode($parent), ENT_QUOTES) ?>)">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" title="Hapus"
                                onclick="deleteMenu(<?= $parent['id'] ?>, '<?= e($parent['name']) ?>')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Children List -->
            <?php if (!empty($myChildren)): ?>
            <div class="list-group list-group-flush">
                <?php foreach ($myChildren as $child): ?>
                <div class="list-group-item px-4 py-2 <?= !$child['is_active'] ? 'bg-light' : '' ?>">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2 ps-2">
                            <i class="bi bi-arrow-return-right text-muted me-1"></i>
                            <div class="rounded-1 bg-secondary-subtle d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:28px;height:28px;">
                                <i class="bi <?= e($child['icon'] ?: 'bi-circle') ?> text-secondary" style="font-size:0.8rem;"></i>
                            </div>
                            <div>
                                <div class="fw-semibold small"><?= e($child['name']) ?></div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <?php if ($child['url']): ?>
                                    <code class="text-muted" style="font-size:0.7rem;"><?= e($child['url']) ?></code>
                                    <?php endif; ?>
                                    <?php if ($child['perm_slug']): ?>
                                    <span class="badge bg-warning-subtle text-warning" style="font-size:0.65rem;">
                                        <i class="bi bi-shield me-1"></i><?= e($child['perm_slug']) ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-1 flex-wrap">
                            <span class="badge bg-secondary-subtle text-secondary" style="font-size:0.68rem;">
                                <i class="bi bi-sort-numeric-down me-1"></i><?= $child['sort_order'] ?>
                            </span>
                            <span class="badge bg-<?= $child['is_active'] ? 'success' : 'secondary' ?>-subtle text-<?= $child['is_active'] ? 'success' : 'secondary' ?>">
                                <?= $child['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                            <button class="btn btn-sm btn-outline-warning py-0 px-2" title="Edit"
                                    onclick="editMenu(<?= htmlspecialchars(json_encode($child), ENT_QUOTES) ?>)">
                                <i class="bi bi-pencil" style="font-size:0.75rem;"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger py-0 px-2" title="Hapus"
                                    onclick="deleteMenu(<?= $child['id'] ?>, '<?= e($child['name']) ?>')">
                                <i class="bi bi-trash" style="font-size:0.75rem;"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="card-body py-2 text-center text-muted small bg-light">
                <i class="bi bi-diagram-3 me-1 opacity-50"></i>Tidak ada submenu
            </div>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>

        <?php if (empty($menus)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-list-nested display-6 d-block mb-2 opacity-25"></i>
            <p>Belum ada menu. Tambahkan menu pertama.</p>
        </div>
        <?php endif; ?>
    </div>

</div>
<?php include dirname(__DIR__) . '/layout/footer.php'; ?>

<form id="menuDeleteForm" method="POST" action="">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="menuDeleteId">
</form>

<!-- Menu Modal -->
<div class="modal fade" id="menuModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create" id="menuAction">
            <input type="hidden" name="id" id="menuId">

            <div class="modal-header">
                <h5 class="modal-title" id="menuModalTitle">Tambah Menu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nama Menu <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" id="menuName" required>
                </div>
                <div class="row g-3">
                    <div class="col-8">
                        <label class="form-label">URL</label>
                        <input type="text" class="form-control" name="url" id="menuUrl"
                               placeholder="/admin/bookings/">
                    </div>
                    <div class="col-4">
                        <label class="form-label">Urutan</label>
                        <input type="number" class="form-control" name="sort_order" id="menuSort" value="0" min="0">
                    </div>
                </div>
                <div class="mb-3 mt-3">
                    <label class="form-label">Icon Bootstrap Icons</label>
                    <div class="input-group">
                        <span class="input-group-text"><i id="iconPreview" class="bi bi-circle"></i></span>
                        <input type="text" class="form-control" name="icon" id="menuIcon"
                               placeholder="bi-calendar-check">
                    </div>
                    <div class="form-text">
                        Lihat icon: <a href="https://icons.getbootstrap.com/" target="_blank">Bootstrap Icons</a>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Parent Menu</label>
                    <select class="form-select" name="parent_id" id="menuParent">
                        <option value="">-- Tidak ada (top level) --</option>
                        <?php foreach ($parentMenus as $pm): ?>
                        <option value="<?= $pm['id'] ?>"><?= e($pm['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Permission (hak akses)</label>
                    <select class="form-select select2" name="permission_id" id="menuPerm">
                        <option value="">-- Tidak ada (selalu tampil) --</option>
                        <?php
                        $currentModule = '';
                        foreach ($permissions as $p):
                            if ($p['module'] !== $currentModule) {
                                if ($currentModule !== '') echo '</optgroup>';
                                echo '<optgroup label="' . e($p['module']) . '">';
                                $currentModule = $p['module'];
                            }
                        ?>
                        <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['slug']) ?>)</option>
                        <?php endforeach; ?>
                        <?php if ($currentModule !== '') echo '</optgroup>'; ?>
                    </select>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" id="menuActive" value="1" checked>
                    <label class="form-check-label" for="menuActive">Aktif</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function editMenu(data) {
    document.getElementById('menuModalTitle').textContent = 'Edit Menu';
    document.getElementById('menuAction').value = 'edit';
    document.getElementById('menuId').value = data.id;
    document.getElementById('menuName').value = data.name || '';
    document.getElementById('menuUrl').value = data.url || '';
    document.getElementById('menuIcon').value = data.icon || '';
    document.getElementById('menuSort').value = data.sort_order || 0;
    document.getElementById('menuActive').checked = data.is_active == 1;
    document.getElementById('iconPreview').className = 'bi ' + (data.icon || 'bi-circle');

    // Set selects
    const parentSel = document.getElementById('menuParent');
    const permSel   = document.getElementById('menuPerm');
    if (parentSel) parentSel.value = data.parent_id || '';
    if (permSel)   permSel.value   = data.permission_id || '';

    // Update Select2 if initialized
    if (typeof $ !== 'undefined' && $(permSel).data('select2')) {
        $(permSel).trigger('change');
    }

    new bootstrap.Modal(document.getElementById('menuModal')).show();
}

// Live icon preview
document.getElementById('menuIcon')?.addEventListener('input', function() {
    document.getElementById('iconPreview').className = 'bi ' + (this.value || 'bi-circle');
});

// Reset modal on close
document.getElementById('menuModal')?.addEventListener('hidden.bs.modal', function() {
    document.getElementById('menuModalTitle').textContent = 'Tambah Menu';
    document.getElementById('menuAction').value = 'create';
    document.getElementById('menuId').value = '';
    this.querySelector('form').reset();
    document.getElementById('iconPreview').className = 'bi bi-circle';
});

function deleteMenu(id, name) {
    Swal.fire({
        title: 'Hapus Menu?',
        html: 'Menu <strong>' + name + '</strong> akan dihapus. Submenu-nya akan menjadi top level.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-trash me-1"></i> Ya, Hapus',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('menuDeleteId').value = id;
            document.getElementById('menuDeleteForm').submit();
        }
    });
}
</script>

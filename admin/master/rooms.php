<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requirePermission('rooms.view');

$pageTitle = 'Data Ruangan';
$db = db();

// Toggle & Delete
if (isPost() && verifyCsrf()) {
    $action = pParam('action');
    $id = intVal(pParam('id'));

    if ($action === 'toggle' && Auth::can('rooms.manage')) {
        $room = $db->fetch("SELECT * FROM rooms WHERE id = ?", [$id]);
        if ($room) {
            $db->update('rooms', ['is_active' => $room['is_active'] ? 0 : 1], ['id' => $id]);
            flash('success', 'Status ruangan diubah.');
        }
    } elseif ($action === 'delete' && Auth::can('rooms.manage')) {
        $used = (int) $db->fetchColumn("SELECT COUNT(*) FROM bookings WHERE room_id = ? AND status IN ('pending','confirmed')", [$id]);
        if ($used > 0) {
            flash('error', "Ruangan tidak dapat dihapus karena ada $used booking aktif.");
        } else {
            $room = $db->fetch("SELECT * FROM rooms WHERE id = ?", [$id]);
            if ($room) {
                if ($room['image']) deleteFile('rooms/' . $room['image']);
                $db->delete('rooms', ['id' => $id]);
                auditLog('delete', 'rooms', 'Hapus ruangan: ' . $room['name']);
                flash('success', 'Ruangan berhasil dihapus.');
            }
        }
    }
    redirect(BASE_URL . '/admin/master/rooms.php');
}

$rooms = $db->fetchAll(
    "SELECT r.*,
     (SELECT COUNT(*) FROM bookings b WHERE b.room_id = r.id AND b.status IN ('pending','confirmed')) as active_booking_count,
     (SELECT COUNT(*) FROM bookings b WHERE b.room_id = r.id) as total_booking_count
     FROM rooms r ORDER BY r.id ASC"
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
            <h5><i class="bi bi-door-open me-2 text-primary"></i><?= e($pageTitle) ?></h5>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/">Dashboard</a></li>
                    <li class="breadcrumb-item active">Ruangan</li>
                </ol>
            </nav>
        </div>
        <?php if (Auth::can('rooms.manage')): ?>
        <a href="<?= BASE_URL ?>/admin/master/room-form.php" class="btn btn-primary btn-md">
            <i class="bi bi-plus-circle me-1"></i><span class="d-none d-sm-inline">Tambah</span> Ruangan
        </a>
        <?php endif; ?>
    </div>

    <?= showFlash() ?>

    <!-- Room Cards View -->
    <div class="row g-3 mb-4">
        <?php foreach ($rooms as $room): ?>
        <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
            <div class="card border-0 shadow-sm h-100 rounded-3 overflow-hidden">
                <!-- Room Image -->
                <div style="height:160px;overflow:hidden;background:#f8f9fa;">
                    <img src="<?= roomImageUrl($room['image']) ?>" alt="<?= e($room['name']) ?>"
                         class="w-100 h-100" style="object-fit:cover;">
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between mb-1">
                        <h6 class="fw-bold mb-0"><?= e($room['name']) ?></h6>
                        <span class="badge bg-<?= $room['is_active'] ? 'success' : 'secondary' ?> ms-1">
                            <?= $room['is_active'] ? 'Aktif' : 'Off' ?>
                        </span>
                    </div>
                    <?php if ($room['code']): ?>
                    <div class="text-muted small mb-2"><code><?= e($room['code']) ?></code></div>
                    <?php endif; ?>
                    <div class="small text-muted mb-1">
                        <i class="bi bi-geo-alt me-1"></i> <?= e($room['location'] ?? '-') ?>
                        <?php if ($room['floor']): ?> · Lantai <?= e($room['floor']) ?><?php endif; ?>
                    </div>
                    <div class="small mb-1">
                        <i class="bi bi-people me-1 text-primary"></i>
                        Kapasitas: <strong><?= numId($room['capacity']) ?> orang</strong>
                    </div>
                    <div class="small mb-2">
                        <i class="bi bi-shield-check me-1 text-<?= $room['requires_approval'] ? 'warning' : 'success' ?>"></i>
                        <?= $room['requires_approval'] ? 'Perlu Approval' : 'Otomatis Konfirmasi' ?>
                    </div>

                    <!-- Facilities -->
                    <?php if ($room['facilities']): ?>
                    <div class="mb-2">
                        <?php $facilities = jsonDecode($room['facilities']); ?>
                        <?php foreach (array_slice($facilities, 0, 3) as $fac): ?>
                        <span class="room-facility-badge"><?= e($fac) ?></span>
                        <?php endforeach; ?>
                        <?php if (count($facilities) > 3): ?>
                        <span class="room-facility-badge">+<?= count($facilities) - 3 ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top">
                        <span class="small text-muted">
                            <i class="bi bi-calendar2-check me-1"></i>
                            <?= numId($room['total_booking_count']) ?> booking
                        </span>
                        <div class="d-flex gap-1">
                            <?php if (Auth::can('rooms.manage')): ?>
                            <a href="<?= BASE_URL ?>/admin/master/room-form.php?id=<?= $room['id'] ?>"
                               class="btn btn-sm btn-outline-warning" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $room['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-<?= $room['is_active'] ? 'secondary' : 'success' ?>"
                                        title="<?= $room['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                    <i class="bi bi-<?= $room['is_active'] ? 'toggle-on' : 'toggle-off' ?>"></i>
                                </button>
                            </form>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Hapus ruangan <?= e($room['name']) ?>?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $room['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (empty($rooms)): ?>
        <div class="col-12">
            <div class="text-center py-5 text-muted">
                <i class="bi bi-door-open display-3 d-block mb-3 opacity-25"></i>
                <p>Belum ada data ruangan. Tambahkan ruangan pertama.</p>
                <?php if (Auth::can('rooms.manage')): ?>
                <a href="<?= BASE_URL ?>/admin/master/room-form.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i> Tambah Ruangan
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>
<?php include dirname(__DIR__) . '/layout/footer.php'; ?>

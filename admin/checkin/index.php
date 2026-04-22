<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requirePermission('checkin.manage');

$pageTitle = 'Manajemen Check-in/out';
$db = db();

// Handle manual override
if (isPost() && verifyCsrf()) {
    $action    = pParam('action');
    $bookingId = intVal(pParam('booking_id'));
    $booking   = $db->fetch("SELECT * FROM bookings WHERE id = ?", [$bookingId]);

    if ($booking && $action === 'manual_checkin' && $booking['status'] === 'confirmed' && !$booking['checkin_at']) {
        $db->update('bookings', ['checkin_at' => date('Y-m-d H:i:s')], ['id' => $bookingId]);
        Notification::createForAdmins('checkin', 'Check-in Manual: ' . $booking['booking_code'],
            Auth::user()['name'] . ' melakukan check-in manual untuk booking ' . $booking['booker_name'], $bookingId);
        flash('success', 'Check-in berhasil dilakukan secara manual.');
    } elseif ($booking && $action === 'manual_checkout' && $booking['checkin_at'] && !$booking['checkout_at']) {
        $db->update('bookings', [
            'checkout_at' => date('Y-m-d H:i:s'),
            'status'      => 'completed',
        ], ['id' => $bookingId]);
        Notification::createForAdmins('checkout', 'Check-out Manual: ' . $booking['booking_code'],
            Auth::user()['name'] . ' melakukan check-out manual untuk booking ' . $booking['booker_name'], $bookingId);
        flash('success', 'Check-out berhasil dilakukan secara manual.');
    }
    redirect(BASE_URL . '/admin/checkin/');
}

$today = date('Y-m-d');
$date = qParam('date', $today);
$status = qParam('status', '');

$where = ["b.booking_date = ?", "b.status IN ('confirmed', 'completed')"];
$params = [$date];

if ($status === 'not_checked_in') {
    $where[] = "b.checkin_at IS NULL AND b.status = 'confirmed'";
} elseif ($status === 'checked_in') {
    $where[] = "b.checkin_at IS NOT NULL AND b.checkout_at IS NULL";
} elseif ($status === 'completed') {
    $where[] = "b.checkout_at IS NOT NULL";
}

$bookings = $db->fetchAll(
    "SELECT b.*, r.name as room_name, r.location, r.floor, d.name as dept_name
     FROM bookings b
     JOIN rooms r ON b.room_id = r.id
     LEFT JOIN departments d ON b.department_id = d.id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY b.start_time ASC",
    $params
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
            <h5><i class="bi bi-box-arrow-in-right me-2 text-primary"></i><?= e($pageTitle) ?></h5>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/">Dashboard</a></li>
                    <li class="breadcrumb-item active">Check-in/out</li>
                </ol>
            </nav>
        </div>
    </div>

    <?= showFlash() ?>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-6 col-md-3">
                    <label class="form-label small mb-1">Tanggal</label>
                    <input type="date" class="form-control form-control-sm" name="date" value="<?= e($date) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small mb-1">Status Check-in</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <option value="not_checked_in" <?= $status === 'not_checked_in' ? 'selected' : '' ?>>Belum Check-in</option>
                        <option value="checked_in" <?= $status === 'checked_in' ? 'selected' : '' ?>>Sudah Check-in</option>
                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Selesai</option>
                    </select>
                </div>
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                    <a href="<?= BASE_URL ?>/admin/checkin/" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-0 pt-3 d-flex align-items-center justify-content-between">
            <h6 class="fw-bold mb-0">
                Jadwal <?= formatDateId($date) ?>
                <span class="badge bg-secondary ms-1"><?= count($bookings) ?></span>
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover <?= !empty($bookings) ? 'datatable' : '' ?> align-middle mb-0" data-no-filter="true">
                    <thead>
                        <tr>
                            <th class="px-4" width="50">Waktu</th>
                            <th>Ruangan</th>
                            <th>Pemesanan</th>
                            <th>Departemen</th>
                            <th class="text-center">In</th>
                            <th class="text-center">Out</th>
                            <th class="text-center">Status</th>
                            <th class="text-center no-sort" style="min-width:90px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td class="px-3 fw-semibold small text-nowrap">
                                <?= formatTimeRange($b['start_time'], $b['end_time']) ?>
                            </td>
                            <td>
                                <div class="fw-semibold small"><?= e($b['room_name']) ?></div>
                                <div class="text-muted tiny"><?= e($b['location']) ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold small"><?= e($b['booker_name']) ?></div>
                                <div class="text-muted tiny"><?= e($b['booker_nik'] ?? $b['booker_email']) ?></div>
                                <code class="text-primary tiny"><?= e($b['booking_code']) ?></code>
                            </td>
                            <td class="small text-muted"><?= e($b['dept_name'] ?? '-') ?></td>
                            <td class="text-center small">
                                <?php if ($b['checkin_at']): ?>
                                <span class="text-success fw-semibold">
                                    <i class="bi bi-check-circle me-1"></i>
                                    <?= date('H:i', strtotime($b['checkin_at'])) ?>
                                </span>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center small">
                                <?php if ($b['checkout_at']): ?>
                                <span class="text-warning fw-semibold">
                                    <i class="bi bi-check-circle me-1"></i>
                                    <?= date('H:i', strtotime($b['checkout_at'])) ?>
                                </span>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php
                                if ($b['checkout_at']) echo '<span class="badge bg-success">Selesai</span>';
                                elseif ($b['checkin_at']) echo '<span class="badge bg-info">Dalam Rapat</span>';
                                else echo '<span class="badge bg-warning">Belum Hadir</span>';
                                ?>
                            </td>
                            <td class="text-center">
                                <?php if (!$b['checkin_at']): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Check-in manual untuk <?= e($b['booker_name']) ?>?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="manual_checkin">
                                    <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success">
                                        <i class="bi bi-box-arrow-in-right me-1"></i> Check-in
                                    </button>
                                </form>
                                <?php elseif ($b['checkin_at'] && !$b['checkout_at']): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Check-out manual?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="manual_checkout">
                                    <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-warning text-dark">
                                        <i class="bi bi-box-arrow-left me-1"></i> Check-out
                                    </button>
                                </form>
                                <?php else: ?>
                                <span class="text-muted small">Selesai</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($bookings)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">Tidak ada jadwal pada tanggal ini</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
<?php include dirname(__DIR__) . '/layout/footer.php'; ?>

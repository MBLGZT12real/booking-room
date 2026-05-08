<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requirePermission('bookings.view');

$db = db();
$id = intVal(qParam('id'));
if (!$id) redirect(BASE_URL . '/admin/bookings/');

$booking = $db->fetch(
    "SELECT b.*, r.name as room_name, r.code as room_code, r.location, r.floor,
     r.capacity as room_capacity, r.requires_approval,
     d.name as dept_name, mt.name as type_name,
     u.name as approver_name,
     cu.name as canceller_name
     FROM bookings b
     JOIN rooms r ON b.room_id = r.id
     LEFT JOIN departments d ON b.department_id = d.id
     LEFT JOIN meeting_types mt ON b.meeting_type_id = mt.id
     LEFT JOIN users u ON b.approved_by = u.id
     LEFT JOIN users cu ON b.cancelled_by = cu.id
     WHERE b.id = ?",
    [$id]
);

if (!$booking) { flash('error', 'Booking tidak ditemukan.'); redirect(BASE_URL . '/admin/bookings/'); }

$pageTitle = 'Detail Booking: ' . $booking['booking_code'];

// Handle actions
if (isPost() && verifyCsrf()) {
    $action = pParam('action');

    if ($action === 'approve' && Auth::can('bookings.approve') && $booking['status'] === 'pending') {

        // Acquire advisory lock (same lock key used by createBooking) to prevent
        // two admins simultaneously approving conflicting pending bookings.
        $lockKey = 'rb_' . $booking['room_id'] . '_' . str_replace('-', '', $booking['booking_date']);
        $locked  = (bool) $db->fetchColumn("SELECT GET_LOCK(?, 5)", [$lockKey]);

        if (!$locked) {
            flash('error', 'Sistem sedang memproses permintaan lain. Silakan coba kembali.');
            redirect(BASE_URL . '/admin/bookings/detail.php?id=' . $id);
        }

        // Re-fetch current status — another admin may have acted in the meantime.
        $freshStatus = (string) $db->fetchColumn("SELECT status FROM bookings WHERE id = ?", [$id]);
        if ($freshStatus !== 'pending') {
            $db->fetchColumn("SELECT RELEASE_LOCK(?)", [$lockKey]);
            flash('warning', 'Status booking sudah berubah. Silakan refresh halaman.');
            redirect(BASE_URL . '/admin/bookings/detail.php?id=' . $id);
        }

        // Check whether another booking for the SAME room + date + overlapping time
        // was already confirmed (covers race condition at submission time AND
        // the case where two pending bookings slipped through concurrently).
        $conflict = $db->fetch(
            "SELECT booking_code FROM bookings
             WHERE room_id = ? AND booking_date = ? AND status = 'confirmed'
               AND id != ? AND start_time < ? AND end_time > ?",
            [$booking['room_id'], $booking['booking_date'], $id,
             $booking['end_time'], $booking['start_time']]
        );

        if ($conflict) {
            $db->fetchColumn("SELECT RELEASE_LOCK(?)", [$lockKey]);
            flash('error',
                'Tidak dapat menyetujui: slot ' . formatTimeRange($booking['start_time'], $booking['end_time']) .
                ' sudah dikonfirmasi untuk booking ' . $conflict['booking_code'] .
                '. Booking ini harus ditolak.'
            );
            redirect(BASE_URL . '/admin/bookings/detail.php?id=' . $id);
        }

        $db->update('bookings', [
            'status'      => 'confirmed',
            'approved_by' => Auth::id(),
            'approved_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        // Release lock now — update is committed, subsequent requests will see confirmed status.
        $db->fetchColumn("SELECT RELEASE_LOCK(?)", [$lockKey]);

        $booking = array_merge($booking, ['status' => 'confirmed', 'approved_by' => Auth::id()]);
        $room    = $db->fetch("SELECT * FROM rooms WHERE id = ?", [$booking['room_id']]);

        auditLog('approve', 'bookings', 'Approve booking: ' . $booking['booking_code']);
        flash('success', 'Booking berhasil disetujui. Email konfirmasi telah dikirim.');

        // Redirect to browser immediately, then send email in background
        $redirectUrl = BASE_URL . '/admin/bookings/detail.php?id=' . $id;
        header('Location: ' . $redirectUrl, true, 302);
        header('Connection: close');
        header('Content-Length: 0');
        ini_set('zlib.output_compression', 'Off');
        ob_end_clean();
        flush();
        ignore_user_abort(true);
        set_time_limit(30);
        Mailer::sendBookingApproved($booking, $room);
        Notification::createForAdmins('approved',
            'Booking Disetujui: ' . $booking['booking_code'],
            Auth::user()['name'] . ' menyetujui booking ' . $booking['booker_name'],
            $id,
            'bookings.approve'
        );
        exit;

    } elseif ($action === 'reject' && Auth::can('bookings.approve') && $booking['status'] === 'pending') {
        $reason = trim(pParam('reject_reason'));
        if (empty($reason)) { flash('error', 'Alasan penolakan harus diisi.'); redirect(BASE_URL . '/admin/bookings/detail.php?id=' . $id); }

        $db->update('bookings', [
            'status'        => 'rejected',
            'reject_reason' => $reason,
            'approved_by'   => Auth::id(),
            'approved_at'   => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        $room = $db->fetch("SELECT * FROM rooms WHERE id = ?", [$booking['room_id']]);

        auditLog('reject', 'bookings', 'Reject booking: ' . $booking['booking_code']);
        flash('warning', 'Booking berhasil ditolak.');

        $redirectUrl = BASE_URL . '/admin/bookings/detail.php?id=' . $id;
        header('Location: ' . $redirectUrl, true, 302);
        header('Connection: close');
        header('Content-Length: 0');
        ini_set('zlib.output_compression', 'Off');
        ob_end_clean();
        flush();
        ignore_user_abort(true);
        set_time_limit(30);
        Mailer::sendBookingRejected($booking, $room, $reason);
        Notification::createForAdmins('rejected', 'Booking Ditolak: ' . $booking['booking_code'],
            Auth::user()['name'] . ' menolak booking ' . $booking['booker_name'], $id, 'bookings.approve');
        exit;

    } elseif ($action === 'cancel' && Auth::can('bookings.cancel') && in_array($booking['status'], ['pending', 'confirmed'])) {
        $cancelReason = trim(pParam('cancel_reason'));
        $db->update('bookings', [
            'status'        => 'cancelled',
            'cancel_reason' => $cancelReason ?: null,
            'cancelled_by'  => Auth::id(),
            'cancelled_at'  => date('Y-m-d H:i:s'),
        ], ['id' => $id]);
        Notification::createForAdmins('cancelled', 'Booking Dibatalkan: ' . $booking['booking_code'],
            'Booking ' . $booking['booker_name'] . ' dibatalkan oleh admin', $id, 'bookings.approve');
        auditLog('cancel', 'bookings', 'Cancel booking: ' . $booking['booking_code']);
        flash('warning', 'Booking dibatalkan. Email notifikasi telah dikirim ke pemesanan.');

        $room            = $db->fetch("SELECT * FROM rooms WHERE id = ?", [$booking['room_id']]);
        $cancelledByName = Auth::user()['name'];

        $redirectUrl = BASE_URL . '/admin/bookings/detail.php?id=' . $id;
        header('Location: ' . $redirectUrl, true, 302);
        header('Connection: close');
        header('Content-Length: 0');
        ini_set('zlib.output_compression', 'Off');
        ob_end_clean();
        flush();
        ignore_user_abort(true);
        set_time_limit(30);
        Mailer::sendBookingCancelled($booking, $room, $cancelledByName);
        exit;

    } elseif ($action === 'cancel_series' && Auth::can('bookings.cancel') && !empty($booking['recurring_group_id'])) {
        $today = date('Y-m-d');
        $scope = pParam('cancel_scope', 'upcoming');

        $cancelledAt = date('Y-m-d H:i:s');
        $cancelledId = Auth::id();

        if ($scope === 'all') {
            $affectedBookings = $db->fetchAll(
                "SELECT b.*, r.name as room_name FROM bookings b
                 JOIN rooms r ON b.room_id = r.id
                 WHERE b.recurring_group_id = ? AND b.status IN ('pending','confirmed')",
                [$booking['recurring_group_id']]
            );
            $stmt = $db->query(
                "UPDATE bookings SET status = 'cancelled', cancelled_by = ?, cancelled_at = ?
                 WHERE recurring_group_id = ? AND status IN ('pending','confirmed')",
                [$cancelledId, $cancelledAt, $booking['recurring_group_id']]
            );
        } else {
            $affectedBookings = $db->fetchAll(
                "SELECT b.*, r.name as room_name FROM bookings b
                 JOIN rooms r ON b.room_id = r.id
                 WHERE b.recurring_group_id = ? AND b.status IN ('pending','confirmed') AND b.booking_date >= ?",
                [$booking['recurring_group_id'], $today]
            );
            $stmt = $db->query(
                "UPDATE bookings SET status = 'cancelled', cancelled_by = ?, cancelled_at = ?
                 WHERE recurring_group_id = ? AND status IN ('pending','confirmed') AND booking_date >= ?",
                [$cancelledId, $cancelledAt, $booking['recurring_group_id'], $today]
            );
        }
        $count           = $stmt->rowCount();
        $cancelledByName = Auth::user()['name'];
        auditLog('cancel', 'bookings', 'Cancel recurring series (' . $scope . '): ' . $booking['recurring_group_id']);
        flash('warning', $count . ' booking dalam seri berulang telah dibatalkan. Email notifikasi dikirim.');

        $redirectUrl = BASE_URL . '/admin/bookings/?recurring_group=' . urlencode($booking['recurring_group_id']);
        header('Location: ' . $redirectUrl, true, 302);
        header('Connection: close');
        header('Content-Length: 0');
        ini_set('zlib.output_compression', 'Off');
        ob_end_clean();
        flush();
        ignore_user_abort(true);
        set_time_limit(60);
        foreach ($affectedBookings as $ab) {
            Mailer::sendBookingCancelled($ab, [], $cancelledByName);
        }
        exit;

    } elseif ($action === 'admin_checkin' && Auth::can('checkin.manage') && $booking['status'] === 'confirmed' && !$booking['checkin_at']) {
        $result = BookingHelper::checkIn($booking['token'], true);
        if ($result['success']) {
            auditLog('checkin', 'bookings', 'Admin check-in: ' . $booking['booking_code']);
            flash('success', 'Check-in berhasil dicatat oleh admin.');
        } else {
            flash('error', $result['message']);
        }
        redirect(BASE_URL . '/admin/bookings/detail.php?id=' . $id);

    } elseif ($action === 'admin_checkout' && Auth::can('checkin.manage') && $booking['checkin_at'] && !$booking['checkout_at']) {
        $result = BookingHelper::checkOut($booking['token']);
        if ($result['success']) {
            auditLog('checkout', 'bookings', 'Admin check-out: ' . $booking['booking_code']);
            flash('success', 'Check-out berhasil dicatat oleh admin.');
        } else {
            flash('error', $result['message']);
        }
        redirect(BASE_URL . '/admin/bookings/detail.php?id=' . $id);
    }
}

// Refresh after action
$booking = $db->fetch(
    "SELECT b.*, r.name as room_name, r.location, r.floor, r.capacity as room_capacity,
     d.name as dept_name, mt.name as type_name,
     u.name as approver_name,
     cu.name as canceller_name
     FROM bookings b
     JOIN rooms r ON b.room_id = r.id
     LEFT JOIN departments d ON b.department_id = d.id
     LEFT JOIN meeting_types mt ON b.meeting_type_id = mt.id
     LEFT JOIN users u ON b.approved_by = u.id
     LEFT JOIN users cu ON b.cancelled_by = cu.id
     WHERE b.id = ?", [$id]
);

// Load recurring series summary if this booking belongs to one
$seriesInfo = null;
if (!empty($booking['recurring_group_id'])) {
    $seriesInfo = $db->fetch(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status IN ('pending','confirmed') AND booking_date >= CURDATE() THEN 1 ELSE 0 END) AS upcoming_active,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
            MIN(booking_date) AS first_date,
            MAX(booking_date) AS last_date
         FROM bookings WHERE recurring_group_id = ?",
        [$booking['recurring_group_id']]
    );
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
            <h5><i class="bi bi-journal-text me-2 text-primary"></i>Detail Booking</h5>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/bookings/">Booking</a></li>
                    <li class="breadcrumb-item active"><?= e($booking['booking_code']) ?></li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <?= statusBadge($booking['status']) ?>
            <a href="<?= BASE_URL ?>/admin/bookings/" class="btn btn-md btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
        </div>
    </div>

    <?= showFlash() ?>

    <div class="row g-3">
        <!-- Detail Left -->
        <div class="col-12 col-lg-8">
            <!-- Booking Info -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h6 class="fw-bold mb-0">
                        <i class="bi bi-info-circle me-2 text-primary"></i>Informasi Booking
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6 col-md-4">
                            <div class="text-muted small">Kode Booking</div>
                            <div class="fw-bold"><code><?= e($booking['booking_code']) ?></code></div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="text-muted small">Status</div>
                            <div><?= statusBadge($booking['status']) ?></div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="text-muted small">Dibuat</div>
                            <div class="small"><?= formatDatetimeId($booking['created_at']) ?></div>
                        </div>
                        <div class="col-12"><hr class="my-2"></div>
                        <div class="col-6 col-md-4">
                            <div class="text-muted small">Ruangan</div>
                            <div class="fw-semibold"><?= e($booking['room_name']) ?></div>
                            <div class="text-muted small"><?= e($booking['location'] . ' - Lantai ' . $booking['floor']) ?></div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="text-muted small">Tanggal</div>
                            <div class="fw-semibold"><?= formatDateId($booking['booking_date']) ?></div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="text-muted small">Waktu</div>
                            <div class="fw-semibold"><?= formatTimeRange($booking['start_time'], $booking['end_time']) ?></div>
                            <div class="text-muted small"><?= formatDuration($booking['start_time'], $booking['end_time']) ?></div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="text-muted small">Tipe Meeting</div>
                            <div><?= e($booking['type_name'] ?? '-') ?></div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="text-muted small">Kapasitas Dibutuhkan</div>
                            <div><?= $booking['capacity_needed'] ? numId($booking['capacity_needed']) . ' orang' : '-' ?></div>
                        </div>
                    </div>

                    <?php if ($booking['purpose'] || $booking['notes_equipment'] || $booking['notes_other']): ?>
                    <hr>
                    <?php if ($booking['purpose']): ?>
                    <div class="mb-2">
                        <div class="text-muted small">Keperluan/Agenda</div>
                        <div><?= nl2br(e($booking['purpose'])) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($booking['notes_equipment']): ?>
                    <div class="mb-2">
                        <div class="text-muted small"><i class="bi bi-tools me-1"></i>Catatan Alat Tambahan</div>
                        <div><?= nl2br(e($booking['notes_equipment'])) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($booking['notes_other']): ?>
                    <div class="mb-0">
                        <div class="text-muted small"><i class="bi bi-chat-text me-1"></i>Catatan Lainnya</div>
                        <div><?= nl2br(e($booking['notes_other'])) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Booker Info -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h6 class="fw-bold mb-0">
                        <i class="bi bi-person me-2 text-primary"></i>Data Pemesanan
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6 col-md-3">
                            <div class="text-muted small">Nama</div>
                            <div class="fw-semibold"><?= e($booking['booker_name']) ?></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-muted small">NIK</div>
                            <div><?= e($booking['booker_nik'] ?? '-') ?></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-muted small">Email</div>
                            <div><a href="mailto:<?= e($booking['booker_email']) ?>"><?= e($booking['booker_email']) ?></a></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-muted small">HP</div>
                            <div><?= e($booking['booker_phone'] ?? '-') ?></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-muted small">Departemen</div>
                            <div><?= e($booking['dept_name'] ?? '-') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Check-in/out Status -->
            <?php if ($booking['status'] === 'confirmed' || $booking['status'] === 'completed'): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h6 class="fw-bold mb-0">
                        <i class="bi bi-box-arrow-in-right me-2 text-primary"></i>Status Check-in/out
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-3 text-center">
                        <div class="col-6">
                            <div class="p-3 rounded-3 <?= $booking['checkin_at'] ? 'bg-success-subtle' : 'bg-light' ?>">
                                <i class="bi bi-box-arrow-in-right <?= $booking['checkin_at'] ? 'text-success' : 'text-muted' ?> fs-3 d-block mb-1"></i>
                                <div class="fw-semibold small">Check-in</div>
                                <div class="small <?= $booking['checkin_at'] ? 'text-success' : 'text-muted' ?>">
                                    <?= $booking['checkin_at'] ? formatDatetimeId($booking['checkin_at']) : 'Belum check-in' ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded-3 <?= $booking['checkout_at'] ? 'bg-warning-subtle' : 'bg-light' ?>">
                                <i class="bi bi-box-arrow-left <?= $booking['checkout_at'] ? 'text-warning' : 'text-muted' ?> fs-3 d-block mb-1"></i>
                                <div class="fw-semibold small">Check-out</div>
                                <div class="small <?= $booking['checkout_at'] ? 'text-warning' : 'text-muted' ?>">
                                    <?= $booking['checkout_at'] ? formatDatetimeId($booking['checkout_at']) : 'Belum check-out' ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Checkout Reminder Status -->
                    <?php if ($booking['checkin_at']): ?>
                    <?php $reminderSent = !empty($booking['checkout_reminder_sent_at']); ?>
                    <div class="mt-3 d-flex align-items-center gap-2 p-2 rounded-3 <?= $reminderSent ? 'bg-warning-subtle' : 'bg-light' ?>">
                        <i class="bi bi-<?= $reminderSent ? 'bell-fill text-warning' : 'bell text-muted' ?> fs-5 flex-shrink-0"></i>
                        <div class="small flex-grow-1">
                            <?php if ($reminderSent): ?>
                            Email reminder check-out dikirim pukul
                            <strong><?= date('H:i', strtotime($booking['checkout_reminder_sent_at'])) ?></strong>
                            <?= $booking['checkout_at'] ? '(sudah check-out)' : '<span class="text-warning fw-semibold">— belum check-out</span>' ?>
                            <?php else: ?>
                            <span class="text-muted">Email reminder check-out belum dikirim</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Token Link -->
                    <?php $checkinUrl = BASE_URL . '/checkin.php?token=' . $booking['token']; ?>
                    <div class="mt-3 p-3 bg-light rounded-3">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <div>
                                <div class="fw-semibold small">Link Check-in Karyawan:</div>
                                <div class="small text-muted text-break"><?= e($checkinUrl) ?></div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary flex-shrink-0"
                                    onclick="copyToClipboard('<?= e($checkinUrl) ?>')">
                                <i class="bi bi-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Action Panel Right -->
        <div class="col-12 col-lg-4">
            <!-- Actions -->
            <?php if (in_array($booking['status'], ['pending', 'confirmed'])): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h6 class="fw-bold mb-0">Aksi</h6>
                </div>
                <div class="card-body d-grid gap-2">
                    <?php if ($booking['status'] === 'pending' && Auth::can('bookings.approve')): ?>
                    <form method="POST" action="">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn btn-success w-100"
                                onclick="return confirm('Setujui booking ini?')">
                            <i class="bi bi-check-circle me-1"></i> Setujui Booking
                        </button>
                    </form>

                    <button type="button" class="btn btn-outline-danger w-100"
                            data-bs-toggle="modal" data-bs-target="#rejectModal">
                        <i class="bi bi-x-circle me-1"></i> Tolak Booking
                    </button>
                    <?php endif; ?>

                    <?php if (Auth::can('bookings.cancel') && in_array($booking['status'], ['pending', 'confirmed'])): ?>
                    <button type="button" class="btn btn-outline-secondary w-100"
                            data-bs-toggle="modal" data-bs-target="#cancelModal">
                        <i class="bi bi-dash-circle me-1"></i> Batalkan Booking
                    </button>
                    <?php endif; ?>

                    <?php if (Auth::can('checkin.manage') && $booking['status'] === 'confirmed'): ?>
                    <hr class="my-1">
                    <div class="text-muted small mb-1">
                        <i class="bi bi-shield-check me-1"></i>Check-in/out oleh Admin
                    </div>
                    <?php if (!$booking['checkin_at']): ?>
                    <form method="POST" action="">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="admin_checkin">
                        <button type="submit" class="btn btn-outline-success w-100"
                                onclick="return confirm('Tandai check-in untuk booking ini?')">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Tandai Check-in
                        </button>
                    </form>
                    <?php elseif ($booking['checkin_at'] && !$booking['checkout_at']): ?>
                    <form method="POST" action="">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="admin_checkout">
                        <button type="submit" class="btn btn-outline-warning w-100"
                                onclick="return confirm('Tandai check-out untuk booking ini?')">
                            <i class="bi bi-box-arrow-right me-1"></i> Tandai Check-out
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Cancel Reason (if cancelled) -->
            <?php if ($booking['status'] === 'cancelled' && $booking['cancel_reason']): ?>
            <div class="card border-0 shadow-sm border-secondary mb-3">
                <div class="card-header bg-secondary-subtle border-0 pt-3">
                    <h6 class="fw-bold mb-0 text-secondary">
                        <i class="bi bi-dash-circle me-2"></i>Alasan Pembatalan
                    </h6>
                </div>
                <div class="card-body">
                    <p class="mb-0"><?= nl2br(e($booking['cancel_reason'])) ?></p>
                    <?php if ($booking['canceller_name']): ?>
                    <div class="text-muted small mt-2">Oleh: <?= e($booking['canceller_name']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Reject Reason (if rejected) -->
            <?php if ($booking['status'] === 'rejected' && $booking['reject_reason']): ?>
            <div class="card border-0 shadow-sm border-danger mb-3">
                <div class="card-header bg-danger-subtle border-0 pt-3">
                    <h6 class="fw-bold mb-0 text-danger">
                        <i class="bi bi-x-circle me-2"></i>Alasan Penolakan
                    </h6>
                </div>
                <div class="card-body">
                    <p class="mb-0"><?= nl2br(e($booking['reject_reason'])) ?></p>
                    <?php if ($booking['approver_name']): ?>
                    <div class="text-muted small mt-2">Oleh: <?= e($booking['approver_name']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Approval Info (if confirmed) -->
            <?php if ($booking['status'] === 'confirmed' && $booking['approved_by']): ?>
            <div class="card border-0 shadow-sm border-success mb-3">
                <div class="card-body">
                    <div class="text-success small">
                        <i class="bi bi-check-circle me-1"></i>
                        Disetujui oleh <strong><?= e($booking['approver_name']) ?></strong>
                        <?php if ($booking['approved_at']): ?>
                        <div class="text-muted"><?= formatDatetimeId($booking['approved_at']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recurring Series Info -->
            <?php if ($seriesInfo): ?>
            <div class="card border-0 shadow-sm mb-3" style="border-left: 3px solid #6610f2 !important;">
                <div class="card-header bg-transparent border-0 pt-3 pb-1">
                    <h6 class="fw-bold mb-0" style="color:#6610f2;">
                        <i class="bi bi-arrow-repeat me-2"></i>Seri Berulang
                    </h6>
                </div>
                <div class="card-body pt-2">
                    <!-- Series stats -->
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="text-center p-2 rounded bg-light">
                                <div class="fw-bold fs-5"><?= $seriesInfo['total'] ?></div>
                                <div class="text-muted" style="font-size:.72rem;">Total</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-2 rounded" style="background:#e8f5e9;">
                                <div class="fw-bold fs-5 text-success"><?= $seriesInfo['upcoming_active'] ?></div>
                                <div class="text-muted" style="font-size:.72rem;">Akan Datang</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-2 rounded" style="background:#fff3e0;">
                                <div class="fw-bold fs-5 text-warning"><?= $seriesInfo['cancelled_count'] ?></div>
                                <div class="text-muted" style="font-size:.72rem;">Dibatalkan</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-2 rounded" style="background:#e3f2fd;">
                                <div class="fw-bold fs-5 text-info"><?= $seriesInfo['completed_count'] ?></div>
                                <div class="text-muted" style="font-size:.72rem;">Selesai</div>
                            </div>
                        </div>
                    </div>
                    <div class="small text-muted mb-3">
                        <i class="bi bi-calendar-range me-1"></i>
                        <?= formatDateId($seriesInfo['first_date'], false) ?> &ndash;
                        <?= formatDateId($seriesInfo['last_date'], false) ?>
                    </div>

                    <!-- Actions -->
                    <a href="<?= BASE_URL ?>/admin/bookings/?recurring_group=<?= urlencode($booking['recurring_group_id']) ?>"
                       class="btn btn-sm btn-outline-secondary w-100 mb-2">
                        <i class="bi bi-list-ul me-1"></i>Lihat Semua di Seri Ini
                    </a>

                    <?php if (Auth::can('bookings.cancel') && $seriesInfo['upcoming_active'] > 0): ?>
                    <button type="button" class="btn btn-sm btn-outline-danger w-100"
                            data-bs-toggle="modal" data-bs-target="#cancelSeriesModal">
                        <i class="bi bi-x-octagon me-1"></i>Batalkan Seri...
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Timeline -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h6 class="fw-bold mb-0">Timeline</h6>
                </div>
                <div class="card-body py-2">
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-dot bg-primary"></div>
                            <div class="timeline-content">
                                <div class="fw-semibold small">Booking Dibuat</div>
                                <div class="text-muted tiny"><?= formatDatetimeId($booking['created_at']) ?></div>
                            </div>
                        </div>
                        <?php if ($booking['approved_at']): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot bg-<?= $booking['status'] === 'rejected' ? 'danger' : 'success' ?>"></div>
                            <div class="timeline-content">
                                <div class="fw-semibold small">
                                    <?= $booking['status'] === 'rejected' ? 'Ditolak' : 'Disetujui' ?>
                                </div>
                                <?php if ($booking['approver_name']): ?>
                                <div class="text-muted tiny">Oleh: <?= e($booking['approver_name']) ?></div>
                                <?php endif; ?>
                                <?php if ($booking['status'] === 'rejected' && $booking['reject_reason']): ?>
                                <div class="text-muted tiny fst-italic">"<?= e(truncate($booking['reject_reason'], 60)) ?>"</div>
                                <?php endif; ?>
                                <div class="text-muted tiny"><?= formatDatetimeId($booking['approved_at']) ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($booking['checkin_at']): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot bg-info"></div>
                            <div class="timeline-content">
                                <div class="fw-semibold small">Check-in</div>
                                <div class="text-muted tiny"><?= formatDatetimeId($booking['checkin_at']) ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($booking['checkout_at']): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot bg-warning"></div>
                            <div class="timeline-content">
                                <div class="fw-semibold small">Check-out</div>
                                <div class="text-muted tiny"><?= formatDatetimeId($booking['checkout_at']) ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($booking['status'] === 'cancelled' && $booking['cancelled_at']): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot bg-secondary"></div>
                            <div class="timeline-content">
                                <div class="fw-semibold small text-secondary">Dibatalkan</div>
                                <?php if ($booking['canceller_name']): ?>
                                <div class="text-muted tiny">Oleh: <?= e($booking['canceller_name']) ?></div>
                                <?php endif; ?>
                                <?php if ($booking['cancel_reason']): ?>
                                <div class="text-muted tiny fst-italic">"<?= e(truncate($booking['cancel_reason'], 60)) ?>"</div>
                                <?php endif; ?>
                                <div class="text-muted tiny"><?= formatDatetimeId($booking['cancelled_at']) ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
<?php include dirname(__DIR__) . '/layout/footer.php'; ?>

<!-- Reject Modal -->
<?php if ($booking['status'] === 'pending' && Auth::can('bookings.approve')): ?>
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="reject">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">
                        <i class="bi bi-x-circle me-2"></i>Tolak Booking
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Booking <strong><?= e($booking['booking_code']) ?></strong> akan ditolak dan email pemberitahuan akan dikirim ke pemesanan.</p>
                    <div class="mb-0">
                        <label class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="reject_reason" rows="3" required
                                  placeholder="Jelaskan alasan penolakan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-circle me-1"></i> Tolak Booking
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Cancel Modal -->
<?php if (Auth::can('bookings.cancel') && in_array($booking['status'], ['pending', 'confirmed'])): ?>
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="cancel">
                <div class="modal-header">
                    <h5 class="modal-title text-secondary">
                        <i class="bi bi-dash-circle me-2"></i>Batalkan Booking
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Booking <strong><?= e($booking['booking_code']) ?></strong> akan dibatalkan dan email pemberitahuan akan dikirim ke pemesanan.</p>
                    <div class="mb-0">
                        <label class="form-label">Alasan Pembatalan <span class="text-muted fw-normal small">(opsional)</span></label>
                        <textarea class="form-control" name="cancel_reason" rows="3"
                                  placeholder="Jelaskan alasan pembatalan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-secondary">
                        <i class="bi bi-dash-circle me-1"></i> Batalkan Booking
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Cancel Series Modal -->
<?php if ($seriesInfo && Auth::can('bookings.cancel') && $seriesInfo['upcoming_active'] > 0): ?>
<div class="modal fade" id="cancelSeriesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="cancel_series">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-danger">
                        <i class="bi bi-x-octagon me-2"></i>Batalkan Seri Berulang
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        Booking ini adalah bagian dari seri berulang dengan
                        <strong><?= $seriesInfo['upcoming_active'] ?> booking</strong> yang masih aktif.
                        Pilih cakupan pembatalan:
                    </p>
                    <div class="d-grid gap-2">
                        <label class="border rounded p-3 cursor-pointer" style="cursor:pointer;">
                            <div class="d-flex align-items-start gap-2">
                                <input type="radio" name="cancel_scope" value="upcoming" checked class="mt-1">
                                <div>
                                    <div class="fw-semibold">Batalkan yang Belum Terjadi</div>
                                    <div class="text-muted small">
                                        Hanya membatalkan booking berstatus aktif mulai hari ini ke depan.
                                        Booking yang sudah selesai atau sudah lewat tidak terpengaruh.
                                    </div>
                                </div>
                            </div>
                        </label>
                        <label class="border rounded p-3" style="cursor:pointer;">
                            <div class="d-flex align-items-start gap-2">
                                <input type="radio" name="cancel_scope" value="all" class="mt-1">
                                <div>
                                    <div class="fw-semibold">Batalkan Seluruh Seri</div>
                                    <div class="text-muted small">
                                        Membatalkan semua booking aktif di seri ini, termasuk yang sudah terdaftar
                                        sebelum hari ini tapi belum dilaksanakan.
                                    </div>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-octagon me-1"></i>Batalkan Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.timeline { position: relative; padding-left: 1.5rem; }
.timeline::before { content:''; position:absolute; left:9px; top:0; bottom:0; width:2px; background:#e9ecef; }
.timeline-item { display:flex; align-items:flex-start; gap:0.75rem; margin-bottom:1rem; position:relative; }
.timeline-dot { width:18px; height:18px; border-radius:50%; flex-shrink:0; margin-left:-1.5rem; margin-top:2px; border:2px solid #fff; box-shadow:0 0 0 2px #dee2e6; }
.timeline-content { flex:1; }
</style>
<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        Swal.fire({ icon:'success', title:'Disalin!', text:'Link berhasil disalin ke clipboard.', timer:1500, showConfirmButton:false });
    });
}
</script>

<?php
ini_set('zlib.output_compression', 'Off');
ob_start();
require_once __DIR__ . '/includes/bootstrap.php';

$db = db();
$token = sanitize($_GET['token'] ?? '');

$appName     = getSetting('app_name', 'Sistem Booking Ruangan');
$primaryColor = getSetting('app_color_primary', '#0d6efd');

// Action result
$result             = null;
$pendingCheckinMail = null;

if (!$token) {
    $error = 'Token tidak valid. Pastikan link check-in Anda benar.';
} else {
    // Get booking by token
    $booking = $db->fetch(
        "SELECT b.*, r.name as room_name, r.location, r.floor, r.capacity,
                d.name as dept_name, mt.name as meeting_type_name
         FROM bookings b
         JOIN rooms r ON b.room_id = r.id
         LEFT JOIN departments d ON b.department_id = d.id
         LEFT JOIN meeting_types mt ON b.meeting_type_id = mt.id
         WHERE b.token = ?",
        [$token]
    );

    if (!$booking) {
        $error = 'Link check-in tidak ditemukan atau sudah tidak berlaku.';
    } else {
        $error = null;

        // Handle POST action
        if (isPost()) {
            if (!verifyCsrf()) {
                $result = ['success' => false, 'message' => 'Request tidak valid. Silakan muat ulang halaman.'];
            }
            $action = $_POST['action'] ?? '';

            if ($action === 'checkin') {
                $result = BookingHelper::checkIn($token);
                if ($result['success']) {
                    // Re-fetch booking to get updated checkin_at FIRST
                    $booking = $db->fetch(
                        "SELECT b.*, r.name as room_name, r.location, r.floor, r.capacity,
                                d.name as dept_name, mt.name as meeting_type_name
                         FROM bookings b
                         JOIN rooms r ON b.room_id = r.id
                         LEFT JOIN departments d ON b.department_id = d.id
                         LEFT JOIN meeting_types mt ON b.meeting_type_id = mt.id
                         WHERE b.token = ?",
                        [$token]
                    );
                    // Store for deferred send AFTER re-fetch so checkin_at is populated
                    $pendingCheckinMail = $booking;
                }
            } elseif ($action === 'checkout') {
                $result = BookingHelper::checkOut($token);
                if ($result['success']) {
                    $booking = $db->fetch(
                        "SELECT b.*, r.name as room_name, r.location, r.floor, r.capacity,
                                d.name as dept_name, mt.name as meeting_type_name
                         FROM bookings b
                         JOIN rooms r ON b.room_id = r.id
                         LEFT JOIN departments d ON b.department_id = d.id
                         LEFT JOIN meeting_types mt ON b.meeting_type_id = mt.id
                         WHERE b.token = ?",
                        [$token]
                    );
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-in Ruangan – <?= e($appName) ?></title>
    <?php if ($favicon = getSetting('app_favicon')): ?>
    <link rel="icon" href="<?= e($favicon) ?>">
    <?php else: ?>
    <link rel="icon" href="<?= BASE_URL ?>/assets/images/favicon.png" type="image/png">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        :root { --bs-primary: <?= e($primaryColor) ?>; }
        body {
            background: linear-gradient(135deg, #f0f4f8 0%, #e8eef5 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .checkin-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .checkin-card {
            max-width: 480px;
            width: 100%;
        }
        .status-icon { font-size: 3.5rem; }
        .booking-info-row {
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            padding: .5rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .booking-info-row:last-child { border-bottom: none; }
        .booking-info-icon { width: 22px; color: var(--bs-primary); flex-shrink: 0; margin-top: 2px; }
        .checkin-btn {
            font-size: 1.1rem;
            padding: .75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
        }
        .checkin-time-badge {
            background: rgba(25, 135, 84, .1);
            color: #198754;
            border-radius: 6px;
            padding: 2px 8px;
            font-size: .85rem;
        }
    </style>
</head>
<body>
<div class="checkin-wrapper">
    <div class="checkin-card">
        <div class="text-center mt-3 mb-3">
            <div class="fw-bold text-primary"><?= e($appName) ?></div>
        </div>

        <?php if (isset($error)): ?>
        <!-- ERROR STATE -->
        <div class="card border-0 shadow text-center p-4 p-md-5">
            <div class="status-icon text-danger mb-3"><i class="bi bi-x-circle-fill"></i></div>
            <h4 class="fw-bold text-danger mb-2">Link Tidak Valid</h4>
            <p class="text-muted"><?= e($error) ?></p>
            <a href="<?= BASE_URL ?>/" class="btn btn-outline-primary mt-2">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Halaman Booking
            </a>
        </div>

        <?php elseif ($result && !$result['success']): ?>
        <!-- ACTION ERROR -->
        <div class="card border-0 shadow p-4 p-md-5">
            <div class="status-icon text-warning text-center mb-3"><i class="bi bi-exclamation-circle-fill"></i></div>
            <h4 class="fw-bold text-center mb-3">Gagal</h4>
            <div class="alert alert-warning text-center"><?= e($result['message']) ?></div>
            <a href="<?= BASE_URL ?>/checkin.php?token=<?= urlencode($token) ?>" class="btn btn-outline-secondary w-100">
                <i class="bi bi-arrow-clockwise me-1"></i> Coba Lagi
            </a>
        </div>

        <?php else: ?>
        <!-- MAIN CHECK-IN / CHECK-OUT CARD -->
        <div class="card border-0 shadow p-4 p-md-5">

            <?php
            $isCheckedIn   = !empty($booking['checkin_at']);
            $isCheckedOut  = !empty($booking['checkout_at']);
            $isCompleted   = $booking['status'] === 'completed';
            $isPending     = $booking['status'] === 'pending';
            $isRejected    = in_array($booking['status'], ['rejected', 'cancelled']);
            $today         = date('Y-m-d');
            $isToday       = ($booking['booking_date'] === $today);
            $isPastDay     = ($booking['booking_date'] < $today);
            $nowTs         = time();
            $startTs       = strtotime($booking['booking_date'] . ' ' . $booking['start_time']);
            $endTs         = strtotime($booking['booking_date'] . ' ' . $booking['end_time']);
            $checkinOpenTs = $startTs - (30 * 60);
            $linkExpiresTs = $endTs   + (15 * 60);
            $isCheckinOpen   = $isToday && $nowTs >= $checkinOpenTs;
            $isLinkExpired   = $isToday && $nowTs > $linkExpiresTs && !$isCheckedIn;
            ?>

            <!-- Status Header -->
            <div class="text-center mb-4">
                <?php if ($isCompleted): ?>
                <div class="status-icon text-success"><i class="bi bi-check-circle-fill"></i></div>
                <h4 class="fw-bold text-success mt-2">Selesai</h4>

                <?php elseif ($isCheckedIn && !$isCheckedOut): ?>
                <div class="status-icon text-primary"><i class="bi bi-door-open-fill"></i></div>
                <h4 class="fw-bold text-primary mt-2">Sedang Berlangsung</h4>

                <?php elseif ($isPending): ?>
                <div class="status-icon text-warning"><i class="bi bi-clock-history"></i></div>
                <h4 class="fw-bold text-warning mt-2">Menunggu Persetujuan</h4>

                <?php elseif ($isRejected): ?>
                <div class="status-icon text-danger"><i class="bi bi-x-circle-fill"></i></div>
                <h4 class="fw-bold text-danger mt-2">
                    <?= $booking['status'] === 'rejected' ? 'Ditolak' : 'Dibatalkan' ?>
                </h4>

                <?php else: ?>
                <div class="status-icon text-success"><i class="bi bi-calendar-check"></i></div>
                <h4 class="fw-bold mt-2 text-success">Booking Dikonfirmasi</h4>
                <?php endif; ?>
            </div>

            <!-- Booking Info -->
            <div class="mb-4">
                <div class="booking-info-row">
                    <div class="booking-info-icon"><i class="bi bi-door-closed"></i></div>
                    <div>
                        <div class="fw-semibold"><?= e($booking['room_name']) ?></div>
                        <?php if ($booking['location']): ?>
                        <div class="text-muted small">
                            <?= e($booking['location']) ?>
                            <?= $booking['floor'] ? ' · Lantai ' . e($booking['floor']) : '' ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="booking-info-row">
                    <div class="booking-info-icon"><i class="bi bi-calendar3"></i></div>
                    <div>
                        <div><?= formatDateId($booking['booking_date']) ?></div>
                        <div class="text-muted small"><?= formatTimeRange($booking['start_time'], $booking['end_time']) ?></div>
                    </div>
                </div>
                <div class="booking-info-row">
                    <div class="booking-info-icon"><i class="bi bi-person"></i></div>
                    <div>
                        <div><?= e($booking['booker_name']) ?></div>
                        <?php if ($booking['dept_name']): ?>
                        <div class="text-muted small"><?= e($booking['dept_name']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="booking-info-row">
                    <div class="booking-info-icon"><i class="bi bi-hash"></i></div>
                    <div>
                        <code class="text-primary"><?= e($booking['booking_code']) ?></code>
                    </div>
                </div>
                <?php if ($booking['purpose']): ?>
                <div class="booking-info-row">
                    <div class="booking-info-icon"><i class="bi bi-card-text"></i></div>
                    <div class="small text-muted"><?= e($booking['purpose']) ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Check-in / Check-out info -->
            <?php if ($isCheckedIn): ?>
            <div class="mb-3 small text-muted text-center">
                <i class="bi bi-box-arrow-in-right me-1 text-success"></i>
                Check-in: <span class="checkin-time-badge"><?= date('H:i', strtotime($booking['checkin_at'])) ?></span>
                <?php if ($isCheckedOut): ?>
                &nbsp;&nbsp;
                <i class="bi bi-box-arrow-right me-1 text-danger"></i>
                Check-out: <span class="checkin-time-badge" style="background:rgba(220,53,69,.1);color:#dc3545;">
                    <?= date('H:i', strtotime($booking['checkout_at'])) ?>
                </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Success message -->
            <?php if ($result && $result['success']): ?>
            <div class="alert alert-success text-center mb-3">
                <?php if ($_POST['action'] === 'checkin'): ?>
                <i class="bi bi-check-circle me-1"></i> <strong>Check-in berhasil!</strong> Selamat menggunakan ruangan.
                <?php else: ?>
                <i class="bi bi-check-circle me-1"></i> <strong>Check-out berhasil!</strong> Terima kasih.
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="d-grid gap-2">
                <?php if ($isPending): ?>
                <button class="btn btn-warning checkin-btn" disabled>
                    <i class="bi bi-clock me-2"></i> Menunggu Persetujuan Admin
                </button>

                <?php elseif ($isRejected): ?>
                <div class="text-center text-muted small">Booking tidak dapat diproses.</div>

                <?php elseif ($isCompleted): ?>
                <div class="text-center py-2">
                    <span class="badge bg-success fs-6 px-3 py-2">
                        <i class="bi bi-check-circle me-1"></i> Sesi Selesai
                    </span>
                </div>

                <?php elseif (!$isCheckedIn): ?>
                <!-- CHECK-IN BUTTON -->
                <?php if ($isPastDay): ?>
                    <button class="btn btn-secondary checkin-btn" disabled>
                        <i class="bi bi-calendar-x me-2"></i> Waktu Check-in Sudah Lewat
                    </button>
                <?php elseif (!$isToday): ?>
                    <button class="btn btn-secondary checkin-btn" disabled>
                        <i class="bi bi-calendar me-2"></i> Check-in Tersedia pada Hari Booking
                    </button>
                <?php elseif ($isLinkExpired): ?>
                    <button class="btn btn-secondary checkin-btn" disabled>
                        <i class="bi bi-clock-history me-2"></i> Link Check-in Kedaluwarsa
                    </button>
                    <div class="text-center mt-2 small text-muted">
                        Link berakhir 15 menit setelah jam selesai booking (<?= date('H:i', $linkExpiresTs) ?>).
                    </div>
                <?php elseif (!$isCheckinOpen): ?>
                    <button class="btn btn-secondary checkin-btn" disabled>
                        <i class="bi bi-clock me-2"></i> Check-in Dibuka Pukul <?= date('H:i', $checkinOpenTs) ?>
                    </button>
                    <div class="text-center mt-2 small text-muted">
                        Check-in tersedia 30 menit sebelum jam mulai booking.
                    </div>
                <?php else: ?>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="checkin">
                    <button type="submit" class="btn btn-success checkin-btn w-100"
                            onclick="return confirm('Konfirmasi check-in sekarang?')">
                        <i class="bi bi-box-arrow-in-right me-2"></i> Check-In Sekarang
                    </button>
                </form>
                <?php endif; ?>

                <?php elseif ($isCheckedIn && !$isCheckedOut): ?>
                <!-- CHECK-OUT BUTTON -->
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="checkout">
                    <button type="submit" class="btn btn-danger checkin-btn w-100"
                            onclick="return confirm('Konfirmasi check-out? Ruangan akan ditandai selesai.')">
                        <i class="bi bi-box-arrow-right me-2"></i> Check-Out Sekarang
                    </button>
                </form>
                <?php endif; ?>
            </div>

            <?php if (!$isCompleted && !$isPending && !$isRejected): ?>
            <div class="text-center mt-3 small text-muted">
                Gunakan link ini hanya pada hari booking. <br>
                Jangan bagikan ke orang lain.
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="text-center mt-3 mb-3 small text-danger">
            <a href="<?= BASE_URL ?>/" class="text-danger text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i> Buat Booking Baru
            </a>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$html = ob_get_clean();
header('Content-Type: text/html; charset=utf-8');
header('Connection: close');
header('Content-Encoding: none');
header('Content-Length: ' . strlen($html));
echo $html;
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    ob_implicit_flush(true);
    flush();
}
ignore_user_abort(true);
set_time_limit(30);
if (!empty($pendingCheckinMail)) {
    Mailer::sendCheckinConfirmation($pendingCheckinMail);
}

<?php
// Disable output compression so Content-Length + Connection:close flush works
ini_set('zlib.output_compression', 'Off');
ob_start();
require_once __DIR__ . '/includes/bootstrap.php';

$db = db();
$appName    = getSetting('app_name', 'Sistem Booking Ruangan');
$appTagline = getSetting('app_tagline', 'Booking ruangan kantor dengan mudah dan cepat');
$primaryColor = getSetting('app_color_primary', '#0d6efd');

// Get active rooms
$rooms = $db->fetchAll(
    "SELECT id, name, code, location, floor, capacity, description, facilities, image, requires_approval
     FROM rooms WHERE is_active = 1 ORDER BY name ASC"
);

// Get departments
$departments = $db->fetchAll("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name ASC");

// Get meeting types
$meetingTypes = $db->fetchAll("SELECT id, name FROM meeting_types WHERE is_active = 1 ORDER BY name ASC");

// Allowed email domain (empty = allow all)
$allowedEmailDomain = getSetting('allowed_email_domain', '');

// Booking date range
$dateRange = BookingHelper::getBookingDateRange();

// Handle form submission
$formErrors         = [];
$formSuccess        = null;
$pendingMailBooking = null;
$pendingNotifData   = null;

// Track form load time for bot detection (reset on each GET)
if (!isPost()) {
    $_SESSION['form_load_time'] = time();
}

if (isPost()) {
    $roomId        = (int)($_POST['room_id'] ?? 0);
    $bookingDate   = sanitize($_POST['booking_date'] ?? '');
    $startTime     = sanitize($_POST['start_time'] ?? '');
    $endTime       = sanitize($_POST['end_time'] ?? '');
    $bookerName    = sanitize($_POST['booker_name'] ?? '');
    $bookerNik     = sanitize($_POST['booker_nik'] ?? '');
    $bookerEmail   = sanitize($_POST['booker_email'] ?? '');
    $bookerPhone   = sanitize($_POST['booker_phone'] ?? '');
    $departmentId  = (int)($_POST['department_id'] ?? 0) ?: null;
    $meetingTypeId = (int)($_POST['meeting_type_id'] ?? 0) ?: null;
    $capacityNeeded = (int)($_POST['capacity_needed'] ?? 0) ?: null;
    $purpose       = sanitize($_POST['purpose'] ?? '');
    $notesEquip    = sanitize($_POST['notes_equipment'] ?? '');
    $notesOther    = sanitize($_POST['notes_other'] ?? '');

    // Raw (un-encoded) values used for character-level and format validation
    // SQL injection is prevented by PDO prepared statements (ATTR_EMULATE_PREPARES=false)
    // These raw values are NEVER used in SQL queries directly
    $rawName    = trim($_POST['booker_name']      ?? '');
    $rawNik     = trim($_POST['booker_nik']        ?? '');
    $rawPhone   = trim($_POST['booker_phone']      ?? '');
    $rawPurpose = trim($_POST['purpose']           ?? '');
    $rawEquip   = trim($_POST['notes_equipment']   ?? '');
    $rawOther   = trim($_POST['notes_other']       ?? '');

    // Validation
    if (!$roomId)      $formErrors[] = 'Pilih ruangan terlebih dahulu.';
    if (!$bookingDate) $formErrors[] = 'Tanggal booking wajib diisi.';
    if (!$startTime)   $formErrors[] = 'Jam mulai wajib diisi.';
    if (!$endTime)     $formErrors[] = 'Jam selesai wajib diisi.';
    // Nama: wajib, hanya huruf + spasi + titik + tanda hubung + apostrof
    if (!$rawName) {
        $formErrors[] = 'Nama pemesan wajib diisi.';
    } elseif (mb_strlen($rawName) < 2 || mb_strlen($rawName) > 100) {
        $formErrors[] = 'Nama harus antara 2–100 karakter.';
    } elseif (!preg_match('/^[\p{L}\s\.\'\-]+$/u', $rawName)) {
        $formErrors[] = 'Nama hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung.';
    }

    // Email validation with optional domain restriction
    if (!$bookerEmail) {
        $formErrors[] = 'Email wajib diisi.';
    } elseif (!filter_var($bookerEmail, FILTER_VALIDATE_EMAIL)) {
        $formErrors[] = 'Format email tidak valid.';
    } elseif ($allowedEmailDomain) {
        $emailDomain = strtolower(substr($bookerEmail, strrpos($bookerEmail, '@') + 1));
        if ($emailDomain !== strtolower($allowedEmailDomain)) {
            $formErrors[] = 'Email harus menggunakan domain @' . $allowedEmailDomain . '.';
        }
    }

    // Mandatory fields: departemen, jenis meeting, jumlah peserta
    if (!$departmentId)                          $formErrors[] = 'Departemen wajib dipilih.';
    if (!$meetingTypeId)                         $formErrors[] = 'Jenis meeting wajib dipilih.';
    if (!$capacityNeeded || $capacityNeeded < 1) $formErrors[] = 'Jumlah peserta wajib diisi (minimal 1 orang).';

    // NIK (opsional): hanya huruf, angka, tanda hubung, slash, titik
    if ($rawNik && !preg_match('/^[a-zA-Z0-9\-\.\/]{1,30}$/', $rawNik)) {
        $formErrors[] = 'NIK/No. Karyawan hanya boleh berisi huruf, angka, dan tanda hubung (maks. 30 karakter).';
    }

    // No. HP (opsional): digit, +, -, spasi, kurung
    if ($rawPhone) {
        $phoneDigits = preg_replace('/[\s\-\(\)]/', '', $rawPhone);
        if (!preg_match('/^\+?[0-9]{7,15}$/', $phoneDigits)) {
            $formErrors[] = 'Format nomor HP tidak valid (contoh: 08123456789 atau +6281234567).';
        }
    }

    // Tanggal dan waktu: format check
    if ($bookingDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $bookingDate)) {
        $formErrors[] = 'Format tanggal tidak valid.';
    }
    if ($startTime && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $startTime)) {
        $formErrors[] = 'Format jam mulai tidak valid.';
    }
    if ($endTime && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $endTime)) {
        $formErrors[] = 'Format jam selesai tidak valid.';
    }

    // Keperluan: wajib, 5–500 karakter, tidak boleh ada tag HTML
    if (!$rawPurpose) {
        $formErrors[] = 'Keperluan/tujuan meeting wajib diisi.';
    } elseif (mb_strlen($rawPurpose) < 5) {
        $formErrors[] = 'Keperluan/tujuan terlalu singkat (minimal 5 karakter).';
    } elseif (mb_strlen($rawPurpose) > 50) {
        $formErrors[] = 'Keperluan/tujuan terlalu panjang (maksimal 50 karakter).';
    } elseif ($rawPurpose !== strip_tags($rawPurpose)) {
        $formErrors[] = 'Keperluan/tujuan tidak boleh mengandung tag HTML.';
    }

    // Catatan (opsional): maks. 500 karakter, tidak boleh ada tag HTML
    if ($rawEquip) {
        if (mb_strlen($rawEquip) > 500)         $formErrors[] = 'Catatan kebutuhan alat terlalu panjang (maksimal 500 karakter).';
        if ($rawEquip !== strip_tags($rawEquip)) $formErrors[] = 'Catatan kebutuhan alat tidak boleh mengandung tag HTML.';
    }
    if ($rawOther) {
        if (mb_strlen($rawOther) > 500)          $formErrors[] = 'Catatan lainnya terlalu panjang (maksimal 500 karakter).';
        if ($rawOther !== strip_tags($rawOther))  $formErrors[] = 'Catatan lainnya tidak boleh mengandung tag HTML.';
    }

    // Capacity max check against room setting
    if ($capacityNeeded && $roomId) {
        $roomCheck = $db->fetch("SELECT capacity FROM rooms WHERE id = ? AND is_active = 1", [$roomId]);
        if ($roomCheck && $capacityNeeded > (int)$roomCheck['capacity']) {
            $formErrors[] = 'Jumlah peserta (' . $capacityNeeded . ' orang) melebihi kapasitas ruangan (maks. ' . $roomCheck['capacity'] . ' orang).';
        }
    }

    // Anti-bot: honeypot check
    $honey = $_POST['website'] ?? '';
    if ($honey !== '') {
        $formErrors[] = 'Submission tidak valid.';
    }

    // Anti-bot: time-based check (submitted too quickly = likely a bot)
    $formLoadTime = $_SESSION['form_load_time'] ?? 0;
    if ($formLoadTime > 0 && (time() - $formLoadTime) < 3) {
        $formErrors[] = 'Submission terlalu cepat. Silakan coba kembali.';
    }

    // Rate limiting: prevent spam submissions (max 1 per 60 seconds)
    $lastSubmit = $_SESSION['last_booking_submit'] ?? 0;
    if ($lastSubmit > 0 && (time() - $lastSubmit) < 60) {
        $remaining = 60 - (time() - $lastSubmit);
        $formErrors[] = 'Harap tunggu ' . $remaining . ' detik sebelum mengajukan booking lagi.';
    }

    if (empty($formErrors)) {
        $result = BookingHelper::createBooking([
            'room_id'         => $roomId,
            'department_id'   => $departmentId,
            'meeting_type_id' => $meetingTypeId,
            'booker_name'     => $bookerName,
            'booker_nik'      => $bookerNik,
            'booker_email'    => $bookerEmail,
            'booker_phone'    => $bookerPhone,
            'booking_date'    => $bookingDate,
            'start_time'      => $startTime,
            'end_time'        => $endTime,
            'capacity_needed' => $capacityNeeded,
            'purpose'         => $purpose,
            'notes_equipment' => $notesEquip,
            'notes_other'     => $notesOther,
        ]);

        if ($result['success']) {
            // Fetch booking data (will be used for deferred email after flush)
            $pendingMailBooking = $db->fetch(
                "SELECT b.*, r.name as room_name, r.location, d.name as dept_name, mt.name as meeting_type_name
                 FROM bookings b
                 JOIN rooms r ON b.room_id = r.id
                 LEFT JOIN departments d ON b.department_id = d.id
                 LEFT JOIN meeting_types mt ON b.meeting_type_id = mt.id
                 WHERE b.id = ?",
                [$result['booking_id']]
            );

            // Store notification data (sent after browser receives response)
            $pendingNotifData = [
                'type'    => 'new_booking',
                'title'   => 'Booking Baru: ' . $result['booking_code'],
                'message' => $bookerName . ' mengajukan booking ' . $result['room']['name'] .
                             ' pada ' . formatDateId($bookingDate) . ' ' . formatTimeRange($startTime, $endTime),
                'id'      => $result['booking_id'],
            ];

            $formSuccess = $result;
            // Record submission time for rate limiting
            $_SESSION['last_booking_submit'] = time();
            unset($_SESSION['form_load_time']);
        } else {
            $formErrors[] = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($appName) ?> - Booking Ruangan</title>
    <?php if ($favicon = getSetting('app_favicon')): ?>
    <link rel="icon" href="<?= e($favicon) ?>">
    <?php else: ?>
    <link rel="icon" href="<?= BASE_URL ?>/assets/images/favicon.png" type="image/png">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/select2.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        :root { --bs-primary: <?= e($primaryColor) ?>; }
        body { background: #f0f4f8; min-height: 100vh; }
        .public-header {
            background: var(--bs-primary);
            color: #fff;
            padding: 20px 0;
        }
        .booking-card {
            border-radius: 12px;
            overflow: hidden;
        }
        .step-indicator {
            display: flex;
            gap: 0;
            margin-bottom: 1.5rem;
        }
        .step-item {
            flex: 1;
            text-align: center;
            padding: 10px 5px;
            background: #e9ecef;
            font-size: 0.8rem;
            font-weight: 600;
            color: #6c757d;
            position: relative;
        }
        .step-item.active {
            background: var(--bs-primary);
            color: #fff;
        }
        .step-item.done {
            background: #198754;
            color: #fff;
        }
        .step-item:first-child { border-radius: 8px 0 0 8px; }
        .step-item:last-child  { border-radius: 0 8px 8px 0; }
        .room-card {
            cursor: pointer;
            border: 2px solid transparent;
            transition: all .2s;
            border-radius: 10px;
        }
        .room-card:hover { border-color: var(--bs-primary); box-shadow: 0 0 0 3px rgba(233, 253, 13, 0.1); }
        .room-card.selected { border-color: var(--bs-primary); background: rgba(233, 253, 13, 0.5); }
        .room-img { height: 130px; object-fit: cover; width: 100%; border-radius: 8px 8px 0 0; }
        .time-slot-btn {
            padding: 6px 10px;
            font-size: 0.8rem;
            border-radius: 6px;
            border: 1px solid #dee2e6;
            background: #fff;
            cursor: pointer;
            transition: all .15s;
        }
        .time-slot-btn:hover:not(.booked) { border-color: var(--bs-primary); background: rgba(233, 253, 13, 0.5); color: #000000; }
        .time-slot-btn.selected { background: rgba(233, 253, 13, 0.5); color: #000000; border-color: var(--bs-primary); }
        .room-info-btn {
            position: absolute; top: 6px; right: 6px;
            width: 28px; height: 28px; padding: 0;
            border-radius: 50%; opacity: 0.92;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 1px 3px rgba(0,0,0,.2);
        }
        .room-info-btn:hover { opacity: 1; transform: scale(1.1); }
        .time-slot-btn.booked { background: #f8d7da !important; color: #842029 !important; border-color: #f5c2c7 !important; cursor: not-allowed; text-decoration: line-through; opacity: 1 !important; }
        .time-slot-btn.past   { background: #f8f9fa; color: #adb5bd; border-color: #dee2e6; cursor: not-allowed; text-decoration: none; }
        .success-icon { font-size: 4rem; color: #198754; }
    </style>
</head>
<body>

<!-- Header -->
<div class="public-header">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <h1 class="h4 mb-0 fw-bold"><?= e($appName) ?></h1>
                <div class="opacity-75 small"><?= e($appTagline) ?></div>
            </div>
            <a href="<?= BASE_URL ?>/login.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-lock me-1"></i> Admin
            </a>
        </div>
    </div>
</div>

<div class="container py-3">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-9 col-xl-10">

            <?php if ($formSuccess): ?>
            <!-- SUCCESS STATE -->
            <div class="card border-0 shadow-sm booking-card text-center py-5 px-3">
                <div class="success-icon mb-3"><i class="bi bi-check-circle-fill"></i></div>
                <h3 class="fw-bold text-success mb-2">Booking Berhasil!</h3>
                <p class="text-muted mb-1">Kode Booking Anda:</p>
                <h4 class="fw-bold text-primary mb-3">
                    <code><?= e($formSuccess['booking_code']) ?></code>
                </h4>

                <?php if ($formSuccess['status'] === 'pending'): ?>
                <div class="alert alert-warning d-inline-block mb-3">
                    <i class="bi bi-clock-history me-1"></i>
                    Booking Anda sedang <strong>menunggu persetujuan admin</strong>.<br>
                    Anda akan menerima email konfirmasi setelah disetujui.
                </div>
                <?php else: ?>
                <div class="alert alert-success d-inline-block mb-3">
                    <i class="bi bi-check-circle me-1"></i>
                    Booking Anda telah <strong>dikonfirmasi</strong>.<br>
                    Link check-in telah dikirim ke email Anda.
                </div>
                <?php endif; ?>

                <p class="text-muted small mb-4">
                    Email konfirmasi telah dikirim ke <strong><?= e($_POST['booker_email'] ?? '') ?></strong>.<br>
                    Simpan kode booking untuk referensi Anda.
                </p>

                <a href="<?= BASE_URL ?>/" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i> Buat Booking Lain
                </a>
            </div>

            <?php else: ?>
            <!-- BOOKING FORM -->

            <?php if (!empty($formErrors)): ?>
            <div class="alert alert-danger mb-3">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <strong>Terdapat kesalahan:</strong>
                <ul class="mb-0 mt-1 ps-3">
                    <?php foreach ($formErrors as $err): ?>
                    <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm booking-card py-2">
                <!-- Step Indicator -->
                <div class="step-indicator" id="stepIndicator">
                    <div class="step-item active" data-step="1">
                        <i class="bi bi-door-open me-1"></i>1. Pilih Ruangan
                    </div>
                    <div class="step-item" data-step="2">
                        <i class="bi bi-calendar-check me-1"></i>2. Tanggal & Waktu
                    </div>
                    <div class="step-item" data-step="3">
                        <i class="bi bi-person-lines-fill me-1"></i>3. Data Pemesan
                    </div>
                </div>

                <form method="POST" id="bookingForm" novalidate>
                    <!-- Honeypot (anti-bot) -->
                    <div style="display:none"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>

                    <!-- STEP 1: Room Selection -->
                    <div class="booking-step p-4 py-2" id="step1">
                        <h5 class="fw-bold mb-3"><i class="bi bi-door-open me-2 text-primary"></i>Pilih Ruangan</h5>

                        <input type="hidden" name="room_id" id="roomIdInput">

                        <?php if (empty($rooms)): ?>
                        <div class="text-center py-2 text-muted">
                            <i class="bi bi-exclamation-circle display-4 d-block mb-2 opacity-25"></i>
                            Belum ada ruangan yang tersedia.
                        </div>
                        <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($rooms as $room): ?>
                            <?php
                            $facilities = [];
                            if (!empty($room['facilities'])) {
                                $decoded = json_decode($room['facilities'], true);
                                $facilities = is_array($decoded) ? $decoded : [];
                            }
                            ?>
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="room-card card border-0 shadow-sm h-100"
                                     data-room-id="<?= $room['id'] ?>"
                                     data-room-name="<?= e($room['name']) ?>"
                                     data-room-capacity="<?= $room['capacity'] ?>"
                                     data-room-approval="<?= $room['requires_approval'] ?>"
                                     data-room-location="<?= e($room['location'] ?? '') ?>"
                                     data-room-floor="<?= e($room['floor'] ?? '') ?>"
                                     data-room-description="<?= e($room['description'] ?? '') ?>"
                                     data-room-facilities="<?= e(json_encode($facilities)) ?>"
                                     data-room-image="<?= e(roomImageUrl($room['image'])) ?>"
                                     onclick="selectRoom(this)">
                                    <div style="position:relative;">
                                        <img src="<?= roomImageUrl($room['image']) ?>"
                                             class="room-img" alt="<?= e($room['name']) ?>">
                                        <button type="button"
                                                class="btn btn-light btn-sm room-info-btn"
                                                onclick="event.stopPropagation(); showRoomDetail(this.closest('.room-card'))"
                                                title="Lihat detail ruangan">
                                            <i class="bi bi-info-circle-fill text-primary"></i>
                                        </button>
                                    </div>
                                    <div class="card-body p-2">
                                        <div class="fw-semibold small"><?= e($room['name']) ?></div>
                                        <?php if ($room['location']): ?>
                                        <div class="text-muted" style="font-size:0.75rem;">
                                            <i class="bi bi-geo-alt me-1"></i><?= e($room['location']) ?>
                                            <?= $room['floor'] ? '| Lt. ' . e($room['floor']) : '' ?>
                                        </div>
                                        <?php endif; ?>
                                        <div class="text-muted mt-1" style="font-size:0.75rem;">
                                            <i class="bi bi-people me-1"></i>Kap. <?= $room['capacity'] ?> orang
                                            <?php if ($room['requires_approval']): ?>
                                            <span class="badge bg-warning-subtle text-warning ms-1" style="font-size:0.65rem;">Perlu Approval</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($facilities)): ?>
                                        <div class="mt-1 d-flex flex-wrap gap-1">
                                            <?php foreach (array_slice($facilities, 0, 3) as $f): ?>
                                            <span class="badge bg-light text-dark" style="font-size:0.65rem;"><?= e($f) ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($facilities) > 3): ?>
                                            <span class="badge bg-light text-muted" style="font-size:0.65rem;">+<?= count($facilities) - 3 ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                        <div class="mt-2 text-end">
                                            <a href="schedule.php?room_id=<?= $room['id'] ?>"
                                               target="_blank"
                                               onclick="event.stopPropagation()"
                                               class="badge bg-primary-subtle text-primary"
                                               style="font-size:0.7rem; text-decoration:none;">
                                                <i class="bi bi-calendar3"></i> Lihat Jadwal
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <div class="mt-4 text-end">
                            <button type="button" class="btn btn-primary" onclick="goToStep(2)"
                                    id="step1Next" disabled>
                                Selanjutnya <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 2: Date & Time -->
                    <div class="booking-step p-4 py-2" id="step2" style="display:none;">
                        <h5 class="fw-bold mb-3"><i class="bi bi-calendar-check me-2 text-primary"></i>Tanggal & Waktu</h5>

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Tanggal Booking <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="booking_date" id="bookingDate"
                                       min="<?= $dateRange['min'] ?>" max="<?= $dateRange['max'] ?>"
                                       value="<?= e($_POST['booking_date'] ?? '') ?>">
                            </div>
                            <div class="col-12 col-sm-6" id="selectedRoomInfo">
                                <label class="form-label">Ruangan Dipilih</label>
                                <div class="form-control bg-light fw-semibold" id="selectedRoomName">-</div>
                            </div>
                        </div>

                        <!-- Time Slots -->
                        <div id="timeSlotsSection" style="display:none;">
                            <label class="form-label fw-semibold">Pilih Waktu</label>
                            <div id="timeSlotsContainer" class="d-flex flex-wrap gap-2 mb-3"></div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label small">Jam Mulai</label>
                                    <input type="text" class="form-control form-control-sm bg-light" name="start_time" id="startTimeInput" readonly>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Jam Selesai</label>
                                    <input type="text" class="form-control form-control-sm bg-light" name="end_time" id="endTimeInput" readonly>
                                </div>
                            </div>
                            <div class="mt-2 small text-muted" id="durationInfo"></div>
                        </div>

                        <div id="slotsLoading" style="display:none;" class="text-center py-3 text-muted">
                            <div class="spinner-border spinner-border-sm me-2"></div>
                            Memuat slot waktu...
                        </div>
                        <div id="slotsEmpty" style="display:none;" class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Tidak ada slot waktu tersedia untuk tanggal ini.
                        </div>

                        <!-- Multi-slot selection hint -->
                        <div class="alert alert-info py-2 small mt-2" id="slotHint" style="display:none;">
                            <i class="bi bi-info-circle me-1"></i>
                            Pilih slot berturutan untuk memperpanjang durasi booking.
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn btn-outline-secondary" onclick="goToStep(1)">
                                <i class="bi bi-arrow-left me-1"></i> Kembali
                            </button>
                            <button type="button" class="btn btn-primary" onclick="goToStep(3)"
                                    id="step2Next" disabled>
                                Selanjutnya <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 3: Booker Data -->
                    <div class="booking-step p-4 py-2" id="step3" style="display:none;">
                        <h5 class="fw-bold mb-3"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Data Pemesan</h5>

                        <!-- Summary -->
                        <div class="alert alert-light border mb-4 py-2 small" id="bookingSummary"></div>

                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="booker_name" id="bookerNameInput"
                                       value="<?= e($_POST['booker_name'] ?? '') ?>" required
                                       maxlength="100" autocomplete="name"
                                       placeholder="Contoh: Budi Santoso">
                                <div class="form-text text-muted" id="nameFeedback">Hanya huruf, spasi, titik, dan tanda hubung.</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label">NIK / No. Karyawan</label>
                                <input type="text" class="form-control" name="booker_nik" id="bookerNikInput"
                                       value="<?= e($_POST['booker_nik'] ?? '') ?>"
                                       maxlength="30" autocomplete="off"
                                       placeholder="Opsional">
                                <div class="form-text text-muted" id="nikFeedback"></div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="booker_email" id="emailInput"
                                       value="<?= e($_POST['booker_email'] ?? '') ?>" required
                                       placeholder="<?= $allowedEmailDomain ? 'contoh@' . e($allowedEmailDomain) : 'Link check-in akan dikirim ke email ini' ?>"
                                       autocomplete="email">
                                <div class="form-text text-muted" id="emailFeedback"><?php if ($allowedEmailDomain): ?>Gunakan email dengan domain <strong>@<?= e($allowedEmailDomain) ?></strong><?php endif; ?></div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label">No. HP</label>
                                <input type="tel" class="form-control" name="booker_phone" id="bookerPhoneInput"
                                       value="<?= e($_POST['booker_phone'] ?? '') ?>"
                                       maxlength="20" inputmode="tel" autocomplete="tel"
                                       placeholder="Contoh: 08123456789">
                                <div class="form-text text-muted" id="phoneFeedback"></div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Departemen <span class="text-danger">*</span></label>
                                <select class="form-select select2" name="department_id" id="departmentSelect">
                                    <option value="">-- Pilih Departemen --</option>
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>"
                                        <?= ($_POST['department_id'] ?? '') == $dept['id'] ? 'selected' : '' ?>>
                                        <?= e($dept['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Jenis Meeting <span class="text-danger">*</span></label>
                                <select class="form-select select2" name="meeting_type_id" id="meetingTypeSelect">
                                    <option value="">-- Pilih Jenis Meeting --</option>
                                    <?php foreach ($meetingTypes as $mt): ?>
                                    <option value="<?= $mt['id'] ?>"
                                        <?= ($_POST['meeting_type_id'] ?? '') == $mt['id'] ? 'selected' : '' ?>>
                                        <?= e($mt['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Jumlah Peserta <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="capacity_needed"
                                       value="<?= e($_POST['capacity_needed'] ?? '') ?>"
                                       min="1" id="capacityInput" required>
                                <div class="form-text text-muted" id="capacityFeedback"></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Keperluan / Tujuan Meeting <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="purpose" id="purposeInput"
                                       value="<?= e($_POST['purpose'] ?? '') ?>" required
                                       maxlength="50" autocomplete="purpose"
                                       placeholder="Contoh: TM Exhibitor GIIAS">
                                <div class="d-flex justify-content-between">
                                    <div class="form-text text-muted" id="purposeFeedback">Minimal 5 karakter.</div>
                                    <div class="form-text text-muted"><span id="purposeCount">0</span>/50</div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Catatan Kebutuhan Alat (di dalam ruangan)</label>
                                <textarea class="form-control" name="notes_equipment" id="notesEquipInput"
                                          rows="2" maxlength="500"
                                          placeholder="Contoh: Proyektor, papan tulis, mic..."><?= e($_POST['notes_equipment'] ?? '') ?></textarea>
                                <div class="text-end">
                                    <div class="form-text text-muted small"><span id="notesEquipCount">0</span>/500</div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Catatan Lainnya</label>
                                <textarea class="form-control" name="notes_other" id="notesOtherInput"
                                          rows="2" maxlength="500"><?= e($_POST['notes_other'] ?? '') ?></textarea>
                                <div class="text-end">
                                    <div class="form-text text-muted small"><span id="notesOtherCount">0</span>/500</div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn btn-outline-secondary" onclick="goToStep(2)">
                                <i class="bi bi-arrow-left me-1"></i> Kembali
                            </button>
                            <button type="submit" class="btn btn-success btn-lg px-4">
                                <i class="bi bi-send me-1"></i> Kirim Booking
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<footer class="text-center py-3 text-muted small mt-4">
    &copy; <?= date('Y') ?> | <?= e($appName) ?>
</footer>

<!-- Modal Detail Ruangan -->
<div class="modal fade" id="roomDetailModal" tabindex="-1" aria-labelledby="roomDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-body p-0">
                <img id="modalRoomImage" src="" alt="" class="w-100" style="height:220px; object-fit:cover; border-radius:8px 8px 0 0;">
                <div class="p-4">
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                        <div>
                            <h5 class="fw-bold mb-1" id="modalRoomName"></h5>
                            <div class="d-flex flex-wrap gap-2" id="modalRoomBadges"></div>
                        </div>
                        <button type="button" class="btn-close mt-1" data-bs-dismiss="modal"></button>
                    </div>

                    <div id="modalRoomDescription" class="text-muted small mb-3" style="display:none;"></div>

                    <div id="modalFacilitiesSection" style="display:none;">
                        <div class="fw-semibold small mb-2">
                            <i class="bi bi-check2-square me-1 text-primary"></i>Fasilitas
                        </div>
                        <div class="d-flex flex-wrap gap-2" id="modalFacilitiesList"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary btn-sm" id="modalSelectBtn" onclick="selectFromModal()">
                    <i class="bi bi-check-circle me-1"></i>Pilih Ruangan Ini
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/jquery.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/select2.min.js"></script>
<script>
const BASE_URL = '<?= BASE_URL ?>';

// Initialize Select2
$(document).ready(function() {
    $('.select2').select2({ width: '100%', placeholder: '-- Pilih --' });
});

// ============================================================
// Step Navigation
// ============================================================
let currentStep = 1;
let selectedRoom = null;
let selectedSlots = [];

function goToStep(step) {
    // Validate before proceeding
    if (step === 2 && !selectedRoom) {
        alert('Pilih ruangan terlebih dahulu.');
        return;
    }
    if (step === 3) {
        const date = document.getElementById('bookingDate').value;
        const start = document.getElementById('startTimeInput').value;
        if (!date) { alert('Pilih tanggal booking.'); return; }
        if (!start) { alert('Pilih waktu booking.'); return; }
        updateSummary();
    }

    document.querySelectorAll('.booking-step').forEach(el => el.style.display = 'none');
    document.getElementById('step' + step).style.display = '';

    document.querySelectorAll('.step-item').forEach((el, i) => {
        el.classList.remove('active', 'done');
        if (i + 1 < step) el.classList.add('done');
        else if (i + 1 === step) el.classList.add('active');
    });

    currentStep = step;
    window.scrollTo({top: 0, behavior: 'smooth'});
}

// ============================================================
// Room Selection
// ============================================================
function resetDateAndSlots() {
    document.getElementById('bookingDate').value      = '';
    document.getElementById('timeSlotsSection').style.display = 'none';
    document.getElementById('timeSlotsContainer').innerHTML   = '';
    document.getElementById('slotsLoading').style.display     = 'none';
    document.getElementById('slotsEmpty').style.display       = 'none';
    document.getElementById('slotHint').style.display         = 'none';
    selectedSlots = [];
    clearTimeInputs();
}

function selectRoom(card) {
    const newRoomId = card.dataset.roomId;

    // Jika ruangan berbeda dari sebelumnya, reset tanggal & slot waktu
    if (selectedRoom && selectedRoom.id !== newRoomId) {
        resetDateAndSlots();
    }

    document.querySelectorAll('.room-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');

    selectedRoom = {
        id: newRoomId,
        name: card.dataset.roomName,
        capacity: card.dataset.roomCapacity,
        requiresApproval: card.dataset.roomApproval,
    };

    document.getElementById('roomIdInput').value = selectedRoom.id;
    document.getElementById('step1Next').disabled = false;

    document.getElementById('capacityInput').max = selectedRoom.capacity;
    if (window._revalidateCapacity) window._revalidateCapacity();
    document.getElementById('selectedRoomName').textContent = selectedRoom.name;
}

// ============================================================
// Date change → load time slots
// ============================================================
document.getElementById('bookingDate')?.addEventListener('change', function() {
    if (!selectedRoom) return;
    loadSlots(selectedRoom.id, this.value);
});

function loadSlots(roomId, date) {
    const container = document.getElementById('timeSlotsContainer');
    const section   = document.getElementById('timeSlotsSection');
    const loading   = document.getElementById('slotsLoading');
    const empty     = document.getElementById('slotsEmpty');
    const hint      = document.getElementById('slotHint');

    section.style.display = 'none';
    empty.style.display   = 'none';
    loading.style.display = 'block';
    container.innerHTML   = '';
    selectedSlots = [];
    clearTimeInputs();

    fetch(BASE_URL + '/api/time-slots.php?room_id=' + roomId + '&date=' + date)
        .then(r => r.json())
        .then(data => {
            loading.style.display = 'none';
            if (!data.success || !data.slots.length) {
                empty.style.display = 'block';
                return;
            }

            section.style.display = 'block';
            hint.style.display    = 'block';

            data.slots.forEach((slot, idx) => {
                const btn = document.createElement('button');
                btn.type          = 'button';
                btn.textContent   = slot.label;
                btn.dataset.start = slot.start;
                btn.dataset.end   = slot.end;
                btn.dataset.idx   = idx;

                if (!slot.available && slot.reason === 'booked') {
                    btn.className = 'time-slot-btn booked';
                    btn.disabled  = true;
                    btn.title     = 'Slot sudah dipesan';
                } else if (!slot.available) {
                    btn.className = 'time-slot-btn past';
                    btn.disabled  = true;
                    btn.title     = 'Slot tidak tersedia (terlalu dekat waktu booking)';
                } else {
                    btn.className = 'time-slot-btn';
                    btn.onclick   = () => toggleSlot(btn, data.slots);
                }
                container.appendChild(btn);
            });
        })
        .catch(() => {
            loading.style.display = 'none';
            empty.style.display   = 'block';
        });
}

function toggleSlot(btn, allSlots) {
    const idx       = parseInt(btn.dataset.idx);
    const slotStart = btn.dataset.start;
    const slotEnd   = btn.dataset.end;

    if (btn.classList.contains('selected')) {
        // Deselect: only allow if it's the first or last selected
        const firstIdx = Math.min(...selectedSlots);
        const lastIdx  = Math.max(...selectedSlots);
        if (idx !== firstIdx && idx !== lastIdx) return; // can't deselect middle
        selectedSlots = selectedSlots.filter(i => i !== idx);
        btn.classList.remove('selected');
    } else {
        if (selectedSlots.length === 0) {
            selectedSlots.push(idx);
            btn.classList.add('selected');
        } else {
            const firstIdx  = Math.min(...selectedSlots);
            const lastIdx   = Math.max(...selectedSlots);
            const firstSlot = allSlots[firstIdx];
            const lastSlot  = allSlots[lastIdx];

            // Adjacency check berbasis waktu — aman meski ada slot tersembunyi di antara
            if (slotStart === lastSlot.end || slotEnd === firstSlot.start) {
                selectedSlots.push(idx);
                btn.classList.add('selected');
            } else {
                // Reset dan pilih hanya slot ini
                document.querySelectorAll('.time-slot-btn.selected').forEach(b => b.classList.remove('selected'));
                selectedSlots = [idx];
                btn.classList.add('selected');
            }
        }
    }

    if (selectedSlots.length === 0) {
        clearTimeInputs();
        return;
    }

    const minIdx = Math.min(...selectedSlots);
    const maxIdx = Math.max(...selectedSlots);
    const startSlot = allSlots[minIdx];
    const endSlot   = allSlots[maxIdx];

    document.getElementById('startTimeInput').value = startSlot.start;
    document.getElementById('endTimeInput').value   = endSlot.end;
    document.getElementById('step2Next').disabled   = false;

    // Duration info
    const startMin = timeToMinutes(startSlot.start);
    const endMin   = timeToMinutes(endSlot.end);
    const dur      = endMin - startMin;
    document.getElementById('durationInfo').textContent = `Durasi: ${Math.floor(dur/60)} jam ${dur%60 > 0 ? dur%60 + ' menit' : ''}`;
}

function clearTimeInputs() {
    document.getElementById('startTimeInput').value = '';
    document.getElementById('endTimeInput').value   = '';
    document.getElementById('step2Next').disabled   = true;
    document.getElementById('durationInfo').textContent = '';
}

function timeToMinutes(t) {
    const [h, m] = t.split(':').map(Number);
    return h * 60 + m;
}

function updateSummary() {
    const roomName  = selectedRoom?.name || '-';
    const date      = document.getElementById('bookingDate').value;
    const start     = document.getElementById('startTimeInput').value;
    const end       = document.getElementById('endTimeInput').value;

    const dateLabel = date ? new Date(date).toLocaleDateString('id-ID', {weekday:'long', day:'numeric', month:'long', year:'numeric'}) : '-';
    const approval  = selectedRoom?.requiresApproval == 1
        ? '<span class="badge bg-warning-subtle text-warning ms-2">Perlu Persetujuan Admin</span>'
        : '<span class="badge bg-success-subtle text-success ms-2">Langsung Dikonfirmasi</span>';

    document.getElementById('bookingSummary').innerHTML = `
        <strong>Ringkasan Booking:</strong><br>
        <i class="bi bi-door-open me-1"></i>${roomName} ${approval}<br>
        <i class="bi bi-calendar me-1"></i>${dateLabel}<br>
        <i class="bi bi-clock me-1"></i>${start} – ${end}
    `;
}

// ============================================================
// Room detail modal
// ============================================================
let _detailCard = null;

function showRoomDetail(card) {
    _detailCard = card;
    const d = card.dataset;

    document.getElementById('modalRoomImage').src = d.roomImage || '';
    document.getElementById('modalRoomImage').alt = d.roomName || '';
    document.getElementById('modalRoomName').textContent = d.roomName || '';

    // Badges
    const badges = document.getElementById('modalRoomBadges');
    badges.innerHTML = '';
    if (d.roomLocation) {
        badges.innerHTML += '<span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-geo-alt me-1"></i>'
            + _esc(d.roomLocation) + (d.roomFloor ? ' · Lt. ' + _esc(d.roomFloor) : '') + '</span>';
    }
    badges.innerHTML += '<span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-people me-1"></i>Kapasitas '
        + _esc(d.roomCapacity) + ' orang</span>';
    if (d.roomApproval === '1') {
        badges.innerHTML += '<span class="badge bg-warning-subtle text-warning"><i class="bi bi-clock-history me-1"></i>Perlu Approval</span>';
    }

    // Description
    const descEl = document.getElementById('modalRoomDescription');
    if (d.roomDescription) {
        descEl.textContent = d.roomDescription;
        descEl.style.display = '';
    } else {
        descEl.style.display = 'none';
    }

    // Facilities
    const facSection = document.getElementById('modalFacilitiesSection');
    const facList    = document.getElementById('modalFacilitiesList');
    try {
        const facs = JSON.parse(d.roomFacilities || '[]');
        if (facs.length > 0) {
            facList.innerHTML = facs.map(f =>
                '<span class="badge bg-light text-dark border" style="font-size:0.8rem;">'
                + '<i class="bi bi-check2 me-1 text-success"></i>' + _esc(f) + '</span>'
            ).join('');
            facSection.style.display = '';
        } else {
            facSection.style.display = 'none';
        }
    } catch(e) {
        facSection.style.display = 'none';
    }

    new bootstrap.Modal(document.getElementById('roomDetailModal')).show();
}

function selectFromModal() {
    bootstrap.Modal.getInstance(document.getElementById('roomDetailModal'))?.hide();
    if (_detailCard) {
        selectRoom(_detailCard);
        setTimeout(() => goToStep(2), 300);
    }
}

function _esc(str) {
    const d = document.createElement('div');
    d.textContent = str || '';
    return d.innerHTML;
}

// ============================================================
// Real-time input sanitization & character counters
// ============================================================

// Nama: block characters that are not letters/spaces/.-'
document.getElementById('bookerNameInput')?.addEventListener('input', function() {
    const raw = this.value;
    // Remove any character not in: Unicode letter, space, . - '
    this.value = raw.replace(/[^\p{L}\s.'\\-]/gu, '');
    setFieldState(this, this.value.length >= 2);
});

// NIK: only alphanumeric + - . /
document.getElementById('bookerNikInput')?.addEventListener('input', function() {
    this.value = this.value.replace(/[^a-zA-Z0-9\-\.\/]/g, '').slice(0, 30);
});

// Phone: only digits, +, -, spaces, parentheses
document.getElementById('bookerPhoneInput')?.addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9\+\-\s\(\)]/g, '').slice(0, 20);
});

// Character counters for textareas
function initCounter(textareaId, counterId) {
    const ta = document.getElementById(textareaId);
    const ct = document.getElementById(counterId);
    if (!ta || !ct) return;
    const update = () => {
        const len = ta.value.length;
        ct.textContent = len;
        ct.classList.toggle('text-danger', len > 480);
    };
    ta.addEventListener('input', update);
    update(); // init on page load
}
initCounter('purposeInput',    'purposeCount');
initCounter('notesEquipInput', 'notesEquipCount');
initCounter('notesOtherInput', 'notesOtherCount');

// Helper: set valid/invalid visual state on a field
function setFieldState(el, isValid) {
    el.classList.toggle('is-invalid', !isValid && el.value.length > 0);
    el.classList.toggle('is-valid',    isValid && el.value.length > 0);
}

// ============================================================
// Client-side validation on submit (step 3)
// ============================================================
document.getElementById('bookingForm').addEventListener('submit', function(e) {
    const dept     = document.querySelector('[name="department_id"]').value;
    const meetType = document.querySelector('[name="meeting_type_id"]').value;
    const capacity = parseInt(document.getElementById('capacityInput').value) || 0;
    const email    = document.querySelector('[name="booker_email"]').value.trim();
    const name     = document.querySelector('[name="booker_name"]').value.trim();
    const phone    = document.querySelector('[name="booker_phone"]').value.trim();
    const purpose  = document.getElementById('purposeInput').value.trim();

    let errors = [];

    // Nama lengkap
    if (!name) {
        errors.push('Nama pemesan wajib diisi.');
    } else if (!/^[\p{L}\s.'\\-]+$/u.test(name)) {
        errors.push('Nama hanya boleh berisi huruf, spasi, titik, dan tanda hubung.');
    } else if (name.length < 2 || name.length > 100) {
        errors.push('Nama harus antara 2–100 karakter.');
    }

    // No. HP (jika diisi)
    if (phone) {
        const digitsOnly = phone.replace(/[\s\-\(\)]/g, '');
        if (!/^\+?[0-9]{7,15}$/.test(digitsOnly)) {
            errors.push('Format nomor HP tidak valid (contoh: 08123456789).');
        }
    }

    // Keperluan
    if (!purpose) {
        errors.push('Keperluan/tujuan meeting wajib diisi.');
    } else if (purpose.length < 5) {
        errors.push('Keperluan/tujuan terlalu singkat (minimal 5 karakter).');
    } else if (purpose.length > 50) {
        errors.push('Keperluan/tujuan terlalu panjang (maksimal 50 karakter).');
    }

    if (!dept)          errors.push('Departemen wajib dipilih.');
    if (!meetType)      errors.push('Jenis meeting wajib dipilih.');
    if (capacity < 1)   errors.push('Jumlah peserta wajib diisi (minimal 1 orang).');

    // Capacity max check
    if (selectedRoom && capacity > 0 && capacity > parseInt(selectedRoom.capacity)) {
        errors.push('Jumlah peserta melebihi kapasitas ruangan (' + selectedRoom.capacity + ' orang).');
    }

    <?php if ($allowedEmailDomain): ?>
    // Email domain restriction
    if (email && email.indexOf('@') !== -1) {
        const domain = email.split('@').pop().toLowerCase();
        if (domain !== '<?= strtolower(addslashes($allowedEmailDomain)) ?>') {
            errors.push('Email harus menggunakan domain @<?= e($allowedEmailDomain) ?>.');
        }
    }
    <?php endif; ?>

    if (errors.length > 0) {
        e.preventDefault();
        const step3 = document.getElementById('step3');
        const existing = step3.querySelector('.js-validation-error');
        if (existing) existing.remove();
        const alertEl = document.createElement('div');
        alertEl.className = 'alert alert-danger mb-3 js-validation-error';
        alertEl.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>'
            + '<strong>Harap perbaiki:</strong>'
            + '<ul class="mb-0 mt-1 ps-3">'
            + errors.map(err => '<li>' + err + '</li>').join('')
            + '</ul>';
        const summaryEl = document.getElementById('bookingSummary');
        step3.insertBefore(alertEl, summaryEl);
        window.scrollTo({top: 0, behavior: 'smooth'});
    }
});

// Dismiss client-side error on field change
['department_id', 'meeting_type_id'].forEach(name => {
    document.querySelector('[name="' + name + '"]')?.addEventListener('change', clearJsError);
});
document.getElementById('capacityInput')?.addEventListener('input', clearJsError);
document.querySelector('[name="booker_email"]')?.addEventListener('input', clearJsError);
function clearJsError() {
    document.querySelector('.js-validation-error')?.remove();
}

// ============================================================
// Unified real-time validation engine
// ============================================================
(function () {
    const ALLOWED_DOMAIN = <?= json_encode($allowedEmailDomain) ?>;

    // --- Core engine ---
    // rules: array of (val) => null | string
    //   null  = rule passed, continue
    //   string = error message, stop
    // opts.optional = true  → empty value resets to idle (no error, no success)
    // opts.okMsg    = string | (val) => string
    function makeValidator(inputId, feedbackId, rules, opts) {
        const input = document.getElementById(inputId);
        const fb    = document.getElementById(feedbackId);
        if (!input || !fb) return null;

        const defaultHtml = fb.innerHTML;

        function setIdle()  {
            fb.innerHTML  = defaultHtml;
            fb.className  = 'form-text text-muted';
            input.classList.remove('is-valid', 'is-invalid');
        }
        function setOk(msg) {
            fb.innerHTML  = '<i class="bi bi-check-circle-fill me-1"></i>' + msg;
            fb.className  = 'form-text text-success';
            input.classList.add('is-valid');
            input.classList.remove('is-invalid');
        }
        function setErr(msg) {
            fb.innerHTML  = '<i class="bi bi-x-circle-fill me-1"></i>' + msg;
            fb.className  = 'form-text text-danger';
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
        }

        function run() {
            clearJsError();
            const val = input.value.trim();

            if (val === '' && opts && opts.optional) { setIdle(); return; }

            for (const rule of rules) {
                const err = rule(val);
                if (err !== null) { setErr(err); return; }
            }

            const ok = opts && opts.okMsg;
            setOk(typeof ok === 'function' ? ok(val) : (ok || 'Valid.'));
        }

        let timer;
        input.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(run, opts && opts.debounce != null ? opts.debounce : 350);
        });
        input.addEventListener('blur', run);

        if (input.value.trim()) run(); // validate on load if pre-filled

        return run; // return runner so it can be triggered externally
    }

    // --- Rule helpers ---
    const R = {
        required : msg       => val => val === '' ? msg : null,
        minLen   : (n, msg)  => val => val.length < n  ? msg : null,
        maxLen   : (n, msg)  => val => val.length > n  ? msg : null,
        pattern  : (re, msg) => val => re.test(val)    ? null : msg,
        custom   : fn        => fn,
    };

    // --- Field definitions ---

    // Nama Lengkap
    makeValidator('bookerNameInput', 'nameFeedback', [
        R.required('Nama pemesan wajib diisi.'),
        R.minLen(2,   'Nama minimal 2 karakter.'),
        R.maxLen(100, 'Nama maksimal 100 karakter.'),
        R.pattern(/^[\p{L}\s.'\\-]+$/u, 'Nama hanya boleh berisi huruf, spasi, titik, dan tanda hubung.'),
    ], { okMsg: 'Nama valid.' });

    // NIK (opsional)
    makeValidator('bookerNikInput', 'nikFeedback', [
        R.maxLen(30, 'NIK maksimal 30 karakter.'),
        R.pattern(/^[a-zA-Z0-9\-\.\/]+$/, 'NIK hanya boleh berisi huruf, angka, tanda hubung, slash, dan titik.'),
    ], { optional: true, okMsg: 'NIK valid.' });

    // Email
    makeValidator('emailInput', 'emailFeedback', [
        R.required('Email wajib diisi.'),
        R.pattern(/^[^\s@]+@[^\s@]+\.[^\s@]+$/, 'Format email tidak valid.'),
        R.custom(val => {
            if (!ALLOWED_DOMAIN) return null;
            const domain = val.split('@')[1]?.toLowerCase();
            return domain === ALLOWED_DOMAIN.toLowerCase()
                ? null
                : 'Email harus menggunakan domain @' + ALLOWED_DOMAIN + '.';
        }),
    ], { okMsg: 'Email valid.' });

    // No. HP (opsional)
    makeValidator('bookerPhoneInput', 'phoneFeedback', [
        R.custom(val => {
            const d = val.replace(/[\s\-\(\)]/g, '');
            return /^\+?[0-9]{7,15}$/.test(d) ? null : 'Format nomor HP tidak valid (contoh: 08123456789).';
        }),
    ], { optional: true, okMsg: 'Nomor HP valid.' });

    // Jumlah Peserta
    const runCapacity = makeValidator('capacityInput', 'capacityFeedback', [
        R.required('Jumlah peserta wajib diisi.'),
        R.custom(val => parseInt(val) >= 1 ? null : 'Minimal 1 orang.'),
        R.custom(val => {
            const max = selectedRoom ? parseInt(selectedRoom.capacity) : 0;
            return max && parseInt(val) > max
                ? 'Melebihi kapasitas ruangan (maks. ' + max + ' orang).'
                : null;
        }),
    ], {
        debounce: 0,
        okMsg: val => {
            const max = selectedRoom ? parseInt(selectedRoom.capacity) : 0;
            return max ? parseInt(val) + ' dari ' + max + ' orang.' : parseInt(val) + ' orang.';
        },
    });
    // Expose so selectRoom() can trigger re-validate after room changes
    window._revalidateCapacity = runCapacity;

    // Keperluan / Tujuan
    makeValidator('purposeInput', 'purposeFeedback', [
        R.required('Keperluan/tujuan wajib diisi.'),
        R.minLen(5,  'Keperluan minimal 5 karakter.'),
        R.maxLen(50, 'Keperluan maksimal 50 karakter.'),
    ], { debounce: 200, okMsg: 'Keperluan valid.' });

})();

// Restore state if form was submitted with errors
<?php if (!empty($formErrors) && !empty($_POST)): ?>
(function() {
    const roomId = '<?= e($_POST['room_id'] ?? '') ?>';
    const roomCard = document.querySelector('[data-room-id="' + roomId + '"]');
    if (roomCard) {
        selectRoom(roomCard);
        goToStep(3);
    }
})();
<?php endif; ?>
</script>
</body>
</html>
<?php
// =====================================================================
// Flush HTML to browser immediately, then do slow work (email) after
// =====================================================================
$html = ob_get_clean();
header('Content-Type: text/html; charset=utf-8');
header('Connection: close');
header('Content-Encoding: none');
header('Content-Length: ' . strlen($html));
echo $html;

// Send response to client now
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    ob_implicit_flush(true);
    flush();
}

// Continue running after browser disconnects
ignore_user_abort(true);
set_time_limit(30);

// Deferred: send confirmation email
if (!empty($pendingMailBooking)) {
    Mailer::sendBookingConfirmation($pendingMailBooking);
}

// Deferred: notify admins
if (!empty($pendingNotifData)) {
    Notification::createForAdmins(
        $pendingNotifData['type'],
        $pendingNotifData['title'],
        $pendingNotifData['message'],
        $pendingNotifData['id']
    );
}

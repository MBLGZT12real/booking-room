<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requirePermission('bookings.approve');

$pageTitle = 'Buat Booking';
$db = db();

$rooms        = $db->fetchAll("SELECT id, name, code, capacity FROM rooms WHERE is_active = 1 ORDER BY name ASC");
$departments  = $db->fetchAll("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name ASC");
$meetingTypes = $db->fetchAll("SELECT id, name FROM meeting_types WHERE is_active = 1 ORDER BY name ASC");

$errors = [];
$form   = [
    'room_id'         => '',
    'booking_date'    => date('Y-m-d'),
    'start_time'      => '',
    'end_time'        => '',
    'booker_name'     => '',
    'booker_nik'      => '',
    'booker_email'    => '',
    'booker_phone'    => '',
    'department_id'   => '',
    'meeting_type_id' => '',
    'capacity_needed' => '',
    'purpose'         => '',
    'notes_equipment' => '',
    'notes_other'     => '',
    'is_recurring'    => '0',
    'repeat_type'     => 'weekly',
    'days_of_week'    => [],
    'end_type'        => 'count',
    'end_count'       => '8',
    'end_date'        => date('Y-m-d', strtotime('+3 months')),
];

if (isPost() && verifyCsrf()) {
    // Merge posted values back for repopulation
    foreach ($form as $k => $v) {
        if ($k === 'days_of_week') {
            $form[$k] = array_map('intval', (array) ($_POST['days_of_week'] ?? []));
        } else {
            $form[$k] = pParam($k, $v);
        }
    }

    $roomId      = (int) $form['room_id'];
    $date        = $form['booking_date'];
    $startTime   = $form['start_time'];
    $endTime     = $form['end_time'];
    $isRecurring = $form['is_recurring'] === '1';

    $data = [
        'room_id'         => $roomId,
        'booking_date'    => $date,
        'start_time'      => $startTime,
        'end_time'        => $endTime,
        'booker_name'     => trim($form['booker_name']),
        'booker_nik'      => trim($form['booker_nik'])   ?: null,
        'booker_email'    => trim($form['booker_email']),
        'booker_phone'    => trim($form['booker_phone']) ?: null,
        'department_id'   => $form['department_id']   ?: null,
        'meeting_type_id' => $form['meeting_type_id'] ?: null,
        'capacity_needed' => $form['capacity_needed'] ?: null,
        'purpose'         => trim($form['purpose'])          ?: null,
        'notes_equipment' => trim($form['notes_equipment'])  ?: null,
        'notes_other'     => trim($form['notes_other'])      ?: null,
    ];

    // Basic validation
    if (!$roomId)                           $errors[] = 'Pilih ruangan.';
    if (!$date)                             $errors[] = 'Pilih tanggal booking.';
    if (!$startTime || !$endTime)           $errors[] = 'Pilih waktu mulai dan selesai.';
    if ($startTime && $endTime && $startTime >= $endTime) $errors[] = 'Waktu selesai harus setelah waktu mulai.';
    if (empty($data['booker_name']))        $errors[] = 'Nama pemesanan harus diisi.';
    if (empty($data['booker_email']) || !filter_var($data['booker_email'], FILTER_VALIDATE_EMAIL))
                                            $errors[] = 'Alamat email tidak valid.';

    if (!$errors) {
        if ($isRecurring) {
            $daysOfWeek = $form['days_of_week'];
            $endType    = $form['end_type'];
            $endValue   = ($endType === 'count') ? (int) $form['end_count'] : $form['end_date'];
            $repeatType = $form['repeat_type'];

            if (empty($daysOfWeek)) {
                $errors[] = 'Pilih setidaknya satu hari untuk booking berulang.';
            } elseif ($endType === 'count' && ((int)$endValue < 1 || (int)$endValue > 52)) {
                $errors[] = 'Jumlah pengulangan harus antara 1 dan 52.';
            } elseif ($endType === 'date' && (!$endValue || $endValue <= $date)) {
                $errors[] = 'Tanggal akhir harus setelah tanggal mulai.';
            }

            if (!$errors) {
                $dates = BookingHelper::generateRecurringDates($date, $repeatType, $daysOfWeek, $endType, $endValue);

                if (empty($dates)) {
                    $errors[] = 'Tidak ada tanggal yang dihasilkan. Periksa konfigurasi pengulangan.';
                } else {
                    // Generate UUID for this recurring group
                    $groupId = sprintf(
                        '%08x-%04x-%04x-%04x-%012x',
                        mt_rand(), mt_rand(0, 0xffff),
                        mt_rand(0x4000, 0x4fff),
                        mt_rand(0x8000, 0xbfff),
                        mt_rand()
                    );

                    $createdCount = 0;
                    $failedItems  = [];

                    foreach ($dates as $d) {
                        $result = BookingHelper::createAdminBooking(
                            array_merge($data, ['booking_date' => $d]),
                            $groupId
                        );
                        if ($result['success']) {
                            $createdCount++;
                        } else {
                            $failedItems[] = $d . ': ' . $result['message'];
                        }
                    }

                    if ($createdCount > 0) {
                        $msg = "Berhasil membuat {$createdCount} booking berulang.";
                        if ($failedItems) {
                            $msg .= ' ' . count($failedItems) . ' tanggal dilewati (konflik/libur).';
                        }
                        flash('success', $msg);
                        redirect(BASE_URL . '/admin/bookings/?recurring_group=' . $groupId);
                    } else {
                        $errors[] = 'Semua tanggal gagal dibuat. Kemungkinan ada konflik atau hari libur di seluruh jadwal.';
                        if ($failedItems) {
                            $errors[] = implode('<br>', array_slice($failedItems, 0, 5));
                        }
                    }
                }
            }
        } else {
            $result = BookingHelper::createAdminBooking($data);
            if ($result['success']) {
                flash('success', 'Booking berhasil dibuat: ' . $result['booking_code']);
                redirect(BASE_URL . '/admin/bookings/detail.php?id=' . $result['booking_id']);
            } else {
                $errors[] = $result['message'];
            }
        }
    }
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
        <h5><i class="bi bi-calendar-plus me-2 text-primary"></i><?= e($pageTitle) ?></h5>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/bookings/">Booking</a></li>
                <li class="breadcrumb-item active">Buat Booking</li>
            </ol>
        </nav>
    </div>
    <a href="<?= BASE_URL ?>/admin/bookings/" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <?= implode('<br>', array_map('e', $errors)) ?>
</div>
<?php endif; ?>

<form method="POST" id="createForm" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="is_recurring" id="is_recurring_hidden" value="0">

    <div class="row g-3">

        <!-- Left: Main Booking Info -->
        <div class="col-lg-8">

            <!-- Room & Schedule -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-building me-2 text-primary"></i>Ruangan & Jadwal</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Ruangan <span class="text-danger">*</span></label>
                            <select name="room_id" id="room_id" class="form-select" required>
                                <option value="">— Pilih Ruangan —</option>
                                <?php foreach ($rooms as $r): ?>
                                <option value="<?= $r['id'] ?>"
                                    data-capacity="<?= $r['capacity'] ?>"
                                    <?= $form['room_id'] == $r['id'] ? 'selected' : '' ?>>
                                    <?= e($r['name']) ?><?= $r['code'] ? ' (' . e($r['code']) . ')' : '' ?>
                                    — maks <?= $r['capacity'] ?> orang
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" name="booking_date" id="booking_date" class="form-control"
                                   value="<?= e($form['booking_date']) ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Waktu <span class="text-danger">*</span></label>

                            <!-- Slot legend -->
                            <div class="d-flex flex-wrap gap-3 mb-2 small text-muted">
                                <span><span class="slot-legend slot-legend-available"></span> Tersedia</span>
                                <span><span class="slot-legend slot-legend-selected"></span> Dipilih</span>
                                <span><span class="slot-legend slot-legend-booked"></span> Sudah Dipesan</span>
                                <span><span class="slot-legend slot-legend-past"></span> Tidak Tersedia</span>
                            </div>

                            <!-- Slot container -->
                            <div id="slotsLoading" style="display:none;" class="text-muted small">
                                <span class="spinner-border spinner-border-sm me-1"></span>Memuat slot waktu...
                            </div>
                            <div id="slotsEmpty" style="display:none;" class="alert alert-warning py-2 small mb-0">
                                <i class="bi bi-calendar-x me-1"></i>Tidak ada slot tersedia (hari libur atau bukan hari kerja).
                            </div>
                            <div id="slotsContainer" class="d-flex flex-wrap gap-1"></div>
                            <div id="slotsHint" class="text-muted small mt-1" style="display:none;">
                                Klik slot untuk memilih. Klik beberapa slot berurutan untuk memilih durasi lebih panjang.
                            </div>
                            <div id="slotsPlaceholder" class="text-muted small fst-italic">
                                Pilih ruangan dan tanggal untuk melihat slot waktu tersedia.
                            </div>

                            <input type="hidden" name="start_time" id="start_time" value="<?= e($form['start_time']) ?>">
                            <input type="hidden" name="end_time"   id="end_time"   value="<?= e($form['end_time']) ?>">

                            <div id="timeDisplay" class="mt-2 d-flex align-items-center gap-3" style="display:none;">
                                <span class="badge bg-primary px-3 py-2 fs-6" id="timeDisplayText"></span>
                                <span class="text-muted small" id="durationInfo"></span>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="clearSlotBtn" style="display:none;">
                                    <i class="bi bi-x"></i> Hapus Pilihan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Booker Info -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-person me-2 text-primary"></i>Data Pemesanan</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="booker_name" class="form-control"
                                   value="<?= e($form['booker_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">NIK / ID Karyawan</label>
                            <input type="text" name="booker_nik" class="form-control"
                                   value="<?= e($form['booker_nik']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" name="booker_email" class="form-control"
                                   value="<?= e($form['booker_email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">No. Telepon</label>
                            <input type="tel" name="booker_phone" class="form-control"
                                   value="<?= e($form['booker_phone']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Departemen</label>
                            <select name="department_id" class="form-select">
                                <option value="">— Pilih Departemen —</option>
                                <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $form['department_id'] == $d['id'] ? 'selected' : '' ?>>
                                    <?= e($d['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tipe Meeting</label>
                            <select name="meeting_type_id" class="form-select">
                                <option value="">— Pilih Tipe —</option>
                                <?php foreach ($meetingTypes as $mt): ?>
                                <option value="<?= $mt['id'] ?>" <?= $form['meeting_type_id'] == $mt['id'] ? 'selected' : '' ?>>
                                    <?= e($mt['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Jumlah Peserta</label>
                            <input type="number" name="capacity_needed" id="capacity_needed" class="form-control"
                                   min="1" value="<?= e($form['capacity_needed']) ?>">
                            <div class="form-text" id="capacityHint"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Tujuan / Agenda</label>
                            <textarea name="purpose" class="form-control" rows="2"><?= e($form['purpose']) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Catatan Alat/Perlengkapan</label>
                            <textarea name="notes_equipment" class="form-control" rows="2"><?= e($form['notes_equipment']) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Catatan Lainnya</label>
                            <textarea name="notes_other" class="form-control" rows="2"><?= e($form['notes_other']) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right: Recurring Options & Preview -->
        <div class="col-lg-4">

            <!-- Recurring Toggle -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-arrow-repeat me-2 text-primary"></i>Pengulangan</h6>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="is_recurring_toggle" role="switch">
                        <label class="form-check-label fw-semibold" for="is_recurring_toggle">
                            Booking Berulang
                        </label>
                    </div>

                    <div id="recurringOptions" style="display:none;">
                        <!-- Frequency -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Frekuensi</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="repeat_type" id="rt_weekly" value="weekly"
                                        <?= $form['repeat_type'] === 'weekly' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="rt_weekly">Mingguan</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="repeat_type" id="rt_biweekly" value="biweekly"
                                        <?= $form['repeat_type'] === 'biweekly' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="rt_biweekly">2 Minggu Sekali</label>
                                </div>
                            </div>
                        </div>

                        <!-- Day of week checkboxes -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Hari</label>
                            <div class="d-flex flex-wrap gap-2" id="dayCheckboxes">
                                <?php
                                $dayLabels = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
                                for ($dow = 0; $dow <= 6; $dow++):
                                    $checked = in_array($dow, $form['days_of_week']) ? 'checked' : '';
                                ?>
                                <div class="form-check form-check-inline m-0">
                                    <input class="form-check-input day-check" type="checkbox"
                                           name="days_of_week[]" id="dow_<?= $dow ?>"
                                           value="<?= $dow ?>" <?= $checked ?>>
                                    <label class="form-check-label small" for="dow_<?= $dow ?>">
                                        <?= $dayLabels[$dow] ?>
                                    </label>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <!-- End condition -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Berakhir</label>
                            <div class="d-flex flex-column gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <input class="form-check-input" type="radio" name="end_type" id="et_count" value="count"
                                        <?= $form['end_type'] !== 'date' ? 'checked' : '' ?>>
                                    <label for="et_count" class="form-check-label small me-2">Setelah</label>
                                    <input type="number" name="end_count" id="end_count" class="form-control form-control-sm"
                                           style="width:70px;" min="1" max="52" value="<?= e($form['end_count']) ?>">
                                    <span class="small text-muted">kali</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <input class="form-check-input" type="radio" name="end_type" id="et_date" value="date"
                                        <?= $form['end_type'] === 'date' ? 'checked' : '' ?>>
                                    <label for="et_date" class="form-check-label small me-2">Sampai</label>
                                    <input type="date" name="end_date" id="end_date" class="form-control form-control-sm"
                                           value="<?= e($form['end_date']) ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="noRecurringNote" class="text-muted small fst-italic">
                        Aktifkan untuk membuat booking di tanggal berulang (misal: setiap Selasa).
                    </div>
                </div>
            </div>

            <!-- Date Preview -->
            <div class="card border-0 shadow-sm mb-3" id="previewCard" style="display:none;">
                <div class="card-header bg-transparent border-0 pt-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0"><i class="bi bi-calendar3 me-2 text-success"></i>Preview Tanggal</h6>
                    <span class="badge bg-success" id="previewCount">0</span>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush" id="previewList" style="max-height:320px;overflow-y:auto;font-size:0.82rem;">
                    </ul>
                </div>
            </div>

            <!-- Submit -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Booking yang dibuat melalui panel admin langsung berstatus <strong>Dikonfirmasi</strong>.
                    </div>
                    <button type="submit" class="btn btn-primary w-100" id="submitBtn">
                        <i class="bi bi-check-lg me-1"></i>
                        <span id="submitLabel">Buat Booking</span>
                    </button>
                </div>
            </div>

        </div>
    </div>
</form>

</div>

<style>
.time-slot-btn {
    padding: 6px 10px;
    font-size: 0.8rem;
    border-radius: 6px;
    border: 1px solid #dee2e6;
    background: #fff;
    cursor: pointer;
    transition: all .15s;
    user-select: none;
}
.time-slot-btn:hover:not(.booked):not(.past):not([disabled]) {
    border-color: var(--bs-primary);
    background: rgba(13, 110, 253, 0.08);
}
.time-slot-btn.selected {
    background: #0d6efd;
    color: #fff;
    border-color: #0d6efd;
}
.time-slot-btn.booked {
    background: #f8d7da !important;
    color: #842029 !important;
    border-color: #f5c2c7 !important;
    cursor: not-allowed;
    text-decoration: line-through;
    opacity: 1 !important;
}
.time-slot-btn.past {
    background: #f8f9fa;
    color: #adb5bd;
    border-color: #dee2e6;
    cursor: not-allowed;
}
/* Legend dots */
.slot-legend {
    display: inline-block;
    width: 12px; height: 12px;
    border-radius: 3px;
    border: 1px solid #dee2e6;
    vertical-align: middle;
    margin-right: 3px;
}
.slot-legend-available { background: #fff; border-color: #dee2e6; }
.slot-legend-selected  { background: #0d6efd; border-color: #0d6efd; }
.slot-legend-booked    { background: #f8d7da; border-color: #f5c2c7; }
.slot-legend-past      { background: #f8f9fa; border-color: #dee2e6; }
</style>

<!-- Recurring confirmation modal -->
<div class="modal fade" id="recurringConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-arrow-repeat text-primary me-2"></i>Konfirmasi Booking Berulang
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="mb-1">Anda akan membuat <strong id="rcCount">0</strong> booking sekaligus.</p>
                <p class="text-muted small mb-0" id="rcRange"></p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-pencil me-1"></i>Cek Lagi
                </button>
                <button type="button" class="btn btn-primary" id="rcConfirmBtn">
                    <i class="bi bi-check-lg me-1"></i>Ya, Buat Semua
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const roomSel        = document.getElementById('room_id');
    const datePicker     = document.getElementById('booking_date');
    const slotsLoading   = document.getElementById('slotsLoading');
    const slotsEmpty     = document.getElementById('slotsEmpty');
    const slotsContainer = document.getElementById('slotsContainer');
    const slotsHint      = document.getElementById('slotsHint');
    const slotsPlaceholder = document.getElementById('slotsPlaceholder');
    const startHidden    = document.getElementById('start_time');
    const endHidden      = document.getElementById('end_time');
    const timeDisplay    = document.getElementById('timeDisplay');
    const timeDisplayText = document.getElementById('timeDisplayText');
    const durationInfo   = document.getElementById('durationInfo');
    const clearSlotBtn   = document.getElementById('clearSlotBtn');
    const capInput       = document.getElementById('capacity_needed');
    const capHint        = document.getElementById('capacityHint');

    const recurringToggle  = document.getElementById('is_recurring_toggle');
    const recurringHidden  = document.getElementById('is_recurring_hidden');
    const recurringOptions = document.getElementById('recurringOptions');
    const noRecurringNote  = document.getElementById('noRecurringNote');
    const previewCard      = document.getElementById('previewCard');
    const previewList      = document.getElementById('previewList');
    const previewCount     = document.getElementById('previewCount');
    const submitLabel      = document.getElementById('submitLabel');

    // ── Slot logic (same as public booking page) ─────────────────

    let selectedSlots = []; // array of slot indices
    let currentSlots  = []; // full slots array from API

    function timeToMinutes(t) {
        const [h, m] = t.split(':').map(Number);
        return h * 60 + m;
    }

    function clearSlotSelection() {
        selectedSlots = [];
        startHidden.value = '';
        endHidden.value   = '';
        timeDisplay.style.display = 'none';
        document.querySelectorAll('.time-slot-btn.selected').forEach(b => b.classList.remove('selected'));
    }

    function showSlotState(state) {
        slotsLoading.style.display   = state === 'loading'  ? '' : 'none';
        slotsEmpty.style.display     = state === 'empty'    ? '' : 'none';
        slotsHint.style.display      = state === 'slots'    ? '' : 'none';
        slotsPlaceholder.style.display = state === 'initial' ? '' : 'none';
    }

    function loadSlots() {
        const roomId = roomSel.value;
        const date   = datePicker.value;

        slotsContainer.innerHTML = '';
        clearSlotSelection();

        if (!roomId || !date) {
            showSlotState('initial');
            return;
        }

        showSlotState('loading');

        fetch(`<?= BASE_URL ?>/api/available-slots.php?room_id=${roomId}&date=${date}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.slots.length) {
                    showSlotState('empty');
                    currentSlots = [];
                    return;
                }
                currentSlots = data.slots;
                renderSlots(data.slots);
                showSlotState('slots');
            })
            .catch(() => {
                showSlotState('empty');
                currentSlots = [];
            });
    }

    function renderSlots(slots) {
        slotsContainer.innerHTML = '';

        slots.forEach((slot, idx) => {
            const btn = document.createElement('button');
            btn.type        = 'button';
            btn.textContent = slot.label;
            btn.dataset.idx   = idx;
            btn.dataset.start = slot.start_full;
            btn.dataset.end   = slot.end_full;

            if (!slot.available && slot.reason === 'booked') {
                btn.className = 'time-slot-btn booked';
                btn.disabled  = true;
                btn.title     = 'Slot sudah dipesan';
            } else if (!slot.available) {
                btn.className = 'time-slot-btn past';
                btn.disabled  = true;
                btn.title     = 'Waktu sudah lewat / terlalu dekat';
            } else {
                btn.className = 'time-slot-btn';
                btn.onclick   = () => toggleSlot(btn, slots);
            }

            slotsContainer.appendChild(btn);
        });

        // Restore previous selection after form error re-submit
        const prevStart = '<?= e($form['start_time']) ?>';
        const prevEnd   = '<?= e($form['end_time']) ?>';
        if (prevStart && prevEnd) {
            slots.forEach((slot, idx) => {
                if (!slot.available) return;
                if (slot.start_full >= prevStart && slot.end_full <= prevEnd) {
                    selectedSlots.push(idx);
                    const b = slotsContainer.querySelectorAll('.time-slot-btn')[idx];
                    if (b) b.classList.add('selected');
                }
            });
            startHidden.value = prevStart;
            endHidden.value   = prevEnd;
            updateTimeDisplay(slots);
        }
    }

    function toggleSlot(btn, slots) {
        const idx       = parseInt(btn.dataset.idx);
        const slotStart = btn.dataset.start;
        const slotEnd   = btn.dataset.end;

        if (btn.classList.contains('selected')) {
            // Deselect only the endpoints of the selection
            const firstIdx = Math.min(...selectedSlots);
            const lastIdx  = Math.max(...selectedSlots);
            if (idx !== firstIdx && idx !== lastIdx) return;
            selectedSlots = selectedSlots.filter(i => i !== idx);
            btn.classList.remove('selected');
        } else {
            if (selectedSlots.length === 0) {
                selectedSlots.push(idx);
                btn.classList.add('selected');
            } else {
                const firstIdx  = Math.min(...selectedSlots);
                const lastIdx   = Math.max(...selectedSlots);
                const firstSlot = slots[firstIdx];
                const lastSlot  = slots[lastIdx];

                // Must be adjacent (time-based check, same as public page)
                if (slotStart === lastSlot.end_full || slotEnd === firstSlot.start_full) {
                    selectedSlots.push(idx);
                    btn.classList.add('selected');
                } else {
                    // Not adjacent — reset to this slot only
                    document.querySelectorAll('.time-slot-btn.selected').forEach(b => b.classList.remove('selected'));
                    selectedSlots = [idx];
                    btn.classList.add('selected');
                }
            }
        }

        if (selectedSlots.length === 0) {
            startHidden.value = '';
            endHidden.value   = '';
            timeDisplay.style.display = 'none';
            return;
        }

        const minIdx    = Math.min(...selectedSlots);
        const maxIdx    = Math.max(...selectedSlots);
        const startSlot = slots[minIdx];
        const endSlot   = slots[maxIdx];

        startHidden.value = startSlot.start_full;
        endHidden.value   = endSlot.end_full;
        updateTimeDisplay(slots);
    }

    function updateTimeDisplay(slots) {
        if (!selectedSlots.length) return;
        const s = slots || currentSlots;
        const minIdx    = Math.min(...selectedSlots);
        const maxIdx    = Math.max(...selectedSlots);
        const startSlot = s[minIdx];
        const endSlot   = s[maxIdx];
        const startMin  = timeToMinutes(startSlot.start_full.substring(0,5));
        const endMin    = timeToMinutes(endSlot.end_full.substring(0,5));
        const dur       = endMin - startMin;
        const durText   = Math.floor(dur / 60) + ' jam' + (dur % 60 ? ' ' + (dur % 60) + ' menit' : '');

        timeDisplayText.textContent = startSlot.start_full.substring(0,5) + ' – ' + endSlot.end_full.substring(0,5) + ' WIB';
        durationInfo.textContent    = 'Durasi: ' + durText;
        clearSlotBtn.style.display  = '';
        timeDisplay.style.display   = 'flex';
    }

    // ── Capacity hint ─────────────────────────────────────────────

    function updateCapacityHint() {
        const opt = roomSel.options[roomSel.selectedIndex];
        const cap = opt ? parseInt(opt.dataset.capacity) : 0;
        if (cap) {
            capHint.textContent = 'Kapasitas ruangan: ' + cap + ' orang';
        } else {
            capHint.textContent = '';
        }
    }

    // ── Recurring logic ───────────────────────────────────────────

    function dayNameId(dow) {
        return ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][dow];
    }

    function formatDateId(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr + 'T00:00:00');
        const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
        return dayNameId(d.getDay()) + ', ' + d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
    }

    function generatePreviewDates() {
        const startDate = datePicker.value;
        if (!startDate || !recurringToggle.checked) return [];

        const repeatType = document.querySelector('input[name="repeat_type"]:checked')?.value || 'weekly';
        const daysOfWeek = Array.from(document.querySelectorAll('.day-check:checked')).map(el => parseInt(el.value));
        const endType    = document.querySelector('input[name="end_type"]:checked')?.value  || 'count';
        const endCount   = parseInt(document.getElementById('end_count').value) || 8;
        const endDateStr = document.getElementById('end_date').value;

        if (!daysOfWeek.length) return [];

        const stepDays = (repeatType === 'biweekly') ? 14 : 7;
        const startD   = new Date(startDate + 'T00:00:00');
        const maxDate  = (endType === 'date' && endDateStr) ? new Date(endDateStr + 'T00:00:00') : null;
        const maxCount = (endType === 'count') ? endCount : 200;

        // Sunday of the week containing startDate
        const startDow  = startD.getDay();
        const weekStart = new Date(startD);
        weekStart.setDate(weekStart.getDate() - startDow);

        const dates     = [];
        let weekCurrent = new Date(weekStart);
        let iterations  = 0;
        const sortedDow = [...daysOfWeek].sort((a, b) => a - b);

        while (dates.length < maxCount && iterations < 500) {
            iterations++;
            for (const dow of sortedDow) {
                const d = new Date(weekCurrent);
                d.setDate(d.getDate() + dow);
                if (d < startD) continue;
                if (maxDate && d > maxDate) return dates;
                if (dates.length >= maxCount) return dates;
                dates.push(d.toISOString().substring(0, 10));
            }
            weekCurrent.setDate(weekCurrent.getDate() + stepDays);
        }
        return dates;
    }

    function updatePreview() {
        if (!recurringToggle.checked) {
            previewCard.style.display = 'none';
            return;
        }
        const dates = generatePreviewDates();
        previewCount.textContent = dates.length;
        previewList.innerHTML = '';

        if (!dates.length) {
            previewList.innerHTML = '<li class="list-group-item text-muted small fst-italic py-2">Pilih hari untuk melihat preview.</li>';
        } else {
            dates.forEach((d, i) => {
                const li = document.createElement('li');
                li.className = 'list-group-item py-1 px-3 d-flex align-items-center gap-2';
                li.innerHTML = `<span class="badge bg-secondary" style="min-width:28px;">${i+1}</span><span>${formatDateId(d)}</span>`;
                previewList.appendChild(li);
            });
        }
        previewCard.style.display = 'block';
    }

    // ── Recurring toggle ─────────────────────────────────────────

    function applyRecurringToggle() {
        const on = recurringToggle.checked;
        recurringHidden.value     = on ? '1' : '0';
        recurringOptions.style.display = on ? '' : 'none';
        noRecurringNote.style.display  = on ? 'none' : '';
        submitLabel.textContent = on ? 'Buat Semua Booking' : 'Buat Booking';
        updatePreview();
    }

    // Auto-check the day corresponding to the selected booking_date
    function syncDayCheckbox() {
        const d = datePicker.value;
        if (!d) return;
        const dow = new Date(d + 'T00:00:00').getDay();
        const chk = document.getElementById('dow_' + dow);
        if (chk && !chk.checked) chk.checked = true;
        updatePreview();
    }

    // ── Event wiring ─────────────────────────────────────────────

    clearSlotBtn.addEventListener('click', () => {
        clearSlotSelection();
        document.querySelectorAll('.time-slot-btn.selected').forEach(b => b.classList.remove('selected'));
        clearSlotBtn.style.display = 'none';
    });

    roomSel.addEventListener('change', () => { loadSlots(); updateCapacityHint(); });
    datePicker.addEventListener('change', () => { loadSlots(); syncDayCheckbox(); });
    recurringToggle.addEventListener('change', applyRecurringToggle);

    document.querySelectorAll('input[name="repeat_type"], input[name="end_type"]').forEach(el => {
        el.addEventListener('change', updatePreview);
    });
    document.querySelectorAll('.day-check').forEach(el => el.addEventListener('change', updatePreview));
    document.getElementById('end_count').addEventListener('input', updatePreview);
    document.getElementById('end_date').addEventListener('change', updatePreview);

    // ── Submit confirmation ───────────────────────────────────────

    document.getElementById('createForm').addEventListener('submit', function (e) {
        if (!recurringToggle.checked) return;
        var dates = generatePreviewDates();
        if (!dates || dates.length < 2) return;
        e.preventDefault();
        var f = this;
        document.getElementById('rcCount').textContent = dates.length;
        document.getElementById('rcRange').textContent = dates[0] + ' – ' + dates[dates.length - 1];
        var modal = new bootstrap.Modal(document.getElementById('recurringConfirmModal'));
        document.getElementById('rcConfirmBtn').onclick = function () {
            modal.hide();
            f.submit();
        };
        modal.show();
    });

    // ── Init ─────────────────────────────────────────────────────

    updateCapacityHint();
    showSlotState('initial');
    applyRecurringToggle();

    // If form was re-submitted with errors, reload slots to restore selection
    <?php if ($form['room_id'] && $form['booking_date']): ?>
    loadSlots();
    <?php endif; ?>

    // Restore recurring toggle state on error repopulation
    <?php if ($form['is_recurring'] === '1'): ?>
    recurringToggle.checked = true;
    applyRecurringToggle();
    <?php endif; ?>

})();
</script>

<?php include dirname(__DIR__) . '/layout/footer.php'; ?>

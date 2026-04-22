<?php
require_once __DIR__ . '/includes/bootstrap.php';

$db           = db();
$appName      = getSetting('app_name', 'Sistem Booking Ruangan');
$appTagline   = getSetting('app_tagline', 'Booking ruangan kantor dengan mudah dan cepat');
$primaryColor = getSetting('app_color_primary', '#0d6efd');

$rooms  = $db->fetchAll("SELECT id, name, code, location, floor, capacity FROM rooms WHERE is_active = 1 ORDER BY name ASC");
$roomId = (int)($_GET['room_id'] ?? 0);
$date   = $_GET['date'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$room = null;
if ($roomId) {
    $room = $db->fetch("SELECT id, name, code, location, floor, capacity FROM rooms WHERE id = ? AND is_active = 1", [$roomId]);
    if (!$room) $roomId = 0;
}

$today    = date('Y-m-d');
$prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$shareUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']
          . ($roomId ? '?room_id=' . $roomId : '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal <?= $room ? e($room['name']) . ' — ' : '' ?><?= e($appName) ?></title>
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
        body  { background: #f0f4f8; min-height: 100vh; }

        .public-header {
            background: var(--bs-primary);
            color: #fff;
            padding: 20px 0;
        }

        /* Cards */
        .schedule-card { border-radius: 12px; overflow: hidden; }

        /* Date nav */
        .date-nav { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }

        /* Room selector card */
        .room-selector-card { border-radius:12px; }

        /* Schedule table */
        .schedule-table { width:100%; border-collapse:collapse; }
        .schedule-table th {
            background:#f8f9fa; font-size:0.8rem; color:#6c757d;
            font-weight:600; padding:10px 14px; border-bottom:1px solid #dee2e6;
        }
        .schedule-table td { padding:0; vertical-align:top; border-bottom:1px solid #f0f0f0; }
        .slot-time {
            width:90px; padding:10px 14px; font-size:0.82rem;
            color:#6c757d; font-weight:500; white-space:nowrap;
            border-right:1px solid #f0f0f0;
        }
        .slot-avail { padding:10px 14px; color:#198754; font-size:0.82rem; }
        .slot-avail i { opacity:.6; }

        /* Booking blocks */
        .booking-block { padding:10px 14px; border-left:3px solid; }
        .booking-block.status-confirmed { background:rgba(255,193,7,.06);  border-color:#ffc107; }
        .booking-block.status-pending   { background:rgba(253,126,20,.06); border-color:#fd7e14; }
        .booking-block.status-completed { background:rgba(108,117,125,.06);border-color:#6c757d; }

        .booking-block.block-start  { padding-bottom:4px; }
        .booking-block.block-middle { padding-top:2px; padding-bottom:2px; border-top:none; }
        .booking-block.block-end    { padding-top:2px; border-top:none; }

        .booking-name    { font-weight:600; font-size:0.88rem; color:#212529; }
        .booking-meta    { font-size:0.78rem; color:#6c757d; margin-top:3px; }
        .booking-meta span { margin-right:12px; }
        .booking-purpose {
            font-size:0.8rem; color:#495057; margin-top:4px;
            display:-webkit-box; -webkit-line-clamp:2; line-clamp:2;
            -webkit-box-orient:vertical; overflow:hidden;
        }
        .booking-cont { font-size:0.76rem; color:#adb5bd; }

        /* Status badges */
        .badge-confirmed { background:#d1e7dd; color:#0a3622; }
        .badge-pending   { background:#fff3cd; color:#664d03; }
        .badge-completed { background:#e2e3e5; color:#41464b; }

        /* Non-working / empty */
        .state-empty { text-align:center; padding:56px 16px; color:#6c757d; }
        .state-empty i { font-size:2.8rem; opacity:.35; display:block; margin-bottom:12px; }

        /* Refresh badge */
        #refreshBadge { font-size:0.73rem; }

        /* QR canvas */
        #qrCanvas { display:block; margin:0 auto; }
    </style>
</head>
<body>

<!-- Header (sama dengan index.php) -->
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

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-9 col-xl-10">

            <!-- Breadcrumb / judul halaman -->
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="fw-bold mb-0">
                    <i class="bi bi-calendar3 me-2 text-primary"></i>Jadwal Ruangan
                    <?php if ($room): ?>
                    <span class="text-muted fw-normal">— <?= e($room['name']) ?></span>
                    <?php endif; ?>
                </h5>
                <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i>Buat Booking
                </a>
            </div>

            <!-- Room selector -->
            <div class="card border-0 shadow-sm room-selector-card mb-3 p-3">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-md">
                        <label class="form-label fw-semibold small mb-1">Pilih Ruangan</label>
                        <select class="form-select form-select-sm" id="roomSelect" onchange="changeRoom(this.value)">
                            <option value="">-- Pilih Ruangan --</option>
                            <?php foreach ($rooms as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= $r['id'] == $roomId ? 'selected' : '' ?>>
                                <?= e($r['name']) ?><?= $r['location'] ? ' — ' . e($r['location']) : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($room): ?>
                    <div class="col-auto d-flex align-items-end gap-2 flex-wrap">
                        <span class="badge bg-secondary-subtle text-secondary py-2 px-2">
                            <i class="bi bi-people me-1"></i>Kap. <?= $room['capacity'] ?> orang
                        </span>
                        <?php if ($room['floor']): ?>
                        <span class="badge bg-secondary-subtle text-secondary py-2 px-2">
                            <i class="bi bi-layers me-1"></i>Lt. <?= e($room['floor']) ?>
                        </span>
                        <?php endif; ?>
                        <button class="btn btn-outline-secondary btn-sm" onclick="showQr()">
                            <i class="bi bi-qr-code me-1"></i>QR Code
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($roomId): ?>

            <!-- Date navigation -->
            <div class="card border-0 shadow-sm mb-3 p-3">
                <div class="date-nav">
                    <a href="?room_id=<?= $roomId ?>&date=<?= $prevDate ?>"
                       class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                    <input type="date" id="datePicker" class="form-control form-control-sm"
                           value="<?= e($date) ?>"
                           style="width:auto; cursor:pointer;"
                           onchange="goToDate(this.value)">
                    <a href="?room_id=<?= $roomId ?>&date=<?= $nextDate ?>"
                       class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                    <a href="?room_id=<?= $roomId ?>&date=<?= $today ?>"
                       class="btn btn-sm <?= $date === $today ? 'btn-primary' : 'btn-outline-primary' ?>">
                        Hari Ini
                    </a>
                    <span class="fw-semibold ms-1 d-none d-sm-inline"><?= e(formatDateId($date)) ?></span>
                    <span class="badge bg-light text-muted ms-auto border" id="refreshBadge">
                        <i class="bi bi-arrow-clockwise me-1"></i>Refresh <span id="countdown">60</span>s
                    </span>
                </div>
            </div>

            <!-- Schedule table -->
            <div class="card border-0 shadow-sm schedule-card">
                <div id="scheduleContent">
                    <div class="state-empty">
                        <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
                        <p class="mt-3 mb-0 small">Memuat jadwal…</p>
                    </div>
                </div>
            </div>

            <?php else: ?>

            <!-- Tidak ada ruangan dipilih -->
            <div class="card border-0 shadow-sm schedule-card">
                <div class="state-empty">
                    <i class="bi bi-door-open"></i>
                    <p class="mb-3">Pilih ruangan di atas untuk melihat jadwal.</p>
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <?php foreach ($rooms as $r): ?>
                        <a href="?room_id=<?= $r['id'] ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-door-closed me-1"></i><?= e($r['name']) ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<!-- QR Modal -->
<div class="modal fade" id="qrModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-qr-code me-2 text-primary"></i>QR Code Jadwal<?= $room ? ' ' . e($room['name']) : '' ?>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center pt-2">
                <canvas id="qrCanvas"></canvas>
                <p class="text-muted mt-3 mb-2" style="font-size:0.73rem; word-break:break-all;" id="qrUrl"></p>
                <button class="btn btn-outline-secondary btn-sm" onclick="copyUrl()">
                    <i class="bi bi-clipboard me-1"></i>Salin Link
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
<script>
<?php if ($roomId): ?>
const ROOM_ID   = <?= $roomId ?>;
const DATE      = '<?= e($date) ?>';
const SHARE_URL = '<?= e($shareUrl . '&date=' . $date) ?>';

// ============================================================
// Render schedule
// ============================================================
function renderSchedule(data) {
    const el = document.getElementById('scheduleContent');

    if (!data.success) {
        el.innerHTML = '<div class="state-empty"><i class="bi bi-exclamation-circle"></i><p>' + esc(data.message || 'Gagal memuat data.') + '</p></div>';
        return;
    }
    if (!data.is_working_day) {
        const msg = data.is_holiday ? 'Hari libur — tidak ada jadwal.' : 'Bukan hari kerja.';
        el.innerHTML = '<div class="state-empty"><i class="bi bi-moon-stars"></i><p>' + msg + '</p></div>';
        return;
    }
    if (!data.slots || data.slots.length === 0) {
        el.innerHTML = '<div class="state-empty"><i class="bi bi-calendar-x"></i><p>Tidak ada slot tersedia.</p></div>';
        return;
    }

    const statusMap = {
        confirmed: ['badge-confirmed', 'Dikonfirmasi'],
        pending:   ['badge-pending',   'Menunggu'],
        completed: ['badge-completed', 'Selesai'],
    };

    let html = '<table class="schedule-table">'
             + '<thead class="text-center"><tr><th style="width:90px">Waktu</th><th>Keterangan</th></tr></thead>'
             + '<tbody>';

    for (let i = 0; i < data.slots.length; i++) {
        const slot = data.slots[i];
        const bk   = slot.booking;
        let cellHtml = '';

        if (!bk) {
            cellHtml = '<div class="slot-avail"><i class="bi bi-check-circle me-1"></i>Tersedia</div>';
        } else {
            const prevBk  = i > 0 ? data.slots[i - 1].booking : null;
            const nextBk  = i < data.slots.length - 1 ? data.slots[i + 1].booking : null;
            const isFirst = !prevBk || prevBk.id !== bk.id;
            const isLast  = !nextBk || nextBk.id !== bk.id;

            let pos = 'block-single';
            if (isFirst && !isLast)  pos = 'block-start';
            else if (!isFirst && !isLast) pos = 'block-middle';
            else if (!isFirst && isLast)  pos = 'block-end';

            const [badgeClass, badgeLabel] = statusMap[bk.status] || ['bg-secondary', bk.status];
            const badge = '<span class="badge ' + badgeClass + ' ms-1" style="font-size:0.65rem;">' + badgeLabel + '</span>';

            if (isFirst) {
                cellHtml = '<div class="booking-block status-' + bk.status + ' ' + pos + '">'
                    + '<div class="booking-name">' + esc(bk.booker_name) + badge + '</div>'
                    + '<div class="booking-meta">'
                    +   '<span><i class="bi bi-building me-1"></i>' + esc(bk.department || '-') + '</span>'
                    +   '<span><i class="bi bi-clock me-1"></i>' + bk.start_time + ' – ' + bk.end_time + '</span>'
                    +   '<span><i class="bi bi-people me-1"></i>' + bk.capacity + ' orang</span>'
                    + '</div>'
                    + (bk.meeting_type ? '<div class="booking-meta"><i class="bi bi-tag me-1"></i>' + esc(bk.meeting_type) + '</div>' : '')
                    + (bk.purpose ? '<div class="booking-purpose">' + esc(bk.purpose) + '</div>' : '')
                    + '</div>';
            } else {
                cellHtml = '<div class="booking-block status-' + bk.status + ' ' + pos + '">'
                    + '<span class="booking-cont"><i class="bi bi-arrow-up me-1"></i>' + esc(bk.booker_name) + '</span>'
                    + '</div>';
            }
        }

        html += '<tr><td class="slot-time text-center">' + slot.start + '</td><td>' + cellHtml + '</td></tr>';
    }

    html += '</tbody></table>';
    el.innerHTML = html;
}

function esc(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ============================================================
// Fetch & auto-refresh
// ============================================================
function fetchSchedule() {
    fetch('api/room-schedule.php?room_id=' + ROOM_ID + '&date=' + DATE)
        .then(r => r.json())
        .then(renderSchedule)
        .catch(() => {
            document.getElementById('scheduleContent').innerHTML =
                '<div class="state-empty"><i class="bi bi-wifi-off"></i><p>Gagal memuat jadwal.</p></div>';
        });
}

fetchSchedule();

let countdown = 60;
setInterval(() => {
    countdown--;
    const el = document.getElementById('countdown');
    if (el) el.textContent = countdown;
    if (countdown <= 0) { countdown = 60; fetchSchedule(); }
}, 1000);

// ============================================================
// Room / date navigation
// ============================================================
function changeRoom(roomId) {
    if (roomId) window.location.href = 'schedule.php?room_id=' + roomId + '&date=' + DATE;
    else window.location.href = 'schedule.php';
}

function goToDate(dateVal) {
    if (dateVal) window.location.href = 'schedule.php?room_id=' + ROOM_ID + '&date=' + dateVal;
}

// ============================================================
// QR Code
// ============================================================
function showQr() {
    document.getElementById('qrUrl').textContent = SHARE_URL;
    QRCode.toCanvas(document.getElementById('qrCanvas'), SHARE_URL, { width: 200, margin: 2 });
    new bootstrap.Modal(document.getElementById('qrModal')).show();
}

function copyUrl() {
    navigator.clipboard.writeText(SHARE_URL).then(() => {
        const btn = event.target.closest('button');
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check me-1"></i>Tersalin!';
        setTimeout(() => { btn.innerHTML = orig; }, 2000);
    });
}

<?php else: ?>
function changeRoom(roomId) {
    if (roomId) window.location.href = 'schedule.php?room_id=' + roomId;
}
<?php endif; ?>
</script>
</body>
</html>

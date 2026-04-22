<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requirePermission('reports.bookings');

$pageTitle = 'Laporan Booking';
$db = db();

$dateFrom = qParam('date_from', date('Y-m-01'));
$dateTo   = qParam('date_to', date('Y-m-d'));
$roomId   = intVal(qParam('room_id'));
$status   = qParam('status', '');
$deptId   = intVal(qParam('dept_id'));
$export   = qParam('export');

$where  = ["b.booking_date BETWEEN ? AND ?"];
$params = [$dateFrom, $dateTo];

if ($roomId) { $where[] = "b.room_id = ?"; $params[] = $roomId; }
if ($status !== '') { $where[] = "b.status = ?"; $params[] = $status; }
if ($deptId) { $where[] = "b.department_id = ?"; $params[] = $deptId; }

$whereStr = implode(' AND ', $where);

$bookings = $db->fetchAll(
    "SELECT b.*, r.name as room_name, d.name as dept_name, mt.name as type_name
     FROM bookings b
     JOIN rooms r ON b.room_id = r.id
     LEFT JOIN departments d ON b.department_id = d.id
     LEFT JOIN meeting_types mt ON b.meeting_type_id = mt.id
     WHERE $whereStr
     ORDER BY b.booking_date DESC, b.start_time DESC",
    $params
);

// CSV Export
if ($export === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="laporan_booking_' . date('Ymd_His') . '.csv"');
    header('Cache-Control: no-cache');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Kode', 'Tanggal', 'Waktu', 'Ruangan', 'Pemesanan', 'NIK', 'Email', 'HP', 'Departemen', 'Tipe Meeting', 'Kapasitas', 'Status', 'Check-in', 'Check-out', 'Dibuat']);
    foreach ($bookings as $b) {
        fputcsv($out, [
            $b['booking_code'],
            $b['booking_date'],
            substr($b['start_time'], 0, 5) . '-' . substr($b['end_time'], 0, 5),
            $b['room_name'],
            $b['booker_name'],
            $b['booker_nik'] ?? '',
            $b['booker_email'],
            $b['booker_phone'] ?? '',
            $b['dept_name'] ?? '',
            $b['type_name'] ?? '',
            $b['capacity_needed'] ?? '',
            $b['status'],
            $b['checkin_at'] ?? '',
            $b['checkout_at'] ?? '',
            $b['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

$rooms = $db->fetchAll("SELECT id, name FROM rooms WHERE is_active = 1 ORDER BY name");
$depts = $db->fetchAll("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");

// Summary stats
$totalCount   = count($bookings);
$statusCounts = [];
foreach ($bookings as $b) {
    $statusCounts[$b['status']] = ($statusCounts[$b['status']] ?? 0) + 1;
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
            <h5><i class="bi bi-file-earmark-bar-graph me-2 text-primary"></i><?= e($pageTitle) ?></h5>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/">Dashboard</a></li>
                    <li class="breadcrumb-item active">Laporan Booking</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/admin/reports/bookings.php?<?= http_build_query(array_filter(['date_from'=>$dateFrom,'date_to'=>$dateTo,'room_id'=>$roomId,'status'=>$status,'dept_id'=>$deptId])) ?>&export=csv"
               class="btn btn-success btn-md no-print">
                <i class="bi bi-download me-1"></i> Export CSV
            </a>
            <button onclick="window.print()" class="btn btn-outline-secondary btn-md no-print">
                <i class="bi bi-printer me-1"></i> Cetak
            </button>
        </div>
    </div>

    <!-- Filter -->
    <div class="card border-0 shadow-sm mb-3 no-print">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Dari Tanggal</label>
                    <input type="date" class="form-control form-control-sm" name="date_from" value="<?= e($dateFrom) ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Sampai Tanggal</label>
                    <input type="date" class="form-control form-control-sm" name="date_to" value="<?= e($dateTo) ?>">
                </div>
                <div class="col-6 col-md-2">
                    <select name="room_id" class="form-select form-select-sm">
                        <option value="">Semua Ruangan</option>
                        <?php foreach ($rooms as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= $roomId === $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="dept_id" class="form-select form-select-sm">
                        <option value="">Semua Departemen</option>
                        <?php foreach ($depts as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $deptId === $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Semua Status</option>
                        <?php foreach (['pending','confirmed','rejected','cancelled','completed'] as $s): ?>
                        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-auto">
                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                    <a href="<?= BASE_URL ?>/admin/reports/bookings.php" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary -->
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="card border-0 bg-primary-subtle text-center py-3">
                <div class="h4 fw-bold text-primary mb-0"><?= numId($totalCount) ?></div>
                <div class="small text-primary">Total Booking</div>
            </div>
        </div>
        <?php foreach ($statusCounts as $s => $cnt): ?>
        <?php $info = BOOKING_STATUS[$s] ?? ['class' => 'secondary', 'label' => $s]; ?>
        <div class="col-6 col-md-3">
            <div class="card border-0 bg-<?= $info['class'] ?>-subtle text-center py-3">
                <div class="h4 fw-bold text-<?= $info['class'] ?> mb-0"><?= numId($cnt) ?></div>
                <div class="small text-<?= $info['class'] ?>"><?= $info['label'] ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Report Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-0 pt-3">
            <div class="fw-semibold small">
                Periode: <strong><?= formatDateId($dateFrom, false) ?> s/d <?= formatDateId($dateTo, false) ?></strong>
                &mdash; Total: <strong><?= numId($totalCount) ?> booking</strong>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover <?= !empty($bookings) ? 'datatable' : '' ?> align-middle mb-0" style="font-size:0.82rem;" data-no-filter="true">
                    <thead>
                        <tr>
                            <th class="px-4" width="50">#</th>
                            <th>Kode</th>
                            <th>Tanggal</th>
                            <th>Waktu</th>
                            <th>Ruangan</th>
                            <th>Pemesanan</th>
                            <th>Departemen</th>
                            <th>Tipe</th>
                            <th class="text-center">Check-in</th>
                            <th class="text-center">Check-out</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $i => $b): ?>
                        <tr>
                            <td class="px-3 text-muted"><?= $i + 1 ?></td>
                            <td><code class="text-primary small"><?= e($b['booking_code']) ?></code></td>
                            <td class="text-nowrap"><?= formatDateId($b['booking_date'], false) ?></td>
                            <td class="text-nowrap"><?= substr($b['start_time'], 0, 5) ?> - <?= substr($b['end_time'], 0, 5) ?></td>
                            <td><?= e($b['room_name']) ?></td>
                            <td>
                                <div><?= e($b['booker_name']) ?></div>
                                <div class="text-muted tiny"><?= e($b['booker_nik'] ?? $b['booker_email']) ?></div>
                            </td>
                            <td class="text-muted"><?= e($b['dept_name'] ?? '-') ?></td>
                            <td class="text-muted"><?= e($b['type_name'] ?? '-') ?></td>
                            <td class="text-center text-muted">
                                <?= $b['checkin_at'] ? '<span class="text-success">'.date('H:i', strtotime($b['checkin_at'])).'</span>' : '-' ?>
                            </td>
                            <td class="text-center text-muted">
                                <?= $b['checkout_at'] ? '<span class="text-warning">'.date('H:i', strtotime($b['checkout_at'])).'</span>' : '-' ?>
                            </td>
                            <td class="text-center"><?= statusBadge($b['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($bookings)): ?>
                        <tr><td colspan="11" class="text-center py-4 text-muted">Tidak ada data pada periode ini</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include dirname(__DIR__) . '/layout/footer.php'; ?>

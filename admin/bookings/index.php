<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requirePermission('bookings.view');

$pageTitle = 'Data Booking';
$db = db();

// Filters
$status   = qParam('status', '');
$search   = trim(qParam('search'));
$roomId   = intVal(qParam('room_id'));
$dateFrom = qParam('date_from');
$dateTo   = qParam('date_to');
$page     = max(1, intVal(qParam('page', 1)));
$perPage  = DEFAULT_PER_PAGE;

$where  = ['1=1'];
$params = [];

if ($status !== '') { $where[] = "b.status = ?"; $params[] = $status; }
if ($search) {
    $where[] = "(b.booking_code LIKE ? OR b.booker_name LIKE ? OR b.booker_email LIKE ? OR b.booker_nik LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}
if ($roomId)   { $where[] = "b.room_id = ?";     $params[] = $roomId; }
if ($dateFrom) { $where[] = "b.booking_date >= ?"; $params[] = $dateFrom; }
if ($dateTo)   { $where[] = "b.booking_date <= ?"; $params[] = $dateTo; }

$whereStr = implode(' AND ', $where);

$total = (int) $db->fetchColumn("SELECT COUNT(*) FROM bookings b WHERE $whereStr", $params);
$pagination = paginate($total, $perPage, $page, BASE_URL . '/admin/bookings/' . '?' . http_build_query(array_filter(compact('status', 'search', 'roomId', 'dateFrom', 'dateTo'))));

$bookings = $db->fetchAll(
    "SELECT b.*, r.name as room_name, r.code as room_code,
     d.name as dept_name, mt.name as type_name,
     u.name as approver_name
     FROM bookings b
     JOIN rooms r ON b.room_id = r.id
     LEFT JOIN departments d ON b.department_id = d.id
     LEFT JOIN meeting_types mt ON b.meeting_type_id = mt.id
     LEFT JOIN users u ON b.approved_by = u.id
     WHERE $whereStr
     ORDER BY b.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $pagination['offset']])
);

$rooms = $db->fetchAll("SELECT id, name FROM rooms WHERE is_active = 1 ORDER BY name ASC");

// Status counts for tabs — single aggregate query
$statusCounts = ['' => 0, 'pending' => 0, 'confirmed' => 0, 'rejected' => 0, 'cancelled' => 0, 'completed' => 0];
foreach ($db->fetchAll("SELECT status, COUNT(*) as cnt FROM bookings GROUP BY status") as $row) {
    if (array_key_exists($row['status'], $statusCounts)) {
        $statusCounts[$row['status']] = (int) $row['cnt'];
    }
    $statusCounts[''] += (int) $row['cnt'];
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
            <h5><i class="bi bi-journal-check me-2 text-primary"></i><?= e($pageTitle) ?></h5>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/">Dashboard</a></li>
                    <li class="breadcrumb-item active">Booking</li>
                </ol>
            </nav>
        </div>
    </div>

    <?= showFlash() ?>

    <!-- Status Tabs -->
    <div class="d-flex flex-wrap gap-1 mb-3">
        <?php
        $tabItems = [
            '' => ['label' => 'Semua', 'class' => 'secondary'],
            'pending'   => ['label' => 'Menunggu', 'class' => 'warning'],
            'confirmed' => ['label' => 'Dikonfirmasi', 'class' => 'success'],
            'rejected'  => ['label' => 'Ditolak', 'class' => 'danger'],
            'cancelled' => ['label' => 'Dibatalkan', 'class' => 'secondary'],
            'completed' => ['label' => 'Selesai', 'class' => 'info'],
        ];
        ?>
        <?php foreach ($tabItems as $s => $tab): ?>
        <?php $isActive = $status === $s; ?>
        <a href="<?= BASE_URL ?>/admin/bookings/?status=<?= $s ?>"
           class="btn btn-sm btn-<?= $isActive ? $tab['class'] : 'outline-' . $tab['class'] ?>">
            <?= $tab['label'] ?>
            <span class="badge bg-white bg-opacity-25 ms-1"><?= $statusCounts[$s] ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Search & Filter -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="status" value="<?= e($status) ?>">
                <div class="col-12 col-sm-4 col-md-3">
                    <input type="text" class="form-control form-control-sm" name="search"
                           placeholder="Cari kode, nama, email, NIK..." value="<?= e($search) ?>">
                </div>
                <div class="col-6 col-sm-3 col-md-2">
                    <select name="room_id" class="form-select form-select-sm">
                        <option value="">Semua Ruangan</option>
                        <?php foreach ($rooms as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= $roomId === $r['id'] ? 'selected' : '' ?>>
                            <?= e($r['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-sm-3 col-md-2">
                    <input type="date" class="form-control form-control-sm" name="date_from"
                           value="<?= e($dateFrom) ?>" placeholder="Dari tanggal">
                </div>
                <div class="col-6 col-sm-3 col-md-2">
                    <input type="date" class="form-control form-control-sm" name="date_to"
                           value="<?= e($dateTo) ?>" placeholder="Sampai tanggal">
                </div>
                <div class="col-6 col-sm-auto d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-search"></i>
                    </button>
                    <a href="<?= BASE_URL ?>/admin/bookings/" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Booking Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-0 pt-3 d-flex align-items-center justify-content-between">
            <h6 class="fw-bold mb-0">Daftar Booking (<?= numId($total) ?>)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 table-bordered">
                    <thead class="text-center">
                        <tr>
                            <th width="150px">Kode</th>
                            <th>Pemesanan</th>
                            <th>Ruangan</th>
                            <th width="150px">Waktu</th>
                            <th>Tipe</th>
                            <th>In/Out</th>
                            <th width="150px">Status</th>
                            <th class="no-sort" style="min-width:50px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td class="px-2">
                                <div>
                                    <code class="text-primary small"><?= e($b['booking_code']) ?></code>
                                </div>
                                <div class="text-muted" style="font-size:0.72rem;"><?= timeAgo($b['created_at']) ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold small"><?= e($b['booker_name']) ?></div>
                                <div class="text-muted" style="font-size:0.75rem;">
                                    <?= e($b['booker_nik'] ? 'NIK: ' . $b['booker_nik'] : $b['booker_email']) ?>
                                </div>
                                <?php if ($b['dept_name']): ?>
                                <div class="text-muted" style="font-size:0.72rem;">
                                    <i class="bi bi-building me-1"></i><?= e($b['dept_name']) ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-semibold small"><?= e($b['room_name']) ?></div>
                                <?php if ($b['room_code']): ?>
                                <code class="text-muted" style="font-size:0.72rem;"><?= e($b['room_code']) ?></code>
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <div><?= formatDateId($b['booking_date'], false) ?></div>
                                <div class="text-muted"><?= formatTimeRange($b['start_time'], $b['end_time']) ?></div>
                            </td>
                            <td class="text-muted"><?= e($b['type_name'] ?? '-') ?></td>
                            <td class="text-center">
                                <?php if ($b['checkin_at']): ?>
                                <div class="text-success">
                                    <i class="bi bi-box-arrow-in-right me-1"></i>
                                    <?= date('H:i', strtotime($b['checkin_at'])) ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($b['checkout_at']): ?>
                                <div class="text-warning">
                                    <i class="bi bi-box-arrow-left me-1"></i>
                                    <?= date('H:i', strtotime($b['checkout_at'])) ?>
                                </div>
                                <?php endif; ?>
                                <?php if (!$b['checkin_at'] && !$b['checkout_at']): ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?= statusBadge($b['status']) ?></td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="<?= BASE_URL ?>/admin/bookings/detail.php?id=<?= $b['id'] ?>"
                                       class="btn btn-sm btn-outline-primary" title="Detail">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($b['status'] === 'pending' && Auth::can('bookings.approve')): ?>
                                    <a href="<?= BASE_URL ?>/admin/bookings/detail.php?id=<?= $b['id'] ?>"
                                       class="btn btn-sm btn-outline-success" title="Review">
                                        <i class="bi bi-check-lg"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($bookings)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">Tidak ada data booking</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($pagination['total_pages'] > 1): ?>
        <div class="card-footer bg-transparent d-flex align-items-center justify-content-between">
            <div class="small text-muted">
                Menampilkan <?= $pagination['offset'] + 1 ?>-<?= min($pagination['offset'] + $perPage, $total) ?> dari <?= numId($total) ?> booking
            </div>
            <?= renderPagination($pagination) ?>
        </div>
        <?php endif; ?>
    </div>

</div>
<?php include dirname(__DIR__) . '/layout/footer.php'; ?>

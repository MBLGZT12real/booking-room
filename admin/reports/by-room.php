<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requirePermission('reports.by_room');

$pageTitle = 'Laporan Per Ruangan';
$db = db();
$dateFrom = qParam('date_from', date('Y-m-01'));
$dateTo   = qParam('date_to', date('Y-m-d'));

$rooms = $db->fetchAll(
    "SELECT r.id, r.name, r.code, r.capacity,
     COUNT(b.id) as total_bookings,
     SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) as completed,
     SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
     SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
     SUM(CASE WHEN b.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
     SUM(CASE WHEN b.checkin_at IS NOT NULL THEN 1 ELSE 0 END) as checkedin,
     SUM(TIMESTAMPDIFF(MINUTE, CONCAT(b.booking_date,' ',b.start_time), CONCAT(b.booking_date,' ',b.end_time))) as total_minutes
     FROM rooms r
     LEFT JOIN bookings b ON r.id = b.room_id AND b.booking_date BETWEEN ? AND ? AND b.status != 'rejected'
     WHERE r.is_active = 1
     GROUP BY r.id ORDER BY total_bookings DESC",
    [$dateFrom, $dateTo]
);

$chartLabels = array_column($rooms, 'code');
$chartData   = array_column($rooms, 'total_bookings');
?>
<?php include dirname(__DIR__) . '/layout/header.php'; ?>
<div class="admin-wrapper">
<?php include dirname(__DIR__) . '/layout/sidebar.php'; ?>
<div class="main-content">
<?php include dirname(__DIR__) . '/layout/navbar.php'; ?>
<div class="page-content">

    <div class="page-header d-flex align-items-center justify-content-between">
        <div>
            <h5><i class="bi bi-door-closed me-2 text-primary"></i><?= e($pageTitle) ?></h5>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/">Dashboard</a></li>
                    <li class="breadcrumb-item active">Laporan Per Ruangan</li>
                </ol>
            </nav>
        </div>
        <button onclick="window.print()" class="btn btn-outline-secondary btn-md no-print">
            <i class="bi bi-printer me-1"></i> Cetak
        </button>
    </div>

    <!-- Filter -->
    <div class="card border-0 shadow-sm mb-3 no-print">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-6 col-sm-3">
                    <label class="form-label small mb-1">Dari Tanggal</label>
                    <input type="date" class="form-control form-control-sm" name="date_from" value="<?= e($dateFrom) ?>">
                </div>
                <div class="col-6 col-sm-3">
                    <label class="form-label small mb-1">Sampai Tanggal</label>
                    <input type="date" class="form-control form-control-sm" name="date_to" value="<?= e($dateTo) ?>">
                </div>
                <div class="col-12 col-sm-auto">
                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                    <a href="<?= BASE_URL ?>/admin/reports/by-room.php" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Chart -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-transparent border-0 pt-3">
            <h6 class="fw-bold mb-0">Grafik Penggunaan Ruangan</h6>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="roomChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover <?= !empty($rooms) ? 'datatable' : '' ?> align-middle mb-0" data-no-filter="true">
                    <thead>
                        <tr>
                            <th class="px-4" width="50">#</th>
                            <th>Ruangan</th>
                            <th class="text-center">Total Booking</th>
                            <th class="text-center">Selesai</th>
                            <th class="text-center">Aktif</th>
                            <th class="text-center">Dibatalkan</th>
                            <th class="text-center">Check-in</th>
                            <th class="text-center">Total Durasi</th>
                            <th class="text-center">Utilisasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rooms as $i => $room): ?>
                        <?php $totalHours = round($room['total_minutes'] / 60, 1); ?>
                        <tr>
                            <td class="px-3 text-muted small"><?= $i + 1 ?></td>
                            <td>
                                <div class="fw-semibold"><?= e($room['name']) ?></div>
                                <?php if ($room['code']): ?>
                                <code class="text-muted small"><?= e($room['code']) ?></code>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><span class="badge bg-primary"><?= numId($room['total_bookings']) ?></span></td>
                            <td class="text-center"><span class="badge bg-success-subtle text-success"><?= numId($room['completed']) ?></span></td>
                            <td class="text-center"><span class="badge bg-info-subtle text-info"><?= numId($room['confirmed']) ?></span></td>
                            <td class="text-center"><span class="badge bg-secondary-subtle text-secondary"><?= numId($room['cancelled']) ?></span></td>
                            <td class="text-center">
                                <?php if ($room['total_bookings'] > 0): ?>
                                <span class="text-success"><?= round(($room['checkedin'] / $room['total_bookings']) * 100) ?>%</span>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center small"><?= $totalHours ?> jam</td>
                            <td class="text-center">
                                <?php
                                // Calculate workdays in period
                                $start = strtotime($dateFrom);
                                $end   = strtotime($dateTo);
                                $workdays = 0;
                                for ($d = $start; $d <= $end; $d += 86400) {
                                    $dow = (int) date('w', $d);
                                    if ($dow >= 1 && $dow <= 5) $workdays++;
                                }
                                $totalAvailable = $workdays * 9; // 9 jam kerja/hari
                                $util = $totalAvailable > 0 ? round(($totalHours / $totalAvailable) * 100, 1) : 0;
                                $utilColor = $util >= 70 ? 'success' : ($util >= 40 ? 'warning' : 'secondary');
                                ?>
                                <div class="d-flex align-items-center gap-2 justify-content-center">
                                    <div class="progress flex-grow-1" style="height:6px;max-width:60px;">
                                        <div class="progress-bar bg-<?= $utilColor ?>" style="width:<?= min(100, $util) ?>%"></div>
                                    </div>
                                    <span class="small text-<?= $utilColor ?>"><?= $util ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include dirname(__DIR__) . '/layout/footer.php'; ?>
<script>
new Chart(document.getElementById('roomChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
            label: 'Total Booking',
            data: <?= json_encode($chartData) ?>,
            backgroundColor: 'rgba(13, 110, 253, 0.2)',
            borderColor: 'rgba(13, 110, 253, 0.8)',
            borderWidth: 2,
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => ' ' + ctx.parsed.y + ' booking' } }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { stepSize: 1, precision: 0, font: { size: 11 } },
                grid: { color: 'rgba(0,0,0,0.05)' }
            },
            x: {
                grid: { display: false },
                ticks: {
                    font: { size: 11 },
                    maxRotation: 45,
                    minRotation: 0
                }
            }
        }
    }
});
</script>

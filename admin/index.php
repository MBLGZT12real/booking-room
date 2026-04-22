<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requirePermission('dashboard.view');

$pageTitle = 'Dashboard';

// Stats
$stats     = BookingHelper::getStats();
$today     = date('Y-m-d');
$db        = db();

// Recent bookings (last 10)
$recentBookings = $db->fetchAll(
    "SELECT b.*, r.name as room_name, d.name as dept_name
     FROM bookings b
     JOIN rooms r ON b.room_id = r.id
     LEFT JOIN departments d ON b.department_id = d.id
     ORDER BY b.created_at DESC
     LIMIT 10"
);

// Booking chart data: last 7 days — single aggregate query
$chartLabels = [];
$chartData   = [];
$chartFrom   = date('Y-m-d', strtotime('-6 days'));
$chartRows   = $db->fetchAll(
    "SELECT booking_date, COUNT(*) as cnt FROM bookings
     WHERE booking_date BETWEEN ? AND ? AND status != 'rejected'
     GROUP BY booking_date",
    [$chartFrom, $today]
);
$chartByDate = array_column($chartRows, 'cnt', 'booking_date');
for ($i = 6; $i >= 0; $i--) {
    $date          = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('d/m', strtotime($date));
    $chartData[]   = (int) ($chartByDate[$date] ?? 0);
}

// Today's schedule
$todayBookings = $db->fetchAll(
    "SELECT b.*, r.name as room_name FROM bookings b
     JOIN rooms r ON b.room_id = r.id
     WHERE b.booking_date = ? AND b.status IN ('confirmed','pending')
     ORDER BY b.start_time ASC",
    [$today]
);

// Reuse from getStats() — no extra query needed
$pendingCount = $stats['pending'];
?>
<?php include __DIR__ . '/layout/header.php'; ?>
<div class="admin-wrapper">
    <?php include __DIR__ . '/layout/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/layout/navbar.php'; ?>
        <div class="page-content">

            <!-- Page Header -->
            <div class="page-header d-flex align-items-center justify-content-between">
                <div>
                    <h5><i class="bi bi-speedometer2 me-2 text-primary"></i><?= e($pageTitle) ?></h5>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 small">
                            <li class="breadcrumb-item active">Dashboard</li>
                        </ol>
                    </nav>
                </div>
                <div class="text-muted small">
                    <i class="bi bi-calendar3 me-1"></i>
                    <?= formatDateId($today) ?>
                </div>
            </div>

            <?= showFlash() ?>

            <?php if ($pendingCount > 0 && Auth::can('bookings.approve')): ?>
            <div class="alert alert-warning d-flex align-items-center gap-3 mb-3">
                <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                <div>
                    <strong><?= $pendingCount ?> booking</strong> menunggu persetujuan.
                    <a href="<?= BASE_URL ?>/admin/bookings/?status=pending" class="alert-link ms-1">
                        Lihat sekarang &rarr;
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Stat Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card stat-card h-100">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="stat-icon bg-primary-subtle text-primary">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                            <div>
                                <div class="h4 mb-0 fw-bold"><?= numId($stats['total_today']) ?></div>
                                <div class="small text-muted">Booking Hari Ini</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card h-100">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="stat-icon bg-warning-subtle text-warning">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <div>
                                <div class="h4 mb-0 fw-bold"><?= numId($stats['pending']) ?></div>
                                <div class="small text-muted">Menunggu Approval</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card h-100">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="stat-icon bg-success-subtle text-success">
                                <i class="bi bi-calendar3"></i>
                            </div>
                            <div>
                                <div class="h4 mb-0 fw-bold"><?= numId($stats['this_month']) ?></div>
                                <div class="small text-muted">Booking Bulan Ini</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card h-100">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="stat-icon bg-info-subtle text-info">
                                <i class="bi bi-door-open"></i>
                            </div>
                            <div>
                                <div class="h4 mb-0 fw-bold"><?= numId($stats['total_rooms']) ?></div>
                                <div class="small text-muted">Ruangan Aktif</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <!-- Booking Chart -->
                <div class="col-12 col-lg-7">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-transparent border-0 pt-3 pb-0">
                            <h6 class="fw-bold mb-0">
                                <i class="bi bi-calendar3-week me-2 text-primary"></i>
                                Booking 7 Hari Terakhir
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="bookingChart" height="220"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Today's Schedule -->
                <div class="col-12 col-lg-5">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-transparent border-0 pt-3 pb-0 d-flex align-items-center justify-content-between">
                            <h6 class="fw-bold mb-0">
                                <i class="bi bi-calendar-day me-2 text-success"></i>
                                Jadwal Hari Ini
                            </h6>
                            <span class="badge bg-secondary"><?= count($todayBookings) ?></span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($todayBookings)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-calendar-x display-5 d-block mb-2 opacity-25"></i>
                                <small>Tidak ada booking hari ini</small>
                            </div>
                            <?php else: ?>
                            <div class="list-group list-group-flush" style="max-height:300px;overflow-y:auto;">
                                <?php foreach ($todayBookings as $b): ?>
                                <a href="<?= BASE_URL ?>/admin/bookings/detail.php?id=<?= $b['id'] ?>"
                                   class="list-group-item list-group-item-action px-3 py-2">
                                    <div class="d-flex align-items-start gap-2">
                                        <?= statusBadge($b['status']) ?>
                                        <div class="flex-grow-1 overflow-hidden">
                                            <div class="fw-semibold small text-truncate"><?= e($b['room_name']) ?></div>
                                            <div class="text-muted" style="font-size:0.78rem;">
                                                <i class="bi bi-clock me-1"></i>
                                                <?= formatTimeRange($b['start_time'], $b['end_time']) ?>
                                            </div>
                                            <div class="text-muted" style="font-size:0.78rem;">
                                                <i class="bi bi-person me-1"></i>
                                                <?= e($b['booker_name']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Bookings Table -->
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent border-0 pt-3 d-flex align-items-center justify-content-between">
                            <h6 class="fw-bold mb-0">
                                <i class="bi bi-bookmark-star me-2 text-primary"></i>
                                Booking Terbaru
                            </h6>
                            <a href="<?= BASE_URL ?>/admin/bookings/" class="btn btn-outline-primary btn-sm">
                                Lihat Semua
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th class="px-4" width="50">Kode</th>
                                            <th>Nama</th>
                                            <th>Ruangan</th>
                                            <th>Tanggal</th>
                                            <th>Waktu</th>
                                            <th>Status</th>
                                            <th class="text-center no-sort" style="min-width:90px;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentBookings as $b): ?>
                                        <tr>
                                            <td class="px-3">
                                                <code class="text-primary small"><?= e($b['booking_code']) ?></code>
                                            </td>
                                            <td>
                                                <div class="fw-semibold small"><?= e($b['booker_name']) ?></div>
                                                <div class="text-muted" style="font-size:0.75rem;"><?= e($b['dept_name'] ?? '-') ?></div>
                                            </td>
                                            <td class="small"><?= e($b['room_name']) ?></td>
                                            <td class="small"><?= formatDateId($b['booking_date'], false) ?></td>
                                            <td class="small text-nowrap"><?= formatTimeRange($b['start_time'], $b['end_time']) ?></td>
                                            <td><?= statusBadge($b['status']) ?></td>
                                            <td>
                                                <a href="<?= BASE_URL ?>/admin/bookings/detail.php?id=<?= $b['id'] ?>"
                                                   class="btn btn-xs btn-outline-secondary" style="font-size:0.75rem;padding:2px 8px;">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($recentBookings)): ?>
                                        <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada booking</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /.page-content -->
        <?php include __DIR__ . '/layout/footer.php'; ?>

<script>
// Booking Chart
const ctx = document.getElementById('bookingChart')?.getContext('2d');
if (ctx) {
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: 'Jumlah Booking',
                data: <?= json_encode($chartData) ?>,
                backgroundColor: 'rgba(13, 110, 253, 0.15)',
                borderColor: 'rgba(13, 110, 253, 0.8)',
                borderWidth: 2,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, precision: 0 },
                    grid: { color: 'rgba(0,0,0,0.04)' }
                },
                x: { grid: { display: false } }
            }
        }
    });
}
</script>

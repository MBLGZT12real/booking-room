<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requirePermission('reports.by_date');

$pageTitle = 'Laporan Per Tanggal';
$db = db();
$dateFrom = qParam('date_from', date('Y-m-01'));
$dateTo   = qParam('date_to', date('Y-m-d'));

// Daily booking data
$dailyData = $db->fetchAll(
    "SELECT
        b.booking_date,
        COUNT(b.id) as total_bookings,
        SUM(CASE WHEN b.status = 'completed'  THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN b.status = 'confirmed'  THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN b.status = 'cancelled'  THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN b.status = 'rejected'   THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN b.checkin_at IS NOT NULL THEN 1 ELSE 0 END) as checkedin,
        COUNT(DISTINCT b.room_id) as unique_rooms,
        SUM(TIMESTAMPDIFF(MINUTE, CONCAT(b.booking_date,' ',b.start_time), CONCAT(b.booking_date,' ',b.end_time))) as total_minutes
     FROM bookings b
     WHERE b.booking_date BETWEEN ? AND ? AND b.status != 'rejected'
     GROUP BY b.booking_date
     ORDER BY b.booking_date ASC",
    [$dateFrom, $dateTo]
);

// Index by date for quick lookup
$dataByDate = [];
foreach ($dailyData as $row) {
    $dataByDate[$row['booking_date']] = $row;
}

// Build all dates in range for calendar / table
$allDates = [];
$d = strtotime($dateFrom);
$e = strtotime($dateTo);
while ($d <= $e) {
    $allDates[] = date('Y-m-d', $d);
    $d += 86400;
}

// Chart: top 14 days with most bookings
$chartRows   = array_slice($dailyData, 0, 31);
$chartLabels = array_map(fn($r) => date('d/m', strtotime($r['booking_date'])), $chartRows);
$chartData   = array_column($chartRows, 'total_bookings');

// Summary totals
$totalBookings  = array_sum(array_column($dailyData, 'total_bookings'));
$totalCompleted = array_sum(array_column($dailyData, 'completed'));
$totalCheckin   = array_sum(array_column($dailyData, 'checkedin'));
$totalMinutes   = array_sum(array_column($dailyData, 'total_minutes'));
$totalHours     = round($totalMinutes / 60, 1);
$activeDays     = count($dailyData);
?>
<?php include dirname(__DIR__) . '/layout/header.php'; ?>
<div class="admin-wrapper">
<?php include dirname(__DIR__) . '/layout/sidebar.php'; ?>
<div class="main-content">
<?php include dirname(__DIR__) . '/layout/navbar.php'; ?>
<div class="page-content">

    <div class="page-header d-flex align-items-center justify-content-between">
        <div>
            <h5><i class="bi bi-calendar3 me-2 text-primary"></i><?= e($pageTitle) ?></h5>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/">Dashboard</a></li>
                    <li class="breadcrumb-item active">Laporan Per Tanggal</li>
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
                    <a href="<?= BASE_URL ?>/admin/reports/by-date.php" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="h3 fw-bold text-primary mb-1"><?= numId($totalBookings) ?></div>
                <div class="small text-muted">Total Booking</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="h3 fw-bold text-success mb-1"><?= numId($totalCompleted) ?></div>
                <div class="small text-muted">Selesai</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="h3 fw-bold text-info mb-1"><?= numId($activeDays) ?></div>
                <div class="small text-muted">Hari Aktif</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="h3 fw-bold text-warning mb-1"><?= $totalHours ?></div>
                <div class="small text-muted">Total Jam Terpakai</div>
            </div>
        </div>
    </div>

    <!-- Chart -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-transparent border-0 pt-3">
            <h6 class="fw-bold mb-0">Grafik Booking Harian</h6>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="dateChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Calendar Heatmap -->
    <?php if (!empty($allDates)): ?>
    <?php
    $firstDate  = $allDates[0];
    $lastDate   = end($allDates);
    $rangeMonth = date('Y-m', strtotime($firstDate));
    $lastMonth  = date('Y-m', strtotime($lastDate));

    // Group dates by month
    $monthGroups = [];
    foreach ($allDates as $dt) {
        $monthGroups[substr($dt, 0, 7)][] = $dt;
    }

    // Max bookings for heatmap scaling
    $maxBookings = max(array_column($dailyData, 'total_bookings') ?: [1]);
    ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-transparent border-0 pt-3">
            <h6 class="fw-bold mb-0">Calendar Heatmap</h6>
        </div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-4">
            <?php foreach ($monthGroups as $month => $dates): ?>
            <?php
            $monthName = MONTH_NAMES[(int) date('n', strtotime($month . '-01'))];
            $year      = date('Y', strtotime($month . '-01'));
            $firstDay  = (int) date('w', strtotime($month . '-01'));
            $daysInMonth = (int) date('t', strtotime($month . '-01'));
            ?>
            <div class="heatmap-month">
                <div class="fw-semibold small mb-2 text-center"><?= $monthName ?> <?= $year ?></div>
                <div class="heatmap-grid">
                    <div class="heatmap-day-header">Min</div>
                    <div class="heatmap-day-header">Sen</div>
                    <div class="heatmap-day-header">Sel</div>
                    <div class="heatmap-day-header">Rab</div>
                    <div class="heatmap-day-header">Kam</div>
                    <div class="heatmap-day-header">Jum</div>
                    <div class="heatmap-day-header">Sab</div>
                    <?php for ($blank = 0; $blank < $firstDay; $blank++): ?>
                    <div></div>
                    <?php endfor; ?>
                    <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                    <?php
                    $dateStr = $month . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                    $inRange = $dateStr >= $dateFrom && $dateStr <= $dateTo;
                    $dayData = $dataByDate[$dateStr] ?? null;
                    $count   = $dayData ? (int)$dayData['total_bookings'] : 0;
                    $intensity = $maxBookings > 0 ? round(($count / $maxBookings) * 4) : 0;
                    $title   = $count > 0 ? "$count booking" : ($inRange ? 'Tidak ada booking' : '');
                    ?>
                    <div class="heatmap-cell heat-<?= $intensity ?> <?= !$inRange ? 'out-range' : '' ?>"
                         title="<?= $dateStr . ': ' . $title ?>">
                        <span class="day-num"><?= $day ?></span>
                        <?php if ($count > 0): ?>
                        <span class="day-count"><?= $count ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
            <div class="d-flex align-items-center gap-2 mt-3 text-muted small">
                <span>Lebih sedikit</span>
                <div class="heatmap-cell heat-0 d-inline-flex" style="width:20px;height:20px;"></div>
                <div class="heatmap-cell heat-1 d-inline-flex" style="width:20px;height:20px;"></div>
                <div class="heatmap-cell heat-2 d-inline-flex" style="width:20px;height:20px;"></div>
                <div class="heatmap-cell heat-3 d-inline-flex" style="width:20px;height:20px;"></div>
                <div class="heatmap-cell heat-4 d-inline-flex" style="width:20px;height:20px;"></div>
                <span>Lebih banyak</span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-0 pt-3">
            <h6 class="fw-bold mb-0">Detail Per Tanggal</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover <?= !empty($dailyData) ? 'datatable' : '' ?> align-middle mb-0" data-no-filter="true">
                    <thead>
                        <tr>
                            <th class="px-4" width="50">#</th>
                            <th>Tanggal</th>
                            <th>Hari</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Selesai</th>
                            <th class="text-center">Aktif</th>
                            <th class="text-center">Dibatalkan</th>
                            <th class="text-center">Check-in</th>
                            <th class="text-center">Ruangan</th>
                            <th class="text-center">Total Durasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dailyData as $i => $row): ?>
                        <?php
                        $rowHours = round($row['total_minutes'] / 60, 1);
                        $dow      = date('w', strtotime($row['booking_date']));
                        $dayName  = DAY_NAMES[(int)$dow];
                        $isWeekend = ((int)$dow === 0 || (int)$dow === 6);
                        ?>
                        <tr class="<?= $isWeekend ? 'table-light' : '' ?>">
                            <td class="px-3 text-muted small"><?= $i + 1 ?></td>
                            <td class="fw-semibold"><?= formatDateId($row['booking_date'], false) ?></td>
                            <td>
                                <span class="badge <?= $isWeekend ? 'bg-secondary-subtle text-secondary' : 'bg-primary-subtle text-primary' ?>">
                                    <?= $dayName ?>
                                </span>
                            </td>
                            <td class="text-center"><span class="badge bg-primary"><?= numId($row['total_bookings']) ?></span></td>
                            <td class="text-center"><span class="badge bg-success-subtle text-success"><?= numId($row['completed']) ?></span></td>
                            <td class="text-center"><span class="badge bg-info-subtle text-info"><?= numId($row['confirmed']) ?></span></td>
                            <td class="text-center"><span class="badge bg-secondary-subtle text-secondary"><?= numId($row['cancelled']) ?></span></td>
                            <td class="text-center">
                                <?php if ($row['total_bookings'] > 0): ?>
                                <span class="text-success small"><?= round(($row['checkedin'] / $row['total_bookings']) * 100) ?>%</span>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center small"><?= numId($row['unique_rooms']) ?></td>
                            <td class="text-center small"><?= $rowHours ?> jam</td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($dailyData)): ?>
                        <tr><td colspan="10" class="text-center py-4 text-muted">Tidak ada data untuk periode ini</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
<?php include dirname(__DIR__) . '/layout/footer.php'; ?>
<script>
new Chart(document.getElementById('dateChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
            label: 'Total Booking',
            data: <?= json_encode($chartData) ?>,
            backgroundColor: 'rgba(13, 110, 253, 0.08)',
            borderColor: 'rgba(13, 110, 253, 0.8)',
            borderWidth: 2,
            pointBackgroundColor: 'rgba(13, 110, 253, 0.8)',
            pointRadius: 4,
            fill: true,
            tension: 0.3,
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
                    minRotation: 0,
                    maxTicksLimit: 15
                }
            }
        }
    }
});
</script>
<style>
.heatmap-month { min-width: 180px; }
.heatmap-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 3px;
}
.heatmap-day-header {
    font-size: 0.65rem;
    text-align: center;
    color: #aaa;
    padding-bottom: 2px;
}
.heatmap-cell {
    width: 26px;
    height: 26px;
    border-radius: 4px;
    position: relative;
    cursor: default;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
}
.heatmap-cell .day-num  { font-size: 0.6rem; line-height:1; }
.heatmap-cell .day-count { font-size: 0.55rem; line-height:1; font-weight:700; }
.heat-0 { background:#e9ecef; }
.heat-1 { background:#bde3ff; color:#0d6efd; }
.heat-2 { background:#74bcf0; color:#fff; }
.heat-3 { background:#3d8fd8; color:#fff; }
.heat-4 { background:#0d6efd; color:#fff; }
.out-range { opacity: 0.25; }
</style>

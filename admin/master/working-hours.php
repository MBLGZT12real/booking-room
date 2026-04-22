<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requirePermission('working_hours.manage');

$pageTitle = 'Jam Kerja & Interval';
$db = db();

if (isPost() && verifyCsrf()) {
    $days = $_POST['days'] ?? [];
    $db->beginTransaction();
    try {
        foreach (range(0, 6) as $dow) {
            $is_working = isset($days[$dow]) ? 1 : 0;
            $start = $days[$dow]['start'] ?? '08:00';
            $end   = $days[$dow]['end'] ?? '17:00';
            $interval = max(15, min(120, intVal($days[$dow]['interval'] ?? 30)));

            $db->query(
                "UPDATE working_hours SET is_working_day=?, start_time=?, end_time=?, interval_minutes=? WHERE day_of_week=?",
                [$is_working, $start, $end, $interval, $dow]
            );
        }
        $db->commit();
        auditLog('update', 'working_hours', 'Update jam kerja');
        flash('success', 'Jam kerja berhasil diperbarui.');
    } catch (Exception $e) {
        $db->rollback();
        flash('error', 'Gagal menyimpan: ' . $e->getMessage());
    }
    redirect(BASE_URL . '/admin/master/working-hours.php');
}

// Get working hours indexed by day
$whRaw = $db->fetchAll("SELECT * FROM working_hours ORDER BY day_of_week ASC");
$wh = [];
foreach ($whRaw as $row) {
    $wh[$row['day_of_week']] = $row;
}
?>
<?php include dirname(__DIR__) . '/layout/header.php'; ?>
<div class="admin-wrapper">
<?php include dirname(__DIR__) . '/layout/sidebar.php'; ?>
<div class="main-content">
<?php include dirname(__DIR__) . '/layout/navbar.php'; ?>
<div class="page-content">

    <div class="page-header">
        <h5><i class="bi bi-clock me-2 text-primary"></i><?= e($pageTitle) ?></h5>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/">Dashboard</a></li>
                <li class="breadcrumb-item active">Jam Kerja</li>
            </ol>
        </nav>
    </div>

    <?= showFlash() ?>

    <div class="row justify-content-center">
        <div class="col-lg-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom pb-3 pt-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-clock text-primary fs-5"></i>
                        <div>
                            <h6 class="fw-bold mb-0">Pengaturan Jam Kerja per Hari</h6>
                            <p class="text-muted small mb-0">Atur hari kerja, jam mulai/selesai, dan interval slot booking.</p>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <?= csrfField() ?>

                        <div class="d-flex flex-column gap-2 mb-4">
                            <?php
                            $colors = [0 => 'danger', 1 => 'primary', 2 => 'primary', 3 => 'primary', 4 => 'primary', 5 => 'primary', 6 => 'warning'];
                            $colorsBg = [0 => 'danger', 1 => 'primary', 2 => 'primary', 3 => 'primary', 4 => 'primary', 5 => 'primary', 6 => 'warning'];
                            ?>
                            <?php foreach (DAY_NAMES as $dow => $dayName): ?>
                            <?php $day = $wh[$dow] ?? ['is_working_day' => 0, 'start_time' => '08:00:00', 'end_time' => '17:00:00', 'interval_minutes' => 30]; ?>
                            <div class="day-row border rounded-3 p-3 <?= !$day['is_working_day'] ? 'bg-light opacity-75' : 'bg-white' ?>" data-dow="<?= $dow ?>">
                                <div class="row align-items-center g-2">

                                    <!-- Day Toggle + Name -->
                                    <div class="col-lg-2 col-sm-3 col-md-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input day-toggle" type="checkbox"
                                                       name="days[<?= $dow ?>][working]"
                                                       id="day_<?= $dow ?>"
                                                       data-dow="<?= $dow ?>"
                                                       <?= $day['is_working_day'] ? 'checked' : '' ?>
                                                       role="switch">
                                            </div>
                                            <label class="fw-bold text-<?= $colors[$dow] ?>" for="day_<?= $dow ?>" style="cursor:pointer;min-width:70px;">
                                                <?= $dayName ?>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Jam Mulai -->
                                    <div class="col-lg-3 col-sm-3 col-md-2">
                                        <label class="form-label small text-muted mb-1">
                                            <i class="bi bi-play-circle me-1"></i>Mulai
                                        </label>
                                        <input type="time" class="form-control form-control-sm day-field"
                                               name="days[<?= $dow ?>][start]"
                                               value="<?= substr($day['start_time'], 0, 5) ?>"
                                               <?= !$day['is_working_day'] ? 'disabled' : '' ?>
                                               onchange="updatePreview(<?= $dow ?>)">
                                    </div>

                                    <!-- Jam Selesai -->
                                    <div class="col-lg-3 col-sm-3 col-md-2">
                                        <label class="form-label small text-muted mb-1">
                                            <i class="bi bi-stop-circle me-1"></i>Selesai
                                        </label>
                                        <input type="time" class="form-control form-control-sm day-field"
                                               name="days[<?= $dow ?>][end]"
                                               value="<?= substr($day['end_time'], 0, 5) ?>"
                                               <?= !$day['is_working_day'] ? 'disabled' : '' ?>
                                               onchange="updatePreview(<?= $dow ?>)">
                                    </div>

                                    <!-- Interval -->
                                    <div class="col-lg-2 col-sm-3 col-md-2">
                                        <label class="form-label small text-muted mb-1">
                                            <i class="bi bi-stopwatch me-1"></i>Interval
                                        </label>
                                        <select class="form-select form-select-sm day-field interval-select"
                                                name="days[<?= $dow ?>][interval]"
                                                data-dow="<?= $dow ?>"
                                                <?= !$day['is_working_day'] ? 'disabled' : '' ?>
                                                onchange="updatePreview(<?= $dow ?>)">
                                            <option value="15" <?= $day['interval_minutes'] == 15 ? 'selected' : '' ?>>15 menit</option>
                                            <option value="30" <?= $day['interval_minutes'] == 30 ? 'selected' : '' ?>>30 menit</option>
                                            <option value="45" <?= $day['interval_minutes'] == 45 ? 'selected' : '' ?>>45 menit</option>
                                            <option value="60" <?= $day['interval_minutes'] == 60 ? 'selected' : '' ?>>60 menit</option>
                                            <option value="90" <?= $day['interval_minutes'] == 90 ? 'selected' : '' ?>>90 menit</option>
                                            <option value="120" <?= $day['interval_minutes'] == 120 ? 'selected' : '' ?>>120 menit</option>
                                        </select>
                                    </div>

                                    <!-- Preview Slot -->
                                    <div class="col-lg-2 col-md-4">
                                        <label class="form-label small text-muted mb-1">
                                            <i class="bi bi-eye me-1"></i>Contoh Slot
                                        </label>
                                        <div class="preview-slot" id="preview_<?= $dow ?>">
                                            <?php if ($day['is_working_day']): ?>
                                            <span class="badge bg-primary-subtle text-primary px-2 py-1">
                                                <i class="bi bi-clock me-1"></i>
                                                <?= substr($day['start_time'], 0, 5) ?> &ndash; <?= date('H:i', strtotime($day['start_time']) + $day['interval_minutes'] * 60) ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger px-2 py-1">
                                                <i class="bi bi-x-circle me-1"></i>Libur
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="alert alert-info py-2 small mb-4">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>Interval Slot</strong>: Durasi setiap slot booking. Contoh: interval 30 menit → slot 08:00–08:30, 08:30–09:00, dst.
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i> Simpan Pengaturan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>
<?php include dirname(__DIR__) . '/layout/footer.php'; ?>
<script>
// Toggle day on/off
document.querySelectorAll('.day-toggle').forEach(toggle => {
    toggle.addEventListener('change', function() {
        const dow = this.dataset.dow;
        const row = document.querySelector(`.day-row[data-dow="${dow}"]`);
        const fields = row.querySelectorAll('.day-field');
        fields.forEach(f => f.disabled = !this.checked);

        // Dim card when off
        if (this.checked) {
            row.classList.remove('bg-light', 'opacity-75');
            row.classList.add('bg-white');
            updatePreview(dow);
        } else {
            row.classList.add('bg-light', 'opacity-75');
            row.classList.remove('bg-white');
            document.getElementById('preview_' + dow).innerHTML =
                '<span class="badge bg-danger-subtle text-danger px-2 py-1"><i class="bi bi-x-circle me-1"></i>Libur</span>';
        }
    });
});

function updatePreview(dow) {
    const row = document.querySelector(`.day-row[data-dow="${dow}"]`);
    const toggle = document.getElementById('day_' + dow);
    if (!toggle.checked) return;

    const start = row.querySelector('[name^="days[' + dow + '][start]"]')?.value || '08:00';
    const interval = parseInt(row.querySelector('[name^="days[' + dow + '][interval]"]')?.value || 30);

    const [h, m] = start.split(':').map(Number);
    const startMin = h * 60 + m;
    const endMin = startMin + interval;
    const endH = String(Math.floor(endMin / 60)).padStart(2, '0');
    const endM = String(endMin % 60).padStart(2, '0');

    document.getElementById('preview_' + dow).innerHTML =
        '<span class="badge bg-primary-subtle text-primary px-2 py-1">'
        + '<i class="bi bi-clock me-1"></i>' + start + ' &ndash; ' + endH + ':' + endM
        + '</span>';
}
</script>

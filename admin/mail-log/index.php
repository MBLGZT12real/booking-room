<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requirePermission('maillog.view');

$pageTitle = 'Mail Log';
$db = db();

$page    = max(1, intVal(qParam('page', 1)));
$perPage = DEFAULT_PER_PAGE;
$search  = trim(qParam('search'));
$status  = qParam('status', '');

$where  = ['1=1'];
$params = [];
if ($search) {
    $where[] = "(ml.recipient_email LIKE ? OR ml.subject LIKE ? OR ml.recipient_name LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($status !== '') {
    $where[] = "ml.status = ?";
    $params[] = $status;
}
$whereStr = implode(' AND ', $where);

$total = (int) $db->fetchColumn("SELECT COUNT(*) FROM mail_logs ml WHERE $whereStr", $params);
$pagination = paginate($total, $perPage, $page, BASE_URL . '/admin/mail-log/?' . http_build_query(array_filter(compact('search', 'status'))));

$logs = $db->fetchAll(
    "SELECT ml.*, b.booking_code FROM mail_logs ml
     LEFT JOIN bookings b ON ml.booking_id = b.id
     WHERE $whereStr ORDER BY ml.sent_at DESC LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $pagination['offset']])
);

$stats = [
    'sent'   => (int) $db->fetchColumn("SELECT COUNT(*) FROM mail_logs WHERE status = 'sent'"),
    'failed' => (int) $db->fetchColumn("SELECT COUNT(*) FROM mail_logs WHERE status = 'failed'"),
];
?>
<?php include dirname(__DIR__) . '/layout/header.php'; ?>
<div class="admin-wrapper">
<?php include dirname(__DIR__) . '/layout/sidebar.php'; ?>
<div class="main-content">
<?php include dirname(__DIR__) . '/layout/navbar.php'; ?>
<div class="page-content">

    <div class="page-header">
        <h5><i class="bi bi-envelope-check me-2 text-primary"></i><?= e($pageTitle) ?></h5>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/">Dashboard</a></li>
                <li class="breadcrumb-item active">Mail Log</li>
            </ol>
        </nav>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-envelope-check"></i></div>
                    <div>
                        <div class="h5 fw-bold mb-0"><?= numId($stats['sent']) ?></div>
                        <div class="small text-muted">Berhasil Dikirim</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-danger-subtle text-danger"><i class="bi bi-envelope-x"></i></div>
                    <div>
                        <div class="h5 fw-bold mb-0"><?= numId($stats['failed']) ?></div>
                        <div class="small text-muted">Gagal Dikirim</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-12 col-sm-5">
                    <input type="text" class="form-control form-control-sm" name="search"
                           placeholder="Cari email, nama, subjek..." value="<?= e($search) ?>">
                </div>
                <div class="col-6 col-sm-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Semua Status</option>
                        <option value="sent" <?= $status === 'sent' ? 'selected' : '' ?>>Berhasil</option>
                        <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Gagal</option>
                    </select>
                </div>
                <div class="col-6 col-sm-auto d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
                    <a href="<?= BASE_URL ?>/admin/mail-log/" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="px-4" width="50">#</th>
                            <th>Penerima</th>
                            <th>Subjek</th>
                            <th>Booking</th>
                            <th class="text-center">Status</th>
                            <th>Waktu Kirim</th>
                            <th class="text-center no-sort" style="min-width:90px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $i => $log): ?>
                        <tr>
                            <td class="px-3 text-muted small"><?= $pagination['offset'] + $i + 1 ?></td>
                            <td>
                                <div class="small fw-semibold"><?= e($log['recipient_name'] ?? '-') ?></div>
                                <div class="text-muted tiny"><?= e($log['recipient_email']) ?></div>
                            </td>
                            <td class="small"><?= e(truncate($log['subject'], 60)) ?></td>
                            <td class="small">
                                <?php if ($log['booking_code']): ?>
                                <a href="<?= BASE_URL ?>/admin/bookings/detail.php?id=<?= $log['booking_id'] ?>">
                                    <code><?= e($log['booking_code']) ?></code>
                                </a>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($log['status'] === 'sent'): ?>
                                <span class="badge bg-success">
                                    <i class="bi bi-check-circle me-1"></i>Terkirim
                                </span>
                                <?php else: ?>
                                <span class="badge bg-danger" title="<?= e($log['error_message'] ?? '') ?>">
                                    <i class="bi bi-x-circle me-1"></i>Gagal
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= formatDatetimeId($log['sent_at']) ?></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        onclick="showBody(<?= htmlspecialchars(json_encode(['subject' => $log['subject'], 'body' => $log['body'], 'error' => $log['error_message']])) ?>)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($logs)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada log email</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($pagination['total_pages'] > 1): ?>
        <div class="card-footer bg-transparent d-flex align-items-center justify-content-between py-2">
            <div class="small text-muted">Total: <?= numId($total) ?> log</div>
            <?= renderPagination($pagination) ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php include dirname(__DIR__) . '/layout/footer.php'; ?>

<!-- Body Preview Modal -->
<div class="modal fade" id="bodyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bodyModalTitle">Isi Email</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="errorSection" class="alert alert-danger d-none"></div>
                <div id="bodyFrame" style="border:1px solid #dee2e6;border-radius:6px;overflow:hidden;height:500px;"></div>
            </div>
        </div>
    </div>
</div>

<script>
function showBody(data) {
    document.getElementById('bodyModalTitle').textContent = data.subject;
    const frame = document.getElementById('bodyFrame');
    const errSec = document.getElementById('errorSection');
    if (data.error) {
        errSec.textContent = 'Error: ' + data.error;
        errSec.classList.remove('d-none');
    } else {
        errSec.classList.add('d-none');
    }
    const iframe = document.createElement('iframe');
    iframe.style.cssText = 'width:100%;height:500px;border:0;';
    frame.innerHTML = '';
    frame.appendChild(iframe);
    iframe.contentDocument.open();
    iframe.contentDocument.write(data.body || '<p>Isi email kosong</p>');
    iframe.contentDocument.close();
    new bootstrap.Modal(document.getElementById('bodyModal')).show();
}
</script>

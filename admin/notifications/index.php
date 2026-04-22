<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requirePermission('notifications.view');

$pageTitle = 'Notifikasi';
$db = db();

// Handle mark all read
if (isPost() && verifyCsrf() && pParam('action') === 'mark_all_read') {
    Notification::markAllRead();
    flash('success', 'Semua notifikasi ditandai sebagai dibaca.');
    redirect(BASE_URL . '/admin/notifications/');
}

$page    = max(1, intVal(qParam('page', 1)));
$perPage = 20;
$total   = Notification::countAll();
$pagination = paginate($total, $perPage, $page, BASE_URL . '/admin/notifications/');
$notifications = Notification::getAll($pagination['offset'], $perPage);
$unreadCount = Notification::getUnreadCount();
?>
<?php include dirname(__DIR__) . '/layout/header.php'; ?>
<div class="admin-wrapper">
<?php include dirname(__DIR__) . '/layout/sidebar.php'; ?>
<div class="main-content">
<?php include dirname(__DIR__) . '/layout/navbar.php'; ?>
<div class="page-content">

    <div class="page-header d-flex align-items-center justify-content-between">
        <div>
            <h5>
                <i class="bi bi-bell me-2 text-primary"></i><?= e($pageTitle) ?>
                <?php if ($unreadCount > 0): ?>
                <span class="badge bg-danger ms-1"><?= $unreadCount ?></span>
                <?php endif; ?>
            </h5>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/">Dashboard</a></li>
                    <li class="breadcrumb-item active">Notifikasi</li>
                </ol>
            </nav>
        </div>
        <?php if ($unreadCount > 0): ?>
        <form method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="mark_all_read">
            <button type="submit" class="btn btn-md btn-outline-primary">
                <i class="bi bi-check2-all me-1"></i><span class="d-none d-sm-inline">Tandai</span> Semua Dibaca
            </button>
        </form>
        <?php endif; ?>
    </div>

    <?= showFlash() ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($notifications)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-bell-slash display-3 d-block mb-3 opacity-25"></i>
                <p>Tidak ada notifikasi</p>
            </div>
            <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($notifications as $n): ?>
                <?php $typeInfo = NOTIF_TYPES[$n['type']] ?? ['icon' => 'bi-bell', 'class' => 'secondary']; ?>
                <div class="list-group-item <?= !$n['is_read'] ? 'bg-primary-subtle' : '' ?> border-0 border-bottom">
                    <div class="d-flex align-items-start gap-3">
                        <div class="notif-icon flex-shrink-0 rounded-circle bg-<?= $typeInfo['class'] ?>-subtle text-<?= $typeInfo['class'] ?> d-flex align-items-center justify-content-center mt-1"
                             style="width:40px;height:40px;">
                            <i class="bi <?= $typeInfo['icon'] ?>"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <div class="fw-semibold <?= !$n['is_read'] ? '' : 'text-muted' ?>">
                                    <?= e($n['title']) ?>
                                    <?php if (!$n['is_read']): ?>
                                    <span class="badge bg-primary ms-1 small">Baru</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted tiny flex-shrink-0"><?= timeAgo($n['created_at']) ?></div>
                            </div>
                            <?php if ($n['message']): ?>
                            <div class="small text-muted"><?= e($n['message']) ?></div>
                            <?php endif; ?>
                            <div class="d-flex gap-2 mt-1">
                                <?php if ($n['booking_id']): ?>
                                <a href="<?= BASE_URL ?>/admin/bookings/detail.php?id=<?= $n['booking_id'] ?>"
                                   class="btn btn-xs btn-outline-primary small"
                                   style="font-size:0.72rem;padding:2px 8px;"
                                   onclick="markRead(<?= $n['id'] ?>)">
                                    <i class="bi bi-eye me-1"></i>Lihat Booking
                                </a>
                                <?php endif; ?>
                                <?php if (!$n['is_read']): ?>
                                <button type="button" class="btn btn-xs btn-outline-secondary small"
                                        style="font-size:0.72rem;padding:2px 8px;"
                                        onclick="markReadAndUpdate(<?= $n['id'] ?>, this)">
                                    Tandai Dibaca
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($pagination['total_pages'] > 1): ?>
        <div class="card-footer bg-transparent d-flex align-items-center justify-content-between py-2">
            <div class="small text-muted">Halaman <?= $page ?> dari <?= $pagination['total_pages'] ?></div>
            <?= renderPagination($pagination) ?>
        </div>
        <?php endif; ?>
    </div>

</div>
<?php include dirname(__DIR__) . '/layout/footer.php'; ?>
<script>
function markReadAndUpdate(id, btn) {
    $.post(APP.baseUrl + '/api/mark-read.php', { id: id, csrf_token: APP.csrfToken }, function() {
        const item = btn.closest('.list-group-item');
        item.classList.remove('bg-primary-subtle');
        item.querySelector('.badge.bg-primary')?.remove();
        btn.remove();
        // Update navbar badge
        const badge = document.getElementById('notifBadge');
        if (badge) {
            const count = Math.max(0, parseInt(badge.textContent) - 1);
            badge.textContent = count;
            if (count === 0) badge.classList.add('d-none');
        }
    });
}
</script>

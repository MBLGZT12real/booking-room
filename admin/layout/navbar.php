<?php
$unreadCount = Notification::getUnreadCount();
$recentNotifs = Notification::getUnread(8);
$currentUser = Auth::user();
?>

<!-- Top Navbar -->
<nav class="admin-navbar navbar navbar-expand-lg">
    <div class="container-fluid px-3">
        <!-- Hamburger Toggle -->
        <button class="navbar-toggler border-0 p-1 me-2 d-lg-none" type="button" onclick="toggleSidebar()" aria-label="Toggle sidebar">
            <i class="bi bi-list fs-4"></i>
        </button>

        <!-- Page Title (mobile) -->
        <span class="navbar-brand d-lg-none fw-semibold text-truncate" style="max-width:180px;">
            <?= e($pageTitle ?? 'Dashboard') ?>
        </span>

        <div class="d-flex align-items-center ms-auto gap-2">

            <!-- Quick Booking Link -->
            <a href="<?= BASE_URL ?>" target="_blank"
               class="btn btn-sm btn-outline-primary d-none d-sm-inline-flex align-items-center gap-1">
                <i class="bi bi-plus-circle"></i>
                <span class="d-none d-md-inline">Halaman Booking</span>
            </a>

            <!-- Notifications -->
            <div class="dropdown">
                <button class="btn btn-sm btn-light position-relative border-0 px-2"
                        type="button" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-bell fs-5"></i>
                    <?php if ($unreadCount > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notifBadge">
                        <?= $unreadCount > 99 ? '99+' : $unreadCount ?>
                    </span>
                    <?php else: ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="notifBadge">0</span>
                    <?php endif; ?>
                </button>

                <div class="dropdown-menu dropdown-menu-end notif-dropdown shadow" aria-labelledby="notifDropdown">
                    <div class="notif-header d-flex align-items-center justify-content-between px-3 py-2">
                        <span class="fw-semibold">Notifikasi</span>
                        <?php if ($unreadCount > 0): ?>
                        <a href="#" class="text-decoration-none small text-primary" onclick="markAllRead(); return false;">
                            Tandai semua dibaca
                        </a>
                        <?php endif; ?>
                    </div>

                    <div class="notif-list" id="notifList">
                        <?php if (empty($recentNotifs)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-bell-slash display-6 d-block mb-2"></i>
                            <small>Tidak ada notifikasi baru</small>
                        </div>
                        <?php else: ?>
                        <?php foreach ($recentNotifs as $n): ?>
                        <?php
                        $typeInfo = NOTIF_TYPES[$n['type']] ?? ['icon' => 'bi-bell', 'class' => 'secondary'];
                        ?>
                        <a href="<?= $n['booking_id'] ? BASE_URL . '/admin/bookings/detail.php?id=' . $n['booking_id'] : '#' ?>"
                           class="notif-item d-flex align-items-start gap-2 p-3 text-decoration-none border-bottom"
                           onclick="markRead(<?= $n['id'] ?>)">
                            <div class="notif-icon flex-shrink-0 rounded-circle bg-<?= $typeInfo['class'] ?>-subtle text-<?= $typeInfo['class'] ?> d-flex align-items-center justify-content-center"
                                 style="width:36px;height:36px;">
                                <i class="bi <?= $typeInfo['icon'] ?> small"></i>
                            </div>
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="fw-semibold small text-dark text-truncate"><?= e($n['title']) ?></div>
                                <div class="small text-muted text-truncate"><?= e($n['message'] ?? '') ?></div>
                                <div class="tiny text-muted mt-1"><?= timeAgo($n['created_at']) ?></div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="notif-footer text-center py-2 border-top">
                        <a href="<?= BASE_URL ?>/admin/notifications/" class="text-decoration-none small text-primary">
                            Lihat Semua Notifikasi
                        </a>
                    </div>
                </div>
            </div>

            <!-- User Menu -->
            <div class="dropdown">
                <button class="btn btn-sm btn-light border-0 d-flex align-items-center gap-2 px-2"
                        type="button" data-bs-toggle="dropdown">
                    <img src="<?= avatarUrl($currentUser['avatar']) ?>" class="rounded-circle" width="32" height="32" alt="Avatar">
                    <span class="d-none d-md-block small fw-medium text-truncate" style="max-width:120px;">
                        <?= e($currentUser['name']) ?>
                    </span>
                    <i class="bi bi-chevron-down small d-none d-md-block"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li>
                        <div class="dropdown-item-text">
                            <div class="fw-semibold"><?= e($currentUser['name']) ?></div>
                            <small class="text-muted"><?= e($currentUser['role_name']) ?></small>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <a class="dropdown-item" href="<?= BASE_URL ?>/admin/users/profile.php">
                            <i class="bi bi-person-circle me-2"></i> Profil Saya
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i> Keluar
                        </a>
                    </li>
                </ul>
            </div>

        </div>
    </div>
</nav>

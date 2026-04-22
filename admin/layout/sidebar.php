<?php
// Build dynamic sidebar menu from database based on user's permissions
$db = db();
$currentUser = Auth::user();

// Fetch all accessible menus in one query, then group in PHP
$allMenus = $db->fetchAll(
    "SELECT DISTINCT m.* FROM menus m
     LEFT JOIN permissions p ON m.permission_id = p.id
     LEFT JOIN role_permissions rp ON p.id = rp.permission_id
     WHERE m.is_active = 1
     AND (
         m.permission_id IS NULL
         OR (rp.role_id = ? AND p.is_active = 1)
         OR ? = 'superadmin'
     )
     ORDER BY m.parent_id ASC, m.sort_order ASC",
    [$currentUser['role_id'], $currentUser['role_slug']]
);

$parentMenus = [];
$childrenMap = [];
foreach ($allMenus as $menu) {
    if ($menu['parent_id'] === null) {
        $parentMenus[] = $menu;
    } else {
        $childrenMap[$menu['parent_id']][] = $menu;
    }
}
foreach ($parentMenus as &$menu) {
    $menu['children'] = $childrenMap[$menu['id']] ?? [];
}
unset($menu);

// Current URL for active state detection
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = parse_url(BASE_URL, PHP_URL_PATH) ?: '';

function isMenuActive(string $url): bool {
    global $currentPath, $basePath;
    if (!$url) return false;
    $menuPath = $basePath . parse_url($url, PHP_URL_PATH);
    return $menuPath && (
        $currentPath === $menuPath ||
        $currentPath === $menuPath . 'index.php' ||
        strpos($currentPath, rtrim($menuPath, '/') . '/') === 0
    );
}
?>

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
        <?php if ($logo = getSetting('app_logo')): ?>
        <img src="<?= e($logo) ?>" alt="Logo" class="me-2" style="max-height:36px;max-width:160px;width:auto;height:auto;flex-shrink:0;object-fit:contain;">
        <?php else: ?>
        <i class="bi bi-building-fill-check me-2 fs-5"></i>
        <?php endif; ?>
        <span><?= e(getSetting('app_name', APP_NAME)) ?></span>
    </div>

    <!-- User Info -->
    <div class="sidebar-user">
        <img src="<?= avatarUrl($currentUser['avatar']) ?>" alt="Avatar" class="sidebar-avatar">
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?= e($currentUser['name']) ?></div>
            <div class="sidebar-user-role">
                <span class="badge bg-primary-subtle text-primary-emphasis"><?= e($currentUser['role_name']) ?></span>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <ul class="sidebar-nav" id="sidebarNav">
        <?php foreach ($parentMenus as $menu): ?>

        <?php if (empty($menu['children'])): ?>
            <!-- Direct link menu -->
            <li class="sidebar-nav-item">
                <a href="<?= e(BASE_URL . $menu['url']) ?>"
                   class="sidebar-nav-link <?= isMenuActive($menu['url']) ? 'active' : '' ?>">
                    <i class="bi <?= e($menu['icon'] ?? 'bi-circle') ?> sidebar-nav-icon"></i>
                    <span><?= e($menu['name']) ?></span>
                </a>
            </li>
        <?php else: ?>
            <?php
            // Check if any child is active
            $parentActive = isMenuActive($menu['url'] ?? '');
            if (!$parentActive) {
                foreach ($menu['children'] as $child) {
                    if (isMenuActive($child['url'] ?? '')) {
                        $parentActive = true;
                        break;
                    }
                }
            }
            $collapseId = 'collapse_menu_' . $menu['id'];
            ?>
            <!-- Collapsible parent menu -->
            <li class="sidebar-nav-item has-children">
                <a href="#<?= $collapseId ?>"
                   class="sidebar-nav-link <?= $parentActive ? 'active' : 'collapsed' ?>"
                   data-bs-toggle="collapse"
                   aria-expanded="<?= $parentActive ? 'true' : 'false' ?>">
                    <i class="bi <?= e($menu['icon'] ?? 'bi-circle') ?> sidebar-nav-icon"></i>
                    <span><?= e($menu['name']) ?></span>
                    <i class="bi bi-chevron-down sidebar-nav-arrow ms-auto"></i>
                </a>
                <div id="<?= $collapseId ?>" class="collapse <?= $parentActive ? 'show' : '' ?>">
                    <ul class="sidebar-subnav">
                        <?php foreach ($menu['children'] as $child): ?>
                        <li>
                            <a href="<?= e(BASE_URL . $child['url']) ?>"
                               class="sidebar-subnav-link <?= isMenuActive($child['url']) ? 'active' : '' ?>">
                                <i class="bi <?= e($child['icon'] ?? 'bi-dot') ?> me-2"></i>
                                <?= e($child['name']) ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </li>
        <?php endif; ?>

        <?php endforeach; ?>
    </ul>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <a href="<?= BASE_URL ?>" target="_blank" class="sidebar-footer-link">
            <i class="bi bi-box-arrow-up-right me-2"></i> Halaman Booking
        </a>
        <a href="<?= BASE_URL ?>/logout.php" class="sidebar-footer-link text-danger">
            <i class="bi bi-box-arrow-right me-2"></i> Keluar
        </a>
    </div>
</nav>

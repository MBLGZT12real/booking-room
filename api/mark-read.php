<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireLogin();

header('Content-Type: application/json');

if (!isPost()) {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$id  = (int)($_POST['id'] ?? 0);
$all = (bool)($_POST['all'] ?? false);

if ($all) {
    Notification::markAllRead(Auth::id());
    jsonResponse(['success' => true, 'message' => 'All notifications marked as read']);
} elseif ($id > 0) {
    Notification::markRead($id, Auth::id());
    jsonResponse(['success' => true, 'message' => 'Notification marked as read']);
} else {
    jsonResponse(['success' => false, 'message' => 'Invalid parameters'], 400);
}

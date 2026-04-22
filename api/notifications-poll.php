<?php
/**
 * JSON polling fallback for notifications (when SSE is not available).
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (!Auth::check()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

header('Content-Type: application/json');

$userId = Auth::id();
$lastId = (int)($_GET['last_id'] ?? 0);

$notifs      = Notification::getSinceId($lastId, $userId);
$unreadCount = Notification::getUnreadCount($userId);
$newLastId   = !empty($notifs) ? max(array_column($notifs, 'id')) : $lastId;

echo json_encode([
    'success'       => true,
    'notifications' => $notifs,
    'unread_count'  => $unreadCount,
    'last_id'       => $newLastId,
]);

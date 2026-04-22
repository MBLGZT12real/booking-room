<?php
/**
 * SSE (Server-Sent Events) endpoint for realtime notifications.
 * Compatible with shared hosting (25-second loop).
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

// Must be logged in
if (!Auth::check()) {
    http_response_code(401);
    exit;
}

$userId = Auth::id();
$lastId = (int)($_GET['last_id'] ?? 0);

// Prevent output buffering
if (ob_get_level()) ob_end_clean();

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store');
header('X-Accel-Buffering: no'); // Nginx: disable proxy buffering
header('Connection: keep-alive');

// Close session to avoid blocking other requests
session_write_close();

// Disable PHP timeout (or set short for shared hosting)
set_time_limit(30);
ignore_user_abort(false);

$start     = time();
$maxTime   = 23; // seconds (under 25s shared hosting limit)
$pollInterval = 2; // seconds between polls
$sent      = false;

while ((time() - $start) < $maxTime) {
    // Check if client disconnected
    if (connection_aborted()) {
        exit;
    }

    // Fetch new notifications since lastId
    $notifs = Notification::getSinceId($lastId, $userId);

    if (!empty($notifs)) {
        $lastId = max(array_column($notifs, 'id'));
        $unreadCount = Notification::getUnreadCount($userId);

        echo "event: notification\n";
        echo "data: " . json_encode([
            'notifications' => $notifs,
            'unread_count'  => $unreadCount,
            'last_id'       => $lastId,
        ]) . "\n\n";

        $sent = true;
    }

    // Send keepalive ping to prevent connection timeout
    echo ": ping " . time() . "\n\n";

    // Flush output buffer
    if (ob_get_level()) ob_flush();
    flush();

    if (connection_aborted()) exit;

    sleep($pollInterval);
}

// End of stream — client will auto-reconnect
echo "event: reconnect\n";
echo "data: {\"reconnect\": true}\n\n";
if (ob_get_level()) ob_flush();
flush();

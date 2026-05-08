<?php
/**
 * API: Available time slots for a room on a date (for admin booking form)
 * GET: room_id, date (Y-m-d)
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$roomId = (int)($_GET['room_id'] ?? 0);
$date   = trim($_GET['date'] ?? '');

if (!$roomId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak valid']);
    exit;
}

$slots = BookingHelper::getAvailableSlots($roomId, $date);

echo json_encode(['success' => true, 'slots' => $slots]);

<?php
/**
 * AJAX endpoint: return available time slots for a room + date.
 * GET params: room_id, date (Y-m-d), [exclude_booking_id]
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json');

$roomId    = (int)($_GET['room_id'] ?? 0);
$date      = sanitize($_GET['date'] ?? '');
$excludeId = (int)($_GET['exclude_booking_id'] ?? 0);

if (!$roomId || !$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak valid']);
    exit;
}

// Validate date is not in the past
if ($date < date('Y-m-d')) {
    echo json_encode(['success' => false, 'message' => 'Tanggal tidak boleh di masa lalu', 'slots' => []]);
    exit;
}

$db = db();

// Check room exists
$room = $db->fetch("SELECT id, name, capacity FROM rooms WHERE id = ? AND is_active = 1", [$roomId]);
if (!$room) {
    echo json_encode(['success' => false, 'message' => 'Ruangan tidak ditemukan', 'slots' => []]);
    exit;
}

// Check holiday
$isHoliday = BookingHelper::isHoliday($date);
if ($isHoliday) {
    echo json_encode([
        'success'    => false,
        'message'    => 'Tanggal ini adalah hari libur',
        'slots'      => [],
        'is_holiday' => true,
    ]);
    exit;
}

$slots = BookingHelper::getAvailableSlots($roomId, $date, $excludeId);

echo json_encode([
    'success'  => true,
    'room'     => $room,
    'date'     => $date,
    'slots'    => $slots,
    'total'    => count($slots),
]);

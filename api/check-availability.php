<?php
/**
 * AJAX endpoint: check if a room is available for a specific date+time range.
 * POST params: room_id, date, start_time, end_time, [exclude_booking_id]
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json');

$roomId     = (int)($_POST['room_id'] ?? 0);
$date       = sanitize($_POST['date'] ?? '');
$startTime  = sanitize($_POST['start_time'] ?? '');
$endTime    = sanitize($_POST['end_time'] ?? '');
$excludeId  = (int)($_POST['exclude_booking_id'] ?? 0);

if (!$roomId || !$date || !$startTime || !$endTime) {
    echo json_encode(['success' => false, 'available' => false, 'message' => 'Parameter tidak lengkap']);
    exit;
}

// Validate format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ||
    !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $startTime) ||
    !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $endTime)) {
    echo json_encode(['success' => false, 'available' => false, 'message' => 'Format tidak valid']);
    exit;
}

if ($startTime >= $endTime) {
    echo json_encode(['success' => false, 'available' => false, 'message' => 'Jam mulai harus sebelum jam selesai']);
    exit;
}

$db = db();

// Check room
$room = $db->fetch("SELECT id, name, requires_approval FROM rooms WHERE id = ? AND is_active = 1", [$roomId]);
if (!$room) {
    echo json_encode(['success' => false, 'available' => false, 'message' => 'Ruangan tidak ditemukan']);
    exit;
}

// Check holiday
if (BookingHelper::isHoliday($date)) {
    echo json_encode(['success' => false, 'available' => false, 'message' => 'Tanggal ini adalah hari libur']);
    exit;
}

// Check working hours
$dayOfWeek = (int) date('w', strtotime($date));
$wh = $db->fetch("SELECT * FROM working_hours WHERE day_of_week = ? AND is_working_day = 1 AND is_active = 1", [$dayOfWeek]);
if (!$wh) {
    echo json_encode(['success' => false, 'available' => false, 'message' => 'Hari ini bukan hari kerja']);
    exit;
}

if ($startTime < $wh['start_time'] || $endTime > $wh['end_time']) {
    echo json_encode([
        'success'   => false,
        'available' => false,
        'message'   => 'Waktu di luar jam kerja (' . substr($wh['start_time'], 0, 5) . ' - ' . substr($wh['end_time'], 0, 5) . ')',
    ]);
    exit;
}

$available = BookingHelper::isRoomAvailable($roomId, $date, $startTime, $endTime, $excludeId);

echo json_encode([
    'success'          => true,
    'available'        => $available,
    'requires_approval' => (bool) $room['requires_approval'],
    'message'          => $available
        ? 'Ruangan tersedia'
        : 'Ruangan sudah dipesan pada waktu tersebut',
]);

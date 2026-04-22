<?php
/**
 * API: Jadwal harian satu ruangan
 * GET: room_id, date (Y-m-d)
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json');

$roomId = (int)($_GET['room_id'] ?? 0);
$date   = $_GET['date'] ?? date('Y-m-d');

if (!$roomId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak valid']);
    exit;
}

$db = db();

$room = $db->fetch(
    "SELECT id, name, code, location, floor, capacity FROM rooms WHERE id = ? AND is_active = 1",
    [$roomId]
);
if (!$room) {
    echo json_encode(['success' => false, 'message' => 'Ruangan tidak ditemukan']);
    exit;
}

$ts        = strtotime($date);
$fullDate  = date('Y-m-d', $ts);
$dayOfWeek = (int) date('w', $ts);

$wh = $db->fetch(
    "SELECT * FROM working_hours WHERE day_of_week = ? AND is_working_day = 1 AND is_active = 1",
    [$dayOfWeek]
);

$isHoliday    = BookingHelper::isHoliday($fullDate);
$isWorkingDay = $wh !== null && !$isHoliday;

$slots = [];

if ($isWorkingDay) {
    // Ambil semua booking aktif di ruangan+tanggal ini
    $bookings = $db->fetchAll(
        "SELECT b.id, b.booking_code, b.start_time, b.end_time,
                b.booker_name, b.capacity_needed, b.purpose, b.status,
                d.name AS department_name, mt.name AS meeting_type_name
         FROM bookings b
         LEFT JOIN departments d  ON d.id  = b.department_id
         LEFT JOIN meeting_types mt ON mt.id = b.meeting_type_id
         WHERE b.room_id = ? AND b.booking_date = ?
           AND b.status IN ('pending', 'confirmed', 'completed')
         ORDER BY b.start_time ASC",
        [$roomId, $fullDate]
    );

    // Build slot list
    $interval = $wh['interval_minutes'] * 60;
    $startTs  = strtotime($fullDate . ' ' . $wh['start_time']);
    $endTs    = strtotime($fullDate . ' ' . $wh['end_time']);

    for ($cur = $startTs; $cur < $endTs; $cur += $interval) {
        $slotStart = date('H:i:s', $cur);
        $slotEnd   = date('H:i:s', $cur + $interval);

        $booking = null;
        foreach ($bookings as $b) {
            if ($slotStart < $b['end_time'] && $slotEnd > $b['start_time']) {
                $booking = [
                    'id'           => (int) $b['id'],
                    'booking_code' => $b['booking_code'],
                    'start_time'   => substr($b['start_time'], 0, 5),
                    'end_time'     => substr($b['end_time'],   0, 5),
                    'booker_name'  => $b['booker_name'],
                    'department'   => $b['department_name'],
                    'meeting_type' => $b['meeting_type_name'],
                    'purpose'      => $b['purpose'],
                    'capacity'     => (int) $b['capacity_needed'],
                    'status'       => $b['status'],
                ];
                break;
            }
        }

        $slots[] = [
            'start'   => substr($slotStart, 0, 5),
            'end'     => substr($slotEnd,   0, 5),
            'booking' => $booking,
        ];
    }
}

echo json_encode([
    'success'        => true,
    'room'           => $room,
    'date'           => $fullDate,
    'date_formatted' => formatDateId($fullDate),
    'is_working_day' => $isWorkingDay,
    'is_holiday'     => $isHoliday,
    'slots'          => $slots,
]);

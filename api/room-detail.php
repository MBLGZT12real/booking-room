<?php
/**
 * AJAX endpoint: get room info (capacity, facilities, requires_approval, image, etc.)
 * GET params: id
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json');

$roomId = (int)($_GET['id'] ?? 0);

if (!$roomId) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit;
}

$db   = db();
$room = $db->fetch("SELECT * FROM rooms WHERE id = ? AND is_active = 1", [$roomId]);

if (!$room) {
    echo json_encode(['success' => false, 'message' => 'Ruangan tidak ditemukan']);
    exit;
}

// Parse facilities
$facilities = [];
if (!empty($room['facilities'])) {
    $decoded = json_decode($room['facilities'], true);
    $facilities = is_array($decoded) ? $decoded : [];
}

echo json_encode([
    'success'           => true,
    'id'                => (int) $room['id'],
    'name'              => $room['name'],
    'code'              => $room['code'],
    'location'          => $room['location'],
    'floor'             => $room['floor'],
    'capacity'          => (int) $room['capacity'],
    'description'       => $room['description'],
    'facilities'        => $facilities,
    'requires_approval' => (bool) $room['requires_approval'],
    'image_url'         => roomImageUrl($room['image']),
]);

<?php
// ============================================================
// Cron: Auto check-out semua booking yang lupa check-out
// Jalankan setiap hari pukul 23:55 via Task Scheduler / crontab
//
// Windows Task Scheduler:
//   Program : C:\xampp\php\php.exe
//   Argument: C:\xampp\htdocs\booking-room\cron\auto-checkout.php
//   Schedule: Daily at 23:55
//
// Linux crontab:
//   55 23 * * * /usr/bin/php /var/www/html/booking-room/cron/auto-checkout.php >> /var/log/booking-room-cron.log 2>&1
// ============================================================

require_once dirname(__DIR__) . '/includes/cron-bootstrap.php';

$db    = db();
$today = date('Y-m-d');
$now   = date('Y-m-d H:i:s');

// Find all bookings that are still checked-in (not checked-out) for today
$bookings = $db->fetchAll(
    "SELECT b.*, r.name AS room_name
     FROM bookings b
     JOIN rooms r ON b.room_id = r.id
     WHERE b.booking_date = ?
       AND b.status = 'confirmed'
       AND b.checkin_at IS NOT NULL
       AND b.checkout_at IS NULL",
    [$today]
);

if (empty($bookings)) {
    echo "[$now] [AUTO-CHECKOUT] No bookings to auto-checkout.\n";
    exit(0);
}

$count = 0;
$codes = [];

foreach ($bookings as $booking) {
    $checkoutTime = date('Y-m-d 23:55:00');

    $db->query(
        "UPDATE bookings SET checkout_at = ?, status = 'completed' WHERE id = ? AND checkout_at IS NULL",
        [$checkoutTime, $booking['id']]
    );

    $codes[] = $booking['booking_code'];
    $count++;
    echo "[$now] [AUTO-CHECKOUT] {$booking['booking_code']} — {$booking['booker_name']} @ {$booking['room_name']}\n";
}

// Send single summary notification to all admins
if ($count > 0) {
    Notification::createForAdmins(
        'checkout',
        "Auto Check-out: {$count} Booking ({$today})",
        "Sistem melakukan check-out otomatis pukul 23:55 untuk {$count} booking yang belum check-out: " . implode(', ', $codes) . '.',
        null,
        'checkin.manage'
    );
}

echo "[$now] [AUTO-CHECKOUT] Done. {$count} booking(s) auto-checked out at 23:55.\n";
exit(0);

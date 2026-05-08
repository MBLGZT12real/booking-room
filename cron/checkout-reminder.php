<?php
// ============================================================
// Cron: Kirim email reminder check-out
// Jalankan setiap 5–10 menit via Task Scheduler / crontab
//
// Windows Task Scheduler:
//   Program : C:\xampp\php\php.exe
//   Argument: C:\xampp\htdocs\booking-room\cron\checkout-reminder.php
//   Schedule: Every 5 minutes (or every 10 minutes)
//
// Linux crontab:
//   */10 * * * * /usr/bin/php /var/www/html/booking-room/cron/checkout-reminder.php >> /var/log/booking-room-reminder.log 2>&1
// ============================================================

require_once dirname(__DIR__) . '/includes/cron-bootstrap.php';

$db    = db();
$today = date('Y-m-d');
$now   = date('Y-m-d H:i:s');

// Find all bookings that:
//  - Are confirmed and have been checked in
//  - Have NOT yet been checked out
//  - Booking end_time has passed (meeting is over)
//  - Reminder email has NOT been sent yet
//  - Booking date is today
$bookings = $db->fetchAll(
    "SELECT b.*, r.name AS room_name
     FROM bookings b
     JOIN rooms r ON b.room_id = r.id
     WHERE b.booking_date = ?
       AND b.status = 'confirmed'
       AND b.checkin_at IS NOT NULL
       AND b.checkout_at IS NULL
       AND b.checkout_reminder_sent_at IS NULL
       AND b.end_time <= CURTIME()",
    [$today]
);

if (empty($bookings)) {
    echo "[$now] [CHECKOUT-REMINDER] No pending reminders.\n";
    exit(0);
}

$sent   = 0;
$failed = 0;

foreach ($bookings as $booking) {
    // Mark reminder as sent BEFORE sending to prevent double-send on race condition
    $db->query(
        "UPDATE bookings SET checkout_reminder_sent_at = NOW() WHERE id = ? AND checkout_reminder_sent_at IS NULL",
        [$booking['id']]
    );

    $result = Mailer::sendCheckoutReminder($booking);

    if ($result['success']) {
        $sent++;
        echo "[$now] [CHECKOUT-REMINDER] SENT   → {$booking['booking_code']} to {$booking['booker_email']}\n";
    } else {
        $failed++;
        echo "[$now] [CHECKOUT-REMINDER] FAILED → {$booking['booking_code']} ({$result['message']})\n";
    }
}

echo "[$now] [CHECKOUT-REMINDER] Done. Sent: $sent, Failed: $failed\n";
exit(0);

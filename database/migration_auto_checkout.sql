-- ============================================================
-- Migration: Auto Checkout & Checkout Reminder
-- Run this against your existing booking_room database
-- ============================================================

-- Track whether a checkout reminder email has been sent
ALTER TABLE `bookings`
    ADD COLUMN `checkout_reminder_sent_at` DATETIME NULL DEFAULT NULL
        COMMENT 'Timestamp when checkout reminder email was sent'
        AFTER `checkout_at`;

-- Email template for checkout reminder
INSERT INTO `email_templates` (`type`, `name`, `subject`, `body_html`, `variables`) VALUES
('checkout_reminder', 'Reminder Check-Out',
 '[{{app_name}}] Pengingat: Waktu Booking Anda Telah Berakhir - {{booking_code}}',
 '<p>Halo <strong>{{booker_name}}</strong>,</p>
<p>Waktu booking ruangan Anda telah berakhir. Jika Anda masih berada di ruangan, jangan lupa melakukan <strong>Check-Out</strong> agar jadwal tercatat dengan benar.</p>

<div style="background:#fff3cd;padding:15px;border-radius:8px;margin:15px 0;border-left:4px solid #ffc107;">
  <h3 style="margin:0 0 10px;color:#856404;">Detail Booking</h3>
  <table style="width:100%;border-collapse:collapse;">
    <tr><td style="padding:5px 0;color:#6c757d;width:140px;">Kode Booking</td><td><strong>{{booking_code}}</strong></td></tr>
    <tr><td style="padding:5px 0;color:#6c757d;">Ruangan</td><td><strong>{{room_name}}</strong></td></tr>
    <tr><td style="padding:5px 0;color:#6c757d;">Tanggal</td><td>{{booking_date}}</td></tr>
    <tr><td style="padding:5px 0;color:#6c757d;">Waktu Booking</td><td>{{booking_time}}</td></tr>
  </table>
</div>

<div style="text-align:center;margin:25px 0;">
  <a href="{{checkout_url}}"
     style="background:#ffc107;color:#212529;padding:14px 28px;border-radius:8px;text-decoration:none;font-size:16px;font-weight:bold;display:inline-block;">
    &#128682; Klik untuk Check-Out
  </a>
  <p style="margin:10px 0 0;color:#6c757d;font-size:12px;">Link ini berlaku hingga akhir hari ini.</p>
</div>

<p style="color:#6c757d;font-size:13px;">Jika Anda tidak melakukan check-out, sistem akan melakukan check-out otomatis pada pukul 23:55 malam ini.</p>',
 '["app_name","booker_name","booking_code","room_name","booking_date","booking_time","checkout_url"]')

ON DUPLICATE KEY UPDATE
    `name`     = VALUES(`name`),
    `subject`  = VALUES(`subject`),
    `body_html`= VALUES(`body_html`),
    `variables`= VALUES(`variables`);

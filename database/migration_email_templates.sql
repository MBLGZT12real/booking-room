-- Migration: Email Templates
-- Jalankan query ini di database booking_room

CREATE TABLE IF NOT EXISTS `email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL COMMENT 'booking_confirmation, booking_approved, booking_rejected, checkin_confirmation',
  `name` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body_html` text NOT NULL,
  `variables` text DEFAULT NULL COMMENT 'JSON array variable yang tersedia',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: Template default (bisa diedit dari admin)
INSERT INTO `email_templates` (`type`, `name`, `subject`, `body_html`, `variables`) VALUES

('booking_confirmation', 'Konfirmasi Booking Baru',
 '[{{app_name}}] Konfirmasi Booking - {{booking_code}}',
 '<p>Halo <strong>{{booker_name}}</strong>,</p>
<p>Booking ruangan Anda telah berhasil dibuat dengan status: <strong>{{status_label}}</strong></p>

<div style="background:#f8f9fa;padding:15px;border-radius:8px;margin:15px 0;border-left:4px solid #0d6efd;">
  <h3 style="margin:0 0 10px;color:#0d6efd;">Detail Booking</h3>
  <table style="width:100%;border-collapse:collapse;">
    <tr><td style="padding:5px 0;color:#6c757d;width:140px;">Kode Booking</td><td><strong>{{booking_code}}</strong></td></tr>
    <tr><td style="padding:5px 0;color:#6c757d;">Ruangan</td><td><strong>{{room_name}}</strong></td></tr>
    <tr><td style="padding:5px 0;color:#6c757d;">Tanggal</td><td>{{booking_date}}</td></tr>
    <tr><td style="padding:5px 0;color:#6c757d;">Waktu</td><td>{{booking_time}}</td></tr>
  </table>
</div>

{{checkin_section}}

<p style="font-size:12px;color:#6c757d;">Jika Anda tidak merasa membuat booking ini, abaikan email ini.</p>',
 '["app_name","booker_name","booking_code","room_name","booking_date","booking_time","status_label","checkin_section"]'),

('booking_approved', 'Booking Disetujui',
 '[{{app_name}}] Booking Disetujui - {{booking_code}}',
 '<p>Halo <strong>{{booker_name}}</strong>,</p>
<p>Kabar baik! Booking ruangan Anda telah <strong style="color:#198754;">disetujui</strong>.</p>

<div style="background:#f8f9fa;padding:15px;border-radius:8px;margin:15px 0;border-left:4px solid #198754;">
  <table style="width:100%;border-collapse:collapse;">
    <tr><td style="padding:5px 0;color:#6c757d;width:140px;">Kode Booking</td><td><strong>{{booking_code}}</strong></td></tr>
    <tr><td style="padding:5px 0;color:#6c757d;">Ruangan</td><td><strong>{{room_name}}</strong></td></tr>
    <tr><td style="padding:5px 0;color:#6c757d;">Tanggal</td><td>{{booking_date}}</td></tr>
    <tr><td style="padding:5px 0;color:#6c757d;">Waktu</td><td>{{booking_time}}</td></tr>
  </table>
</div>

<div style="background:#d1fae5;padding:15px;border-radius:8px;margin:15px 0;text-align:center;">
  <p style="margin:0 0 10px;color:#065f46;font-weight:bold;">Link Check-in Anda:</p>
  <a href="{{checkin_url}}" style="background:#198754;color:#fff;padding:12px 25px;border-radius:6px;text-decoration:none;font-size:16px;display:inline-block;">
    Buka Link Check-in
  </a>
  <p style="margin:10px 0 0;color:#065f46;font-size:12px;">Gunakan link ini pada hari booking untuk melakukan check-in.</p>
</div>',
 '["app_name","booker_name","booking_code","room_name","booking_date","booking_time","checkin_url"]'),

('booking_rejected', 'Booking Tidak Disetujui',
 '[{{app_name}}] Booking Tidak Disetujui - {{booking_code}}',
 '<p>Halo <strong>{{booker_name}}</strong>,</p>
<p>Mohon maaf, booking ruangan Anda <strong style="color:#dc3545;">tidak dapat disetujui</strong>.</p>

{{reason_section}}

<div style="background:#f8f9fa;padding:15px;border-radius:8px;margin:15px 0;border-left:4px solid #dc3545;">
  <table style="width:100%;border-collapse:collapse;">
    <tr><td style="padding:5px 0;color:#6c757d;width:140px;">Kode Booking</td><td>{{booking_code}}</td></tr>
    <tr><td style="padding:5px 0;color:#6c757d;">Ruangan</td><td>{{room_name}}</td></tr>
    <tr><td style="padding:5px 0;color:#6c757d;">Tanggal</td><td>{{booking_date}}</td></tr>
    <tr><td style="padding:5px 0;color:#6c757d;">Waktu</td><td>{{booking_time}}</td></tr>
  </table>
</div>

<p>Anda dapat melakukan booking ulang untuk tanggal atau ruangan yang lain.</p>',
 '["app_name","booker_name","booking_code","room_name","booking_date","booking_time","reason_section"]'),

('checkin_confirmation', 'Konfirmasi Check-in',
 '[{{app_name}}] Check-in Berhasil - {{booking_code}}',
 '<p>Halo <strong>{{booker_name}}</strong>,</p>
<p>Check-in Anda telah berhasil dicatat.</p>

<div style="background:#d1fae5;padding:15px;border-radius:8px;margin:15px 0;border-left:4px solid #198754;">
  <table style="width:100%;border-collapse:collapse;">
    <tr><td style="padding:5px 0;color:#065f46;width:140px;">Ruangan</td><td><strong>{{room_name}}</strong></td></tr>
    <tr><td style="padding:5px 0;color:#065f46;">Tanggal</td><td>{{booking_date}}</td></tr>
    <tr><td style="padding:5px 0;color:#065f46;">Waktu Booking</td><td>{{booking_time}}</td></tr>
    <tr><td style="padding:5px 0;color:#065f46;">Check-in pukul</td><td><strong>{{checkin_time}}</strong></td></tr>
  </table>
</div>

<p>Jangan lupa melakukan check-out setelah selesai menggunakan ruangan.</p>',
 '["app_name","booker_name","booking_code","room_name","booking_date","booking_time","checkin_time"]');

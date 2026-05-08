-- ============================================================
-- Migration: Tambah email template booking_cancelled
-- Jalankan sekali pada database yang sudah ada
-- ============================================================

INSERT IGNORE INTO `email_templates` (`type`, `name`, `subject`, `body_html`, `variables`) VALUES
('booking_cancelled', 'Booking Dibatalkan',
 '[{{app_name}}] Booking Dibatalkan - {{booking_code}}',
 '<p>Halo <strong>{{booker_name}}</strong>,</p>
<p>Kami informasikan bahwa booking ruangan Anda telah <strong style="color:#dc3545;">dibatalkan</strong> oleh admin.</p>

<div style="background:#f8f9fa;padding:15px;border-radius:8px;margin:15px 0;border-left:4px solid #dc3545;">
  <table style="width:100%;border-collapse:collapse;">
    <tr><td style="padding:5px 0;color:#6c757d;width:140px;">Kode Booking</td><td><strong>{{booking_code}}</strong></td></tr>
    <tr><td style="padding:5px 0;color:#6c757d;">Ruangan</td><td><strong>{{room_name}}</strong></td></tr>
    <tr><td style="padding:5px 0;color:#6c757d;">Tanggal</td><td>{{booking_date}}</td></tr>
    <tr><td style="padding:5px 0;color:#6c757d;">Waktu</td><td>{{booking_time}}</td></tr>
  </table>
</div>

{{cancelled_by_section}}

<p>Jika Anda memiliki pertanyaan atau ingin melakukan booking ulang, silakan hubungi kami atau kunjungi halaman booking.</p>',
 '["app_name","booker_name","booking_code","room_name","booking_date","booking_time","cancelled_by_section"]');

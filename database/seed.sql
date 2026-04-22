-- ============================================================
-- BOOKING ROOM SYSTEM - Seed Data
-- Jalankan SETELAH schema.sql
-- ============================================================

USE `booking_room`;

-- ============================================================
-- ROLES
-- ============================================================
INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `is_active`) VALUES
(1, 'Superadmin', 'superadmin', 'Akses penuh ke seluruh sistem termasuk manajemen role dan user', 1),
(2, 'Admin', 'admin', 'Kelola data master, laporan, notifikasi, dan mail log', 1),
(3, 'Operator', 'operator', 'Kelola beberapa data master dan manajemen check-in/out', 1),
(4, 'Staff', 'staff', 'Akses terbatas (placeholder jika diperlukan)', 0);

-- ============================================================
-- PERMISSIONS
-- ============================================================
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `description`, `is_active`) VALUES
-- Dashboard
(1, 'Lihat Dashboard', 'dashboard.view', 'dashboard', 'Akses halaman dashboard admin', 1),

-- Roles
(2, 'Lihat Role', 'roles.view', 'roles', 'Melihat daftar role', 1),
(3, 'Tambah Role', 'roles.create', 'roles', 'Membuat role baru', 1),
(4, 'Edit Role', 'roles.edit', 'roles', 'Mengedit role yang ada', 1),
(5, 'Hapus Role', 'roles.delete', 'roles', 'Menghapus role', 1),
(6, 'Kelola Permission Role', 'roles.permissions', 'roles', 'Mengatur permission tiap role', 1),

-- Users
(7, 'Lihat User', 'users.view', 'users', 'Melihat daftar user', 1),
(8, 'Tambah User', 'users.create', 'users', 'Membuat user baru', 1),
(9, 'Edit User', 'users.edit', 'users', 'Mengedit user yang ada', 1),
(10, 'Hapus User', 'users.delete', 'users', 'Menghapus user', 1),

-- Menus
(11, 'Kelola Menu', 'menus.manage', 'menus', 'Mengatur menu dinamis sistem', 1),

-- System Settings
(12, 'Pengaturan Sistem', 'settings.manage', 'settings', 'Mengatur konfigurasi sistem', 1),

-- Departments
(13, 'Lihat Departemen', 'departments.view', 'departments', 'Melihat daftar departemen', 1),
(14, 'Kelola Departemen', 'departments.manage', 'departments', 'CRUD departemen', 1),

-- Meeting Types
(15, 'Lihat Tipe Meeting', 'meeting_types.view', 'meeting_types', 'Melihat daftar tipe meeting', 1),
(16, 'Kelola Tipe Meeting', 'meeting_types.manage', 'meeting_types', 'CRUD tipe meeting', 1),

-- Rooms
(17, 'Lihat Ruangan', 'rooms.view', 'rooms', 'Melihat daftar ruangan', 1),
(18, 'Kelola Ruangan', 'rooms.manage', 'rooms', 'CRUD data ruangan', 1),

-- Working Hours
(19, 'Kelola Jam Kerja', 'working_hours.manage', 'working_hours', 'Mengatur jam kerja dan interval', 1),

-- Holidays
(20, 'Kelola Hari Libur', 'holidays.manage', 'holidays', 'Mengatur hari libur/day off', 1),

-- Bookings
(21, 'Lihat Semua Booking', 'bookings.view', 'bookings', 'Melihat semua data booking', 1),
(22, 'Approve/Reject Booking', 'bookings.approve', 'bookings', 'Menyetujui atau menolak booking', 1),
(23, 'Batalkan Booking', 'bookings.cancel', 'bookings', 'Membatalkan booking', 1),

-- Check-in/out
(24, 'Kelola Check-in/out', 'checkin.manage', 'checkin', 'Melihat dan override check-in/out', 1),

-- Notifications
(25, 'Lihat Notifikasi', 'notifications.view', 'notifications', 'Melihat notifikasi sistem', 1),

-- Mail Log
(26, 'Lihat Mail Log', 'maillog.view', 'maillog', 'Melihat log email terkirim', 1),

-- Reports
(27, 'Laporan Per Ruangan', 'reports.by_room', 'reports', 'Laporan penggunaan per ruangan', 1),
(28, 'Laporan Per Tanggal', 'reports.by_date', 'reports', 'Laporan penggunaan per tanggal', 1),
(29, 'Laporan Booking', 'reports.bookings', 'reports', 'Laporan full data booking', 1);

-- ============================================================
-- ROLE PERMISSIONS
-- ============================================================

-- Superadmin: semua permission
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions` WHERE `is_active` = 1;

-- Admin: semua kecuali role/user/menu management
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(2, 1),   -- dashboard
(2, 12),  -- settings
(2, 13), (2, 14), -- departments
(2, 15), (2, 16), -- meeting types
(2, 17), (2, 18), -- rooms
(2, 19),  -- working hours
(2, 20),  -- holidays
(2, 21), (2, 22), (2, 23), -- bookings
(2, 24),  -- checkin
(2, 25),  -- notifications
(2, 26),  -- mail log
(2, 27), (2, 28), (2, 29); -- reports

-- Operator: terbatas
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(3, 1),   -- dashboard
(3, 13),  -- lihat departemen
(3, 15),  -- lihat tipe meeting
(3, 17),  -- lihat ruangan
(3, 21), (3, 22), (3, 23), -- bookings
(3, 24),  -- checkin manage
(3, 25);  -- notifications

-- ============================================================
-- MENUS (Dynamic)
-- ============================================================
INSERT INTO `menus` (`id`, `parent_id`, `name`, `url`, `icon`, `sort_order`, `permission_id`, `is_active`) VALUES
-- Root menus
(1, NULL, 'Dashboard', '/admin/', 'bi-speedometer2', 1, 1, 1),

-- Superadmin section
(2, NULL, 'Manajemen Sistem', NULL, 'bi-shield-lock', 2, 2, 1),
(3, 2, 'Role & Permission', '/admin/roles/', 'bi-person-badge', 1, 2, 1),
(4, 2, 'Manajemen User', '/admin/users/', 'bi-people', 2, 7, 1),
(5, 2, 'Pengaturan Menu', '/admin/menus/', 'bi-list-nested', 3, 11, 1),

-- Master Data section
(6, NULL, 'Data Master', NULL, 'bi-database', 3, 12, 1),
(7, 6, 'Pengaturan Sistem', '/admin/master/settings.php', 'bi-gear', 1, 12, 1),
(8, 6, 'Departemen', '/admin/master/departments.php', 'bi-building', 2, 14, 1),
(9, 6, 'Tipe Meeting', '/admin/master/meeting-types.php', 'bi-calendar-event', 3, 16, 1),
(10, 6, 'Ruangan', '/admin/master/rooms.php', 'bi-door-open', 4, 18, 1),
(11, 6, 'Jam Kerja', '/admin/master/working-hours.php', 'bi-clock', 5, 19, 1),
(12, 6, 'Hari Libur', '/admin/master/holidays.php', 'bi-calendar-x', 6, 20, 1),

-- Bookings
(13, NULL, 'Booking', '/admin/bookings/', 'bi-journal-check', 4, 21, 1),
(14, NULL, 'Check-in/out', '/admin/checkin/', 'bi-box-arrow-in-right', 5, 24, 1),

-- Reports
(15, NULL, 'Laporan', NULL, 'bi-bar-chart-line', 6, 27, 1),
(16, 15, 'Per Ruangan', '/admin/reports/by-room.php', 'bi-door-closed', 1, 27, 1),
(17, 15, 'Per Tanggal', '/admin/reports/by-date.php', 'bi-calendar3', 2, 28, 1),
(18, 15, 'Laporan Booking', '/admin/reports/bookings.php', 'bi-file-earmark-bar-graph', 3, 29, 1),

-- Notifications & Mail Log
(19, NULL, 'Notifikasi', '/admin/notifications/', 'bi-bell', 7, 25, 1),
(20, NULL, 'Mail Log', '/admin/mail-log/', 'bi-envelope-check', 8, 26, 1),

-- Email Templates (child of Data Master, permission: settings.manage = id 12)
(21, 6, 'Template Email', '/admin/email-templates/', 'bi-envelope-paper', 7, 12, 1);

-- ============================================================
-- USERS (password di-hash dengan Argon2ID + Pepper)
-- Semua password di sini menggunakan:
--   Auth::hashPassword($password) → Argon2ID + AUTH_PEPPER
--
-- superadmin   : password = superadmin@123
-- admin        : password = admin123
-- operator1    : password = operator123
-- operator2    : password = operator123
-- staff1       : password = ap1208
--
-- Untuk re-generate hash: php database/gen-passwords.php
-- ============================================================
INSERT INTO `users` (`id`, `role_id`, `name`, `username`, `email`, `password`, `phone`, `is_active`) VALUES
(1, 1, 'Super Administrator', 'superadmin', 'no-reply@booking-room.com',
 '$argon2id$v=19$m=65536,t=4,p=1$UktmalpjYTZtcVp2VGxQRw$Sl635yjhTJQPs4N9TOzB9q3sbfosTlC7rcpIIYJcFTk',
 NULL, 1),
(2, 2, 'Administrator', 'admin', 'it@booking-room.com',
 '$argon2id$v=19$m=65536,t=4,p=1$eUJaajVLSVpTM0NwSThCMw$pGDX1YjeeCnyDbi7DaUGPhbDy/5bnVpx2XOT6t5qKw0',
 NULL, 1),
(3, 3, 'Operator Satu', 'operator1', 'operator1@booking-room.com',
 '$argon2id$v=19$m=65536,t=4,p=1$Unczb0NCZnpGem9BSTR1SQ$VmYBnq6HRe7wqjUafwHTuOG77JU5z6J9LvSfEPQTMFQ',
 NULL, 1);
(4, 3, 'Operator Dua', 'operator2', 'operator2@booking-room.com',
 '$argon2id$v=19$m=65536,t=4,p=1$Unczb0NCZnpGem9BSTR1SQ$VmYBnq6HRe7wqjUafwHTuOG77JU5z6J9LvSfEPQTMFQ',
 NULL, 1);
(5, 4, 'Staff Satu', 'staff1', 'staff1@booking-room.com',
 '$argon2id$v=19$m=65536,t=4,p=1$UXZXU09mRmdCQ1BVTnBFVQ$+w1XaakRvWqBaAiGFUEHhccDvS7Su86n+xWVR1oTnnE',
 NULL, 1);

-- ============================================================
-- SYSTEM SETTINGS
-- ============================================================
INSERT INTO `system_settings` (`key_name`, `value`, `type`, `label`, `description`, `group_name`, `sort_order`) VALUES
-- General
('app_name', 'Sistem Booking Ruangan', 'text', 'Nama Aplikasi', 'Nama aplikasi yang tampil di header dan email', 'general', 1),
('app_tagline', 'Booking ruangan kantor dengan mudah dan cepat', 'text', 'Tagline Aplikasi', 'Kalimat pendek di bawah nama aplikasi', 'general', 2),
('app_url', 'http://localhost/booking-room', 'text', 'URL Aplikasi', 'URL dasar aplikasi (tanpa trailing slash)', 'general', 3),
('app_logo', 'http://localhost/booking-room/assets/images/logo.png', 'file', 'Logo Aplikasi', 'Upload logo dalam format PNG/JPG (maks 2MB)', 'general', 4),
('app_favicon', 'http://localhost/booking-room/assets/images/favicon.png', 'file', 'Favicon', 'Upload favicon .ico atau .png', 'general', 5),
('app_timezone', 'Asia/Jakarta', 'text', 'Timezone', 'Timezone server (contoh: Asia/Jakarta)', 'general', 6),
('app_locale', 'id', 'text', 'Locale', 'Bahasa aplikasi (id=Indonesia, en=English)', 'general', 7),
('app_color_primary', '#0d6efd', 'color', 'Warna Primer', 'Warna utama tampilan (hex)', 'general', 8),

-- Booking
('booking_max_days_advance', '60', 'number', 'Maksimal Hari ke Depan', 'Berapa hari ke depan booking bisa dilakukan', 'booking', 1),
('booking_min_minutes_before', '30', 'number', 'Minimal Menit Sebelum Booking', 'Minimal berapa menit sebelum jam mulai booking bisa dilakukan (contoh: 30 = setengah jam)', 'booking', 2),
('booking_max_duration_hours', '8', 'number', 'Maksimal Durasi (Jam)', 'Maksimal durasi booking dalam jam', 'booking', 3),
('booking_allow_same_day', '1', 'boolean', 'Boleh Booking Hari Ini', 'Izinkan booking untuk hari yang sama', 'booking', 4),
('booking_token_expire_hours', '24', 'number', 'Kedaluwarsa Token (Jam)', 'Berapa jam token check-in valid setelah hari booking', 'booking', 5),
('allowed_email_domain', 'company.com', 'text', 'Pembatasan Domain Email', 'Kosongkan untuk izinkan semua domain. Isi domain untuk membatasi (contoh: company.com)', 'booking', 6),

-- Email (SMTP)
('smtp_host', 'smtp.gmail.com', 'text', 'SMTP Host', 'Host server SMTP', 'email', 1),
('smtp_port', '465', 'number', 'SMTP Port', 'Port SMTP (587 untuk TLS, 465 untuk SSL)', 'email', 2),
('smtp_username', 'no-reply@company.com', 'text', 'SMTP Username', 'Username/email akun SMTP', 'email', 3),
('smtp_password', 'Passw0rd', 'text', 'SMTP Password', 'Password akun SMTP', 'email', 4),
('smtp_encryption', 'ssl', 'text', 'Enkripsi SMTP', 'Enkripsi: tls atau ssl', 'email', 5),
('smtp_from_email', 'no-reply@company.com', 'text', 'Email Pengirim', 'Alamat email pengirim', 'email', 6),
('smtp_from_name', 'Company - Booking Ruangan', 'text', 'Nama Pengirim', 'Nama yang tampil sebagai pengirim email', 'email', 7),
('email_notification_enabled', '1', 'boolean', 'Aktifkan Email Notifikasi', 'Kirim email notifikasi ke pemesanan', 'email', 8),

-- Notification
('notif_new_booking', '1', 'boolean', 'Notif: Booking Baru', 'Kirim notifikasi saat ada booking baru', 'notification', 1),
('notif_checkin', '1', 'boolean', 'Notif: Check-in', 'Kirim notifikasi saat karyawan check-in', 'notification', 2),
('notif_checkout', '1', 'boolean', 'Notif: Check-out', 'Kirim notifikasi saat karyawan check-out', 'notification', 3),
('notif_cancelled', '1', 'boolean', 'Notif: Dibatalkan', 'Kirim notifikasi saat booking dibatalkan', 'notification', 4);

-- ============================================================
-- WORKING HOURS (Senin-Jumat aktif, Sabtu-Minggu non-aktif)
-- ============================================================
INSERT INTO `working_hours` (`day_of_week`, `is_working_day`, `start_time`, `end_time`, `interval_minutes`, `is_active`) VALUES
(0, 0, '09:00:00', '20:00:00', 30, 1), -- Minggu: libur
(1, 1, '09:00:00', '20:00:00', 30, 1), -- Senin
(2, 1, '09:00:00', '20:00:00', 30, 1), -- Selasa
(3, 1, '09:00:00', '20:00:00', 30, 1), -- Rabu
(4, 1, '09:00:00', '20:00:00', 30, 1), -- Kamis
(5, 1, '09:00:00', '20:00:00', 30, 1), -- Jumat
(6, 0, '09:00:00', '20:00:00', 30, 1); -- Sabtu: libur

-- ============================================================
-- SAMPLE DEPARTMENTS
-- ============================================================
INSERT INTO `departments` (`name`, `code`, `description`, `is_active`) VALUES
('IT & Technology', 'IT', 'Departemen Teknologi Informasi', 1),
('Human Resources', 'HR', 'Departemen Sumber Daya Manusia', 1),
('Finance & Accounting', 'FIN', 'Departemen Keuangan', 1),
('Marketing', 'MKT', 'Departemen Pemasaran', 1),
('Operations', 'OPS', 'Departemen Operasional', 1),
('Management', 'MGT', 'Manajemen Eksekutif', 1);

-- ============================================================
-- SAMPLE MEETING TYPES
-- ============================================================
INSERT INTO `meeting_types` (`name`, `description`, `is_active`) VALUES
('Rapat Internal', 'Rapat tim internal departemen', 1),
('Presentasi', 'Presentasi kepada tim atau manajemen', 1),
('Training & Workshop', 'Pelatihan atau workshop karyawan', 1),
('Interview', 'Wawancara kandidat karyawan baru', 1),
('Rapat Eksternal', 'Rapat dengan klien atau vendor', 1),
('Brainstorming', 'Sesi diskusi dan ideasi', 1),
('Video Conference', 'Konferensi video dengan pihak luar', 1);

-- ============================================================
-- SAMPLE ROOMS
-- ============================================================
INSERT INTO `rooms` (`name`, `code`, `location`, `floor`, `capacity`, `description`, `facilities`, `requires_approval`, `is_active`) VALUES
('Ruang Rapat A', 'RR-A', 'Gedung Utama', 'Lantai 1', 10, 'Ruang rapat standar untuk meeting harian', '["Proyektor","Whiteboard","AC","TV Monitor"]', 0, 1),
('Ruang Rapat B', 'RR-B', 'Gedung Utama', 'Lantai 1', 20, 'Ruang rapat besar untuk presentasi', '["Proyektor HD","Whiteboard","AC","Sound System","Laser Pointer"]', 0, 1),
('Ruang Boardroom', 'BR-1', 'Gedung Utama', 'Lantai 3', 15, 'Ruang eksekutif untuk rapat direksi', '["Proyektor 4K","TV 75 inch","AC","Video Conference","Whiteboard","Mini Bar"]', 1, 1),
('Ruang Training', 'TR-1', 'Gedung Utama', 'Lantai 2', 30, 'Ruang pelatihan kapasitas besar', '["Proyektor","Whiteboard","AC","Komputer Trainer","Microphone"]', 1, 1),
('Ruang Video Conference', 'VC-1', 'Gedung Utama', 'Lantai 2', 8, 'Ruang khusus video conference', '["Kamera VC","TV 65 inch","AC","Microphone Array","Speaker"]', 0, 1);

-- ============================================================
-- HOLIDAYS 2025-2028
-- Catatan: Tanggal hari raya Islam/Hindu dihitung berdasarkan perkiraan
-- kalender Hijriah/Saka. Bisa berbeda 1-2 hari dari ketetapan pemerintah.
-- ============================================================
INSERT INTO `holidays` (`date`, `name`, `description`, `is_recurring`, `is_active`) VALUES

-- 2025
('2025-01-01', 'Tahun Baru Masehi', 'Libur Tahun Baru 2025', 0, 1),
('2025-01-27', 'Isra Mi\'raj Nabi Muhammad SAW', '27 Rajab 1446 H', 0, 1),
('2025-01-29', 'Tahun Baru Imlek 2576', 'Tahun Ular', 0, 1),
('2025-03-29', 'Hari Raya Nyepi', 'Tahun Baru Saka 1947', 0, 1),
('2025-03-31', 'Idul Fitri 1446 H', '1 Syawal 1446 H', 0, 1),
('2025-04-01', 'Idul Fitri 1446 H', '2 Syawal 1446 H (Cuti Bersama)', 0, 1),
('2025-04-18', 'Wafat Isa Al Masih', 'Good Friday', 0, 1),
('2025-05-01', 'Hari Buruh Internasional', 'Labor Day', 0, 1),
('2025-05-12', 'Hari Raya Waisak', 'Waisak 2569 BE', 0, 1),
('2025-05-29', 'Kenaikan Isa Al Masih', 'Ascension Day', 0, 1),
('2025-06-01', 'Hari Lahir Pancasila', 'Pancasila Day', 0, 1),
('2025-06-06', 'Idul Adha 1446 H', '10 Dzulhijjah 1446 H', 0, 1),
('2025-06-27', 'Tahun Baru Islam 1447 H', '1 Muharram 1447 H', 0, 1),
('2025-08-17', 'Hari Kemerdekaan RI', 'HUT RI ke-80', 0, 1),
('2025-09-05', 'Maulid Nabi Muhammad SAW', '12 Rabi\'ul Awwal 1447 H', 0, 1),
('2025-12-25', 'Hari Raya Natal', 'Christmas Day', 0, 1),

-- 2026
('2026-01-01', 'Tahun Baru Masehi', 'Libur Tahun Baru 2026', 0, 1),
('2026-01-16', 'Isra Mi\'raj Nabi Muhammad SAW', '27 Rajab 1447 H', 0, 1),
('2026-02-17', 'Tahun Baru Imlek 2577', 'Tahun Kuda', 0, 1),
('2026-03-19', 'Hari Raya Nyepi', 'Tahun Baru Saka 1948', 0, 1),
('2026-03-20', 'Idul Fitri 1447 H', '1 Syawal 1447 H', 0, 1),
('2026-03-21', 'Idul Fitri 1447 H', '2 Syawal 1447 H', 0, 1),
('2026-04-03', 'Wafat Isa Al Masih', 'Good Friday', 0, 1),
('2026-05-01', 'Hari Buruh Internasional', 'Labor Day', 0, 1),
('2026-05-14', 'Kenaikan Isa Al Masih', 'Ascension Day', 0, 1),
('2026-05-27', 'Idul Adha 1447 H', '10 Dzulhijjah 1447 H', 0, 1),
('2026-05-31', 'Hari Raya Waisak', 'Waisak 2570 BE', 0, 1),
('2026-06-01', 'Hari Lahir Pancasila', 'Pancasila Day', 0, 1),
('2026-06-16', 'Tahun Baru Islam 1448 H', '1 Muharram 1448 H', 0, 1),
('2026-08-17', 'Hari Kemerdekaan RI', 'HUT RI ke-81', 0, 1),
('2026-08-25', 'Maulid Nabi Muhammad SAW', '12 Rabi\'ul Awwal 1448 H', 0, 1),
('2026-12-25', 'Hari Raya Natal', 'Christmas Day', 0, 1),

-- 2027
('2027-01-01', 'Tahun Baru Masehi', 'Libur Tahun Baru 2027', 0, 1),
('2027-01-06', 'Isra Mi\'raj Nabi Muhammad SAW', '27 Rajab 1448 H', 0, 1),
('2027-02-06', 'Tahun Baru Imlek 2578', 'Tahun Kambing', 0, 1),
('2027-03-08', 'Hari Raya Nyepi', 'Tahun Baru Saka 1949', 0, 1),
('2027-03-09', 'Idul Fitri 1448 H', '1 Syawal 1448 H', 0, 1),
('2027-03-10', 'Idul Fitri 1448 H', '2 Syawal 1448 H', 0, 1),
('2027-03-26', 'Wafat Isa Al Masih', 'Good Friday', 0, 1),
('2027-05-01', 'Hari Buruh Internasional', 'Labor Day', 0, 1),
('2027-05-06', 'Kenaikan Isa Al Masih', 'Ascension Day', 0, 1),
('2027-05-13', 'Hari Raya Waisak', 'Waisak 2571 BE', 0, 1),
('2027-05-16', 'Idul Adha 1448 H', '10 Dzulhijjah 1448 H', 0, 1),
('2027-06-01', 'Hari Lahir Pancasila', 'Pancasila Day', 0, 1),
('2027-06-06', 'Tahun Baru Islam 1449 H', '1 Muharram 1449 H', 0, 1),
('2027-08-15', 'Maulid Nabi Muhammad SAW', '12 Rabi\'ul Awwal 1449 H', 0, 1),
('2027-08-17', 'Hari Kemerdekaan RI', 'HUT RI ke-82', 0, 1),
('2027-12-25', 'Hari Raya Natal', 'Christmas Day', 0, 1),
('2027-12-26', 'Isra Mi\'raj Nabi Muhammad SAW', '27 Rajab 1449 H', 0, 1),

-- 2028
('2028-01-01', 'Tahun Baru Masehi', 'Libur Tahun Baru 2028', 0, 1),
('2028-01-26', 'Tahun Baru Imlek 2579', 'Tahun Monyet', 0, 1),
('2028-02-27', 'Idul Fitri 1449 H', '1 Syawal 1449 H', 0, 1),
('2028-02-28', 'Idul Fitri 1449 H', '2 Syawal 1449 H', 0, 1),
('2028-03-26', 'Hari Raya Nyepi', 'Tahun Baru Saka 1950', 0, 1),
('2028-04-14', 'Wafat Isa Al Masih', 'Good Friday', 0, 1),
('2028-05-01', 'Hari Buruh Internasional', 'Labor Day', 0, 1),
('2028-05-05', 'Idul Adha 1449 H', '10 Dzulhijjah 1449 H', 0, 1),
('2028-05-25', 'Kenaikan Isa Al Masih', 'Ascension Day', 0, 1),
('2028-05-26', 'Tahun Baru Islam 1450 H', '1 Muharram 1450 H', 0, 1),
('2028-06-01', 'Hari Lahir Pancasila', 'Pancasila Day', 0, 1),
('2028-06-02', 'Hari Raya Waisak', 'Waisak 2572 BE', 0, 1),
('2028-08-05', 'Maulid Nabi Muhammad SAW', '12 Rabi\'ul Awwal 1450 H', 0, 1),
('2028-08-17', 'Hari Kemerdekaan RI', 'HUT RI ke-83', 0, 1),
('2028-12-15', 'Isra Mi\'raj Nabi Muhammad SAW', '27 Rajab 1450 H', 0, 1),
('2028-12-25', 'Hari Raya Natal', 'Christmas Day', 0, 1);

-- ============================================================
-- Default email templates
-- ============================================================
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

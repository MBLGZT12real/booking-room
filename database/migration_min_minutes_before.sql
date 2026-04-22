-- Migration: Ganti booking_min_hours_before → booking_min_minutes_before
-- Untuk database yang sudah ada (tidak perlu dijalankan jika setup dari awal)

USE `booking_room`;

-- Ambil nilai lama (jam), konversi ke menit, simpan dengan key baru
INSERT INTO `system_settings`
  (`key_name`, `value`, `type`, `label`, `description`, `group_name`, `sort_order`)
SELECT
  'booking_min_minutes_before',
  CAST(CAST(`value` AS UNSIGNED) * 60 AS CHAR),
  'number',
  'Minimal Menit Sebelum Booking',
  'Minimal berapa menit sebelum jam mulai booking bisa dilakukan (contoh: 30 = setengah jam)',
  'booking',
  2
FROM `system_settings`
WHERE `key_name` = 'booking_min_hours_before'
ON DUPLICATE KEY UPDATE
  `value`       = VALUES(`value`),
  `label`       = VALUES(`label`),
  `description` = VALUES(`description`);

-- Hapus key lama
DELETE FROM `system_settings` WHERE `key_name` = 'booking_min_hours_before';

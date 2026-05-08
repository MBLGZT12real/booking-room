-- ============================================================
-- Migration: Tambah kolom cancelled_at dan cancelled_by pada tabel bookings
-- Jalankan sekali pada database yang sudah ada
-- ============================================================

ALTER TABLE `bookings`
    ADD COLUMN `cancel_reason` TEXT DEFAULT NULL AFTER `reject_reason`,
    ADD COLUMN `cancelled_by` INT(11) DEFAULT NULL AFTER `approved_at`,
    ADD COLUMN `cancelled_at` DATETIME DEFAULT NULL AFTER `cancelled_by`,
    ADD KEY `fk_booking_canceller` (`cancelled_by`),
    ADD CONSTRAINT `fk_booking_canceller` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

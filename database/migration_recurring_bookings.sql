-- ============================================================
-- Migration: Recurring Bookings Support
-- Run this against your existing booking_room database
-- ============================================================

ALTER TABLE `bookings`
    ADD COLUMN `recurring_group_id` VARCHAR(36) NULL DEFAULT NULL COMMENT 'UUID shared by all bookings in a recurring series' AFTER `notes_other`,
    ADD COLUMN `booked_by_admin`    TINYINT(1)  NOT NULL DEFAULT 0 COMMENT '1 if created via admin panel' AFTER `recurring_group_id`,
    ADD INDEX  `idx_recurring_group` (`recurring_group_id`);

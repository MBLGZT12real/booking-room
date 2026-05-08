-- ============================================================
-- BOOKING ROOM SYSTEM - Database Schema
-- PHP 7.4.20 Native | MySQL 5.7+
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+07:00";

CREATE DATABASE IF NOT EXISTS `booking_room` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `booking_room`;

-- ============================================================
-- 1. ROLES
-- ============================================================
CREATE TABLE `roles` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `slug` VARCHAR(50) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. PERMISSIONS
-- ============================================================
CREATE TABLE `permissions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `module` VARCHAR(50) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. ROLE PERMISSIONS
-- ============================================================
CREATE TABLE `role_permissions` (
  `role_id` INT(11) NOT NULL,
  `permission_id` INT(11) NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  KEY `fk_rp_permission` (`permission_id`),
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. MENUS (Dynamic)
-- ============================================================
CREATE TABLE `menus` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `parent_id` INT(11) DEFAULT NULL,
  `name` VARCHAR(100) NOT NULL,
  `url` VARCHAR(255) DEFAULT NULL,
  `icon` VARCHAR(100) DEFAULT NULL,
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  `permission_id` INT(11) DEFAULT NULL COMMENT 'Required permission to see this menu item',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_menu_parent` (`parent_id`),
  KEY `fk_menu_permission` (`permission_id`),
  CONSTRAINT `fk_menu_parent` FOREIGN KEY (`parent_id`) REFERENCES `menus` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_menu_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. USERS (Admin Panel Accounts)
-- ============================================================
CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `role_id` INT(11) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_user_role` (`role_id`),
  CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. SYSTEM SETTINGS
-- ============================================================
CREATE TABLE `system_settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `key_name` VARCHAR(100) NOT NULL,
  `value` TEXT DEFAULT NULL,
  `type` ENUM('text','number','boolean','json','file','textarea','color') NOT NULL DEFAULT 'text',
  `label` VARCHAR(100) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `group_name` VARCHAR(50) NOT NULL DEFAULT 'general',
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_name` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. DEPARTMENTS
-- ============================================================
CREATE TABLE `departments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(20) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. MEETING TYPES
-- ============================================================
CREATE TABLE `meeting_types` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. ROOMS
-- ============================================================
CREATE TABLE `rooms` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(20) DEFAULT NULL,
  `location` VARCHAR(200) DEFAULT NULL,
  `floor` VARCHAR(20) DEFAULT NULL,
  `capacity` INT(11) NOT NULL DEFAULT 1 COMMENT 'Kapasitas maksimal peserta meeting',
  `description` TEXT DEFAULT NULL,
  `facilities` TEXT DEFAULT NULL COMMENT 'JSON array of facilities',
  `image` VARCHAR(255) DEFAULT NULL,
  `requires_approval` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=otomatis confirmed, 1=perlu approval',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. WORKING HOURS
-- ============================================================
CREATE TABLE `working_hours` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `day_of_week` TINYINT(1) NOT NULL COMMENT '0=Minggu, 1=Senin, ..., 6=Sabtu',
  `is_working_day` TINYINT(1) NOT NULL DEFAULT 1,
  `start_time` TIME DEFAULT '08:00:00',
  `end_time` TIME DEFAULT '17:00:00',
  `interval_minutes` INT(11) NOT NULL DEFAULT 30 COMMENT 'Interval slot booking dalam menit',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `day_of_week` (`day_of_week`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. HOLIDAYS
-- ============================================================
CREATE TABLE `holidays` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `date` DATE NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `is_recurring` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Jika 1, berulang setiap tahun pada bulan-tanggal yang sama',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_holidays_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. BOOKINGS
-- ============================================================
CREATE TABLE `bookings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `booking_code` VARCHAR(20) NOT NULL,
  `room_id` INT(11) NOT NULL,
  `department_id` INT(11) DEFAULT NULL,
  `meeting_type_id` INT(11) DEFAULT NULL,
  `booker_name` VARCHAR(100) NOT NULL,
  `booker_nik` VARCHAR(50) DEFAULT NULL,
  `booker_email` VARCHAR(100) NOT NULL,
  `booker_phone` VARCHAR(20) DEFAULT NULL,
  `booking_date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `capacity_needed` INT(11) DEFAULT NULL,
  `purpose` TEXT DEFAULT NULL,
  `notes_equipment` TEXT DEFAULT NULL COMMENT 'Catatan tambahan alat dalam ruangan',
  `notes_other` TEXT DEFAULT NULL COMMENT 'Catatan lainnya',
  `recurring_group_id` VARCHAR(36) NULL DEFAULT NULL COMMENT 'UUID grup booking berulang (weekly/biweekly)',
  `booked_by_admin` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 jika dibuat langsung oleh admin/operator',
  `status` ENUM('pending','confirmed','rejected','cancelled','completed') NOT NULL DEFAULT 'pending',
  `reject_reason` TEXT DEFAULT NULL,
  `cancel_reason` TEXT DEFAULT NULL,
  `token` VARCHAR(128) NOT NULL,
  `token_expires_at` DATETIME DEFAULT NULL,
  `checkin_at` DATETIME DEFAULT NULL,
  `checkout_at` DATETIME DEFAULT NULL,
  `checkout_reminder_sent_at` DATETIME NULL DEFAULT NULL COMMENT 'Timestamp terakhir email reminder checkout dikirim',
  `approved_by` INT(11) DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `cancelled_by` INT(11) DEFAULT NULL,
  `cancelled_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_code` (`booking_code`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_bookings_date` (`booking_date`),
  KEY `idx_bookings_status` (`status`),
  KEY `idx_bookings_room` (`room_id`),
  KEY `idx_bookings_conflict` (`room_id`, `booking_date`, `status`),
  KEY `idx_recurring_group` (`recurring_group_id`),
  KEY `fk_booking_room` (`room_id`),
  KEY `fk_booking_dept` (`department_id`),
  KEY `fk_booking_type` (`meeting_type_id`),
  KEY `fk_booking_approver` (`approved_by`),
  KEY `fk_booking_canceller` (`cancelled_by`),
  CONSTRAINT `fk_booking_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`),
  CONSTRAINT `fk_booking_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_booking_type` FOREIGN KEY (`meeting_type_id`) REFERENCES `meeting_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_booking_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_booking_canceller` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 13. NOTIFICATIONS
-- ============================================================
CREATE TABLE `notifications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL COMMENT 'NULL = broadcast ke semua admin',
  `booking_id` INT(11) DEFAULT NULL,
  `type` VARCHAR(50) NOT NULL COMMENT 'new_booking, approved, rejected, checkin, checkout, reminder, cancelled',
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `read_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`, `is_read`),
  KEY `idx_notif_created` (`created_at`),
  KEY `fk_notif_user` (`user_id`),
  KEY `fk_notif_booking` (`booking_id`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 14. MAIL LOGS
-- ============================================================
CREATE TABLE `mail_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `booking_id` INT(11) DEFAULT NULL,
  `recipient_email` VARCHAR(100) NOT NULL,
  `recipient_name` VARCHAR(100) DEFAULT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body` LONGTEXT DEFAULT NULL,
  `status` ENUM('sent','failed') NOT NULL DEFAULT 'sent',
  `error_message` TEXT DEFAULT NULL,
  `sent_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_maillog_booking` (`booking_id`),
  KEY `idx_maillog_status` (`status`),
  CONSTRAINT `fk_maillog_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 15. AUDIT LOGS
-- ============================================================
CREATE TABLE `audit_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `module` VARCHAR(50) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `old_data` LONGTEXT DEFAULT NULL COMMENT 'JSON',
  `new_data` LONGTEXT DEFAULT NULL COMMENT 'JSON',
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_audit_user` (`user_id`),
  KEY `idx_audit_module` (`module`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 16. LOGIN ATTEMPTS (rate limiting brute-force)
-- ============================================================
CREATE TABLE `login_attempts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `ip_address` VARCHAR(45) NOT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 17. EMAIL TEMPLATES
-- ============================================================
CREATE TABLE `email_templates` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(50) NOT NULL COMMENT 'booking_confirmation, booking_approved, booking_rejected, booking_cancelled, checkin_confirmation, checkout_reminder',
  `name` VARCHAR(100) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body_html` TEXT NOT NULL,
  `variables` TEXT DEFAULT NULL COMMENT 'JSON array variable yang tersedia',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MIGRATIONS (run on existing databases)
-- ============================================================

-- v1.1: Composite index for double-booking conflict detection
--       Run this if you already have the bookings table created.
-- ALTER TABLE `bookings` ADD KEY `idx_bookings_conflict` (`room_id`, `booking_date`, `status`);

-- v1.1: Allowed email domain setting
--       Run this if you already ran the original seed.sql.
-- INSERT IGNORE INTO `system_settings`
--   (`key_name`, `value`, `type`, `label`, `description`, `group_name`, `sort_order`)
--   VALUES ('allowed_email_domain', '', 'text', 'Pembatasan Domain Email',
--           'Kosongkan untuk izinkan semua domain. Isi domain untuk membatasi (contoh: seven-event.com)',
--           'booking', 6);

-- v1.2: Login rate limiting & email templates (sudah included di schema ini sebagai tabel 16 & 17)
--       Untuk database yang sudah ada, jalankan file migration berikut:
--       - database/migration_login_attempts.sql
--       - database/migration_email_templates.sql
--       - database/migration_menu_email_templates.sql

-- v1.3: Ganti booking_min_hours_before → booking_min_minutes_before (satuan menit)
--       Untuk database yang sudah ada, jalankan:
--       - database/migration_min_minutes_before.sql

-- v1.4: Fitur booking berulang (recurring) dan booking oleh admin
--       Kolom baru di tabel bookings: recurring_group_id, booked_by_admin
--       Untuk database yang sudah ada, jalankan:
--       - database/migration_recurring_bookings.sql

-- v1.5: Auto check-out dan email reminder checkout
--       Kolom baru di tabel bookings: checkout_reminder_sent_at
--       Template email baru: checkout_reminder (ada di seed.sql INSERT IGNORE)
--       Untuk database yang sudah ada, jalankan:
--       - database/migration_auto_checkout.sql

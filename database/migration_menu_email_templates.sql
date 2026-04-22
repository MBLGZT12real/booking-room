-- Migration: Tambah menu Template Email
-- Untuk database yang sudah ada (tidak perlu dijalankan jika setup dari awal)

USE `booking_room`;

INSERT IGNORE INTO `menus`
  (`id`, `parent_id`, `name`, `url`, `icon`, `sort_order`, `permission_id`, `is_active`)
VALUES
  (21, 6, 'Template Email', '/admin/email-templates/', 'bi-envelope-paper', 7, 12, 1);

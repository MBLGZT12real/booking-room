<?php
// ============================================================
// BOOKING ROOM SYSTEM - Main Configuration (TEMPLATE)
// Copy this file to config/config.php and adjust the values.
// NEVER commit config/config.php to the repository!
// ============================================================

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Base URL — change to your domain in production
define('BASE_URL',    'http://localhost/booking-room');
define('BASE_PATH',   dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('UPLOAD_URL',  BASE_URL  . '/uploads');

// Application Info
define('APP_VERSION', '1.0.0');
define('APP_NAME',    'Sistem Booking Ruangan');

// Session Config
define('SESSION_NAME',       'booking_room_session');
define('SESSION_LIFETIME',   3600);
define('SESSION_ADMIN_PATH', '/admin');

// Upload Config
define('MAX_FILE_SIZE',            2 * 1024 * 1024);
define('ALLOWED_IMAGE_TYPES',      ['image/jpeg','image/jpg','image/png','image/gif','image/webp']);
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg','jpeg','png','gif','webp']);

// Pagination
define('DEFAULT_PER_PAGE', 15);

// Token
define('TOKEN_LENGTH',     64); // bytes → 128 hex chars

// Password Hashing Algorithm
define('AUTH_ALGO',         PASSWORD_ARGON2ID);
define('AUTH_ALGO_OPTIONS', ['memory_cost'=>65536,'time_cost'=>4,'threads'=>1]);

// Booking Status
define('BOOKING_STATUS', [
    'pending'   => ['label'=>'Menunggu Approval','class'=>'warning',  'icon'=>'bi-clock'],
    'confirmed' => ['label'=>'Dikonfirmasi',      'class'=>'success',  'icon'=>'bi-check-circle'],
    'rejected'  => ['label'=>'Ditolak',           'class'=>'danger',   'icon'=>'bi-x-circle'],
    'cancelled' => ['label'=>'Dibatalkan',         'class'=>'secondary','icon'=>'bi-dash-circle'],
    'completed' => ['label'=>'Selesai',            'class'=>'info',     'icon'=>'bi-flag'],
]);

// Notification Types
define('NOTIF_TYPES', [
    'new_booking'=>['label'=>'Booking Baru','class'=>'primary',  'icon'=>'bi-calendar-plus'],
    'approved'   =>['label'=>'Disetujui',   'class'=>'success',  'icon'=>'bi-check-circle'],
    'rejected'   =>['label'=>'Ditolak',     'class'=>'danger',   'icon'=>'bi-x-circle'],
    'cancelled'  =>['label'=>'Dibatalkan',  'class'=>'secondary','icon'=>'bi-dash-circle'],
    'checkin'    =>['label'=>'Check-in',    'class'=>'info',     'icon'=>'bi-box-arrow-in-right'],
    'checkout'   =>['label'=>'Check-out',   'class'=>'warning',  'icon'=>'bi-box-arrow-left'],
    'reminder'   =>['label'=>'Pengingat',   'class'=>'warning',  'icon'=>'bi-alarm'],
]);

// Day Names (Bahasa Indonesia)
define('DAY_NAMES', [
    0 => 'Minggu',
    1 => 'Senin',
    2 => 'Selasa',
    3 => 'Rabu',
    4 => 'Kamis',
    5 => 'Jumat',
    6 => 'Sabtu',
]);

// Month Names (Bahasa Indonesia)
define('MONTH_NAMES', [
    1  => 'Januari',
    2  => 'Februari',
    3  => 'Maret',
    4  => 'April',
    5  => 'Mei',
    6  => 'Juni',
    7  => 'Juli',
    8  => 'Agustus',
    9  => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember',
]);

// Role Slugs
define('ROLE_SUPERADMIN', 'superadmin');
define('ROLE_ADMIN', 'admin');
define('ROLE_OPERATOR', 'operator');
define('ROLE_STAFF', 'staff');

// Error Reporting (set Off di production)
if (strpos(BASE_URL, 'localhost') !== false) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', BASE_PATH . '/logs/error.log');
}
?>
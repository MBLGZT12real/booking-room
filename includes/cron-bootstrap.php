<?php
// ============================================================
// Cron Bootstrap — for CLI scripts only (no session, no Auth)
// ============================================================

// Block direct web access — HTTP_HOST hanya ada pada web request, tidak pada CLI/cron
if (!empty($_SERVER['HTTP_HOST']) || !empty($_SERVER['REMOTE_ADDR'])) {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

define('CRON_MODE', true);

$_secretsFile = dirname(__DIR__) . '/config/secrets.php';
if (!file_exists($_secretsFile)) {
    exit('File config/secrets.php tidak ditemukan.');
}
require_once $_secretsFile;
unset($_secretsFile);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/mailer.php';

// PHPMailer
require_once dirname(__DIR__) . '/vendor/phpmailer/Exception.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/PHPMailer.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/SMTP.php';

// Core classes (no Auth — not needed for cron)
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Functions.php';
require_once dirname(__DIR__) . '/includes/BookingHelper.php';
require_once dirname(__DIR__) . '/includes/Mailer.php';
require_once dirname(__DIR__) . '/includes/Notification.php';

<?php
// ============================================================
// Application Bootstrap - Include this in every page
// ============================================================

// Secrets harus di-load pertama (pepper & encryption key)
$_secretsFile = dirname(__DIR__) . '/config/secrets.php';
if (!file_exists($_secretsFile)) {
    die('File config/secrets.php tidak ditemukan. Salin dari config/secrets.example.php dan isi nilainya.');
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

// Core classes
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Auth.php';
require_once dirname(__DIR__) . '/includes/Functions.php';
require_once dirname(__DIR__) . '/includes/Notification.php';
require_once dirname(__DIR__) . '/includes/BookingHelper.php';
require_once dirname(__DIR__) . '/includes/Mailer.php';

// Start session
Auth::startSession();

<?php
// ============================================================
// BOOKING ROOM SYSTEM - PHPMailer Configuration (TEMPLATE)
// Copy this file to config/mailer.php and fill in your values.
// NEVER commit config/mailer.php to the repository!
// ============================================================

define('MAIL_SMTP_HOST',       'smtp.gmail.com');
define('MAIL_SMTP_PORT',       465);
define('MAIL_SMTP_USERNAME',   'no-reply@yourdomain.com');
define('MAIL_SMTP_PASSWORD',   'your-app-password-here');   // Gmail: use App Password
define('MAIL_SMTP_ENCRYPTION', 'ssl');                       // 'tls' or 'ssl'
define('MAIL_FROM_EMAIL',      'no-reply@yourdomain.com');
define('MAIL_FROM_NAME',       'Company - Booking Ruangan');
define('MAIL_REPLY_TO',        '');
define('MAIL_DEBUG',           0); // 0=off, 2=verbose (dev only)
?>
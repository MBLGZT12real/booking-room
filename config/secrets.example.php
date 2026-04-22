<?php
// ============================================================
// SECRETS CONFIGURATION - TEMPLATE
// Salin file ini ke config/secrets.php dan isi nilainya.
// Jangan commit config/secrets.php ke repository!
// ============================================================

// Password pepper untuk Argon2ID - jangan ubah setelah ada user terdaftar
define('AUTH_PEPPER', 'ganti-dengan-string-acak-minimal-32-karakter');

// Kunci enkripsi untuk nilai sensitif di database (SMTP password, dll)
// Generate dengan: php -r "echo bin2hex(random_bytes(32));"
define('ENCRYPTION_KEY', 'ganti-dengan-hex-64-karakter-acak');
?>
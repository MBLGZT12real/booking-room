<?php
// ============================================================
// Helper Functions
// ============================================================

/**
 * Sanitize string output (XSS prevention)
 */
function e(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF token hidden input field
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

/**
 * Verify CSRF token
 */
function verifyCsrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return !empty($token) && hash_equals(csrfToken(), $token);
}

/**
 * Require CSRF - die with 403 if invalid
 */
function requireCsrf(): void
{
    if (!verifyCsrf()) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Invalid CSRF token.']));
    }
}

/**
 * Redirect to URL
 */
function redirect(string $url, int $statusCode = 302): void
{
    header("Location: $url", true, $statusCode);
    exit;
}

/**
 * Redirect back to referrer
 */
function redirectBack(string $fallback = ''): void
{
    $referer  = $_SERVER['HTTP_REFERER'] ?? '';
    $baseHost = parse_url(BASE_URL, PHP_URL_HOST);
    if ($referer && parse_url($referer, PHP_URL_HOST) === $baseHost) {
        redirect($referer);
    } else {
        redirect($fallback ?: BASE_URL . '/admin/');
    }
}

/**
 * Flash message: set in session
 */
function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash message
 */
function getFlash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Render Bootstrap alert from flash
 */
function showFlash(): string
{
    $flash = getFlash();
    if (!$flash) return '';

    $typeMap = [
        'success' => 'success',
        'error'   => 'danger',
        'warning' => 'warning',
        'info'    => 'info',
    ];
    $bsType = $typeMap[$flash['type']] ?? 'info';

    return '<div class="alert alert-' . $bsType . ' alert-dismissible fade show" role="alert">
        <i class="bi bi-' . ($bsType === 'success' ? 'check-circle' : ($bsType === 'danger' ? 'exclamation-triangle' : 'info-circle')) . ' me-2"></i>
        ' . e($flash['message']) . '
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>';
}

/**
 * JSON response helper
 */
function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Sanitize input (basic)
 */
function sanitize(string $input): string
{
    return trim(stripslashes(htmlspecialchars($input, ENT_QUOTES, 'UTF-8')));
}


/**
 * Validate email
 */
function isValidEmail(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Generate booking code (format: BR-YYYYMMDD-XXXXX)
 */
function generateBookingCode(): string
{
    $prefix = 'BR-' . date('Ymd') . '-';
    $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
    return $prefix . $suffix;
}

/**
 * Generate secure token (128 hex chars)
 */
function generateToken(): string
{
    return bin2hex(random_bytes(TOKEN_LENGTH));
}

/**
 * Format date to Indonesian (e.g., "Senin, 24 Februari 2026")
 */
function formatDateId(string $date, bool $withDay = true): string
{
    $ts = strtotime($date);
    if (!$ts) return $date;

    $day   = DAY_NAMES[(int) date('w', $ts)] ?? '';
    $day_num = date('j', $ts);
    $month = MONTH_NAMES[(int) date('n', $ts)] ?? '';
    $year  = date('Y', $ts);

    if ($withDay) {
        return "$day, $day_num $month $year";
    }
    return "$day_num $month $year";
}

/**
 * Format time (e.g., "08:00 - 09:00")
 */
function formatTimeRange(string $start, string $end): string
{
    return substr($start, 0, 5) . ' - ' . substr($end, 0, 5) . ' WIB';
}

/**
 * Calculate duration in human readable format
 */
function formatDuration(string $start, string $end): string
{
    $startTs = strtotime('today ' . $start);
    $endTs   = strtotime('today ' . $end);
    $diff    = $endTs - $startTs;
    $hours   = floor($diff / 3600);
    $minutes = ($diff % 3600) / 60;

    if ($hours > 0 && $minutes > 0) {
        return $hours . ' jam ' . $minutes . ' menit';
    } elseif ($hours > 0) {
        return $hours . ' jam';
    }
    return $minutes . ' menit';
}

/**
 * Format datetime to Indonesian (e.g., "24 Feb 2026, 08:00")
 */
function formatDatetimeId(string $datetime): string
{
    if (!$datetime || $datetime === '0000-00-00 00:00:00') return '-';
    $ts    = strtotime($datetime);
    $day   = date('j', $ts);
    $month = MONTH_NAMES[(int) date('n', $ts)] ?? '';
    $year  = date('Y', $ts);
    $time  = date('H:i', $ts);
    return "$day " . substr($month, 0, 3) . " $year, $time WIB";
}

/**
 * Time ago format
 */
function timeAgo(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'Baru saja';
    if ($diff < 3600) return floor($diff / 60) . ' menit lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';
    if ($diff < 2592000) return floor($diff / 86400) . ' hari lalu';
    return formatDatetimeId($datetime);
}

/**
 * Get system setting value from DB
 */
function getSetting(string $key, string $default = ''): string
{
    global $_settings_cache;
    if ($_settings_cache === null) {
        try {
            $rows = db()->fetchAll("SELECT key_name, value FROM system_settings WHERE is_active = 1");
            $_settings_cache = array_column($rows, 'value', 'key_name');
        } catch (Exception $_e) {
            $_settings_cache = [];
        }
    }
    return $_settings_cache[$key] ?? $default;
}

/**
 * Force reload settings cache on next getSetting() call
 */
function clearSettingsCache(): void
{
    global $_settings_cache;
    $_settings_cache = null;
}

/**
 * Build badge HTML for booking status
 */
function statusBadge(string $status): string
{
    $statuses = BOOKING_STATUS;
    $info = $statuses[$status] ?? ['label' => ucfirst($status), 'class' => 'secondary', 'icon' => 'bi-question'];
    return '<span class="badge bg-' . $info['class'] . '">
        <i class="bi ' . $info['icon'] . ' me-1"></i>' . $info['label'] . '
    </span>';
}

/**
 * Pagination helper
 */
function paginate(int $total, int $perPage, int $currentPage, string $baseUrl): array
{
    $totalPages = max(1, ceil($total / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    return [
        'total'        => $total,
        'per_page'     => $perPage,
        'current_page' => $currentPage,
        'total_pages'  => $totalPages,
        'offset'       => $offset,
        'has_prev'     => $currentPage > 1,
        'has_next'     => $currentPage < $totalPages,
        'prev_page'    => $currentPage - 1,
        'next_page'    => $currentPage + 1,
        'base_url'     => $baseUrl,
    ];
}

/**
 * Render pagination HTML (Bootstrap 5)
 */
function renderPagination(array $p): string
{
    if ($p['total_pages'] <= 1) return '';

    $sep = strpos($p['base_url'], '?') !== false ? '&' : '?';
    $html = '<nav aria-label="Page navigation"><ul class="pagination pagination-sm mb-0">';

    // Previous
    if ($p['has_prev']) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $p['base_url'] . $sep . 'page=' . $p['prev_page'] . '">&laquo;</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
    }

    // Pages
    $start = max(1, $p['current_page'] - 2);
    $end   = min($p['total_pages'], $p['current_page'] + 2);

    if ($start > 1) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $p['current_page'] ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . $p['base_url'] . $sep . 'page=' . $i . '">' . $i . '</a></li>';
    }
    if ($end < $p['total_pages']) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';

    // Next
    if ($p['has_next']) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $p['base_url'] . $sep . 'page=' . $p['next_page'] . '">&raquo;</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
    }

    $html .= '</ul></nav>';
    return $html;
}

/**
 * Encrypt sensitive value (untuk SMTP password di DB)
 */
function encryptValue(string $value): string
{
    if (empty($value)) return '';
    $key = substr(hash('sha256', ENCRYPTION_KEY), 0, 32);
    $iv  = openssl_random_pseudo_bytes(16);
    $enc = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode(base64_encode($iv) . '::' . $enc);
}

/**
 * Decrypt sensitive value dari DB
 */
function decryptValue(string $value): string
{
    if (empty($value)) return '';
    $decoded = base64_decode($value);
    if ($decoded === false || strpos($decoded, '::') === false) {
        return $value; // belum terenkripsi, kembalikan apa adanya
    }
    [$ivB64, $enc] = explode('::', $decoded, 2);
    $iv  = base64_decode($ivB64);
    $key = substr(hash('sha256', ENCRYPTION_KEY), 0, 32);
    $result = openssl_decrypt($enc, 'AES-256-CBC', $key, 0, $iv);
    return $result !== false ? $result : $value;
}

/**
 * Handle file upload (room images / avatars)
 * $maxDimension: jika > 0, resize gambar ke max lebar/tinggi (px) menggunakan GD
 */
function uploadImage(array $file, string $subDir = 'rooms', int $maxDimension = 0): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload gagal: error code ' . $file['error']];
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'Ukuran file melebihi 2MB.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ALLOWED_IMAGE_TYPES)) {
        return ['success' => false, 'message' => 'Format file tidak didukung. Gunakan JPG, PNG, atau WebP.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_IMAGE_EXTENSIONS)) {
        return ['success' => false, 'message' => 'Ekstensi file tidak valid.'];
    }

    $dir = UPLOAD_PATH . '/' . $subDir;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = uniqid('img_', true) . '.' . $ext;
    $destPath = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['success' => false, 'message' => 'Gagal menyimpan file.'];
    }

    // Resize jika maxDimension ditentukan dan GD tersedia
    if ($maxDimension > 0 && function_exists('imagecreatefromjpeg')) {
        _resizeImage($destPath, $mime, $maxDimension);
    }

    return [
        'success'  => true,
        'filename' => $filename,
        'url'      => UPLOAD_URL . '/' . $subDir . '/' . $filename,
        'path'     => $destPath,
    ];
}

/**
 * Resize gambar ke max dimension menggunakan GD (internal helper)
 */
function _resizeImage(string $path, string $mime, int $maxDim): void
{
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $src = @imagecreatefromjpeg($path); break;
        case 'image/png':
            $src = @imagecreatefrompng($path); break;
        case 'image/webp':
            $src = @imagecreatefromwebp($path); break;
        default:
            return;
    }
    if (!$src) return;

    $w = imagesx($src);
    $h = imagesy($src);

    if ($w <= $maxDim && $h <= $maxDim) {
        imagedestroy($src);
        return; // tidak perlu resize
    }

    $ratio  = $w / $h;
    if ($w > $h) {
        $newW = $maxDim;
        $newH = (int) round($maxDim / $ratio);
    } else {
        $newH = $maxDim;
        $newW = (int) round($maxDim * $ratio);
    }

    $dst = imagecreatetruecolor($newW, $newH);

    // Preserve transparency untuk PNG
    if ($mime === 'image/png') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            imagejpeg($dst, $path, 85); break;
        case 'image/png':
            imagepng($dst, $path, 8); break;
        case 'image/webp':
            imagewebp($dst, $path, 85); break;
    }

    imagedestroy($src);
    imagedestroy($dst);
}

/**
 * Delete uploaded file safely
 */
function deleteFile(string $relativePath): void
{
    $fullPath = UPLOAD_PATH . '/' . ltrim($relativePath, '/');
    if (is_file($fullPath)) {
        unlink($fullPath);
    }
}

/**
 * Get room image URL with fallback
 */
function roomImageUrl(?string $image): string
{
    if ($image && file_exists(UPLOAD_PATH . '/rooms/' . $image)) {
        return UPLOAD_URL . '/rooms/' . $image;
    }
    return BASE_URL . '/assets/images/room-default.png';
}

/**
 * User avatar URL with fallback
 */
function avatarUrl(?string $avatar): string
{
    if ($avatar && file_exists(UPLOAD_PATH . '/avatars/' . $avatar)) {
        return UPLOAD_URL . '/avatars/' . $avatar;
    }
    return BASE_URL . '/assets/images/avatar-default.png';
}

/**
 * Write to audit log
 */
function auditLog(string $action, string $module, string $description = '', $oldData = null, $newData = null): void
{
    try {
        db()->insert('audit_logs', [
            'user_id'     => Auth::id(),
            'action'      => $action,
            'module'      => $module,
            'description' => $description,
            'old_data'    => $oldData ? json_encode($oldData) : null,
            'new_data'    => $newData ? json_encode($newData) : null,
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Exception $e) {
        // Silent fail for audit log
    }
}

/**
 * Get current URL
 */
function currentUrl(): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Check if string is valid JSON
 */
function isJson(string $str): bool
{
    json_decode($str);
    return json_last_error() === JSON_ERROR_NONE;
}

/**
 * Safe json decode with fallback
 */
function jsonDecode(string $str, array $default = []): array
{
    $result = json_decode($str, true);
    return (json_last_error() === JSON_ERROR_NONE && is_array($result)) ? $result : $default;
}

/**
 * Format number with Indonesian locale
 */
function numId(int $num): string
{
    return number_format($num, 0, ',', '.');
}

/**
 * Truncate string with ellipsis
 */
function truncate(string $str, int $length = 100, string $suffix = '...'): string
{
    if (mb_strlen($str) <= $length) return $str;
    return mb_substr($str, 0, $length) . $suffix;
}

/**
 * Get request method
 */
function isPost(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function isGet(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'GET';
}

/**
 * Get query param (GET)
 */
function qParam(string $key, $default = '')
{
    return $_GET[$key] ?? $default;
}

/**
 * Get POST param
 */
function pParam(string $key, $default = '')
{
    return $_POST[$key] ?? $default;
}

/**
 * Check if AJAX request
 */
function isAjax(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

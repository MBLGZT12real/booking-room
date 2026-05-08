<?php
// ============================================================
// Authentication & Session Management
// ============================================================

class Auth
{
    private static bool $sessionStarted = false;

    /**
     * Start secure session
     */
    public static function startSession(): void
    {
        if (self::$sessionStarted || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.use_trans_sid', 0);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            ini_set('session.cookie_secure', 1);
        }

        session_name(SESSION_NAME);
        session_start();
        self::$sessionStarted = true;
    }

    /**
     * Hash password with Argon2ID + pepper
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password . AUTH_PEPPER, AUTH_ALGO, AUTH_ALGO_OPTIONS);
    }

    /**
     * Verify password with pepper
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password . AUTH_PEPPER, $hash);
    }

    /**
     * Check if hash needs rehash (algorithm/options changed)
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, AUTH_ALGO, AUTH_ALGO_OPTIONS);
    }

    /**
     * Check login rate limit by IP. Returns error message or empty string if OK.
     */
    private static function checkRateLimit(string $ip): string
    {
        $db = db();
        $window  = 15 * 60; // 15 menit
        $maxTry  = 5;
        $since   = date('Y-m-d H:i:s', time() - $window);

        $attempts = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at >= ?",
            [$ip, $since]
        );

        if ($attempts >= $maxTry) {
            return "Terlalu banyak percobaan login. Coba lagi dalam 15 menit.";
        }
        return '';
    }

    /**
     * Record a failed login attempt for IP.
     */
    private static function recordFailedAttempt(string $ip): void
    {
        try {
            db()->insert('login_attempts', ['ip_address' => $ip]);
        } catch (Exception $_e) {
            // Tabel belum ada — jalankan migration_login_attempts.sql
        }
    }

    /**
     * Clear login attempts for IP on successful login.
     */
    private static function clearAttempts(string $ip): void
    {
        try {
            db()->query("DELETE FROM login_attempts WHERE ip_address = ?", [$ip]);
        } catch (Exception $_e) {}
    }

    /**
     * Login user
     */
    public static function login(string $username, string $password): array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Rate limit check
        $rateLimitMsg = self::checkRateLimit($ip);
        if ($rateLimitMsg) {
            return ['success' => false, 'message' => $rateLimitMsg];
        }

        $db = db();
        $user = $db->fetch(
            "SELECT u.*, r.slug as role_slug, r.name as role_name
             FROM users u
             JOIN roles r ON u.role_id = r.id
             WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1",
            [$username, $username]
        );

        if (!$user) {
            self::recordFailedAttempt($ip);
            return ['success' => false, 'message' => 'Username atau password salah.'];
        }

        if (!self::verifyPassword($password, $user['password'])) {
            self::recordFailedAttempt($ip);
            return ['success' => false, 'message' => 'Username atau password salah.'];
        }

        // Login sukses — hapus catatan percobaan gagal untuk IP ini
        self::clearAttempts($ip);

        // Rehash transparently if algorithm/options changed
        if (self::needsRehash($user['password'])) {
            $db->update('users', ['password' => self::hashPassword($password)], ['id' => $user['id']]);
        }

        // Regenerate session ID on login (prevent session fixation)
        session_regenerate_id(true);

        // Store user data in session
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['role_id']    = $user['role_id'];
        $_SESSION['role_slug']  = $user['role_slug'];
        $_SESSION['role_name']  = $user['role_name'];
        $_SESSION['avatar']     = $user['avatar'];
        $_SESSION['last_activity'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';

        // Load user permissions into session
        $permissions = $db->fetchAll(
            "SELECT p.slug FROM role_permissions rp
             JOIN permissions p ON rp.permission_id = p.id
             WHERE rp.role_id = ? AND p.is_active = 1",
            [$user['role_id']]
        );
        $_SESSION['permissions'] = array_column($permissions, 'slug');

        // Update last login
        $db->update('users', ['last_login' => date('Y-m-d H:i:s')], ['id' => $user['id']]);

        return ['success' => true, 'role' => $user['role_slug']];
    }

    /**
     * Logout user
     */
    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }

        session_destroy();
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }

    /**
     * Check if user is logged in
     */
    public static function check(): bool
    {
        self::startSession();
        return !empty($_SESSION['user_id']);
    }

    /**
     * Require authentication, redirect to login if not
     */
    public static function requireLogin(): void
    {
        self::startSession();

        if (empty($_SESSION['user_id'])) {
            // Strip base path (e.g. '/booking-room') dari REQUEST_URI agar tidak double saat login redirect
            $basePath    = rtrim(parse_url(BASE_URL, PHP_URL_PATH) ?? '', '/');
            $requestUri  = $_SERVER['REQUEST_URI'] ?? '/';
            $appRelative = $basePath ? substr($requestUri, strlen($basePath)) : $requestUri;
            $redirect    = urlencode($appRelative ?: '/admin/');
            header('Location: ' . BASE_URL . '/login.php?redirect=' . $redirect);
            exit;
        }

        // Check session timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
            self::logout();
        }

        // Check if user is still active in DB (catches admin-deactivated accounts)
        $active = db()->fetchColumn("SELECT is_active FROM users WHERE id = ?", [$_SESSION['user_id']]);
        if (!$active) {
            self::logout();
        }

        // Update last activity
        $_SESSION['last_activity'] = time();
    }

    /**
     * Require specific permission
     */
    public static function requirePermission(string $slug): void
    {
        self::requireLogin();

        if (!self::can($slug)) {
            http_response_code(403);
            include BASE_PATH . '/includes/403.php';
            exit;
        }
    }

    /**
     * Check if current user has permission
     */
    public static function can(string $slug): bool
    {
        if (empty($_SESSION['permissions'])) {
            return false;
        }
        // Superadmin has all permissions
        if (isset($_SESSION['role_slug']) && $_SESSION['role_slug'] === ROLE_SUPERADMIN) {
            return true;
        }
        return in_array($slug, $_SESSION['permissions']);
    }

    /**
     * Check if user has any of the given permissions
     */
    public static function canAny(array $slugs): bool
    {
        foreach ($slugs as $slug) {
            if (self::can($slug)) return true;
        }
        return false;
    }

    /**
     * Get current user ID
     */
    public static function id(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get current user role slug
     */
    public static function role(): ?string
    {
        return $_SESSION['role_slug'] ?? null;
    }

    /**
     * Get current user data from session
     */
    public static function user(): array
    {
        return [
            'id'       => $_SESSION['user_id'] ?? null,
            'name'     => $_SESSION['user_name'] ?? '',
            'username' => $_SESSION['username'] ?? '',
            'email'    => $_SESSION['user_email'] ?? '',
            'role_id'  => $_SESSION['role_id'] ?? null,
            'role_slug'=> $_SESSION['role_slug'] ?? '',
            'role_name'=> $_SESSION['role_name'] ?? '',
            'avatar'   => $_SESSION['avatar'] ?? '',
        ];
    }

    /**
     * Reload user profile + permissions from DB (dipanggil setelah update profil)
     */
    public static function reloadPermissions(): void
    {
        if (!self::check()) return;

        $db   = db();
        $user = $db->fetch(
            "SELECT u.*, r.slug as role_slug, r.name as role_name
             FROM users u JOIN roles r ON u.role_id = r.id
             WHERE u.id = ?",
            [$_SESSION['user_id']]
        );

        if ($user) {
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role_id']    = $user['role_id'];
            $_SESSION['role_slug']  = $user['role_slug'];
            $_SESSION['role_name']  = $user['role_name'];
            $_SESSION['avatar']     = $user['avatar'];
        }

        $permissions = $db->fetchAll(
            "SELECT p.slug FROM role_permissions rp
             JOIN permissions p ON rp.permission_id = p.id
             WHERE rp.role_id = ? AND p.is_active = 1",
            [$_SESSION['role_id']]
        );
        $_SESSION['permissions'] = array_column($permissions, 'slug');
    }
}

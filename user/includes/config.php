<?php
// =============================================
// Karavali Lodge — Shared Config
// karavali_lodge/user/includes/config.php
// =============================================
// Secrets (DB password, Razorpay keys) live in  .env
// at the project root — never hardcoded here.
// See  .env.example  for the full list of variables.
// =============================================

// ── Load .env ────────────────────────────────────────────────────
// Walks up from this file's location to find .env at the project root.
(function () {
    $envFile = dirname(__DIR__, 2) . '/.env';   // karavali_lodge/.env
    if (!file_exists($envFile)) {
        // Friendly error so the developer knows what to do
        die('<div style="font-family:sans-serif;padding:30px;background:#fff3cd;border:1px solid #ffc107;'
          . 'border-radius:8px;max-width:640px;margin:40px auto">'
          . '<h3>⚠ Configuration missing</h3>'
          . '<p><code>.env</code> file not found at <code>' . htmlspecialchars($envFile) . '</code></p>'
          . '<p>Copy <code>.env.example</code> to <code>.env</code> in the project root and fill in your values.</p>'
          . '</div>');
    }

    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;          // skip comments
        if (!str_contains($line, '='))        continue;          // skip malformed lines
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        // Only set if not already defined (allows real environment variables to win)
        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
})();

// ── Helper: read env variable with a fallback ─────────────────────
function env(string $key, string $default = ''): string {
    $v = getenv($key);
    return ($v !== false && $v !== '') ? $v : $default;
}

// ── Application constants ─────────────────────────────────────────
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_NAME', env('DB_NAME', 'karavali_lodge'));

define('BASE_URL',  env('BASE_URL', 'http://localhost/karavali_lodge'));
define('SITE_URL',  BASE_URL . '/user');
define('ADMIN_URL', BASE_URL . '/admin');
define('API_URL',   BASE_URL . '/admin/api.php');
define('SITE_NAME', 'Karavali Lodge');

// ── Razorpay Payment Gateway ──────────────────────────────────────
// Keys are read from .env — never hardcode them here.
// Get your keys from: https://dashboard.razorpay.com/app/keys
define('RAZORPAY_KEY_ID',          env('RAZORPAY_KEY_ID'));
define('RAZORPAY_KEY_SECRET',      env('RAZORPAY_KEY_SECRET'));
define('RAZORPAY_CURRENCY',        env('RAZORPAY_CURRENCY', 'INR'));
define('RAZORPAY_ADVANCE_PERCENT', (float) env('RAZORPAY_ADVANCE_PERCENT', '1.00'));

// ── Admin auth — DB only, no hardcoded numbers ───────────────────
// Admin accounts live exclusively in the `admins` table.
// To add an admin: INSERT into admins table directly via phpMyAdmin,
// or use the setup script: admin/setup_admin.php (run once, then delete).

function getAdminByMobile($mobile) {
    try {
        $s = getDB()->prepare("SELECT * FROM admins WHERE mobile = ? LIMIT 1");
        $s->execute([trim($mobile)]);
        return $s->fetch() ?: null;
    } catch (Exception $e) { return null; }
}

function getAdminById($id) {
    try {
        $s = getDB()->prepare("SELECT * FROM admins WHERE id = ? LIMIT 1");
        $s->execute([$id]);
        return $s->fetch() ?: null;
    } catch (Exception $e) { return null; }
}

function isAdminSession(): bool {
    if (empty($_SESSION['admin_id'])) return false;

    // ── Session cache — avoids a DB query on every api.php call ──────
    // The admin record is stored in $_SESSION['admin_cache'] with a
    // timestamp. A fresh DB lookup is only done if:
    //   • the cache is missing or stale (> 5 minutes old), or
    //   • the cached id does not match the current admin_id.
    // This means the session reflects account changes within 5 minutes
    // while eliminating the constant DB hit during polling / auto-sync.
    $cacheLifetime = 300; // seconds (5 minutes)
    $cache = $_SESSION['admin_cache'] ?? null;

    $cacheValid = $cache !== null
        && isset($cache['id'], $cache['cached_at'])
        && $cache['id'] === $_SESSION['admin_id']
        && (time() - $cache['cached_at']) < $cacheLifetime;

    if (!$cacheValid) {
        // Cache is missing, stale, or belongs to a different admin — re-query
        $admin = getAdminById($_SESSION['admin_id']);
        if ($admin === null) {
            // Admin no longer exists — clear everything and reject
            unset($_SESSION['admin_id'], $_SESSION['admin_cache']);
            return false;
        }
        $_SESSION['admin_cache'] = [
            'id'        => $admin['id'],
            'name'      => $admin['name'],
            'mobile'    => $admin['mobile'],
            'cached_at' => time(),
        ];
    }

    return true;
}

// ── Shared: client IP lookup ─────────────────────────────────────
// Used by login.php, check-guest.php, and send-otp.php for rate limiting.
function getClientIp(): string {
    // Prefer real IP even behind proxies
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            // X-Forwarded-For can be a comma-separated list — take the first
            return trim(explode(',', $_SERVER[$key])[0]);
        }
    }
    return '0.0.0.0';
}

// ── Shared: generic per-IP rate limiter ──────────────────────────
// Used to throttle unauthenticated "does this account exist" style
// endpoints (check-guest.php, send-otp.php) so they can't be used to
// enumerate registered mobile numbers at unlimited speed.
function ensureLookupAttemptsTable(): void {
    getDB()->exec("
        CREATE TABLE IF NOT EXISTS lookup_attempts (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            endpoint     VARCHAR(50)  NOT NULL,
            ip           VARCHAR(45)  NOT NULL,
            attempted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_endpoint_ip_time (endpoint, ip, attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// Returns true if the IP has hit the limit for this endpoint (caller
// should reject the request). Does NOT record an attempt by itself —
// call recordLookupAttempt() separately once the request is accepted.
function isRateLimited(string $endpoint, string $ip, int $maxAttempts, int $windowMinutes): bool {
    ensureLookupAttemptsTable();
    $s = getDB()->prepare(
        "SELECT COUNT(*) FROM lookup_attempts
         WHERE endpoint = ? AND ip = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)"
    );
    $s->execute([$endpoint, $ip, $windowMinutes]);
    return (int) $s->fetchColumn() >= $maxAttempts;
}

function recordLookupAttempt(string $endpoint, string $ip): void {
    ensureLookupAttemptsTable();
    getDB()->prepare(
        "INSERT INTO lookup_attempts (endpoint, ip) VALUES (?, ?)"
    )->execute([$endpoint, $ip]);
}

// ── Shared: password policy ──────────────────────────────────────
// Used by both register.php (new account) and send-otp.php's
// reset_password action (forgot-password flow), so a reset can never
// produce a weaker password than registration would have allowed.
// Returns an array of error strings — empty array means the password passes.
function validatePassword(string $password, ?string $confirmPassword = null): array {
    $errors = [];
    if (!$password) {
        $errors[] = 'Password is required.';
        return $errors; // no point checking further rules on an empty value
    }
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    elseif (preg_match('/^[0-9]+$/', $password)) $errors[] = 'Password cannot be only numbers (e.g. 12345678). Add letters too.';
    elseif (preg_match('/^(.)\1+$/', $password)) $errors[] = 'Password is too simple. Use a mix of letters and numbers.';
    elseif (in_array(strtolower($password), [
        '12345678','password','password1','qwerty123','abc12345',
        '11111111','00000000','iloveyou','welcome1','admin123'
    ])) $errors[] = 'This password is too common. Please choose a stronger one.';

    if ($confirmPassword !== null && $password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }
    return $errors;
}

date_default_timezone_set('Asia/Kolkata');

// ── Environment-aware error reporting ────────────────────────────
// Set APP_ENV to 'production' on your live server to hide errors
// from visitors. On localhost (XAMPP) errors show as normal.
//
// To switch to production mode, either:
//   Option A — add this line to your .htaccess or php.ini:
//              SetEnv APP_ENV production
//   Option B — change the define below to 'production' manually
//              before going live.
define('APP_ENV', getenv('APP_ENV') ?: 'development');

if (APP_ENV === 'production') {
    // Production: hide all errors from visitors —
    // log them to a file instead so you can still debug
    error_reporting(0);
    ini_set('display_errors',  '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');
} else {
    // Development (localhost): show all errors as before
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
}

// Give this project its own session name so it never conflicts with
// other projects (e.g. hotel_navarathna) running on the same localhost.
if (session_status() === PHP_SESSION_NONE) {
    session_name('SESS_KARAVALI');
    // Secure cookie flags: httponly prevents JS access, samesite=Lax prevents CSRF
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
            // Set MySQL session timezone to IST so all NOW() and timestamps are correct
            $pdo->exec("SET time_zone = '+05:30'");
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:30px;background:#fff3cd;border:1px solid #ffc107;border-radius:8px;max-width:600px;margin:40px auto">
                <h3>⚠ Database Error</h3><p>'.$e->getMessage().'</p>
                <p>Check: MySQL is running · DB_USER/DB_PASS in config.php · Database karavali_lodge exists</p></div>');
        }
    }
    return $pdo;
}

function generateId() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000,mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff));
}
function generateRequestNo() {
    return 'REQ-'.date('Ymd').'-'.strtoupper(substr(md5(uniqid(mt_rand(),true)),0,6));
}
function generateBookingNo() { return 'BK-'.date('YmdHis').'-'.rand(1000,9999); }
function formatCurrency($a)  { return '₹'.number_format((float)$a,2); }
function sanitize($i)        { return htmlspecialchars(strip_tags(trim((string)$i)),ENT_QUOTES,'UTF-8'); }
function redirect($url)      { header("Location: $url"); exit; }
function isLoggedIn()        { return !empty($_SESSION['guest_id']); }
function getLoggedInGuest()  {
    if (!isLoggedIn()) return null;
    $s = getDB()->prepare("SELECT * FROM guests WHERE id=?");
    $s->execute([$_SESSION['guest_id']]);
    return $s->fetch() ?: null;
}
function getAmenityIcon($a) {
    $m=['WiFi'=>'bi-wifi','TV'=>'bi-tv','AC'=>'bi-thermometer-snow','Mini-bar'=>'bi-cup-straw',
        'Balcony'=>'bi-building','Living Room'=>'bi-couch','Jacuzzi'=>'bi-droplet',
        'Lake View'=>'bi-water','Fan'=>'bi-fan','Extra Beds'=>'bi-person-plus'];
    foreach($m as $k=>$v) if(stripos($a,$k)!==false) return $v;
    return 'bi-check-circle';
}
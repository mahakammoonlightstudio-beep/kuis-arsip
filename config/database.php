<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

// 🔒 SECURITY HEADERS
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// 🔥 PAKSA TIMEZONE KE WAKTU INDONESIA BAGIAN TENGAH (WITA)
date_default_timezone_set('Asia/Makassar');

// 📊 OPcache status check (development helper)
function opcache_status_check(): array {
    if (!function_exists('opcache_get_status')) return ['enabled' => false];
    $status = opcache_get_status(false);
    return [
        'enabled' => $status['opcache_enabled'] ?? false,
        'memory_used' => ($status['memory_usage']['used_memory'] ?? 0) / 1024 / 1024,
        'memory_free' => ($status['memory_usage']['free_memory'] ?? 0) / 1024 / 1024,
        'hit_rate' => $status['opcache_statistics']['opcache_hit_rate'] ?? 0,
    ];
}

// Kredensial asli ada di config/config.local.php (TIDAK di-commit — lihat .gitignore).
// Salin config/config.local.php.example menjadi config/config.local.php lalu isi di sana.
if (is_file(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}
define('DB_HOST', defined('DB_HOST') ? DB_HOST : 'localhost');
define('DB_NAME', defined('DB_NAME') ? DB_NAME : 'kearsipan');
define('DB_USER', defined('DB_USER') ? DB_USER : 'root');
define('DB_PASS', defined('DB_PASS') ? DB_PASS : '');

/**
 * Get optimized PDO instance with persistent connection and query caching
 */
function get_pdo(): PDO {
    static $pdo = null;
    
    if ($pdo instanceof PDO) {
        try {
            $pdo->query('SELECT 1');
            return $pdo;
        } catch (PDOException) {
            $pdo = null;
        }
    }
    
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => true,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'",
        PDO::MYSQL_ATTR_COMPRESS => true,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ];
    
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log("[DIARPUS] DB Connection Failed: " . $e->getMessage());
        throw new RuntimeException("Database connection failed");
    }
}

/**
 * Query cache using APCu if available, fallback to session
 */
function query_cache_get(string $key, int $ttl = 300): mixed {
    if (function_exists('apcu_fetch')) {
        return apcu_fetch($key);
    }
    $cache = $_SESSION['query_cache'] ?? [];
    if (isset($cache[$key]) && $cache[$key]['expires'] > time()) {
        return $cache[$key]['data'];
    }
    return false;
}

function query_cache_set(string $key, mixed $data, int $ttl = 300): void {
    if (function_exists('apcu_store')) {
        apcu_store($key, $data, $ttl);
        return;
    }
    $_SESSION['query_cache'][$key] = ['data' => $data, 'expires' => time() + $ttl];
}

function query_cache_clear(string $pattern = ''): void {
    if (function_exists('apcu_clear_cache')) {
        apcu_clear_cache();
        return;
    }
    if ($pattern === '') {
        unset($_SESSION['query_cache']);
    } else {
        $cache = $_SESSION['query_cache'] ?? [];
        foreach ($cache as $key => $value) {
            if (strpos($key, $pattern) !== false) {
                unset($_SESSION['query_cache'][$key]);
            }
        }
    }
}

/**
 * Execute cached query
 */
function cached_query(string $sql, array $params = [], int $ttl = 300): array {
    $key = 'q_' . md5($sql . serialize($params));
    $cached = query_cache_get($key);
    if ($cached !== false) return $cached;
    
    $pdo = get_pdo();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetchAll();
    
    query_cache_set($key, $result, $ttl);
    return $result;
}

/**
 * Execute cached single row query
 */
function cached_query_one(string $sql, array $params = [], int $ttl = 300): mixed {
    $key = 'q1_' . md5($sql . serialize($params));
    $cached = query_cache_get($key);
    if ($cached !== false) return $cached;
    
    $pdo = get_pdo();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    
    query_cache_set($key, $result, $ttl);
    return $result;
}

// Initialize global $pdo for backward compatibility
try {
    $pdo = get_pdo();
} catch (RuntimeException $e) {
    die("Koneksi database gagal. Silakan hubungi administrator.");
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Gate mode pemeliharaan: non-admin melihat halaman 503 rapi.
 * Dipanggil dari halaman publik setelah sesi tersedia.
 */
function hq_maintenance_gate(): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $maintenance = false;
    $message = '';
    try {
        $row = get_pdo()->query(
            "SELECT is_maintenance, maintenance_message FROM app_settings WHERE id = 1 LIMIT 1"
        )->fetch();
        $maintenance = $row && (int)$row['is_maintenance'] === 1;
        $message = (string)($row['maintenance_message'] ?? '');
    } catch (Throwable $e) {
        // kolom belum dimigrasi / DB down: biarkan aplikasi jalan normal
        return;
    }
    if (!$maintenance || (($_SESSION['role'] ?? '') === 'admin')) {
        return;
    }
    http_response_code(503);
    header('Retry-After: 3600');
    $msg = $message !== '' ? $message : 'Sistem sedang dalam pemeliharaan. Silakan kembali lagi nanti.';
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Pemeliharaan &middot; Kuis Arsip</title>'
        . '<style>body{font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f3f5f3;color:#454a43}'
        . '.box{max-width:460px;background:#fff;padding:40px;border-radius:16px;box-shadow:0 4px 20px rgba(20,24,20,.1);text-align:center}'
        . 'h1{color:#16694a;font-size:1.35rem;margin-bottom:12px}p{line-height:1.6;margin:0}</style></head>'
        . '<body><div class="box"><div style="font-size:3rem">&#128736;</div>'
        . '<h1>Kuis Arsip</h1><p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p style="margin-top:16px;font-size:.85rem;color:#6b7168">Dinas Kearsipan dan Perpustakaan Kabupaten Kutai Kartanegara</p>'
        . '</div></body></html>';
    exit;
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ' . '../auth/login.php');
        exit;
    }
}

function require_admin(): void {
    require_login();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: ' . '../quiz/index.php');
        exit;
    }
}

function e(mixed $str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// ============================================================
// SHUFFLE OPSI JAWABAN (deterministik per peserta & kuesioner)
// Dipakai quiz/start.php saat menampilkan dan quiz/result.php saat
// menilai — keduanya HARUS menghasilkan permutasi yang sama.
// ============================================================

function quiz_seed_str(int|string $user_id, int|string $survey_id, int|string|null $room_id): string {
    return (string)$user_id . '|' . (string)$survey_id . '|' . ($room_id ?? 'indv');
}

function quiz_shuffle_options(array &$q, string $seed_str): void {
    mt_srand(abs(crc32((string)$q['id'] . '|' . $seed_str)));
    $keys = ['option_a', 'option_b', 'option_c', 'option_d', 'option_e'];
    $vals = [];
    foreach ($keys as $k) { $vals[] = $q[$k]; }
    for ($i = count($vals) - 1; $i > 0; $i--) {
        $j = mt_rand(0, $i);
        $tmp = $vals[$i]; $vals[$i] = $vals[$j]; $vals[$j] = $tmp;
    }
    foreach ($keys as $i => $k) { $q[$k] = $vals[$i]; }
}

// ============================================================
// CSRF PROTECTION
// ============================================================

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_verify(): void {
    $stored = $_SESSION['csrf_token'] ?? '';
    $submitted = $_POST['csrf_token'] ?? '';
    if (!hash_equals($stored, $submitted) || empty($submitted)) {
        http_response_code(403);
        $is_json = false;
        foreach (headers_list() as $h) {
            if (stripos($h, 'Content-Type: application/json') === 0) { $is_json = true; break; }
        }
        if ($is_json) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'CSRF token invalid']);
        } else {
            echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Akses Ditolak</title>'
                . '<style>body{font-family:system-ui,sans-serif;text-align:center;padding:60px;background:#f3f5f3;}'
                . '.box{background:#fff;padding:30px;border-radius:12px;box-shadow:0 4px 20px rgba(20,24,20,.1);max-width:400px;margin:auto;}'
                . 'h2{color:#86271e;margin-top:0;}p{color:#454a43;}a{display:inline-block;margin-top:15px;padding:12px 24px;background:#16694a;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;}</style>'
                . '</head><body><div class="box"><h2>🚫 Akses Ditolak</h2>'
                . '<p>Token keamanan tidak valid. Silakan muat ulang halaman dan coba lagi.</p>'
                . '<a href="../quiz/index.php">← Kembali ke Beranda</a></div></body></html>';
        }
        exit;
    }
}

// ============================================================
// RATE LIMITING (IP-based, simple)
// ============================================================

function rate_limit_check(string $key, int $max_attempts = 5, int $window = 300): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $storage_key = 'rate_' . $key . '_' . $ip;

    if (!isset($_SESSION[$storage_key])) {
        $_SESSION[$storage_key] = ['count' => 0, 'reset' => time() + $window];
    }

    $data = &$_SESSION[$storage_key];
    if (time() > $data['reset']) {
        $data = ['count' => 0, 'reset' => time() + $window];
    }

    $data['count']++;
    return $data['count'] > $max_attempts;
}

function rate_limit_remaining(string $key, int $max_attempts = 5, int $window = 300): int {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $storage_key = 'rate_' . $key . '_' . $ip;
    $data = $_SESSION[$storage_key] ?? ['count' => 0, 'reset' => time() + $window];
    if (time() > $data['reset']) return $max_attempts;
    return max(0, $max_attempts - $data['count']);
}

// ============================================================
// AUDIT LOG (batched for performance)
// ============================================================

function audit_log(string $action, string $target_table = '', mixed $target_id = '', string $notes = ''): void {
    global $pdo;
    $log_entry = [
        'admin_id' => $_SESSION['user_id'] ?? null,
        'admin_nama' => $_SESSION['nama'] ?? 'unknown',
        'action' => $action,
        'target_table' => $target_table,
        'target_id' => (string)$target_id,
        'notes' => substr($notes, 0, 500),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255),
        'created_at' => date('Y-m-d H:i:s'),
    ];
    
    $_SESSION['audit_buffer'][] = $log_entry;
    
    // Flush every 10 entries or on critical actions
    if (count($_SESSION['audit_buffer']) >= 10 || in_array($action, ['login', 'logout', 'delete', 'toggle'])) {
        audit_flush();
    }
}

function audit_flush(): void {
    global $pdo;
    $buffer = $_SESSION['audit_buffer'] ?? [];
    if (empty($buffer)) return;
    
    try {
        $sql = "INSERT INTO admin_logs (admin_id, admin_nama, action, target_table, target_id, notes, ip_address, user_agent, created_at) VALUES ";
        $values = [];
        $params = [];
        
        foreach ($buffer as $entry) {
            $values[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $params = array_merge($params, array_values($entry));
        }
        
        $pdo->prepare($sql . implode(', ', $values))->execute($params);
    } catch (Exception $e) {
        error_log("[DIARPUS] Audit log batch insert failed: " . $e->getMessage());
    }
    
    unset($_SESSION['audit_buffer']);
}

// Flush on shutdown
register_shutdown_function('audit_flush');

// ============================================================
// RESPONSE HELPERS
// ============================================================

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $status = 400, mixed $data = null): void {
    json_response(['error' => $message, 'data' => $data], $status);
}

function json_success(mixed $data = null, string $message = ''): void {
    json_response(['success' => true, 'message' => $message, 'data' => $data]);
}

// ============================================================
// PAGINATION HELPER
// ============================================================

function paginate(int $total, int $per_page = 20, int $page = 1): array {
    $page = max(1, $page);
    $total_pages = (int)ceil($total / $per_page);
    $page = min($page, max(1, $total_pages)); // hindari offset negatif saat tabel kosong
    $offset = ($page - 1) * $per_page;
    
    return [
        'page' => $page,
        'per_page' => $per_page,
        'total' => $total,
        'total_pages' => $total_pages,
        'offset' => $offset,
        'has_prev' => $page > 1,
        'has_next' => $page < $total_pages,
    ];
}

// ============================================================
// PERFORMANCE MONITORING
// ============================================================

function timing_start(string $key = 'default'): void {
    $_SESSION['timers'][$key] = microtime(true);
}

function timing_end(string $key = 'default'): float {
    $start = $_SESSION['timers'][$key] ?? microtime(true);
    return round((microtime(true) - $start) * 1000, 2);
}

// For development: add timing header
if (($_SERVER['HTTP_X_DEBUG'] ?? '') === '1') {
    register_shutdown_function(function() {
        $timers = $_SESSION['timers'] ?? [];
        foreach ($timers as $key => $start) {
            header("X-Timing-{$key}: " . round((microtime(true) - $start) * 1000, 2) . "ms");
        }
        $opcache = opcache_status_check();
        if ($opcache['enabled']) {
            header("X-Opcache-Hit-Rate: " . round($opcache['hit_rate'], 1) . "%");
        }
    });
}
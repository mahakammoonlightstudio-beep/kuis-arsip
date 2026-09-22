<?php
require_once __DIR__ . '/../config/database.php';

// Log aktivitas logout (sebelum session dihapus; 'logout' langsung di-flush)
$user_name = $_SESSION['nama'] ?? 'unknown';
$user_id = $_SESSION['user_id'] ?? null;
if ($user_id) {
    audit_log('logout', 'users', $user_id, 'Logout: ' . $user_name);
}

// Kosongkan session
$_SESSION = array();

// Hapus cookie session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hancurkan session
session_destroy();

// Redirect ke login
header('Location: login.php?logged_out=1&t=' . time());
exit;